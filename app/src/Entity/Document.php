<?php

namespace App\Entity;

use App\Enum\StatusDoc;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Vich\UploaderBundle\Validator\Constraints as VichAssert;



#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[Vich\Uploadable()]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255,unique:true)]
    #[Assert\NotBlank()]
    private ?string $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $fichier = null;

    #[Vich\UploadableField(mapping: 'documents', fileNameProperty: 'fichier')]
    #[VichAssert\FileRequired(target: 'fichier')]
    #[Assert\File(
        maxSize: '50M',
        mimeTypes: [
            'application/pdf',
            'image/*',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        mimeTypesMessage: 'Seuls les fichiers PDF, DOCX, XLSX et images sont autorisés'
    )]
    private ?File $fichierUpload = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titulaire = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateArriveDoc = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTime $creationDoc = null;

    #[ORM\Column]
    private ?\DateTime $majDoc = null;

    #[ORM\Column(enumType: StatusDoc::class, options: ['default' => 'Brouillon'])]
    private ?StatusDoc $statucDoc = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Personnel $personnelID = null;


    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeDocument $typeDocumentID = null;

    public function __construct()
    {
        $this->statucDoc = StatusDoc::BROUILLON;
        $this->creationDoc = new \DateTime('now');
        $this->majDoc = new \DateTime('now');
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getFichierUpload(): ?File
    {
        return $this->fichierUpload;
    }

    public function setFichierUpload(?File $fichierUpload): static
    {
        $this->fichierUpload = $fichierUpload;
        if ($this->fichierUpload instanceof UploadedFile) {
            $this->majDoc = new \DateTime('now');
        }
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getTitulaire(): ?string
    {
        return $this->titulaire;
    }

    public function setTitulaire(?string $titulaire): static
    {
        $this->titulaire = $titulaire;

        return $this;
    }

    public function getDateArriveDoc(): ?\DateTime
    {
        return $this->dateArriveDoc;
    }

    public function setDateArriveDoc(?\DateTime $dateArriveDoc): static
    {
        $this->dateArriveDoc = $dateArriveDoc;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreationDoc(): ?\DateTime
    {
        return $this->creationDoc;
    }

    public function setCreationDoc(\DateTime $creationDoc): static
    {
        $this->creationDoc = $creationDoc;

        return $this;
    }

    public function getMajDoc(): ?\DateTime
    {
        return $this->majDoc;
    }

    public function setMajDoc(\DateTime $majDoc): static
    {
        $this->majDoc = $majDoc;

        return $this;
    }

    public function getStatucDoc(): ?StatusDoc
    {
        return $this->statucDoc;
    }

    public function setStatucDoc(StatusDoc $statucDoc): static
    {
        $this->statucDoc = $statucDoc;

        return $this;
    }

    public function getPersonnelID(): ?Personnel
    {
        return $this->personnelID ?? null;
    }

    public function setPersonnelID(?Personnel $personnelID): static
    {
        $this->personnelID = $personnelID;

        return $this;
    }

    public function getTypeDocumentID(): ?TypeDocument
    {
        return $this->typeDocumentID ?? null;
    }

    public function setTypeDocumentID(?TypeDocument $typeDocumentID): static
    {
        $this->typeDocumentID = $typeDocumentID;

        return $this;
    }

    public function getPersonnel(): array
    {
        return [
            'IM' => $this->personnelID->getIM(),
            'nomAg' => $this->personnelID->getNomAg(),
            'prenomAg' => $this->personnelID->getPrenomAg(),
        ];
    }

    public function getTypeDocument(): array
    {
        return [
            'nomTypeDoc' => $this->typeDocumentID->getNomTypeDoc(),
            'dossier' => [
                'nomDos' => $this->typeDocumentID->getDossierID()->getNomDos(),
                'nomenclature' => $this->typeDocumentID->getDossierID()->getNomenclature(),
                'niveauConf' => $this->typeDocumentID->getDossierID()->getNiveauConf()->value,
            ]
        ];
    }

}
