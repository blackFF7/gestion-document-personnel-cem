<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
class Categorie
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 10, unique:true)]
    #[Assert\NotBlank()]
    private ?string $designation = null;

    /**
     * @var Collection<int, Fonction>
     */
    #[ORM\OneToMany(
        targetEntity: Fonction::class, 
        mappedBy: 'categorieID', 
        orphanRemoval: false,      // Ne supprime pas automatiquement les fonctions liées
        cascade: []                // Pas de cascade delete
    )]
    private Collection $fonctions;


    public function __construct()
    {
        $this->fonctions = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité

    }

    public function canBeDeleted(): bool
    {
        return $this->fonctions->isEmpty();
    }


    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->designation ?? 'Catégorie #' . $this->id;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    /**
     * @return Collection<int, Fonction>
     */
    public function getFonctions(): Collection
    {
        return $this->fonctions;
    }

    public function addFonction(Fonction $fonction): static
    {
        if (!$this->fonctions->contains($fonction)) {
            $this->fonctions->add($fonction);
            $fonction->setCategorieID($this);
        }

        return $this;
    }

    public function removeFonction(Fonction $fonction): static
    {
        if ($this->fonctions->removeElement($fonction)) {
            // set the owning side to null (unless already changed)
            if ($fonction->getCategorieID() === $this) {
                $fonction->setCategorieID(null);
            }
        }

        return $this;
    }
}
