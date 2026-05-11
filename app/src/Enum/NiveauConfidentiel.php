<?php

namespace App\Enum;

enum NiveauConfidentiel: string
{
    case PUBLIC = 'Public';
    case CONFIDENTIEL = 'Confidentiel';
    case STRICTEMENT_CONFIDENTIEL = 'Strictement confidentiel';
    
    public static function default(): self
    {
        return self::PUBLIC;
    }
}
