<?php

namespace App\Enum;

enum SituationFamilial: string
{
    case CELIBATAIRE = 'Célibataire';
    case MARIE = 'Marié';
    case DIVORCE = 'Divorcé';
    case VEUVE = 'Veuve';
    
    public static function default(): self
    {
        return self::CELIBATAIRE;
    }
}
