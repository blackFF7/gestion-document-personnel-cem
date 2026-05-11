<?php

namespace App\Entity;

use App\Enum\Sexe;
use App\Repository\EnfantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EnfantRepository::class)]
class Enfant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'enfants', targetEntity: Personnel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Personnel $personnel = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est requis')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est requis')]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateNaiss = null;

    #[ORM\Column(enumType: Sexe::class)]
    private ?Sexe $sexe = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getDateNaiss(): ?\DateTime
    {
        return $this->dateNaiss;
    }

    public function setDateNaiss(?\DateTime $dateNaiss): static
    {
        $this->dateNaiss = $dateNaiss;
        return $this;
    }

    public function getSexe(): ?Sexe
    {
        return $this->sexe;
    }

    public function setSexe(Sexe $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom . ' ' . $this->prenom;
    }
}
