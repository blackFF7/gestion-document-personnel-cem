<?php

namespace App\Entity;

use App\Enum\NiveauConfidentiel;
use App\Repository\DossierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DossierRepository::class)]
class Dossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomDos = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomenclature = null;

    #[ORM\Column(enumType: NiveauConfidentiel::class, options: ['default' => 'Public'])]
    private ?NiveauConfidentiel $niveauConf = null;

    /**
     * @var Collection<int, TypeDocument>
     */
    #[ORM\OneToMany(targetEntity: TypeDocument::class, mappedBy: 'dossierID')]
    private Collection $typeDocuments;

    public function __construct()
    {
        $this->typeDocuments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomDos ?? 'Dossier #' . $this->id;
    }

    public function getNomDos(): ?string
    {
        return $this->nomDos;
    }

    public function setNomDos(string $nomDos): static
    {
        $this->nomDos = $nomDos;

        return $this;
    }

    public function getNomenclature(): ?string
    {
        return $this->nomenclature;
    }

    public function setNomenclature(string $nomenclature): static
    {
        $this->nomenclature = $nomenclature;

        return $this;
    }

    public function getNiveauConf(): ?NiveauConfidentiel
    {
        return $this->niveauConf;
    }

    public function setNiveauConf(NiveauConfidentiel $niveauConf): static
    {
        $this->niveauConf = $niveauConf;

        return $this;
    }

    /**
     * @return Collection<int, TypeDocument>
     */
    public function getTypeDocuments(): Collection
    {
        return $this->typeDocuments;
    }

    public function addTypeDocument(TypeDocument $typeDocument): static
    {
        if (!$this->typeDocuments->contains($typeDocument)) {
            $this->typeDocuments->add($typeDocument);
            $typeDocument->setDossierID($this);
        }

        return $this;
    }

    public function removeTypeDocument(TypeDocument $typeDocument): static
    {
        if ($this->typeDocuments->removeElement($typeDocument)) {
            // set the owning side to null (unless already changed)
            if ($typeDocument->getDossierID() === $this) {
                $typeDocument->setDossierID(null);
            }
        }

        return $this;
    }
}
