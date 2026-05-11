<?php

namespace App\Entity;

use App\Repository\DirectionPersonnelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DirectionPersonnelRepository::class)]
class DirectionPersonnel
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'directionPersonnels')]
    private ?Personnel $personnelID = null;

    #[ORM\ManyToOne(inversedBy: 'directionPersonnels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Service $serviceID = null;

    public function __construct()
    {
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPersonnelID(): ?Personnel
    {
        return $this->personnelID;
    }

    public function setPersonnelID(?Personnel $personnelID): static
    {
        $this->personnelID = $personnelID;

        return $this;
    }

    public function getServiceID(): ?Service
    {
        return $this->serviceID;
    }

    public function setServiceID(?Service $serviceID): static
    {
        $this->serviceID = $serviceID;

        return $this;
    }
}
