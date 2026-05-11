<?php

namespace App\Controller\Admin;

use App\Repository\DocumentRepository;
use App\Repository\PersonnelRepository;
use App\Repository\DossierRepository;
use App\Service\DashboardService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class AdminController extends AbstractDashboardController
{
    public function __construct(
        private DocumentRepository $documentRepo,
        private PersonnelRepository $personnelRepo,
        private DossierRepository $dossierRepo,
        private DashboardService $dashboardService
    ) {}

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
            'byDirection'     => $this->personnelRepo->countByDirection(),
            'byAgence'        => $this->personnelRepo->countByAgence(),
            'monthlyDeposits' => $this->documentRepo->countMonthly(12),
            'lastDocuments'   => $this->documentRepo->findLastN(10),
        ];
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', $this->buildAdminData());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('DigitDoc - Administration')
            ->renderContentMaximized()
            ->setDefaultColorScheme('light')
            ->setFaviconPath('icons/logo3.png');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Entreprises');
        yield MenuItem::linkTo(AgenceCrudController ::class, 'Agences', 'fas fa-building');
        yield MenuItem::linkTo(DirectionCrudController::class, 'Directions', 'fas fa-building');
        yield MenuItem::linkTo(ServiceCrudController::class, 'Services', 'fas fa-building');
        
        yield MenuItem::section('Personnel');
        yield MenuItem::linkTo(CategorieCrudController::class, 'Catégories', 'fas fa-tags');
        yield MenuItem::linkTo(FonctionCrudController::class, 'Fonctions', 'fas fa-user-tie');

        yield MenuItem::section('Documents');
        yield MenuItem::linkTo(DossierCrudController::class, 'Dossiers', 'fas fa-folder');
        yield MenuItem::linkTo(TypeDocumentCrudController::class, 'Types de documents', 'fas fa-file-alt');
    }
}
