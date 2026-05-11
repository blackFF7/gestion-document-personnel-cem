<?php

namespace App\Namer;

use App\Entity\Personnel;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * Namer pour les photos de profil.
 *
 * Nom généré : <IM>_<YmdHis>.<ext>
 * Exemple    : 002_20260505143022.jpg
 */
class PhotoProfilNamer implements NamerInterface
{
    public function name(object $object, PropertyMapping $mapping): string
    {
        /** @var Personnel $object */
        $im  = str_pad((string)($object->getIM() ?? 0), 3, '0', STR_PAD_LEFT);
        $now = (new \DateTimeImmutable())->format('YmdHis');

        $file = $mapping->getFile($object);
        $ext  = $file?->guessExtension() ?? 'jpg';

        return $im . "/" . $im . '_' . $now . '.' . $ext;
    }
}