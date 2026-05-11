<?php

namespace App\Controller;

use App\Entity\Document;
use App\Enum\StatusDoc;
use App\Form\EspaceType;
use App\Repository\DocumentRepository;
use App\Repository\DossierRepository;
use App\Repository\PersonnelRepository;
use App\Repository\TypeDocumentRepository;
use App\Service\MinioService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Notifier\Recipient\NoRecipient;

#[Route('/espace')]
class EspaceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DocumentRepository $documentRepository,
        private MinioService $minioService,
        private NotifierInterface $notifier,
        private PaginatorInterface $paginator,
        private DossierRepository $dossierRepository,
        private TypeDocumentRepository $typeDocumentRepository,
        private PersonnelRepository $personnelRepository,
    ) {}

    // ─── INDEX ────────────────────────────────────────────────────────────────

    #[Route('/', name: 'app_espace_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\Personnel $user */
        $user  = $this->getUser();
        $roles = $user->getRoles();

        $filters = [
            'typeDocument' => $request->query->get('typeDocument'),
            'dossier'      => $request->query->get('dossier'),
            'statut'       => $request->query->get('statut'),
            'dateArrive'   => $request->query->get('dateArrive'),
            'search'       => $request->query->get('search'),
        ];

        $qb = $this->documentRepository->createQueryBuilder('d')
            ->leftJoin('d.typeDocumentID', 't')
            ->leftJoin('t.dossierID', 'dos')
            ->leftJoin('d.personnelID', 'p')
            ->addOrderBy('d.creationDoc', 'DESC');

        // ── Restriction par rôle ──────────────────────────────────────────────

        if ($this->isGranted('ROLE_ADMIN')) {
            // ADMIN : tous les documents, tous statuts, tous personnels, toute confidentialité
            // Aucun filtre supplémentaire

        } elseif ($this->isGranted('ROLE_SAP')) {
            // SAP : ses propres BROUILLON + tous les APPROUVE + tous les ARCHIVE
            // (toute confidentialité)
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        'd.statucDoc = :brouillon',
                        'd.personnelID = :userSap'
                    ),
                    'd.statucDoc IN (:statutsSap)'
                )
            )
            ->setParameter('brouillon', StatusDoc::BROUILLON)
            ->setParameter('userSap', $user)
            ->setParameter('statutsSap', [StatusDoc::APPROUVE, StatusDoc::ARCHIVE]);

        } elseif ($this->isGranted('ROLE_CHEF')) {
            // CHEF : ses propres BROUILLON
            //      + tous les SOUMIS de son agence/service
            //      + tous les APPROUVE de son agence/service
            //      + tous les ARCHIVE de son agence/service
            // (toute confidentialité)
            $agences = $user->getAgencePersonnels()
                ->map(fn($ap) => $ap->getAgenceID()?->getId())
                ->filter(fn($id) => $id !== null)
                ->toArray();

            $services = $user->getDirectionPersonnels()
                ->map(fn($dp) => $dp->getServiceID()?->getId())
                ->filter(fn($id) => $id !== null)
                ->toArray();

            // Jointures pour filtrer par agence/service du personnel auteur
            $qb->leftJoin('p.agencePersonnels', 'apChef')
               ->leftJoin('apChef.agenceID', 'ag')
               ->leftJoin('p.directionPersonnels', 'dpChef')
               ->leftJoin('dpChef.serviceID', 'ser');

            // Construction dynamique de la condition de zone
            $conditions = [];
            if (!empty($agences)) {
                $conditions[] = $qb->expr()->in('ag.id', ':agences');
                $qb->setParameter('agences', $agences);
            }
            if (!empty($services)) {
                $conditions[] = $qb->expr()->in('ser.id', ':services');
                $qb->setParameter('services', $services);
            }

            $zoneCondition = !empty($conditions)
                ? $qb->expr()->orX(...$conditions)
                : '1=0';

            $qb->andWhere(
                $qb->expr()->orX(
                    // Ses propres brouillons
                    $qb->expr()->andX(
                        'd.statucDoc = :brouillon',
                        'd.personnelID = :userChef'
                    ),
                    // APPROUVE + ARCHIVE de son agence/service
                    $qb->expr()->andX(
                        'd.statucDoc IN (:statutsChef)',
                        $zoneCondition
                    )
                )
            )
            ->setParameter('brouillon', StatusDoc::BROUILLON)
            ->setParameter('userChef', $user)
            ->setParameter('statutsChef', [StatusDoc::SOUMIS, StatusDoc::APPROUVE, StatusDoc::ARCHIVE]);

        } elseif ($this->isGranted('ROLE_RH')) {
            // RH : ses propres BROUILLON + tous les ARCHIVE
            // (public et confidentiel seulement → géré par getAllowedConfidentialLevels)
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        'd.statucDoc = :brouillon',
                        'd.personnelID = :userRh'
                    ),
                    'd.statucDoc = :archive'
                )
            )
            ->setParameter('brouillon', StatusDoc::BROUILLON)
            ->setParameter('userRh', $user)
            ->setParameter('archive', StatusDoc::ARCHIVE);

        } else {
            // USER : tous ses propres documents (tous statuts), toute confidentialité
            $qb->andWhere('d.personnelID = :personnel')
               ->setParameter('personnel', $user);
        }

        // ── Restriction confidentialité ───────────────────────────────────────
        // Ne pas appliquer pour ADMIN, SAP, USER et CHEF (ils voient tout)
        // Appliquer uniquement pour RH
        $allowedLevels = $this->getAllowedConfidentialLevels($roles);
        if (!empty($allowedLevels)) {
            $qb->andWhere('dos.niveauConf IN (:levels)')
               ->setParameter('levels', $allowedLevels);
        }

        // ── Filtres ───────────────────────────────────────────────────────────
        if ($filters['typeDocument']) {
            $qb->andWhere('t.id = :typeDoc')
               ->setParameter('typeDoc', $filters['typeDocument']);
        }
        if ($filters['dossier']) {
            $qb->andWhere('dos.id = :dossier')
               ->setParameter('dossier', $filters['dossier']);
        }
        if ($filters['statut']) {
            $statutEnum = StatusDoc::tryFrom($filters['statut']);
            if ($statutEnum) {
                $qb->andWhere('d.statucDoc = :statutFilter')
                   ->setParameter('statutFilter', $statutEnum);
            }
        }
        if ($filters['dateArrive']) {
            $qb->andWhere('d.dateArriveDoc = :date')
               ->setParameter('date', new \DateTime($filters['dateArrive']));
        }

        // ── Filtre search (IM formaté ou nom) ─────────────────────────────────
        if ($filters['search']) {
            if ($this->isGranted('ROLE_ADMIN')) {
                $statusAllowed = [];
            } elseif ($this->isGranted('ROLE_SAP')) {
                $statusAllowed = [StatusDoc::APPROUVE, StatusDoc::ARCHIVE, StatusDoc::BROUILLON];
            } elseif ($this->isGranted('ROLE_CHEF')) {
                $statusAllowed = [StatusDoc::APPROUVE, StatusDoc::ARCHIVE, StatusDoc::BROUILLON];
            } elseif ($this->isGranted('ROLE_RH')) {
                $statusAllowed = [StatusDoc::ARCHIVE, StatusDoc::BROUILLON];
            } else {
                $statusAllowed = null; // USER : pas de search sur autres personnels
            }

            if ($statusAllowed !== null) {
                $ids = $this->documentRepository->searchByIMOrNom(
                    $filters['search'],
                    $statusAllowed,
                );

                if (!empty($ids)) {
                    $qb->andWhere('d.id IN (:searchIds)')
                       ->setParameter('searchIds', $ids);
                } else {
                    $qb->andWhere('1 = 0');
                }
            }
        }

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        // Statuts disponibles pour le filtre selon le rôle
        $statutsDisponibles = match(true) {
            $this->isGranted('ROLE_ADMIN') => StatusDoc::cases(),
            $this->isGranted('ROLE_SAP')   => [StatusDoc::BROUILLON, StatusDoc::APPROUVE, StatusDoc::ARCHIVE],
            $this->isGranted('ROLE_CHEF')  => [StatusDoc::BROUILLON, StatusDoc::SOUMIS, StatusDoc::APPROUVE, StatusDoc::ARCHIVE],
            $this->isGranted('ROLE_RH')    => [StatusDoc::BROUILLON, StatusDoc::ARCHIVE],
            default                        => StatusDoc::cases(),
        };

        return $this->render('espace/index.html.twig', [
            'pagination'         => $pagination,
            'filters'            => $filters,
            'dossiers'           => $this->dossierRepository->findAll(),
            'typesDocument'      => $this->typeDocumentRepository->findAll(),
            'statutsDisponibles' => $statutsDisponibles,
        ]);
    }

    // ─── NEW ──────────────────────────────────────────────────────────────────

    #[Route('/new', name: 'app_espace_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        /** @var \App\Entity\Personnel $user */
        $user = $this->getUser();

        // RH pur (sans SAP, CHEF ni ADMIN) ne peut pas créer
        if ($this->isGranted('ROLE_RH')
            && !$this->isGranted('ROLE_SAP')
            && !$this->isGranted('ROLE_CHEF')
            && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Le rôle RH ne peut pas créer de documents.');
        }

        $document = new Document();
        $document->setStatucDoc(StatusDoc::BROUILLON);
        $document->setPersonnelID($user);

        $form = $this->createForm(EspaceType::class, $document, [
            'current_user'    => $user,
            'is_admin_or_sap' => $this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setReference($this->generateReference($document));

            $this->em->persist($document);
            $this->em->flush();

            $this->sendNotificationAfterCreate($document, $user);

            $this->addFlash('success', 'Document créé avec succès.');
            return $this->redirectToRoute('app_espace_index');
        }

        return $this->render('espace/new.html.twig', [
            'form'     => $form,
            'document' => $document,
        ]);
    }

    // ─── PREVIEW ─────────────────────────────────────────────────────────────

    #[Route('/{id}/preview', name: 'app_espace_preview', methods: ['GET'])]
    public function preview(Document $document): StreamedResponse
    {
        $this->denyAccessUnlessGranted('VIEW', $document);

        if (!$document->getFichier()) {
            throw $this->createNotFoundException('Aucun fichier associé.');
        }

        try {
            $stream = $this->minioService->getStream('documents', $document->getFichier());
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Fichier introuvable dans le stockage.');
        }

        $ext = strtolower(pathinfo($document->getFichier(), PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        });

        $response->headers->set('Content-Type', $mimeMap[$ext] ?? 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'inline; filename="' . basename($document->getFichier()) . '"');
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_espace_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $document);

        $previewUrl = $document->getFichier()
            ? $this->generateUrl('app_espace_preview', ['id' => $document->getId()])
            : null;

        $canApprove    = $this->isGranted('ROLE_CHEF') && $document->getStatucDoc() === StatusDoc::SOUMIS;
        $canArchive    = ($this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN')) && $document->getStatucDoc() === StatusDoc::APPROUVE;
        $canRejectSap  = ($this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN')) && $document->getStatucDoc() === StatusDoc::APPROUVE;
        $canRejectChef = $this->isGranted('ROLE_CHEF') && $document->getStatucDoc() === StatusDoc::SOUMIS;
        $canDownload   = $document->getStatucDoc() === StatusDoc::ARCHIVE;
        $canDelete     = in_array($document->getStatucDoc(), [StatusDoc::BROUILLON, StatusDoc::REJETE]);

        return $this->render('espace/show.html.twig', [
            'document'      => $document,
            'previewUrl'    => $previewUrl,
            'canApprove'    => $canApprove,
            'canArchive'    => $canArchive,
            'canRejectSap'  => $canRejectSap,
            'canRejectChef' => $canRejectChef,
            'canDownload'   => $canDownload,
            'canDelete'     => $canDelete,
        ]);
    }

    // ─── EDIT ─────────────────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'app_espace_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $document);

        /** @var \App\Entity\Personnel $user */
        $user = $this->getUser();

        $previewUrl = null;
        if ($document->getFichier()) {
            try {
                $previewUrl = $this->minioService->getSignedUrl('documents', $document->getFichier());
            } catch (\Exception $e) {}
        }

        $form = $this->createForm(EspaceType::class, $document, [
            'current_user'    => $user,
            'is_admin_or_sap' => $this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setReference($this->generateReference($document));
            $this->em->flush();

            $this->addFlash('success', 'Document modifié avec succès.');
            return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
        }

        return $this->render('espace/edit.html.twig', [
            'form'       => $form,
            'document'   => $document,
            'previewUrl' => $previewUrl,
        ]);
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'app_espace_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $document);

        if ($this->isCsrfTokenValid('delete' . $document->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $this->minioService->deleteFile('documents', $document->getFichier());
            } catch (\Exception $e) {}

            $this->em->remove($document);
            $this->em->flush();
            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('app_espace_index');
    }

    // ─── WORKFLOW ─────────────────────────────────────────────────────────────

    #[Route('/{id}/approuver', name: 'app_espace_approuver', methods: ['POST'])]
    #[IsGranted('ROLE_CHEF')]
    public function approuver(Request $request, Document $document): Response
    {
        if ($document->getStatucDoc() !== StatusDoc::SOUMIS) {
            $this->addFlash('error', 'Ce document ne peut pas être approuvé.');
            return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
        }

        if ($this->isCsrfTokenValid('approuver' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $document->setStatucDoc(StatusDoc::APPROUVE);
            $this->em->flush();

            $this->notifyRole('SAP', $document, 'Un document a été approuvé et attend archivage.');
            $this->addFlash('success', 'Document approuvé. SAP a été notifié.');
        }

        return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/rejeter', name: 'app_espace_rejeter', methods: ['POST'])]
    public function rejeter(Request $request, Document $document): Response
    {
        /** @var \App\Entity\Personnel $user */
        $user = $this->getUser();

        $canReject =
            ($this->isGranted('ROLE_CHEF') && $document->getStatucDoc() === StatusDoc::SOUMIS) ||
            (($this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN')) && $document->getStatucDoc() === StatusDoc::APPROUVE);

        if (!$canReject) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
        }

        if ($this->isCsrfTokenValid('rejeter' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $document->setStatucDoc(StatusDoc::REJETE);
            $this->em->flush();

            $notification = new Notification(
                'Document rejeté : ' . ($document->getTitre() ?? $document->getReference()),
                ['browser']
            );
            $notification->content('Votre document a été rejeté par ' . $user->getFullName() . '.');
            $this->notifier->send($notification, new NoRecipient());

            $this->addFlash('warning', 'Document rejeté. Le créateur a été notifié.');
        }

        return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
    }

    #[Route('/{id}/archiver', name: 'app_espace_archiver', methods: ['POST'])]
    public function archiver(Request $request, Document $document): Response
    {
        if (!($this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN'))) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($document->getStatucDoc(), [StatusDoc::APPROUVE, StatusDoc::BROUILLON])) {
            $this->addFlash('error', 'Ce document ne peut pas être archivé.');
            return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
        }

        if ($this->isCsrfTokenValid('archiver' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $document->setStatucDoc(StatusDoc::ARCHIVE);
            $this->em->flush();

            $notification = new Notification(
                'Document archivé : ' . ($document->getTitre() ?? $document->getReference()),
                ['browser']
            );
            $notification->content('Votre document a été archivé.');
            $this->notifier->send($notification, new NoRecipient());

            $this->addFlash('success', 'Document archivé avec succès.');
        }

        return $this->redirectToRoute('app_espace_show', ['id' => $document->getId()]);
    }

    // ─── DOWNLOAD ─────────────────────────────────────────────────────────────

    #[Route('/{id}/download', name: 'app_espace_download', methods: ['GET'])]
    public function download(Document $document): StreamedResponse
    {
        $this->denyAccessUnlessGranted('VIEW', $document);

        if ($document->getStatucDoc() !== StatusDoc::ARCHIVE) {
            throw $this->createAccessDeniedException('Seuls les documents archivés peuvent être téléchargés.');
        }

        try {
            $stream = $this->minioService->getStream('documents', $document->getFichier());
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $document->getFichier() . '"');

        return $response;
    }

    // ─── MÉTHODES PRIVÉES ─────────────────────────────────────────────────────

    private function generateReference(Document $document): string
    {
        /** @var \App\Entity\Personnel $personnel */
        $personnel    = $document->getPersonnelID();
        $typeDocument = $document->getTypeDocumentID();
        $dossier      = $typeDocument?->getDossierID();

        $parts   = [];
        $parts[] = $personnel ? $personnel->getIMFormatted() : 'XXX';
        $parts[] = $dossier ? $dossier->getNomenclature() : 'DOS';

        if ($document->getTitulaire()) {
            $parts[] = strtoupper(substr(preg_replace('/\s+/', '', $document->getTitulaire()), 0, 8));
        }

        $parts[] = $typeDocument
            ? strtoupper(substr(preg_replace('/\s+/', '', $typeDocument->getNomTypeDoc()), 0, 6))
            : 'TYPE';

        if ($document->getDateArriveDoc()) {
            $parts[] = 'du_' . $document->getDateArriveDoc()->format('dmY');
        }

        return implode('_', $parts);
    }

    /**
     * Retourne les niveaux de confidentialité autorisés selon le rôle.
     * Renvoie null (tableau vide) si aucune restriction n'est nécessaire
     * (ADMIN, SAP, CHEF, USER voient tout).
     * Seul RH est restreint à Public + Confidentiel.
     */
    private function getAllowedConfidentialLevels(array $roles): array
    {
        // ADMIN, SAP, CHEF, USER : accès à tous les niveaux → pas de filtre SQL
        if (
            in_array('ROLE_ADMIN', $roles) ||
            in_array('ROLE_SAP', $roles)   ||
            in_array('ROLE_CHEF', $roles)  ||
            in_array('ROLE_USER', $roles)
        ) {
            return []; // tableau vide = pas de restriction (le WHERE ne sera pas ajouté)
        }

        // RH : Public et Confidentiel seulement
        if (in_array('ROLE_RH', $roles)) {
            return ['Public', 'Confidentiel'];
        }

        // Fallback sécurisé
        return ['Public'];
    }

    private function notifyRole(string $role, Document $document, string $message): void
    {
        $notification = new Notification(
            $document->getTitre() ?? $document->getReference(),
            ['browser']
        );
        $notification->content($message);
        $this->notifier->send($notification, new NoRecipient());
    }

    private function sendNotificationAfterCreate(Document $document, $user): void
    {
        $statut = $document->getStatucDoc();

        if ($statut === StatusDoc::BROUILLON) {
            $notification = new Notification(
                'Brouillon : ' . ($document->getTitre() ?? $document->getReference()),
                ['browser']
            );
            $notification->content('Votre document a été enregistré en brouillon.');
            $this->notifier->send($notification, new NoRecipient());

        } elseif ($statut === StatusDoc::SOUMIS) {
            $this->notifyRole('CHEF', $document, 'Un nouveau document vous a été soumis.');
        }
    }
}