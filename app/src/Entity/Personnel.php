<?php

namespace App\Entity;

use App\Enum\Sexe;
use App\Enum\SituationFamilial;
use App\Enum\StatusCompte;
use App\Repository\PersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà utilisé')]
#[Vich\Uploadable]
class Personnel implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank()]
    private ?string $username = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(unique: true)]
    #[Assert\Length(min: 6)]
    private ?string $password = null;

    #[ORM\Column(unique: true)]
    #[Assert\NotNull()]
    #[Assert\Positive()]
    private ?int $IM = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    private ?string $nomAg = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    private ?string $prenomAg = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank]
    private ?\DateTime $dateNaissAg = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateEntre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseAg = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $mailAg = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contactAg = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $creationCompte = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $majCompte = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dernierConnexion = null;

    #[ORM\Column(enumType: StatusCompte::class, options: ['default' => 'Inactif'])]
    private ?StatusCompte $statusCompte = null;

    #[ORM\Column(enumType: Sexe::class)]
    private ?Sexe $sexe = null;

    #[ORM\Column(enumType: SituationFamilial::class, options: ['default' => 'Célibataire'])]
    private ?SituationFamilial $situationFamilial = null;

    // ── Photo de profil ──────────────────────────────────────────────────────
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoProfil = null;

    #[Vich\UploadableField(mapping: 'photos_profil', fileNameProperty: 'photoProfil')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        mimeTypesMessage: 'Seules les images (JPG, PNG, WEBP, GIF) sont autorisées'
    )]
    private ?File $photoProfilFile = null;

    // ── Relations ────────────────────────────────────────────────────────────
    /** @var Collection<int, AgencePersonnel> */
    #[ORM\OneToMany(targetEntity: AgencePersonnel::class, mappedBy: 'personnelID', cascade: ['remove'], orphanRemoval: true)]
    private Collection $agencePersonnels;

    /** @var Collection<int, DirectionPersonnel> */
    #[ORM\OneToMany(targetEntity: DirectionPersonnel::class, mappedBy: 'personnelID', cascade: ['remove'], orphanRemoval: true)]
    private Collection $directionPersonnels;

    #[ORM\ManyToOne(inversedBy: 'personnels')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fonction $fonctionID = null;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(mappedBy: 'personnelID', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\Column]
    private ?bool $BackFront = null;

    // ── Famille ──────────────────────────────────────────────────────────────
    #[ORM\OneToOne(mappedBy: 'personnel', targetEntity: Conjoint::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Conjoint $conjoint = null;

    /** @var Collection<int, Enfant> */
    #[ORM\OneToMany(mappedBy: 'personnel', targetEntity: Enfant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $enfants;

    public function __construct()
    {
        $this->agencePersonnels = new ArrayCollection();
        $this->directionPersonnels = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->enfants = new ArrayCollection();
        $this->id = Uuid::v7();
        $this->creationCompte = new \DateTimeImmutable();
        $this->majCompte = new \DateTimeImmutable();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        // 🔥 OBLIGATOIRE
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'roles' => $this->roles,
            'password' => $this->password, // IMPORTANT
        ];
    }

    #[\Deprecated]
    public function eraseCredentials(): void {}

    public function getIM(): ?int
    {
        return $this->IM;
    }
    public function getIMFormatted(int $length = 3): string
    {
        return str_pad((string) $this->IM, $length, '0', STR_PAD_LEFT);
    }
    public function setIM(int $IM): static
    {
        $this->IM = $IM;
        return $this;
    }

    public function getNomAg(): ?string
    {
        return $this->nomAg;
    }
    public function setNomAg(string $nomAg): static
    {
        $this->nomAg = $nomAg;
        return $this;
    }

    public function getPrenomAg(): ?string
    {
        return $this->prenomAg;
    }
    public function setPrenomAg(string $prenomAg): static
    {
        $this->prenomAg = $prenomAg;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->nomAg ?? '') . ' ' . ($this->prenomAg ?? ''));
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    public function getDateNaissAg(): ?\DateTime
    {
        return $this->dateNaissAg;
    }
    public function setDateNaissAg(\DateTime $dateNaissAg): static
    {
        $this->dateNaissAg = $dateNaissAg;
        return $this;
    }

    public function getDateEntre(): ?\DateTime
    {
        return $this->dateEntre;
    }
    public function setDateEntre(\DateTime $dateEntre): static
    {
        $this->dateEntre = $dateEntre;
        return $this;
    }

    public function getAdresseAg(): ?string
    {
        return $this->adresseAg;
    }
    public function setAdresseAg(?string $adresseAg): static
    {
        $this->adresseAg = $adresseAg;
        return $this;
    }

    public function getMailAg(): ?string
    {
        return $this->mailAg;
    }
    public function setMailAg(?string $mailAg): static
    {
        $this->mailAg = $mailAg;
        return $this;
    }

    public function getContactAg(): ?array
    {
        return $this->contactAg;
    }
    public function setContactAg(?array $contactAg): static
    {
        $this->contactAg = $contactAg;
        return $this;
    }

    public function getCreationCompte(): ?\DateTimeImmutable
    {
        return $this->creationCompte;
    }
    public function setCreationCompte(\DateTimeImmutable $creationCompte): static
    {
        $this->creationCompte = $creationCompte;
        return $this;
    }

    public function getMajCompte(): ?\DateTimeImmutable
    {
        return $this->majCompte;
    }
    public function setMajCompte(\DateTimeImmutable $majCompte): static
    {
        $this->majCompte = $majCompte;
        return $this;
    }

    public function getDernierConnexion(): ?\DateTimeImmutable
    {
        return $this->dernierConnexion;
    }
    public function setDernierConnexion(?\DateTimeImmutable $dernierConnexion): static
    {
        $this->dernierConnexion = $dernierConnexion;
        return $this;
    }

    public function getStatusCompte(): ?StatusCompte
    {
        return $this->statusCompte;
    }
    public function setStatusCompte(StatusCompte $statusCompte): static
    {
        $this->statusCompte = $statusCompte;
        return $this;
    }

    public function getSexe(): ?Sexe
    {
        return $this->sexe;
    }
    public function setSexe(Sexe $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getSituationFamilial(): ?SituationFamilial
    {
        return $this->situationFamilial;
    }
    public function setSituationFamilial(SituationFamilial $situationFamilial): static
    {
        $this->situationFamilial = $situationFamilial;
        return $this;
    }

    // ── Photo ─────────────────────────────────────────────────────────────────
    public function getPhotoProfil(): ?string
    {
        return $this->photoProfil;
    }
    public function setPhotoProfil(?string $photoProfil): static
    {
        $this->photoProfil = $photoProfil;
        return $this;
    }

    public function getPhotoProfilFile(): ?File
    {
        return $this->photoProfilFile;
    }
    public function setPhotoProfilFile(?File $photoProfilFile): static
    {
        $this->photoProfilFile = $photoProfilFile;
        if ($this->photoProfilFile instanceof UploadedFile) {
            $this->majCompte = new \DateTimeImmutable();
        }
        return $this;
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function getAgencePersonnels(): Collection
    {
        return $this->agencePersonnels;
    }
    public function addAgencePersonnel(AgencePersonnel $agencePersonnel): static
    {
        if (!$this->agencePersonnels->contains($agencePersonnel)) {
            $this->agencePersonnels->add($agencePersonnel);
            $agencePersonnel->setPersonnelID($this);
        }
        return $this;
    }
    public function removeAgencePersonnel(AgencePersonnel $agencePersonnel): static
    {
        if ($this->agencePersonnels->removeElement($agencePersonnel)) {
            if ($agencePersonnel->getPersonnelID() === $this) {
                $agencePersonnel->setPersonnelID(null);
            }
        }
        return $this;
    }

    public function getDirectionPersonnels(): Collection
    {
        return $this->directionPersonnels;
    }
    public function addDirectionPersonnel(DirectionPersonnel $directionPersonnel): static
    {
        if (!$this->directionPersonnels->contains($directionPersonnel)) {
            $this->directionPersonnels->add($directionPersonnel);
            $directionPersonnel->setPersonnelID($this);
        }
        return $this;
    }
    public function removeDirectionPersonnel(DirectionPersonnel $directionPersonnel): static
    {
        if ($this->directionPersonnels->removeElement($directionPersonnel)) {
            if ($directionPersonnel->getPersonnelID() === $this) {
                $directionPersonnel->setPersonnelID(null);
            }
        }
        return $this;
    }

    public function getfonctionID(): ?Fonction
    {
        return $this->fonctionID;
    }
    public function setfonctionID(?Fonction $fonctionID): static
    {
        $this->fonctionID = $fonctionID;
        return $this;
    }

    public function getDocuments(): Collection
    {
        return $this->documents;
    }
    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setPersonnelID($this);
        }
        return $this;
    }
    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getPersonnelID() === $this) {
                $document->setPersonnelID(null);
            }
        }
        return $this;
    }

    public function isBackFront(): ?bool
    {
        return $this->BackFront;
    }
    public function setBackFront(?bool $BackFront): static
    {
        $this->BackFront = $BackFront;
        return $this;
    }

    // ── Famille ──────────────────────────────────────────────────────────────
    public function getConjoint(): ?Conjoint
    {
        return $this->conjoint;
    }
    public function setConjoint(?Conjoint $conjoint): static
    {
        if ($conjoint === null && $this->conjoint !== null) {
            $this->conjoint->setPersonnel(null);
        }
        if ($conjoint !== null && $conjoint->getPersonnel() !== $this) {
            $conjoint->setPersonnel($this);
        }
        $this->conjoint = $conjoint;
        return $this;
    }

    public function getEnfants(): Collection
    {
        return $this->enfants;
    }
    public function addEnfant(Enfant $enfant): static
    {
        if (!$this->enfants->contains($enfant)) {
            $this->enfants->add($enfant);
            $enfant->setPersonnel($this);
        }
        return $this;
    }
    public function removeEnfant(Enfant $enfant): static
    {
        if ($this->enfants->removeElement($enfant)) {
            if ($enfant->getPersonnel() === $this) {
                $enfant->setPersonnel(null);
            }
        }
        return $this;
    }
}
