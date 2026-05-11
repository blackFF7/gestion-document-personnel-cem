<?php

namespace App\Service;

use App\Entity\Personnel;
use App\Repository\DocumentRepository;

class DashboardService
{
    public function __construct(
        private DocumentRepository $documentRepository
    ) {}

    public function build(Personnel $user): array
    {
        $roles = $user->getRoles();

        return [
            'stats' => $this->buildStats($user, $roles),
            'quickAccess' => $this->quickAccess($roles),
            'recentDocuments' => $this->recentDocuments($user),
            'charts' => $this->buildCharts($user, $roles)
        ];
    }

    private function buildStats(Personnel $user, array $roles): array
    {
        // Statistiques globales
        $stats = [
            'totalDocuments' => $this->documentRepository->count(['personnelID' => $user]),
            'validatedDocuments' => $this->documentRepository->countValidated($user),
            'pendingDocuments' => $this->documentRepository->countPending($user),
        ];

        // Statistiques par type de document
        $stats['byType'] = $this->documentRepository->countByType($user);

        return $stats;
    }

    private function buildCharts(Personnel $user, array $roles): array
    {
        // Graphes différents selon les rôles
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->documentRepository->getChartsAdmin();
        }

        if (in_array('ROLE_RH', $roles)) {
            return $this->documentRepository->getChartsRH($user);
        }

        // Pour les autres roles
        return $this->documentRepository->getChartsUser($user);
    }

    private function quickAccess(array $roles): array
    {
        if (in_array('ROLE_ADMIN', $roles)) {
            return ['users','documents','settings'];
        }

        if (in_array('ROLE_RH', $roles)) {
            return ['validation','personnel','archives'];
        }

        return ['upload','mes_documents'];
    }

    private function recentDocuments(Personnel $user)
    {
        return $this->documentRepository->findRecentDocuments($user);
    }
}