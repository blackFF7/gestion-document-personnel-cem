<?php

namespace App\Entity;

use App\Repository\FonctionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FonctionRepository::class)]
class Fonction
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomFon = null;

    #[ORM\ManyToOne(inversedBy: 'fonctions')]
    private ?Categorie $categorieID = null;

    /**
     * @var Collection<int, Personnel>
     */
    #[ORM\OneToMany(targetEntity: Personnel::class, mappedBy: 'fonctionID')]
    private Collection $personnels;

    public function __construct()
    {
        $this->personnels = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomFon ?? 'Fonction #' . $this->id;
    }

    public function getNomFon(): ?string
    {
        return $this->nomFon;
    }

    public function setNomFon(string $nomFon): static
    {
        $this->nomFon = $nomFon;

        return $this;
    }

    public function getCategorieID(): ?Categorie
    {
        return $this->categorieID;
    }

    public function setCategorieID(?Categorie $categorieID): static
    {
        $this->categorieID = $categorieID;

        return $this;
    }

    /**
     * @return Collection<int, Personnel>
     */
    public function getPersonnels(): Collection
    {
        return $this->personnels;
    }

    public function addPersonnel(Personnel $personnel): static
    {
        if (!$this->personnels->contains($personnel)) {
            $this->personnels->add($personnel);
            $personnel->setFonctionID($this);
        }

        return $this;
    }

    public function removePersonnel(Personnel $personnel): static
    {
        if ($this->personnels->removeElement($personnel)) {
            // set the owning side to null (unless already changed)
            if ($personnel->getFonctionID() === $this) {
                $personnel->setFonctionID(null);
            }
        }

        return $this;
    }
}
