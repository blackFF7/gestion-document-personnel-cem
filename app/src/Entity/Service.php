<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomSer = null;

    #[ORM\Column(length: 255, unique:true)]
    #[Assert\NotBlank()]
    private ?string $nomenSer = null;

    /**
     * @var Collection<int, DirectionPersonnel>
     */
    #[ORM\OneToMany(targetEntity: DirectionPersonnel::class, mappedBy: 'serviceID')]
    private Collection $directionPersonnels;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Direction $directionID = null;

    public function __construct()
    {
        $this->directionPersonnels = new ArrayCollection();
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function __toString(): string
    {
        // Affiche le nom si existant, sinon l’ID
        return $this->nomSer ?? 'Service #' . $this->id;
    }

    public function getNomSer(): ?string
    {
        return $this->nomSer;
    }

    public function setNomSer(string $nomSer): static
    {
        $this->nomSer = $nomSer;

        return $this;
    }

    public function getNomenSer(): ?string
    {
        return $this->nomenSer;
    }

    public function setNomenSer(string $nomenSer): static
    {
        $this->nomenSer = $nomenSer;

        return $this;
    }

    /**
     * @return Collection<int, DirectionPersonnel>
     */
    public function getDirectionPersonnels(): Collection
    {
        return $this->directionPersonnels;
    }

    public function addDirectionPersonnel(DirectionPersonnel $directionPersonnel): static
    {
        if (!$this->directionPersonnels->contains($directionPersonnel)) {
            $this->directionPersonnels->add($directionPersonnel);
            $directionPersonnel->setServiceID($this);
        }

        return $this;
    }

    public function removeDirectionPersonnel(DirectionPersonnel $directionPersonnel): static
    {
        if ($this->directionPersonnels->removeElement($directionPersonnel)) {
            // set the owning side to null (unless already changed)
            if ($directionPersonnel->getServiceID() === $this) {
                $directionPersonnel->setServiceID(null);
            }
        }

        return $this;
    }

    public function getDirectionID(): ?Direction
    {
        return $this->directionID;
    }

    public function setDirectionID(?Direction $directionID): static
    {
        $this->directionID = $directionID;

        return $this;
    }
}
