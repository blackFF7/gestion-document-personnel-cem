<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Direction;
use App\Entity\DirectionPersonnel;
use App\Entity\Fonction;
use App\Entity\Personnel;
use App\Entity\Service;
use App\Enum\Sexe;
use App\Enum\SituationFamilial;
use App\Enum\StatusCompte;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InitFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public static function getGroups(): array
    {
        return ['init'];
    }

    public function load(ObjectManager $manager): void
    {
        // ── Catégorie minimale ────────────────────────────────────────────
        $categorie = new Categorie();
        $categorie->setDesignation('I');
        $manager->persist($categorie);

        // ── Direction DG ─────────────────────────────────────────────────
        $direction = new Direction();
        $direction->setNomDir('DG')->setNomenDir('Direction Générale');
        $manager->persist($direction);

        // ── Service DG ───────────────────────────────────────────────────
        $service = new Service();
        $service->setNomSer('DG')
                ->setNomenSer('Direction Générale')
                ->setDirectionID($direction);
        $manager->persist($service);

        // ── Fonction admin ────────────────────────────────────────────────
        $fonction = new Fonction();
        $fonction->setNomFon('Administrateur système')
                 ->setCategorieID($categorie);
        $manager->persist($fonction);

        $manager->flush(); // flush avant d'assigner les relations

        // ── Compte admin ──────────────────────────────────────────────────
        $admin = new Personnel();

        $im = 1;
        $dateNaiss = new \DateTime('1980-01-01');
        $username  = str_pad((string)$im, 3, '0', STR_PAD_LEFT)
                   . 'admin'
                   . $dateNaiss->format('dmY');

        $admin
            ->setIM($im)
            ->setNomAg('ADMIN')
            ->setPrenomAg('Super')
            ->setUsername($username)
            ->setMailAg('respectadmin@gmail.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($admin, 'RespectAdmin2026'))
            ->setStatusCompte(StatusCompte::ACTIF)
            ->setSexe(Sexe::HOMME)
            ->setSituationFamilial(SituationFamilial::CELIBATAIRE)
            ->setDateNaissAg($dateNaiss)
            ->setDateEntre(new \DateTime())
            ->setAdresseAg('Antananarivo')
            ->setContactAg(['0340000000'])
            ->setBackFront(false)
            ->setFonctionID($fonction);

        $manager->persist($admin);

        // ── Affectation direction ─────────────────────────────────────────
        $dp = new DirectionPersonnel();
        $dp->setPersonnelID($admin)->setServiceID($service);
        $manager->persist($dp);

        $manager->flush();
    }
}