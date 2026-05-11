<?php

namespace App\Entity;

use App\Repository\AgenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgenceRepository::class)]
class Agence
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomAgc = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $creationAgc = null;

    #[ORM\Column(length: 180, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomenAgc = null;

    /**
     * @var Collection<int, AgencePersonnel>
     */
    #[ORM\OneToMany(targetEntity: AgencePersonnel::class, mappedBy: 'agenceID')]
    private Collection $agencePersonnels;

    public function __construct()
    {
        $this->agencePersonnels = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomAgc ?? 'Agence #' . $this->id;
    }

    public function getNomAgc(): ?string
    {
        return $this->nomAgc;
    }
    

    public function setNomAgc(string $nomAgc): static
    {
        $this->nomAgc = $nomAgc;

        return $this;
    }

    public function getCreationAgc(): ?\DateTime
    {
        return $this->creationAgc;
    }

    public function setCreationAgc(?\DateTime $creationAgc): static
    {
        $this->creationAgc = $creationAgc;

        return $this;
    }

    public function getNomenAgc(): ?string
    {
        return $this->nomenAgc;
    }

    public function setNomenAgc(string $nomenAgc): static
    {
        $this->nomenAgc = $nomenAgc;

        return $this;
    }

    /**
     * @return Collection<int, AgencePersonnel>
     */
    public function getAgencePersonnels(): Collection
    {
        return $this->agencePersonnels;
    }

    public function addAgencePersonnel(AgencePersonnel $agencePersonnel): static
    {
        if (!$this->agencePersonnels->contains($agencePersonnel)) {
            $this->agencePersonnels->add($agencePersonnel);
            $agencePersonnel->setAgenceID($this);
        }

        return $this;
    }

    public function removeAgencePersonnel(AgencePersonnel $agencePersonnel): static
    {
        if ($this->agencePersonnels->removeElement($agencePersonnel)) {
            // set the owning side to null (unless already changed)
            if ($agencePersonnel->getAgenceID() === $this) {
                $agencePersonnel->setAgenceID(null);
            }
        }

        return $this;
    }
}
