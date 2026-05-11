<?php

namespace App\Repository;

use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Personnel>
 */
class PersonnelRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personnel::class);
    }

    public function findAllOrderByIM(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.IM', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAgenceOrService(Personnel $chef): array
    {
        $agenceIds  = [];
        $serviceIds = [];

        foreach ($chef->getAgencePersonnels() as $ap) {
            if ($ap->getAgenceID()) {
                $agenceIds[] = $ap->getAgenceID()->getId();
            }
        }

        foreach ($chef->getDirectionPersonnels() as $dp) {
            if ($dp->getServiceID()) {
                $serviceIds[] = $dp->getServiceID()->getId();
            }
        }

        if (!$agenceIds && !$serviceIds) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->groupBy('p.id') // 🔥 évite doublons
            ->leftJoin('p.agencePersonnels', 'ap')
            ->leftJoin('ap.agenceID', 'a')
            ->leftJoin('p.directionPersonnels', 'dp')
            ->leftJoin('dp.serviceID', 's');

        $orX = $qb->expr()->orX();

        if ($agenceIds) {
            $orX->add($qb->expr()->in('a.id', ':agenceIds'));
            $qb->setParameter('agenceIds', $agenceIds);
        }

        if ($serviceIds) {
            $orX->add($qb->expr()->in('s.id', ':serviceIds'));
            $qb->setParameter('serviceIds', $serviceIds);
        }

        $qb->where($orX);

        // ✅ TRI PAR IM (IMPORTANT)
        $qb->orderBy('p.IM', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findByCriteriaQuery(string $search = '', string $status = '')
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.fonctionID', 'f')
            ->addSelect('f')
            ->orderBy('p.nomAg', 'ASC');

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.nomAg)', ':q'),
                    $qb->expr()->like('LOWER(p.prenomAg)', ':q'),
                    $qb->expr()->like('LOWER(p.username)', ':q'),
                    $qb->expr()->like('CAST(p.IM AS string)', ':q'),
                )
            )->setParameter('q', '%' . strtolower($search) . '%');
        }

        if ($status !== '') {
            $qb->andWhere('p.statusCompte = :status')
            ->setParameter('status', $status);
        }

        return $qb; // 👈 IMPORTANT : on retourne le QueryBuilder
    }

    /**
     * Recherche par nom, prénom, IM ou username avec filtre statut optionnel.
     */
    public function findByCriteria(string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.fonctionID', 'f')
            ->addSelect('f')
            ->orderBy('p.nomAg', 'ASC');

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.nomAg)', ':q'),
                    $qb->expr()->like('LOWER(p.prenomAg)', ':q'),
                    $qb->expr()->like('LOWER(p.username)', ':q'),
                    $qb->expr()->like('CAST(p.IM AS string)', ':q'),
                )
            )->setParameter('q', '%' . strtolower($search) . '%');
        }

        if ($status !== '') {
            $qb->andWhere('p.statusCompte = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les personnels ayant un rôle donné.
     */
    public function findByRole(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "SELECT * FROM personnel WHERE roles::text LIKE :role";
        
        $rows = $conn->executeQuery($sql, ['role' => '%' . $role . '%'])->fetchAllAssociative();
        
        // Reconvertir en entités
        $em = $this->getEntityManager();
        $results = [];
        foreach ($rows as $row) {
            $results[] = $em->find(Personnel::class, $row['id']);
        }
        return array_filter($results);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Personnel) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countActifs(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.statusCompte = :s')
            ->setParameter('s', 'Actif')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNouveauxTrimestre(): int
    {
        $debut = new \DateTime('first day of january this year');
        // Trimestre en cours
        $mois  = (int) (new \DateTime())->format('n');
        $trim  = (int) ceil($mois / 3);
        $debut = new \DateTime(sprintf('%d-%02d-01', (int)(new \DateTime())->format('Y'), ($trim - 1) * 3 + 1));

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.creationCompte >= :debut')
            ->setParameter('debut', $debut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Agents dans les services (DirectionPersonnel → Service → Direction)
     */
    public function countByDirection(): array
    {
        return $this->createQueryBuilder('p')
            ->select('s.nomSer AS direction, COUNT(p.id) AS total')
            ->join('App\Entity\DirectionPersonnel', 'dp', 'WITH', 'dp.personnelID = p')
            ->join('dp.serviceID', 's')
            ->groupBy('s.nomSer')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Agents en agence (AgencePersonnel → Agence)
     */
    public function countByAgence(): array
    {
        return $this->createQueryBuilder('p')
            ->select('a.nomAgc AS agence, COUNT(p.id) AS total')
            ->join('App\Entity\AgencePersonnel', 'ap', 'WITH', 'ap.personnelID = p')
            ->join('ap.agenceID', 'a')
            ->groupBy('a.nomAgc')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Agents sans affectation (ni service, ni agence)
     */
    public function countSansAffectation(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT COUNT(DISTINCT p.id)
            FROM personnel p
            WHERE NOT EXISTS (SELECT 1 FROM direction_personnel dp WHERE dp.personnel_id_id = p.id)
            AND NOT EXISTS (SELECT 1 FROM agence_personnel   ap WHERE ap.personnel_id_id = p.id)
        ";
        return (int) $conn->executeQuery($sql)->fetchOne();
    }

    public function countMonService($user): int
    {
        // Récupère le service de l'user connecté puis compte les collègues
        $dp = $this->getEntityManager()
            ->getRepository(\App\Entity\DirectionPersonnel::class)
            ->findOneBy(['personnelID' => $user]);

        if (!$dp) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->getRepository(\App\Entity\DirectionPersonnel::class)
            ->createQueryBuilder('dp')
            ->select('COUNT(dp.id)')
            ->where('dp.serviceID = :s')
            ->setParameter('s', $dp->getServiceID())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBySexe(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.sexe AS label, COUNT(p.id) AS total')
            ->groupBy('p.sexe')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            // $row['label'] est un objet Enum, on récupère sa valeur string
            $key = $row['label'] instanceof \BackedEnum
                ? $row['label']->value
                : (string) $row['label'];

            $result[$key] = (int) $row['total'];
        }
        return $result;
    }

    public function countBySituationFamiliale(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.situationFamilial AS label, COUNT(p.id) AS total')
            ->groupBy('p.situationFamilial')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $key = $row['label'] instanceof \BackedEnum
                ? $row['label']->value
                : (string) $row['label'];

            $result[$key] = (int) $row['total'];
        }
        return $result;
    }

    public function countByAnciennete(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "
            SELECT
                CASE
                    WHEN EXTRACT(YEAR FROM AGE(NOW(), date_entre)) BETWEEN 0  AND 2  THEN '0-2 ans'
                    WHEN EXTRACT(YEAR FROM AGE(NOW(), date_entre)) BETWEEN 3  AND 5  THEN '3-5 ans'
                    WHEN EXTRACT(YEAR FROM AGE(NOW(), date_entre)) BETWEEN 6  AND 10 THEN '6-10 ans'
                    WHEN EXTRACT(YEAR FROM AGE(NOW(), date_entre)) BETWEEN 11 AND 15 THEN '11-15 ans'
                    ELSE '15+ ans'
                END AS tranche,
                COUNT(*) AS total
            FROM personnel
            GROUP BY tranche
            ORDER BY MIN(date_entre)
        ";
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
