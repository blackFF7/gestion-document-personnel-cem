<?php

namespace App\Entity;

use App\Repository\TypeDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeDocumentRepository::class)]
class TypeDocument
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomTypeDoc = null;

    #[ORM\ManyToOne(inversedBy: 'typeDocuments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossierID = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'typeDocumentID')]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomTypeDoc ?? 'Type Document #' . $this->id;
    }

    public function getNomTypeDoc(): ?string
    {
        return $this->nomTypeDoc;
    }

    public function setNomTypeDoc(string $nomTypeDoc): static
    {
        $this->nomTypeDoc = $nomTypeDoc;

        return $this;
    }

    public function getDossierID(): ?Dossier
    {
        return $this->dossierID;
    }

    public function setDossierID(?Dossier $dossierID): static
    {
        $this->dossierID = $dossierID;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setTypeDocumentID($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getTypeDocumentID() === $this) {
                $document->setTypeDocumentID(null);
            }
        }

        return $this;
    }
}
