<?php

namespace App\Controller;

use App\Entity\Personnel;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/personnel')]
class PersonnelController extends AbstractController
{
    public function __construct(private PersonnelRepository $personnelRepository) {}

    // ── LIST ─────────────────────────────────────────────────────────────────

    #[Route('/', name: 'app_personnel_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('q', '');
        $status = $request->query->get('status', '');
        $page   = $request->query->getInt('page', 1);

        $queryBuilder = $this->personnelRepository->findByCriteriaQuery($search, $status);

        $personnels = $paginator->paginate(
            $queryBuilder, // 👈 QueryBuilder ici
            $page,
            10 // 👈 nombre par page
        );

        return $this->render('personnel/index.html.twig', [
            'personnels' => $personnels,
            'search'     => $search,
            'status'     => $status,
        ]);
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    #[Route('/nouveau', name: 'app_personnel_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('personnel/new.html.twig');
    }

    // ── SHOW ──────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'app_personnel_show', methods: ['GET'])]
    public function show(Personnel $personnel): Response
    {
        return $this->render('personnel/show.html.twig', [
            'personnel' => $personnel,
        ]);
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    #[Route('/{id}/modifier', name: 'app_personnel_edit', methods: ['GET'])]
    public function edit(Personnel $personnel): Response
    {

        $enfants = array_map(function ($e) {
            return [
                'nom' => $e->getNom(),
                'prenom' => $e->getPrenom(),
                'dateNaiss' => $e->getDateNaiss()?->format('Y-m-d'),
                'sexe' => $e->getSexe()?->value,
            ];
        }, $personnel->getEnfants()->toArray());

        $contacts = [];
        if (is_iterable($personnel->getContactAg())) {
            foreach ($personnel->getContactAg() as $contact) {
                if (is_array($contact) && isset($contact['type'], $contact['valeur'])) {
                    $contacts[] = $contact;
                } elseif (is_string($contact)) {
                    $contacts[] = ['type' => 'mobile', 'valeur' => $contact];
                }
            }
        }

        return $this->render('personnel/edit.html.twig', [
            'personnel' => $personnel,
            'enfantsArray' => $enfants,
            'contactsArray' => $contacts,
        ]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    #[Route('/{id}/supprimer', name: 'app_personnel_delete', methods: ['POST'])]
    public function delete(Request $request, Personnel $personnel, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_' . $personnel->getId(), $request->request->get('_token'))) {
            // Vérifier qu'il reste au moins un admin
            if (in_array('ROLE_ADMIN', $personnel->getRoles(), true)) {
                $admins = $this->personnelRepository->findByRole('ROLE_ADMIN');
                if (count($admins) <= 1) {
                    $this->addFlash('danger', 'Impossible de supprimer le dernier administrateur.');
                    return $this->redirectToRoute('app_personnel_index');
                }
            }

            $em->remove($personnel);
            $em->flush();
            $this->addFlash('success', 'Personnel supprimé avec succès.');
        }

        return $this->redirectToRoute('app_personnel_index');
    }

    // ── TOGGLE STATUS ─────────────────────────────────────────────────────────

    #[Route('/{id}/toggle-status', name: 'app_personnel_toggle_status', methods: ['POST'])]
    public function toggleStatus(Personnel $personnel, EntityManagerInterface $em): Response
    {
        $current = $personnel->getStatusCompte();
        $new = match ($current?->value) {
            'Actif'    => \App\Enum\StatusCompte::INACTIF,
            'Inactif'  => \App\Enum\StatusCompte::ACTIF,
            'Suspendu' => \App\Enum\StatusCompte::ACTIF,
            default    => \App\Enum\StatusCompte::ACTIF,
        };
        $personnel->setStatusCompte($new);
        $em->flush();

        $this->addFlash('success', 'Statut mis à jour : ' . $new->value);
        return $this->redirectToRoute('app_personnel_show', ['id' => $personnel->getId()]);
    }
}
