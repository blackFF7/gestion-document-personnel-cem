<?php

namespace App\DTO;

class EnfantDTO
{
    public function __construct(
        public string $nom = '',
        public string $prenom = '',
        public string $dateNaiss = '',
        public ?string $sexe = null,
    ) {}
}