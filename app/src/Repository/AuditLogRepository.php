<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    // ── Requêtes filtrées ─────────────────────────────────────────────────────

    /**
     * Trouve les logs d'audit filtrés avec pagination.
     */
    public function findFiltered(
        ?string $entityType  = null,
        ?string $action      = null,
        ?string $search      = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo   = null,
        int $page   = 1,
        int $limit  = 30
    ): array {
        $qb = $this->createFilteredQb($entityType, $action, $search, $dateFrom, $dateTo);

        $total = (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $results = $qb
            ->select('a', 'author')
            ->leftJoin('a.author', 'author')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items'      => $results,
            'total'      => (int) $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Tous les logs d'un agent donné.
     */
    public function findByPersonnel(string $personnelId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'author')
            ->addSelect('author')
            ->where('a.entityType = :type AND a.entityId = :id')
            ->orWhere('author.id = :authorId')
            ->setParameter('type', 'personnel')
            ->setParameter('id', $personnelId)
            ->setParameter('authorId', $personnelId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les logs d'un document donné.
     */
    public function findByDocument(string $documentId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'author')
            ->addSelect('author')
            ->where('a.entityType = :type AND a.entityId = :id')
            ->setParameter('type', 'document')
            ->setParameter('id', $documentId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ── Statistiques pour le dashboard audit ─────────────────────────────────

    public function countByAction(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.action, COUNT(a.id) as cnt')
            ->groupBy('a.action')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['action']] = (int) $row['cnt'];
        }
        return $result;
    }

    public function countByEntityType(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.entityType, COUNT(a.id) as cnt')
            ->groupBy('a.entityType')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['entityType']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Activité des N derniers jours (pour graphe).
     */
    public function countDailyActivity(int $days = 30): array
    {
        $from = new \DateTimeImmutable("-{$days} days midnight");

        $rows = $this->createQueryBuilder('a')
            ->select("DATE(a.createdAt) as day, COUNT(a.id) as cnt")
            ->where('a.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Remplir les jours manquants avec 0
        $result = [];
        $current = $from;
        $end     = new \DateTimeImmutable('today midnight');
        while ($current <= $end) {
            $key           = $current->format('Y-m-d');
            $result[$key]  = 0;
            $current       = $current->modify('+1 day');
        }
        foreach ($rows as $row) {
            $result[$row['day']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Les N auteurs les plus actifs.
     */
    public function findTopAuthors(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->select('author.nomAg, author.prenomAg, COUNT(a.id) as cnt')
            ->leftJoin('a.author', 'author')
            ->where('author.id IS NOT NULL')
            ->groupBy('author.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTodayActivity(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :today')
            ->setParameter('today', new \DateTimeImmutable('today midnight'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ── QueryBuilder partagé ──────────────────────────────────────────────────

    private function createFilteredQb(
        ?string $entityType,
        ?string $action,
        ?string $search,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('a');

        if ($entityType) {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }
        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }
        if ($search) {
            $qb->andWhere('a.entityLabel LIKE :search OR a.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')
               ->setParameter('dateTo', $dateTo->modify('+1 day'));
        }

        return $qb;
    }
}
