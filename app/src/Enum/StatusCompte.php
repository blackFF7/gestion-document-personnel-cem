<?php

namespace App\Enum;

enum StatusCompte: string
{
    case ACTIF = 'Actif';
    case INACTIF = 'Inactif';
    case SUSPENDU = 'Suspendu';
    case DESACTIVE = 'Désactivé';

    public static function default(): self
    {
        return self::INACTIF;
    }
}
