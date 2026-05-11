<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Personnel;
use App\Entity\DirectionPersonnel;
use App\Entity\AgencePersonnel;
use App\Enum\StatusDoc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }


    // ── Utilitaire Enum → string ───────────────────────────────────────────────
    private function enumVal(mixed $value): string
    {
        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }

    public function countAccessibleByRole(string $role): int
    {
        // Récupère les niveaux autorisés depuis les paramètres
        // Ici on les passe en dur ou via injection — voir note ci-dessous
        $niveauxParRole = [
            'ROLE_USER'  => ['Public', 'Confidentiel', 'Strictement confidentiel'],
            'ROLE_CHEF'  => ['Public'],
            'ROLE_RH'    => ['Public', 'Confidentiel'],
            'ROLE_SAP'   => ['Public', 'Confidentiel', 'Strictement confidentiel'],
            'ROLE_ADMIN' => ['Public', 'Confidentiel', 'Strictement confidentiel'],
        ];

        $niveaux = $niveauxParRole[$role] ?? ['Public'];

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('dos.niveauConf IN (:niveaux)')
            ->setParameter('niveaux', $niveaux)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countArchivesPublics(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('d.statucDoc = :s AND dos.niveauConf = :n')
            ->setParameter('s', 'Archivé')
            ->setParameter('n', 'Public')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countArchivesPublicsMois(): int
    {
        $debut = new \DateTime('first day of this month');

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('d.statucDoc = :s AND dos.niveauConf = :n AND d.majDoc >= :debut')
            ->setParameter('s', 'Archivé')
            ->setParameter('n', 'Public')
            ->setParameter('debut', $debut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTypesPublics(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(DISTINCT t.id)')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('dos.niveauConf = :n')
            ->setParameter('n', 'Public')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function archivesPublicsByType(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('t.nomTypeDoc AS label, COUNT(d.id) AS total')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('d.statucDoc = :s AND dos.niveauConf = :n')
            ->setParameter('s', 'Archivé')
            ->setParameter('n', 'Public')
            ->groupBy('t.nomTypeDoc')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['label']] = (int) $row['total'];
        }
        return $result;
    }

public function archivesPublicsMonthly(int $months): array
{
    $conn  = $this->getEntityManager()->getConnection();
    $since = (new \DateTime("-{$months} months"))->format('Y-m-d');

    $sql = "
        SELECT TO_CHAR(d.maj_doc, 'YYYY-MM') AS mois, COUNT(*) AS total
        FROM document d
        JOIN type_document t ON t.id = d.type_document_id_id
        JOIN dossier dos      ON dos.id = t.dossier_id_id
        WHERE d.statuc_doc = 'Archivé'
          AND dos.niveau_conf = 'Public'
          AND d.maj_doc >= :since
        GROUP BY mois
        ORDER BY mois
    ";
    return $conn->executeQuery($sql, ['since' => $since])->fetchAllAssociative();
}
public function findArchivesPublics(int $limit): array
{
    return $this->createQueryBuilder('d')
        ->join('d.typeDocumentID', 't')
        ->join('t.dossierID', 'dos')
        ->where('d.statucDoc = :s AND dos.niveauConf = :n')
        ->setParameter('s', 'Archivé')
        ->setParameter('n', 'Public')
        ->orderBy('d.majDoc', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

public function countByPersonnelGroupedByStatus($user): array
{
    $rows = $this->createQueryBuilder('d')
        ->select('d.statucDoc AS label, COUNT(d.id) AS total')
        ->where('d.personnelID = :u')
        ->setParameter('u', $user)
        ->groupBy('d.statucDoc')
        ->getQuery()
        ->getResult();

    $result = [];
    foreach ($rows as $row) {
        $result[$this->enumVal($row['label'])] = (int) $row['total'];
    }
    return $result;
}

public function countMonthlyByPersonnel($user, int $months): array
{
    $conn  = $this->getEntityManager()->getConnection();
    $since = (new \DateTime("-{$months} months"))->format('Y-m-d');

    $sql = "
        SELECT TO_CHAR(creation_doc, 'YYYY-MM') AS mois, COUNT(*) AS total
        FROM document
        WHERE personnel_id_id = :uid
          AND creation_doc >= :since
        GROUP BY mois
        ORDER BY mois
    ";
    return $conn->executeQuery($sql, [
        'uid'   => $user->getId(),
        'since' => $since,
    ])->fetchAllAssociative();
}

public function findByPersonnel($user, int $limit): array
{
    return $this->createQueryBuilder('d')
        ->where('d.personnelID = :u')
        ->setParameter('u', $user)
        ->orderBy('d.creationDoc', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

public function queueEvolutionWeekly(): array
{
    $conn  = $this->getEntityManager()->getConnection();
    $since = (new \DateTime('-7 days'))->format('Y-m-d');

    $sql = "
        SELECT TO_CHAR(creation_doc, 'Dy') AS jour,
               DATE_TRUNC('day', creation_doc) AS jour_date,
               COUNT(*) AS total
        FROM document
        WHERE statuc_doc = 'Soumis'
          AND creation_doc >= :since
        GROUP BY jour, jour_date
        ORDER BY jour_date
    ";
    return $conn->executeQuery($sql, ['since' => $since])->fetchAllAssociative();
}

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statucDoc = :s')
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByConfidentialite(string $niveau): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.typeDocumentID', 't')
            ->join('t.dossierID', 'dos')
            ->where('dos.niveauConf = :n')
            ->setParameter('n', $niveau)
            ->getQuery()->getSingleScalarResult();
    }

    public function countMonthly(int $months): array
    {
        $conn  = $this->getEntityManager()->getConnection();
        $since = (new \DateTime("-{$months} months"))->format('Y-m-d');

        $sql = "
            SELECT TO_CHAR(creation_doc, 'YYYY-MM') AS mois, COUNT(*) AS total
            FROM document
            WHERE creation_doc >= :since
            GROUP BY mois
            ORDER BY mois
        ";
        return $conn->executeQuery($sql, ['since' => $since])->fetchAllAssociative();
    }

    public function findByStatus(string $status, int $limit = 50): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.statucDoc = :s')
            ->setParameter('s', $status)
            ->orderBy('d.creationDoc', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function tauxApprobation(): float
    {
        $approuve = $this->countByStatus('Approuvé');
        $rejete   = $this->countByStatus('Rejeté');
        $total    = $approuve + $rejete;
        return $total > 0 ? round($approuve / $total * 100, 1) : 0;
    }

    public function approuveRejeteByType(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('t.nomTypeDoc AS type, d.statucDoc AS statut, COUNT(d.id) AS total')
            ->join('d.typeDocumentID', 't')
            ->where('d.statucDoc IN (:statuts)')
            ->setParameter('statuts', ['Approuvé', 'Rejeté'])
            ->groupBy('t.nomTypeDoc, d.statucDoc')
            ->getQuery()
            ->getResult();

        // Normalise les Enums avant de retourner
        return array_map(function (array $row) {
            return [
                'type'   => $row['type'],
                'statut' => $this->enumVal($row['statut']),
                'total'  => (int) $row['total'],
            ];
        }, $rows);
    }

    public function countByPersonnel($user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.personnelID = :u')
            ->setParameter('u', $user)
            ->getQuery()->getSingleScalarResult();
    }

    public function countByPersonnelAndStatus($user, string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.personnelID = :u')
            ->andWhere('d.statucDoc = :s')
            ->setParameter('u', $user)
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastN(int $n): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.creationDoc', 'DESC')
            ->setMaxResults($n)
            ->getQuery()->getResult();
    }















    public function countValidated($user): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.personnelID = :user')
            ->andWhere('d.statucDoc = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'VALIDATED')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPending($user): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.personnelID = :user')
            ->andWhere('d.statucDoc = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByType($user): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('t.typeDocumentID as type', 'COUNT(d.id) as total')
            ->join('d.typeDocumentID', 't')
            ->andWhere('d.personnelID = :user')
            ->setParameter('user', $user)
            ->groupBy('t.typeDocumentID')
            ->getQuery()
            ->getArrayResult();

        return $results;
    }

    public function findRecentDocuments($user)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.personnelID = :user')
            ->setParameter('user', $user)
            ->orderBy('d.creationDoc', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    // Graphes pour Admin
    public function getChartsAdmin(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('p.nomAg as personnel', 'COUNT(d.id) as total')
            ->join('d.personnelID', 'p')
            ->groupBy('p.id')
            ->getQuery();

        return $qb->getArrayResult();
    }

    // Graphes pour RH (statut par personnel)
    public function getChartsRH(Personnel $user): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.statucDoc as status', 'COUNT(d.id) as total')
            ->andWhere('d.personnelID = :user')
            ->setParameter('user', $user)
            ->groupBy('d.statucDoc')
            ->getQuery();

        return $qb->getArrayResult();
    }

    // Graphes pour utilisateur simple
    public function getChartsUser(Personnel $user): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.typeDocumentID as type', 'COUNT(d.id) as total')
            ->andWhere('d.personnelID = :user')
            ->setParameter('user', $user)
            ->groupBy('d.typeDocumentID')
            ->getQuery();

        return $qb->getArrayResult();
    }

    /**
     * Recherche de documents par IM formaté (avec zéros) ou nom du personnel
     */
    public function searchByIMOrNom(string $search): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = "
            SELECT DISTINCT d.id
            FROM document d
            LEFT JOIN personnel p ON d.personnel_id_id = p.id
            WHERE LPAD(p.im::text, 3, '0') LIKE :search
            OR LOWER(CONCAT(p.nom_ag, ' ', p.prenom_ag)) LIKE LOWER(:search)
            OR LOWER(CONCAT(p.prenom_ag, ' ', p.nom_ag)) LIKE LOWER(:search)
        ";
        
        $ids = $conn->executeQuery($sql, ['search' => '%' . $search . '%'])
                    ->fetchFirstColumn();
        
        return $ids;
    }











    public function countByStatusForPersonnel($personnel)
    {
        return $this->createQueryBuilder('d')
            ->select('d.statucDoc as status, COUNT(d.id) as total')
            ->where('d.personnelID = :personnel')
            ->setParameter('personnel', $personnel)
            ->groupBy('d.statucDoc')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByDossierForPersonnel($personnel)
    {
        return $this->createQueryBuilder('d')
            ->select('dos.nomDos as dossier, COUNT(d.id) as total')
            ->join('d.typeDocumentID', 'td')
            ->join('td.dossierID', 'dos')
            ->where('d.personnelID = :personnel')
            ->setParameter('personnel', $personnel)
            ->groupBy('dos.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countTotalForPersonnel($personnel)
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.personnelID = :personnel')
            ->setParameter('personnel', $personnel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function documentByPersonnel($personnel)
    {
        return $this->createQueryBuilder('d')
            ->where('d.personnelID = :personnel')
            ->setParameter('personnel', $personnel)
            ->getQuery()
            ->getResult();
    }

    public function documentByPersonnelStatus($personnel)
    {
        return $this->createQueryBuilder('d')
            ->where('d.personnelID = :personnel')
            ->andWhere('d.statucDoc = :status')
            ->setParameter('personnel', $personnel)
            ->setParameter('status', 'Archivé')
            ->orderBy('d.creationDoc', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForUser(Personnel $user, array $allowedLevels, array $allowedStatuses): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.personnelID', 'p')        // association exacte
            ->leftJoin('d.typeDocumentID', 'td')   // association exacte
            ->leftJoin('td.dossierID', 'dos')        // vérifier si TypeDocument::dossier s'appelle bien "dossier"
            ->addSelect('p','td','dos');

        // Admin & SAP: tous
        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SAP', $user->getRoles(), true)) {
            return $qb->orderBy('d.creationDoc','DESC')->getQuery()->getResult();
        }

        // Récupérer memberIds (déjà normalisés en strings)
        $memberIds = [];
        if (in_array('ROLE_CHEF', $user->getRoles(), true) || in_array('ROLE_RH', $user->getRoles(), true)) {
            $memberIds = $this->getMemberIdsForSameServiceOrAgence($user);
        }

        // Construire clause owner OR members
        $orX = $qb->expr()->orX();
        $orX->add('p.id = :me');
        $qb->setParameter('me', (string)$user->getId());

        if (!empty($memberIds)) {
            $orX->add('p.id IN (:members)');
            $qb->setParameter('members', $memberIds);
        }

        $qb->andWhere($orX);

        // Confidentiality and status filters (strings)
        $qb->andWhere('dos.niveauConf IN (:levels)')->setParameter('levels', $allowedLevels);
        $qb->andWhere('d.statucDoc IN (:statuses)')->setParameter('statuses', $allowedStatuses);

        return $qb->orderBy('d.creationDoc','DESC')->getQuery()->getResult();
    }


    /** helper */
    private function getMemberIdsForSameServiceOrAgence(Personnel $user): array
    {
        $em = $this->getEntityManager();
        $ids = [];

        // services de l'utilisateur
        $qb = $em->createQueryBuilder()
            ->select('IDENTITY(dp.serviceID) AS serviceId')
            ->from(DirectionPersonnel::class, 'dp')
            ->where('dp.personnelID = :user')
            ->setParameter('user', $user);

        $serviceRows = $qb->getQuery()->getScalarResult();
        $serviceIds = array_unique(array_filter(array_map(fn($r) => $r['serviceId'] ?? null, $serviceRows)));

        if (!empty($serviceIds)) {
            $qb2 = $em->createQueryBuilder()
                ->select('IDENTITY(dp2.personnelID) AS pid')
                ->from(DirectionPersonnel::class, 'dp2')
                ->where('dp2.serviceID IN (:serviceIds)')
                ->setParameter('serviceIds', $serviceIds);

            foreach ($qb2->getQuery()->getScalarResult() as $r) {
                if (!empty($r['pid'])) $ids[] = (string)$r['pid'];
            }
        }

        // agences de l'utilisateur
        $qb3 = $em->createQueryBuilder()
            ->select('IDENTITY(ap.agenceID) AS agenceId')
            ->from(AgencePersonnel::class, 'ap')
            ->where('ap.personnelID = :user')
            ->setParameter('user', $user);

        $agenceRows = $qb3->getQuery()->getScalarResult();
        $agenceIds = array_unique(array_filter(array_map(fn($r) => $r['agenceId'] ?? null, $agenceRows)));

        if (!empty($agenceIds)) {
            $qb4 = $em->createQueryBuilder()
                ->select('IDENTITY(ap2.personnelID) AS pid')
                ->from(AgencePersonnel::class, 'ap2')
                ->where('ap2.agenceID IN (:agenceIds)')
                ->setParameter('agenceIds', $agenceIds);

            foreach ($qb4->getQuery()->getScalarResult() as $r) {
                if (!empty($r['pid'])) $ids[] = (string)$r['pid'];
            }
        }

        // inclure l'utilisateur lui-même
        $ids[] = (string) $user->getId();

        return array_values(array_unique($ids));
    }



    //    /**
    //     * @return Document[] Returns an array of Document objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Document
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
