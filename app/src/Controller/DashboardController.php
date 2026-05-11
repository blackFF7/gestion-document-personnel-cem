<?php

namespace App\Controller;

use App\Repository\DocumentRepository;
use App\Repository\PersonnelRepository;
use App\Repository\DossierRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/')]
class DashboardController extends AbstractController
{
    public function __construct(
        private DocumentRepository  $documentRepo,
        private PersonnelRepository $personnelRepo,
        private DossierRepository   $dossierRepo,
    ) {}

    #[Route('', name: 'app_dashboard')]
    public function index(): Response
    {
        $user  = $this->getUser();
        $roles = $user->getRoles();

        $data = match (true) {
            in_array('ROLE_ADMIN', $roles) => $this->buildAdminData(),
            in_array('ROLE_SAP',   $roles) => $this->buildSapData(),
            in_array('ROLE_RH',    $roles) => $this->buildRhData(),
            in_array('ROLE_CHEF',  $roles) => $this->buildChefData(),
            default                        => $this->buildUserData($user),
        };

        return $this->render('dashboard/index.html.twig', $data);
    }

    // ── ADMIN ──────────────────────────────────────────────────────────────

    private function buildAdminData(): array
    {
        $statuts  = ['Brouillon', 'Soumis', 'Approuvé', 'Rejeté', 'Archivé'];
        $byStatus = [];
        foreach ($statuts as $s) {
            $byStatus[$s] = $this->documentRepo->countByStatus($s);
        }

        $niveaux = ['Public', 'Confidentiel', 'Strictement confidentiel'];
        $byConf  = [];
        foreach ($niveaux as $n) {
            $byConf[$n] = $this->documentRepo->countByConfidentialite($n);
        }

        return [
            'role'  => 'ADMIN',
            'stats' => [
                'total'     => array_sum($byStatus),
                'personnel' => $this->personnelRepo->countActifs(),
                'soumis'    => $byStatus['Soumis'],
                'dossiers'  => $this->dossierRepo->count([]),
            ],
            'byStatus'        => $byStatus,
            'byConf'          => $byConf,
            'byDirection'     => $this->personnelRepo->countByDirection(),   // agents services
            'byAgence'        => $this->personnelRepo->countByAgence(),      // agents agences
            'monthlyDeposits' => $this->documentRepo->countMonthly(12),
            'lastDocuments'   => $this->documentRepo->findLastN(10),
        ];
    }

    // ── SAP ────────────────────────────────────────────────────────────────

    private function buildSapData(): array
    {
        return [
            'role'            => 'SAP',
            'stats'           => [
                'soumis'   => $this->documentRepo->countByStatus('Soumis'),
                'approuve' => $this->documentRepo->countByStatus('Approuvé'),
                'rejete'   => $this->documentRepo->countByStatus('Rejeté'),
                'tauxAppro'=> $this->documentRepo->tauxApprobation(),
            ],
            'pendingDocuments'=> $this->documentRepo->findByStatus('Soumis'),
            'approByType'     => $this->documentRepo->approuveRejeteByType(),
            'queueWeekly'     => $this->documentRepo->queueEvolutionWeekly(),
        ];
    }

    // ── RH ─────────────────────────────────────────────────────────────────

    private function buildRhData(): array
    {
        return [
            'role'  => 'RH',
            'stats' => [
                'total'     => $this->personnelRepo->countActifs(),
                'nouveaux'  => $this->personnelRepo->countNouveauxTrimestre(),
                'docsRh'    => $this->documentRepo->countAccessibleByRole('ROLE_RH'),
                'aArchiver' => $this->documentRepo->countByStatus('Soumis'),
            ],
            'bySexe'       => $this->personnelRepo->countBySexe(),
            'byFamille'    => $this->personnelRepo->countBySituationFamiliale(),
            'byAnciennete' => $this->personnelRepo->countByAnciennete(),
            'byDirection'  => $this->personnelRepo->countByDirection(),
            'byAgence'     => $this->personnelRepo->countByAgence(),
        ];
    }

    // ── CHEF ───────────────────────────────────────────────────────────────

    private function buildChefData(): array
    {
        return [
            'role'        => 'CHEF',
            'stats'       => [
                'archives'  => $this->documentRepo->countArchivesPublics(),
                'ceMois'    => $this->documentRepo->countArchivesPublicsMois(),
                'service'   => $this->personnelRepo->countMonService($this->getUser()),
                'typesDoc'  => $this->documentRepo->countTypesPublics(),
            ],
            'archivesByType'   => $this->documentRepo->archivesPublicsByType(),
            'archivesMonthly'  => $this->documentRepo->archivesPublicsMonthly(6),
            'lastDocuments'    => $this->documentRepo->findArchivesPublics(10),
        ];
    }

    // ── USER ───────────────────────────────────────────────────────────────

    private function buildUserData($user): array
    {
        return [
            'role'       => 'USER',
            'stats'      => [
                'total'    => $this->documentRepo->countByPersonnel($user),
                'brouillon'=> $this->documentRepo->countByPersonnelAndStatus($user, 'Brouillon'),
                'soumis'   => $this->documentRepo->countByPersonnelAndStatus($user, 'Soumis'),
                'approuve' => $this->documentRepo->countByPersonnelAndStatus($user, 'Approuvé'),
            ],
            'myDocsByStatus'  => $this->documentRepo->countByPersonnelGroupedByStatus($user),
            'myMonthlyDeposit'=> $this->documentRepo->countMonthlyByPersonnel($user, 6),
            'lastDocuments'   => $this->documentRepo->findByPersonnel($user, 10),
        ];
    }

    #[Route('/graph', name: 'app_graph')]
    public function graph(
        DocumentRepository $documentRepo,
        ParameterBagInterface $params,
        Request $request,
        PaginatorInterface $paginator
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        $confMap = $params->get('access_confidential_levels');
        $statusMap = $params->get('access_status_levels');

        $allowedLevels = ['Public'];
        $allowedStatuses = ['Brouillon'];

        foreach (['ROLE_ADMIN','ROLE_SAP','ROLE_RH','ROLE_CHEF','ROLE_USER'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                if (isset($confMap[$role])) {
                    $allowedLevels = $confMap[$role];
                }
                if (isset($statusMap[$role])) {
                    $allowedStatuses = $statusMap[$role];
                }
                break;
            }
        }

        // ⚡ IMPORTANT : retourner un QueryBuilder (optimisation)
        $query = $documentRepo->findForUser(
            $user,
            $allowedLevels,
            $allowedStatuses
        );

        // Pagination
        $documents = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1), // page actuelle
            20 // nombre par page
        );

        return $this->render('personnel/graph.html.twig', [
            'documents' => $documents,
        ]);
    }
}