<?php
namespace App\Controller;

use App\Entity\AgencePersonnel;
use App\Entity\DirectionPersonnel;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Enum\StatusCompte;
use App\Form\PersonnelType;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\MinioService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Repository\ServiceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/agent')]
class AgentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private MinioService $minioService,
        private ServiceRepository $serviceRepository,
    ) {}

    #[Route('', name: 'app_agent_index', methods: ['GET'])]
    public function index(PersonnelRepository $repo, PaginatorInterface $paginator, Request $request): Response
    {
        /** @var Personnel $user */
        $user = $this->getUser();

        $personnels = match (true) {
            $this->isGranted('ROLE_ADMIN'),
            $this->isGranted('ROLE_SAP')  => $repo->findAllOrderByIM(),
            $this->isGranted('ROLE_RH')   => $repo->findAllOrderByIM(),
            $this->isGranted('ROLE_CHEF') => $repo->findByAgenceOrService($user),
            default                       => [$user],
        };

        $photos = [];

        foreach ($personnels as $p) {
            $photos[$p->getId()->toRfc4122()] = $p->getPhotoProfil()
                ? $this->generateUrl('app_agent_photo', ['id' => $p->getId()])
                : null;
        }

        $pagination = $paginator->paginate(
            $personnels,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('agent/index.html.twig', [
            'personnels' => $pagination,
            'photos' => $photos
        ]);
    }

    #[Route('/new', name: 'app_agent_new', methods: ['GET', 'POST'])]

    public function new(Request $request): Response
    {
        $personnel = new Personnel();
        $form = $this->createForm(PersonnelType::class, $personnel, ['is_new' => true, 'is_sap' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Dans votre contrôleur, après $form->isValid() :
            $agence = $form->get('agenceField')->getData();   // Agence|null
            $service = $form->get('serviceField')->getData(); // Service|null

            if ($personnel->isBackFront() && $agence) {
                // Supprimer les anciennes AgencePersonnel, créer la nouvelle
                foreach ($personnel->getAgencePersonnels() as $ap) {
                    $this->em->remove($ap);
                }
                $ap = new AgencePersonnel();
                $ap->setAgenceID($agence)->setPersonnelID($personnel);
                $this->em->persist($ap);
            } elseif (!$personnel->isBackFront() && $service) {
                foreach ($personnel->getDirectionPersonnels() as $dp) {
                    $this->em->remove($dp);
                }
                $dp = new DirectionPersonnel();
                $dp->setServiceID($service)->setPersonnelID($personnel);
                $this->em->persist($dp);
            }
            $this->handlePassword($personnel, $form->get('plainPassword')->getData());
            $personnel->setStatusCompte(StatusCompte::INACTIF);
            $this->em->persist($personnel);
            $this->em->flush();
            $this->addFlash('success', 'Personnel créé avec succès.');
            return $this->redirectToRoute('app_agent_show', ['id' => $personnel->getId()]);
        }

        return $this->render('agent/new.html.twig', [
            'form' => $form,
            'personnel' => $personnel,
            'photoUrl' => null,  // nouveau personnel, pas de photo
        ]);
    }

    #[Route('/service/{id}/direction', name: 'app_service_direction')]
    public function serviceDirection(
        Service $service
    ): JsonResponse {

        return $this->json([
            'direction' => $service->getDirectionID()?->getNomDir()
        ]);
    }

    #[Route('/{id}/photo', name: 'app_agent_photo', methods: ['GET'])]
    public function photo(Personnel $personnel): StreamedResponse
    {
        $this->denyAccessUnlessGranted('PERSONNEL_VIEW', $personnel);

        if (!$personnel->getPhotoProfil()) {
            throw $this->createNotFoundException('Aucune photo.');
        }

        try {
            $stream = $this->minioService->getStream(
                $this->minioService->getBucketPhotos(),
                $personnel->getPhotoProfil()  // clé telle qu'elle est en BDD
            );
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $ext = strtolower(pathinfo($personnel->getPhotoProfil(), PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        });
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->remove('X-Frame-Options');

        return $response;
}

    #[Route('/{id}', name: 'app_agent_show', methods: ['GET'])]
    public function show(Personnel $personnel): Response
    {
        $this->denyAccessUnlessGranted('PERSONNEL_VIEW', $personnel);

        $photoUrl = null;

        if ($personnel->getPhotoProfil()) {
            try {
                $photoUrl = $personnel->getPhotoProfil()
                    ? $this->generateUrl('app_agent_photo', ['id' => $personnel->getId()])
                    : null;
            } catch (\Exception $e) {
                $photoUrl = null;
            }
        }

        return $this->render('agent/show.html.twig', [
            'personnel' => $personnel,
            'photoUrl'  => $photoUrl,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_agent_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Personnel $personnel): Response
    {
        $this->denyAccessUnlessGranted('PERSONNEL_EDIT', $personnel);
        /** @var Personnel $user */
        $user   = $this->getUser();
        $isSelf = $user->getId() === $personnel->getId();
        $isSap  = $this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(PersonnelType::class, $personnel, [
            'is_new' => false, 'is_self' => $isSelf, 'is_sap' => $isSap,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain) { $this->handlePassword($personnel, $plain); }
            $personnel->setMajCompte(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'Personnel mis à jour.');
            return $this->redirectToRoute('app_agent_show', ['id' => $personnel->getId()]);
        }

        $photoUrl = null;
        if ($personnel->getPhotoProfil()) {
            try {
                // Avec SubdirDirectoryNamer actif :
                $filename = $personnel->getPhotoProfil();
                $subdir = substr($filename, 0, 3);
                $photoUrl = $personnel->getPhotoProfil()
                    ? $this->generateUrl('app_agent_photo', ['id' => $personnel->getId()])
                    : null;
            } catch (\Exception) {}
        }

        return $this->render('agent/edit.html.twig', [
            'form' => $form,
            'personnel' => $personnel,
            'is_sap' => $isSap,
            'photoUrl' => $photoUrl,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_agent_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Personnel $personnel): Response
    {
        if ($this->isCsrfTokenValid('delete-'.$personnel->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($personnel);
            $this->em->flush();
            $this->addFlash('success', 'Personnel supprimé.');
        }
        return $this->redirectToRoute('app_agent_index');
    }

    #[Route('/{id}/status', name: 'app_agent_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleStatus(Request $request, Personnel $personnel): Response
    {
        $status = $request->request->get('status');
        $personnel->setStatusCompte(StatusCompte::from($status));
        $this->em->flush();
        $this->addFlash('success', "Statut mis à jour : {$status}");
        return $this->redirectToRoute('app_agent_show', ['id' => $personnel->getId()]);
    }

    private function handlePassword(Personnel $p, ?string $plain): void
    {
        $raw = $plain ?: $p->getNomAg();
        $p->setPassword($this->hasher->hashPassword($p, $raw));
    }

    private function handleUsername(Personnel $p, bool $auto): void
    {
        if ($auto || !$p->getUsername()) {
            $p->setUsername($p->getIM() . strtolower($p->getNomAg() ?? '') . ($p->getDateNaissAg()?->format('dmY') ?? ''));
        }
    }
}
