<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\DocumentRepository;

class DashboardStatsService
{
    public function __construct(
        private CacheInterface $cacheDashboard,  // injecté par nom du pool
        private DocumentRepository $documentRepository,
    ) {}

    /**
     * Statistiques des documents — mises en cache 10 minutes
     */
    public function getDocumentStats(string $personnelId): array
    {
        $cacheKey = "dashboard_stats_" . md5($personnelId);

        return $this->cacheDashboard->get($cacheKey, function (ItemInterface $item) use ($personnelId) {
            $item->expiresAfter(600); // 10 minutes

            return $this->documentRepository->getStatsByPersonnel($personnelId);
        });
    }

    /**
     * Invalider le cache d'un personnel après modification de document
     */
    public function invalidatePersonnelCache(string $personnelId): void
    {
        $cacheKey = "dashboard_stats_" . md5($personnelId);
        $this->cacheDashboard->delete($cacheKey);
    }
}
