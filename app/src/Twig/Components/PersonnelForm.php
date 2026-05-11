<?php

namespace App\Twig\Components;

use App\Entity\Conjoint;
use App\Entity\Enfant;
use App\Entity\Personnel;
use App\Enum\Sexe;
use App\Enum\SituationFamilial;
use App\Enum\StatusCompte;
use App\Repository\FonctionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
class PersonnelForm extends AbstractController
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    // ── Identité ─────────────────────────────────────────────────────────────
    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    public string $nomAg = '';

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    public string $prenomAg = '';

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: "L'IM est obligatoire")]
    #[Assert\Positive(message: "L'IM doit être positif")]
    public ?int $IM = null;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le username est obligatoire')]
    public string $username = '';

    #[LiveProp(writable: true)]
    public string $password = '';

    #[LiveProp(writable: true)]
    public string $sexe = '';

    #[LiveProp(writable: true)]
    public string $situationFamilial = 'Célibataire';

    #[LiveProp(writable: true)]
    public string $dateNaissAg = '';

    #[LiveProp(writable: true)]
    public string $dateEntre = '';

    #[LiveProp(writable: true)]
    public string $adresseAg = '';

    #[LiveProp(writable: true)]
    public string $mailAg = '';

    #[LiveProp(writable: true)]
    public string $statusCompte = 'Inactif';

    #[LiveProp(writable: true)]
    public string $fonctionId = '';

    #[LiveProp(writable: true)]
    public bool $backFront = false;

    #[LiveProp(writable: true)]
    public array $roles = [];

    // ── Contacts ─────────────────────────────────────────────────────────────
    /**
     * Tableau de scalaires simples ou de tableaux associatifs.
     * @var list<array{type: string, valeur: string}>
     */
    #[LiveProp(writable: true, useSerializerForHydration: true)]
    public array $contacts = [];

    // ── Photo ─────────────────────────────────────────────────────────────────
    #[LiveProp(writable: true)]
    public ?string $photoPreviewName = null;

    // ── Famille ──────────────────────────────────────────────────────────────
    #[LiveProp(writable: true)]
    public bool $avecConjoint = false;

    #[LiveProp(writable: true)]
    public string $conjointNom = '';

    #[LiveProp(writable: true)]
    public string $conjointPrenom = '';

    #[LiveProp(writable: true)]
    public string $conjointDateNaiss = '';

    #[LiveProp(writable: true)]
    public string $conjointProfession = '';

    #[LiveProp(writable: true, useSerializerForHydration: true)]
    public array $enfants = [];

    // ── État ─────────────────────────────────────────────────────────────────
    #[LiveProp]
    public ?string $editId = null;

    public function __construct(private FonctionRepository $fonctionRepository) {}

    // ── Helpers template ──────────────────────────────────────────────────────

    public function getFonctions(): array
    {
        return $this->fonctionRepository->findAll();
    }

    public function getSexeChoices(): array { return Sexe::cases(); }
    public function getSituationChoices(): array { return SituationFamilial::cases(); }
    public function getStatusChoices(): array { return StatusCompte::cases(); }

    public function getRolesChoices(): array
    {
        return [
            'ROLE_USER'  => 'Utilisateur',
            'ROLE_CHEF'  => 'Chef',
            'ROLE_RH'    => 'Ressources Humaines',
            'ROLE_SAP'   => 'SAP',
            'ROLE_ADMIN' => 'Administrateur',
        ];
    }

    public function needsConjoint(): bool
    {
        return in_array($this->situationFamilial, ['Marié', 'Veuve'], true);
    }

    // ── Actions contacts ──────────────────────────────────────────────────────

    #[LiveAction]
    public function addContact(): void
    {
        $this->contacts[] = ['type' => 'mobile', 'valeur' => ''];
    }

    #[LiveAction]
    public function removeContact(#[LiveArg] int $index): void
    {
        array_splice($this->contacts, $index, 1);
        $this->contacts = array_values($this->contacts);
    }

    // ── Actions enfants ───────────────────────────────────────────────────────

    #[LiveAction]
    public function addEnfant(): void
    {
        $this->enfants[] = ['nom' => '', 'prenom' => '', 'dateNaiss' => '', 'sexe' => ''];
    }

    #[LiveAction]
    public function removeEnfant(#[LiveArg] int $index): void
    {
        array_splice($this->enfants, $index, 1);
        $this->enfants = array_values($this->enfants);
    }

    // ── Sauvegarde ───────────────────────────────────────────────────────────

    #[LiveAction]
    public function save(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        Request $request
    ): Response {
        $this->validate();

        // Récupération ou création du personnel
        if ($this->editId) {
            $personnel = $em->find(Personnel::class, $this->editId);
            if (!$personnel) {
                throw $this->createNotFoundException('Personnel introuvable');
            }
        } else {
            $personnel = new Personnel();
        }

        // Champs de base
        $personnel->setNomAg($this->nomAg);
        $personnel->setPrenomAg($this->prenomAg);
        $personnel->setIM((int) $this->IM);
        $personnel->setUsername($this->username);
        $personnel->setAdresseAg($this->adresseAg ?: null);
        $personnel->setMailAg($this->mailAg ?: null);
        $personnel->setBackFront($this->backFront);
        $personnel->setRoles($this->roles);

        if ($this->password !== '') {
            $personnel->setPassword($hasher->hashPassword($personnel, $this->password));
        }
        if ($this->sexe !== '') {
            $personnel->setSexe(Sexe::from($this->sexe));
        }
        if ($this->situationFamilial !== '') {
            $personnel->setSituationFamilial(SituationFamilial::from($this->situationFamilial));
        }
        if ($this->statusCompte !== '') {
            $personnel->setStatusCompte(StatusCompte::from($this->statusCompte));
        }
        if ($this->dateNaissAg !== '') {
            $personnel->setDateNaissAg(new \DateTime($this->dateNaissAg));
        }
        if ($this->dateEntre !== '') {
            $personnel->setDateEntre(new \DateTime($this->dateEntre));
        }
        if ($this->fonctionId !== '') {
            $fonction = $em->find(\App\Entity\Fonction::class, $this->fonctionId);
            if ($fonction) {
                $personnel->setfonctionID($fonction);
            }
        }

        // Contacts (scalaires — aucun problème de normalisation)
        $contacts = array_values(array_filter(
            $this->contacts,
            static fn($c) => trim($c['valeur'] ?? '') !== ''
        ));
        $personnel->setContactAg($contacts ?: null);

        // Photo (lue depuis la requête HTTP, PAS depuis un LiveProp)
        $photoFile = $request->files->get('photo_profil');
        if ($photoFile instanceof UploadedFile) {
            $personnel->setPhotoProfilFile($photoFile);
        }

        // Conjoint
        if ($this->avecConjoint && $this->conjointNom !== '' && $this->conjointPrenom !== '') {
            $conjoint = $personnel->getConjoint() ?? new Conjoint();
            $conjoint->setNom($this->conjointNom);
            $conjoint->setPrenom($this->conjointPrenom);
            $conjoint->setProfession($this->conjointProfession ?: null);
            if ($this->conjointDateNaiss !== '') {
                $conjoint->setDateNaiss(new \DateTime($this->conjointDateNaiss));
            }
            $personnel->setConjoint($conjoint);
        } elseif (!$this->avecConjoint) {
            $personnel->setConjoint(null);
        }

        // Enfants — supprimer les existants puis recréer depuis $this->enfants (tableau simple)
        foreach ($personnel->getEnfants()->toArray() as $e) {
            $personnel->removeEnfant($e);
            $em->remove($e);
        }

        foreach ($this->enfants as $data) {
            // Ignorer les lignes vides
            if (trim($data['nom'] ?? '') === '' || trim($data['prenom'] ?? '') === '') {
                continue;
            }
            $enfant = new Enfant();
            $enfant->setNom($data['nom']);
            $enfant->setPrenom($data['prenom']);

            if (($data['sexe'] ?? '') !== '') {
                $enfant->setSexe(Sexe::from($data['sexe']));
            }

            if (($data['dateNaiss'] ?? '') !== '') {
                $enfant->setDateNaiss(new \DateTime($data['dateNaiss']));
            }
            $personnel->addEnfant($enfant);
        }

        $em->persist($personnel);
        $em->flush();

        $this->addFlash(
            'success',
            'Personnel ' . ($this->editId ? 'modifié' : 'créé') . ' avec succès !'
        );

        return $this->redirectToRoute('app_personnel_index');
    }
}
