<?php

namespace App\Namer;

use App\Entity\Personnel;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

/**
 * Directory namer pour les photos de profil.
 *
 * Structure : <IM>/
 * Exemple   : 002/
 */
class PhotoProfilDirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName(object|array $object, PropertyMapping $mapping): string
    {
        /** @var Personnel $object */
        $im = str_pad(
            (string)($object->getIM() ?? 0),
            3,
            '0',
            STR_PAD_LEFT
        );

        return ' ';
    }
}