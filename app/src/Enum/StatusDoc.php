<?php

namespace App\Enum;

enum StatusDoc: string
{
    case BROUILLON = 'Brouillon';
    case SOUMIS = 'Soumis';
    case APPROUVE = 'Approuvé';
    case REJETE = 'Rejeté';
    case ARCHIVE = 'Archivé';
    
    public static function default(): self
    {
        return self::BROUILLON;
    }
}
