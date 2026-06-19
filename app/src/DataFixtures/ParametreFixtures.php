<?php

namespace App\DataFixtures;

use App\Entity\Agence;
use App\Entity\AgencePersonnel;
use App\Entity\Categorie;
use App\Entity\Direction;
use App\Entity\DirectionPersonnel;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\Fonction;
use App\Entity\Service;
use App\Entity\TypeDocument;
use App\Entity\Personnel;
use App\Enum\NiveauConfidentiel;
use App\Enum\Sexe;
use App\Enum\SituationFamilial;
use App\Enum\StatusCompte;
use App\Enum\StatusDoc;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Handler\UploadHandler;
use League\Flysystem\FilesystemOperator;
use FPDF;

class ParametreFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;
    private FilesystemOperator $documentsStorage;
    private FilesystemOperator $photosProfilStorage;
    private UploadHandler $uploadHandler;

    public function __construct(
        UserPasswordHasherInterface $hasher,
        FilesystemOperator $documentsStorage,
        FilesystemOperator $photosProfilStorage,
        UploadHandler $uploadHandler,
    ) {
        $this->hasher               = $hasher;
        $this->documentsStorage     = $documentsStorage;
        $this->photosProfilStorage  = $photosProfilStorage;
        $this->uploadHandler        = $uploadHandler;
    }

    public static function getGroups(): array
    {
        return ['essai'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS NOMMAGE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Namer document :  <reference> (ou <reference>_v<n> si collision)
     * Directory       :  <IM>/<nomenclatureDocument>/<Annee>/
     */
    private function buildDocumentPath(
        string $reference,
        string $im,
        string $nomenclatureDossier,
        \DateTime $dateCreation,
        string $extension,
        FilesystemOperator $storage
    ): string {
        $slugger = new AsciiSlugger('fr');
        $slugRef = $slugger->slug($reference)->toString();
        $dir     = $im . '/' . $nomenclatureDossier . '/' . $dateCreation->format('Y') . '/';
        $filename = $slugRef . '.' . $extension;
        $path = $dir . $filename;

        // gestion collision (_v<n>)
        $version = 1;
        while ($storage->fileExists($path)) {
            $version++;
            $filename = $slugRef . '_v' . $version . '.' . $extension;
            $path     = $dir . $filename;
        }

        return $path;
    }

    /**
     * Namer photo :  <IM>_<timestamp>.<ext>
     * Directory   :  <IM>/
     */
    private function buildPhotoPath(
        string $im,
        string $extension,
        FilesystemOperator $storage
    ): string {
        $dir      = $im . '/';
        $filename = $im . '_' . (new \DateTimeImmutable())->format('YmdHis') . '.' . $extension;
        $path     = $dir . $filename;

        // collision (très rare, mais sécurité)
        $n = 1;
        while ($storage->fileExists($path)) {
            $filename = $im . '_' . (new \DateTimeImmutable())->format('YmdHis') . '_' . $n . '.' . $extension;
            $path     = $dir . $filename;
            $n++;
        }

        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOAD
    // ─────────────────────────────────────────────────────────────────────────

    public function load(ObjectManager $manager): void
    {
        $faker  = Factory::create('fr_FR');
        $faker->seed(1234);

        // ── Récupération des images disponibles dans public/images ─────────
        $imagesDir = dirname(__DIR__, 2) . '/public/images';
        $imageFiles = [];
        if (is_dir($imagesDir)) {
            foreach (new \DirectoryIterator($imagesDir) as $fileInfo) {
                if ($fileInfo->isFile() && in_array(
                    strtolower($fileInfo->getExtension()),
                    ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                    true
                )) {
                    $imageFiles[] = $fileInfo->getRealPath();
                }
            }
        }

        // ── Catégories ────────────────────────────────────────────────────
        $listCat = ['HCI','HCII','HCIII','HCIV','I','II','III','IV','IX','V','VI','VII','VIII'];
        $categories = [];
        foreach ($listCat as $c) {
            $categorie = (new Categorie)->setDesignation($c);
            $manager->persist($categorie);
            $categories[] = $categorie;
        }

        // ── Directions ────────────────────────────────────────────────────
        $listDirection = [
            ['nom' => 'AGENCE',  'nomenclature' => 'Agence'],
            ['nom' => 'DAI',     'nomenclature' => "Direction de l'Audit Interne et de l'Inspection"],
            ['nom' => 'DCR',     'nomenclature' => 'Direction Commerciale et Réseau'],
            ['nom' => 'DFC',     'nomenclature' => 'Direction Financière et Comptable'],
            ['nom' => 'DG',      'nomenclature' => 'Direction Générale'],
            ['nom' => 'DGAFP',   'nomenclature' => 'Direction Générale Adjointe chargée de Finance et du Patrimoine'],
            ['nom' => 'DGARC',   'nomenclature' => 'Direction Générale Adjointe Chargée des Risques et Conformité'],
            ['nom' => 'DID',     'nomenclature' => "Direction de l'Innovation et du Développement"],
            ['nom' => 'DMG',     'nomenclature' => "Direction des Moyens Généraux"],
            ['nom' => 'DOC',     'nomenclature' => 'Direction des Opération de Crédit'],
            ['nom' => 'DRH',     'nomenclature' => 'Direction des Ressources Humaines'],
            ['nom' => 'DSITN',   'nomenclature' => "Direction des Systèmes d'Information et de la Transformation Numérique"],
        ];
        $directions = [];
        foreach ($listDirection as $ld) {
            $direction = (new Direction)->setNomDir($ld['nom'])->setNomenDir($ld['nomenclature']);
            $manager->persist($direction);
            $directions[] = $direction;
        }

        // ── Services ──────────────────────────────────────────────────────
        $listService = [
            ["nom" => "CCRP",  "nomenclature" => 'Cellule Communication et Relations Publiques'],
            ["nom" => "CMBI",  "nomenclature" => "CMBI"],
            ["nom" => "DAI",   "nomenclature" => "DAI"],
            ["nom" => "DFC",   "nomenclature" => "Direction Financière et Comptable"],
            ["nom" => "DG",    "nomenclature" => "DG"],
            ["nom" => "DGAFP", "nomenclature" => "DGAFP"],
            ["nom" => "DGAR",  "nomenclature" => "DGAR"],
            ["nom" => "DID",   "nomenclature" => "DID"],
            ["nom" => "DOC",   "nomenclature" => "DOC"],
            ["nom" => "DRH",   "nomenclature" => 'Direction des Ressources Humaines'],
            ["nom" => "DSITN", "nomenclature" => 'DSITN'],
            ["nom" => "PRMP",  "nomenclature" => 'Responsable des Marchés Publics'],
            ["nom" => "SAC",   "nomenclature" => "Service de l'Appui et de la Coordination"],
            ["nom" => "SAD",   "nomenclature" => 'Service Archive et Documentation'],
            ["nom" => "SAP",   "nomenclature" => "Service de l'Administration du Personnel"],
            ["nom" => "SAR",   "nomenclature" => "Service Animation du Réseau"],
            ["nom" => "SASCI", "nomenclature" => "Service des Affaires Sociales et Communication Interne"],
            ["nom" => "SAUT",  "nomenclature" => "Service Authentification"],
            ["nom" => "SCGP",  "nomenclature" => "Service Contrôle de Gestion et Performance"],
            ["nom" => "SCTB",  "nomenclature" => "Service Comptabilité"],
            ["nom" => "SCTRL", "nomenclature" => "Service Contrôle"],
            ["nom" => "SDD",   "nomenclature" => "Service Data et Décisionnel"],
            ["nom" => "SDO",   "nomenclature" => "Service Recherche et Développement des Offres"],
            ["nom" => "SDRH",  "nomenclature" => "Service du Développement RH"],
            ["nom" => "SEF",   "nomenclature" => "Service de l'Education Financière"],
            ["nom" => "SENG",  "nomenclature" => "Service Engagements"],
            ["nom" => "SFISC", "nomenclature" => "Service Fiscalité"],
            ["nom" => "SGT",   "nomenclature" => "Service des Grands Travaux"],
            ["nom" => "SIA",   "nomenclature" => "Service Immobilisation et Chaîne d'Approvisionnement"],
            ["nom" => "SIE",   "nomenclature" => "Service Infrastructure et Exploitation"],
            ["nom" => "SJC",   "nomenclature" => "Service Juridique et Contentieux"],
            ["nom" => "SLM",   "nomenclature" => "Service Logistique et Maintenance"],
            ["nom" => "SRC",   "nomenclature" => "Service Risques et Conformité"],
            ["nom" => "SSI",   "nomenclature" => "Service Sécurité Informatique"],
            ["nom" => "SSR",   "nomenclature" => "Service Suivi et Recouvrement"],
            ["nom" => "STN",   "nomenclature" => "Service Transformation Numérique"],
            ["nom" => "STRES", "nomenclature" => "Service Trésorerie"],
            ["nom" => "SVP",   "nomenclature" => "Service Valorisation du Patrimoine"],
        ];
        $services = [];
        foreach ($listService as $i => $ls) {
            $service = (new Service)
                ->setNomSer($ls['nom'])
                ->setNomenSer($ls['nomenclature'])
                ->setDirectionID($directions[$i % count($directions)]);
            $manager->persist($service);
            $services[] = $service;
        }

        // ── Agences ───────────────────────────────────────────────────────
        $listAgence = [
            ['nom' => 'CEM 001',   'nomenclature' => 'CEM - WU 001 TSARALALANA'],
            ['nom' => 'CEM 002',   'nomenclature' => 'CEM - WU 002 FIANARANTSOA'],
            ['nom' => 'CEM 003',   'nomenclature' => 'CEM - WU 003 TAMATAVE'],
            ['nom' => 'CEM 004',   'nomenclature' => 'CEM - WU 004 MAJUNGA'],
            ['nom' => 'CEM 005',   'nomenclature' => 'CEM - WU 005 AMBOSITRA'],
            ['nom' => 'CEM 006',   'nomenclature' => 'CEM - WU 006 DIEGO'],
            ['nom' => 'CEM 008',   'nomenclature' => 'CEM - WU 008 TULEAR'],
            ['nom' => 'CEM 009',   'nomenclature' => 'CEM - WU 009 ANTSIRABE'],
            ['nom' => 'CEM 010',   'nomenclature' => 'CEM - WU 010 FORT - DAUPHIN'],
            ['nom' => 'CEM 011',   'nomenclature' => 'CEM - WU 011 MORONDAVA'],
            ['nom' => 'CEM 016',   'nomenclature' => 'CEM - WU 016 SAINTE MARIE'],
            ['nom' => 'CEM 017',   'nomenclature' => 'CEM - WU 017 AMBATONDRAZAKA'],
            ['nom' => 'CEM 018',   'nomenclature' => 'CEM - WU 018 MANAKARA'],
            ['nom' => 'CEM 019',   'nomenclature' => 'CEM - WU 019 MORAMANGA'],
            ['nom' => 'CEM 020',   'nomenclature' => 'CEM - WU 020 TSIROANOMANDIDY'],
            ['nom' => 'CEM 021',   'nomenclature' => 'CEM WU 021 ANALAVORY'],
            ['nom' => 'CEM 024',   'nomenclature' => 'CEM - WU 024 AMBATOLAMPY'],
            ['nom' => 'CEM 028',   'nomenclature' => 'CEM - WU 028 AMBALAVAO'],
            ['nom' => 'CEM 029',   'nomenclature' => 'CEM - WU 029 FANDRIANA'],
            ['nom' => 'CEM 043',   'nomenclature' => 'CEM - WU 043 AMBANJA'],
            ['nom' => 'CEM 044',   'nomenclature' => 'CEM WU 044 AMBILOBE'],
            ['nom' => 'CEM 046',   'nomenclature' => 'CEM - WU 046 SAMBAVA'],
            ['nom' => 'CEM 048',   'nomenclature' => 'CEM - WU 048 ANDRAVOAHANGY'],
            ['nom' => 'CEM 049',   'nomenclature' => 'CEM - WU 049 ANTSAKAVIRO'],
            ['nom' => 'CEM 051',   'nomenclature' => 'CEM WU 051 ANALAMAHITSY'],
            ['nom' => 'CEM 086',   'nomenclature' => 'CEM - WU 086 TAMATAVE'],
            ['nom' => 'WU 004',    'nomenclature' => "Agence dédiée WU Majunga"],
            ['nom' => 'WU 006 I',  'nomenclature' => "Agence dédiée WU Diégo I"],
            ['nom' => 'WU 006 II', 'nomenclature' => "Agence dédiée WU Diégo II"],
            ['nom' => 'WU 012',    'nomenclature' => "Agence dédiée WU Nosy Be"],
            ['nom' => 'WU 67',     'nomenclature' => "Agence dédiée WU 67ha"],
        ];
        $agences = [];
        foreach ($listAgence as $la) {
            $agence = (new Agence)
                ->setNomAgc($la['nom'])
                ->setNomenAgc($la['nomenclature'])
                ->setCreationAgc($faker->dateTimeBetween('-50 years', 'now'));
            $manager->persist($agence);
            $agences[] = $agence;
        }

        // ── Fonctions ─────────────────────────────────────────────────────
        $prefixes = [
            'Chargé de','Responsable','Agent','Technicien','Analyste','Conseiller','Gestionnaire','Assistant',
            'Coordinateur','Inspecteur','Administrateur','Spécialiste','Opérateur','Planificateur','Consultant',
            'Contrôleur','Référent','Médiateur','Animateur','Pilote',
        ];
        $domaines = [
            'agence','clientèle particuliers','clientèle entreprises','recouvrement','comptes','opérations','crédit',
            'trésorerie','comptabilité','audit','conformité','risques','juridique','fiscalité','ressources humaines',
            'recrutement','formation','paie','systèmes d\'information','infrastructure','sécurité informatique',
            'support technique','réseau','logistique','approvisionnement','achats','marketing','communication',
            'relation presse','événementiel','digital','e-commerce','data','business intelligence','crm','reporting',
            'contrôle de gestion','budget','planification','gestion des litiges','contentieux','archivage',
            'documentation','qualité','courrier','gestion documentaire','immobilisations','maintenance','bâtiment',
            'santé sécurité','prévention','change','import-export','transferts','paiements','moyens de paiement',
            'cartes bancaires','clearing','settlement','placements','épargne','assurance','microfinance',
            'financement projet','gestion d\'actifs','gestion de patrimoine','analyse financière','pricing',
            'relation commerciale','expérience client','fidélisation','gestion des réclamations','veille réglementaire',
            'conformité opérationnelle','sécurité physique','prévention fraude','gestion des signatures',
            'gestion contrats','gestion fournisseurs','suivi chantier','immobilier d\'entreprise','aménagement agence',
            'études risques','veille marché','prospection entreprises','analyse crédit','monitoring portefeuille',
            'gestion collatéral','reporting régulateur','contrôle interne','méthodes et procédures',
            'gestion des incidents','continuité activité',
        ];

        $listFonction = [];
        foreach ($prefixes as $p) {
            foreach ($domaines as $d) {
                $titre = $p . ' ' . $d;
                if (!in_array($titre, $listFonction, true)) {
                    $listFonction[] = $titre;
                    if (count($listFonction) >= 700) { break 2; }
                }
            }
        }
        $ix = 0;
        while (count($listFonction) < 100) {
            $a = $domaines[$ix % count($domaines)];
            $b = $domaines[($ix + 7) % count($domaines)];
            $titre = 'Chargé de ' . $a . ' et ' . $b;
            if (!in_array($titre, $listFonction, true)) { $listFonction[] = $titre; }
            $ix++;
        }

        $fonctions = [];
        foreach ($listFonction as $i => $lf) {
            $fonction = (new Fonction)->setNomFon($lf)->setCategorieID($categories[$i % count($categories)]);
            $manager->persist($fonction);
            $fonctions[] = $fonction;
        }

        // ── Types de documents ────────────────────────────────────────────
        $listDocuments = [
            ['nom' => 'Etat civil et identité', 'nomenclature' => 'ETI', 'documents' => [
                'Acte de naissance','Copie carte d\'identité nationale','Copie passeport','Certificat de nationalité',
                'Livret de famille','Extrait d\'acte de mariage','Jugement de divorce','Certificat de vie',
                'Attestation de domicile','Quittance de loyer','Facture d\'électricité','Carte consulaire',
                'Certificat de changement de nom','Autorisation parentale','Permis de conduire',
                'Carte d\'identité temporaire','Duplicata pièce identité','Attestation d\'hébergement',
                'Certificat de résidence','Document d\'immigration',
            ]],
            ['nom' => 'Contrat de travail et avenants', 'nomenclature' => 'CTA', 'documents' => [
                'Contrat de travail initial','Avenant de modification de contrat','Lettre d\'embauche',
                'Notification de promotion','Contrat à durée déterminée','Contrat à durée indéterminée',
                'Contrat de mission','Convention de stage','Preuve de période d\'essai','Lettre de démission',
                'Procès-verbal de licenciement','Attestation de travail','Bulletin de salaire (contrat associé)',
                'Certificat de fin de contrat','Accord de télétravail','Document de mise à pied',
                'Accord de mobilité interne','Engagement de confidentialité',
                'Formulaire d\'affiliation sécurité sociale','Autorisation de cumul emploi',
            ]],
            ['nom' => 'Suivi RH', 'nomenclature' => 'SRH', 'documents' => [
                'Fiche de poste','Entretien annuel - compte rendu','Plan de formation individuel',
                'Dossier disciplinaire','Historique des promotions','Historique des congés',
                'Attestation d\'ancienneté','Dossier de recrutement','Questionnaire d\'intégration',
                'Bilan social','Document d\'évaluation des compétences','Plan de carrière',
                'Rapport d\'audit RH','Certificat de travail','Autorisation spéciale (congé sans solde)',
                'Formulaire de demande de congé','Suivi des absences','Fiche de contrôle des heures',
                'Décision administrative RH','Attestation de reprise',
            ]],
            ['nom' => 'Mobilité', 'nomenclature' => 'MOB', 'documents' => [
                'Demande de mutation','Accord de mobilité','Ordre de mission','Attestation de mobilité',
                'Convention de transfert','Document de relocalisation','Procédure de cession',
                'Autorisation de déplacement professionnel','Accord de détachement','Compte-rendu de mission',
                'Historique des affectations','Demande de mobilité géographique','Attestation de changement d\'agence',
                'Avenant de poste','Lettre d\'affectation','Preuve de prise de fonction','Plan de mobilité',
                'Document de remboursement de frais','Formulaire de changement d\'adresse professionnelle',
                'Validation RH mobilité',
            ]],
            ['nom' => 'Médical', 'nomenclature' => 'MED', 'documents' => [
                'Certificat médical d\'aptitude','Certificat d\'incapacité','Bilan de santé',
                'Compte rendu médical','Fiche médicale professionnelle','Attestation d\'arrêt maladie',
                'Ordonnance','Analyses biologiques','Radiographie / imagerie','Certificat pour inaptitude',
                'Dossier de remboursement soins','Certificat de vaccination','Déclaration d\'accident du travail',
                'Procès-verbal visite médicale','Autorisation médicale pour poste',
                'Formulaire d\'aptitude avant embauche','Rapport de cardiologie','Examen ophtalmologique',
                'Rapport psychologique','Attestation d\'hospitalisation',
            ]],
            ['nom' => 'Diplome et formations', 'nomenclature' => 'DEF', 'documents' => [
                'Diplôme de fin d\'études','Copies des certificats de formation','Attestation de stage',
                'Relevé de notes','Certificat de spécialisation','Titre professionnel','Certificat d\'aptitude',
                'Attestation de participation','Plan de formation suivi','Rapport de formation',
                'Lettre de reconnaissance de qualification','Certification professionnelle','Carte universitaire',
                'Validation des acquis (VAE)','Thèse / mémoire','Diplôme étranger validé',
                'Attestation de réussite','Programme de formation','Évaluation post-formation','Contrat d\'alternance',
            ]],
            ['nom' => 'Notes et evaluations', 'nomenclature' => 'NEV', 'documents' => [
                'Rapport d\'évaluation annuelle','Fiche d\'évaluation','Note de service',
                'Compte rendu de réunion','CQI / qualité - rapport','Feedback 360°',
                'Plan d\'action suite évaluation','Grille d\'évaluation','Note de sanction',
                'Note de félicitations','Rapport de performance','Synthèse d\'entretien','Rapport d\'incident',
                'Attestation d\'évaluation','Note de service RH','Bilan trimestriel',
                'Tableau de bord individuel','Évaluation  probation','Test de compétence','Remarques de hiérarchie',
            ]],
            ['nom' => 'Autre', 'nomenclature' => 'ATR', 'documents' => [
                'Courrier divers','Autorisation parentale','Photocopie divers','Formulaire interne',
                'Attestation diverses','Contrat externe','Document RH non classé','Rapport externe',
                'Accord de confidentialité externe','Bilans divers','Lettre de motivation','Note interne',
                'Procédure opérationnelle','Preuve de paiement','Formulaire de consentement',
                'Autorisation de divulgation','Document fournisseur','Confirmation de réception',
                'Déclaration diverses','Autre pièce justificative',
            ]],
        ];

        $typeDocs = [];
        foreach ($listDocuments as $d) {
            $dossier = (new Dossier)
                ->setNomDos($d['nom'])
                ->setNomenclature($d['nomenclature'])
                ->setNiveauConf($faker->randomElement([
                    NiveauConfidentiel::PUBLIC,
                    NiveauConfidentiel::CONFIDENTIEL,
                    NiveauConfidentiel::STRICTEMENT_CONFIDENTIEL,
                ]));
            $manager->persist($dossier);

            foreach ($d['documents'] as $idx => $docName) {
                $uniqueName = $docName;
                if (array_search($docName, array_column($typeDocs, 2), true) !== false) {
                    $uniqueName = $docName . ' #' . $idx;
                }
                $type = (new TypeDocument)->setNomTypeDoc($uniqueName)->setDossierID($dossier);
                $manager->persist($type);
                $typeDocs[] = [$dossier, $type, $uniqueName];
            }
        }

        $manager->flush();

        // ════════════════════════════════════════════════════════════════════
        // CRÉATION DES PERSONNELS
        // 25 % → autres rôles (ADMIN, CHEF, SAP, RH)  — créés EN PREMIER
        // 75 % → ROLE_USER                              — créés EN DERNIER
        // ════════════════════════════════════════════════════════════════════
        $totalPersonnel   = 200;
        $nbAutresRoles    = (int) round($totalPersonnel * 0.25);  // 50
        $nbUser           = $totalPersonnel - $nbAutresRoles;     // 150

        // Rôles "non-USER" distribués équitablement
        $autresRoles  = ['ROLE_ADMIN', 'ROLE_CHEF', 'ROLE_SAP', 'ROLE_RH'];
        $roleSequence = [];
        for ($r = 0; $r < $nbAutresRoles; $r++) {
            $roleSequence[] = $autresRoles[$r % count($autresRoles)];
        }

        // Construire la séquence finale : autres rôles d'abord, USER ensuite
        $rolesSequence = array_merge(
            $roleSequence,
            array_fill(0, $nbUser, 'ROLE_USER')
        );

        $personnels    = [];
        $usedUsernames = [];
        $usedIM        = [];
        $usedEmails    = [];

        foreach ($rolesSequence as $idx => $role) {

            $person = new Personnel();

            // ── IM unique ─────────────────────────────────────────────────
            $imNum = 2 + $idx;
            while (in_array((string)$imNum, $usedIM, true)) { $imNum++; }
            $usedIM[]  = (string)$imNum;
            $im        = str_pad((string)$imNum, 3, '0', STR_PAD_LEFT);

            // ── Identité ─────────────────────────────────────────────────
            $dateNaiss = $faker->dateTimeBetween('-60 years', '-20 years');
            $nom       = $faker->lastName();
            $prenom    = $faker->firstName();
            $person->setDateNaissAg($dateNaiss);

            // ── Username unique ────────────────────────────────────────
            $baseUsername = strtolower($im . $nom . $dateNaiss->format('dmY'));
            $username     = $baseUsername;
            $suffix       = 1;
            while (in_array($username, $usedUsernames, true)) {
                $username = $baseUsername . $suffix++;
            }
            $usedUsernames[] = $username;

            // ── Email unique ──────────────────────────────────────────
            $email = strtolower($prenom . '.' . $nom . $im . '@cem.local');
            while (in_array($email, $usedEmails, true)) {
                $email = strtolower($prenom . '.' . $nom . rand(1, 999) . '@cem.local');
            }
            $usedEmails[] = $email;

            // ── Password ─────────────────────────────────────────────
            $hashed = $this->hasher->hashPassword($person, '0000');

            // ── Situation familiale ────────────────────────────────────
            $situation = $faker->randomElement(SituationFamilial::cases());

            // ── Set base person ─────────────────────────────────────
            $person
                ->setUsername($username)
                ->setPassword($hashed)
                ->setIM((int)$im)
                ->setNomAg($nom)
                ->setPrenomAg($prenom)
                ->setRoles([$role])
                ->setDateEntre($faker->dateTimeBetween('-15 years', 'now'))
                ->setAdresseAg($faker->address())
                ->setMailAg($email)
                ->setContactAg([$faker->phoneNumber(), $faker->phoneNumber()])
                ->setCreationCompte(new \DateTimeImmutable())
                ->setMajCompte(new \DateTimeImmutable())
                ->setDernierConnexion(new \DateTimeImmutable())
                ->setStatusCompte($faker->randomElement([
                    StatusCompte::INACTIF,
                    StatusCompte::SUSPENDU,
                    StatusCompte::DESACTIVE,
                ]))
                ->setSexe($faker->randomElement(Sexe::cases()))
                ->setSituationFamilial($situation)
                ->setFonctionID($fonctions[array_rand($fonctions)]);

            // ── Photo de profil depuis public/images ───────────────────
            if (!empty($imageFiles)) {
                $srcPath  = $imageFiles[array_rand($imageFiles)];
                $ext      = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
                $photoPath = $this->buildPhotoPath($im, $ext, $this->photosProfilStorage);

                $this->photosProfilStorage->write($photoPath, file_get_contents($srcPath));
                // On stocke UNIQUEMENT le chemin relatif (sans bucket)
                $person->setPhotoProfil($photoPath);
            }

            // ── Affectation agence / service (BackFront) ───────────────
            $backFront = $faker->boolean();
            $person->setBackFront($backFront);

            $manager->persist($person);
            $personnels[] = $person;

            if ($backFront) {
                $ap = new AgencePersonnel();
                $ap->setPersonnelID($person)->setAgenceID($agences[array_rand($agences)]);
                $manager->persist($ap);
            } else {
                $dp = new DirectionPersonnel();
                $dp->setPersonnelID($person)->setServiceID($services[array_rand($services)]);
                $manager->persist($dp);
            }

            // ── Famille ───────────────────────────────────────────────
            if ($situation->name !== 'CELIBATAIRE') {

                if (in_array($situation->name, ['MARIE', 'DIVORCE']) && $faker->boolean(80)) {
                    $conjoint = new \App\Entity\Conjoint();
                    $conjoint->setPersonnel($person)
                        ->setNom($faker->lastName())
                        ->setPrenom($faker->firstName())
                        ->setDateNaiss($faker->dateTimeBetween('-60 years', '-25 years'))
                        ->setProfession($faker->jobTitle());
                    $manager->persist($conjoint);
                    $person->setConjoint($conjoint);
                }

                if (in_array($situation->name, ['MARIE', 'DIVORCE', 'VEUF'])) {
                    $nbEnfants = rand(0, 4);
                    for ($e = 0; $e < $nbEnfants; $e++) {
                        $enfant = new \App\Entity\Enfant();
                        $enfant->setPersonnel($person)
                            ->setNom($nom)
                            ->setPrenom($faker->firstName())
                            ->setDateNaiss($faker->dateTimeBetween('-20 years', '-1 years'))
                            ->setSexe($faker->randomElement(Sexe::cases()));
                        $manager->persist($enfant);
                    }
                }
            }

            // Flush par lot
            if ($idx % 20 === 0) {
                $manager->flush();
            }
        }

        $manager->flush();

        // ════════════════════════════════════════════════════════════════════
        // CRÉATION DES DOCUMENTS (upload direct MinIO sans VichUploader)
        // Namer  : <reference-slug> (ou _v<n> si collision)
        // Dir    : <IM>/<nomenclatureDossier>/<Annee>/
        // ════════════════════════════════════════════════════════════════════
        $slugger  = new AsciiSlugger('fr');
        $countDoc = 0;

        foreach ($typeDocs as [$dossier, $type, $docName]) {
            $nb = rand(50, 75);

            for ($k = 0; $k < $nb; $k++) {

                /** @var Personnel $personOwner */
                $personOwner      = $personnels[array_rand($personnels)];
                $im               = str_pad((string)$personOwner->getIM(), 3, '0', STR_PAD_LEFT);
                $nomenclatureDos  = $dossier->getNomenclature();
                $dateArr          = $faker->dateTimeBetween('-3 years', 'now');

                // ── Construction de la référence ─────────────────────────
                $titulaire = $personOwner->getNomAg() . ' ' . $personOwner->getPrenomAg();
                $referenceBase = sprintf(
                    '%s_%d_%s_%s_du %s',
                    $im,
                    $dossier->getId(),
                    $nomenclatureDos,
                    $type->getNomTypeDoc(),
                    $dateArr->format('d.m.Y')
                );


                // ── Versioning si collision ───────────────────────────────
                $reference = $referenceBase;
                $version   = 1;
                $docRepo   = $manager->getRepository(Document::class);

                while ($docRepo->findOneBy(['reference' => $reference])) {
                    $version++;
                    $reference = $referenceBase . '_v' . $version;
                }

                // ── Path MinIO ────────────────────────────────────────────
                $docPath = $this->buildDocumentPath(
                    $reference,
                    $im,
                    $nomenclatureDos,
                    $dateArr,
                    'pdf',
                    $this->documentsStorage
                );

                // ── Génération PDF en mémoire ─────────────────────────────
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->MultiCell(0, 10,
                    "Document : $docName\n" .
                    "Reference : $reference\n" .
                    "Titulaire : $titulaire\n" .
                    "Date      : " . $dateArr->format('d/m/Y') . "\n\n" .
                    "Description :\n" . $faker->paragraph()
                );

                // ── Upload MinIO ──────────────────────────────────────────
                $this->documentsStorage->write($docPath, $pdf->Output('S'));

                // ── Entité Document ───────────────────────────────────────
                $docCreation = $faker->dateTimeBetween('-2 years', 'now');
                $docMaj      = $faker->dateTimeBetween($docCreation, 'now');

                $doc = new Document();
                $doc->setReference($reference)
                    ->setFichier($docPath)          // chemin relatif stocké
                    ->setTitre($docName . ' - ' . $faker->sentence(3))
                    ->setTitulaire($titulaire)
                    ->setDateArriveDoc($dateArr)
                    ->setDescription($faker->paragraph())
                    ->setCreationDoc($docCreation)
                    ->setMajDoc($docMaj)
                    ->setStatucDoc($faker->randomElement(StatusDoc::cases()))
                    ->setPersonnelID($personOwner)
                    ->setTypeDocumentID($type);

                $manager->persist($doc);
                $countDoc++;

                if ($countDoc % 50 === 0) {
                    $manager->flush();
                }
            }
        }

        $manager->flush();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PASSWORD / USERNAME
    // ─────────────────────────────────────────────────────────────────────────

    private function handlePassword(Personnel $p, ?string $plain): void
    {
        $raw = $plain ?: $p->getNomAg();
        $p->setPassword($this->hasher->hashPassword($p, $raw));
    }

    private function handleUsername(Personnel $p, bool $auto): void
    {
        if ($auto || !$p->getUsername()) {
            $p->setUsername(
                $p->getIM() .
                strtolower($p->getNomAg() ?? '') .
                ($p->getDateNaissAg()?->format('dmY') ?? '')
            );
        }
    }
}