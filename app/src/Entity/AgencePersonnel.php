<?php

namespace App\Entity;

use App\Repository\AgencePersonnelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AgencePersonnelRepository::class)]
class AgencePersonnel
{
    #[ORM\Id]
    #[ORM\Column(type:'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'agencePersonnels')]
    private ?Agence $agenceID = null;

    #[ORM\ManyToOne(inversedBy: 'agencePersonnels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Personnel $personnelID = null;

    public function __construct()
    {
        $this->id = Uuid::v7(); // Génère un UUID v4 lors de la création de l'entité
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAgenceID(): ?Agence
    {
        return $this->agenceID;
    }

    public function setAgenceID(?Agence $agenceID): static
    {
        $this->agenceID = $agenceID;

        return $this;
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
}
