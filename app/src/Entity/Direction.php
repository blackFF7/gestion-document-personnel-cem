<?php

namespace App\Entity;

use App\Repository\DirectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DirectionRepository::class)]
class Direction
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomDir = null;

    #[ORM\Column(length: 180, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomenDir = null;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'directionID')]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomDir ?? 'Direction #' . $this->id;
    }

    public function getNomDir(): ?string
    {
        return $this->nomDir;
    }

    public function setNomDir(string $nomDir): static
    {
        $this->nomDir = $nomDir;

        return $this;
    }

    public function getNomenDir(): ?string
    {
        return $this->nomenDir;
    }

    public function setNomenDir(string $nomenDir): static
    {
        $this->nomenDir = $nomenDir;

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setDirectionID($this);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            // set the owning side to null (unless already changed)
            if ($service->getDirectionID() === $this) {
                $service->setDirectionID(null);
            }
        }

        return $this;
    }
}
