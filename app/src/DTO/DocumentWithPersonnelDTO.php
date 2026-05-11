<?php

namespace App\DTO;

use App\Enum\StatusDoc;
use DateTimeInterface;

class DocumentWithPersonnelDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $titre,
        public readonly ?string $titulaire,
        public readonly ?DateTimeInterface $dateArriveDoc,
        public readonly int $IM,
        public readonly string $nomAg,
        public readonly string $prenomAg,
        public readonly StatusDoc $statucDoc,
    )
    {   }

    public function getIMFormatte(): string
    {
        return str_pad((string) $this->IM, 3, '0', STR_PAD_LEFT);
    }
}