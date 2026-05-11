<?php

namespace App\Namer;

use App\Entity\Document;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

/**
 * Directory namer pour les documents.
 *
 * Structure : <IM>/<nomenclatureDossier>/<Annee>/
 * Exemple   : 002/ETI/2024/
 */
class DocumentDirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName(object|array $object, PropertyMapping $mapping): string
    {
        /** @var Document $object */
        $im = str_pad(
            (string)($object->getPersonnelID()?->getIM() ?? '000'),
            3,
            '0',
            STR_PAD_LEFT
        );

        $nomenclature = $object->getTypeDocumentID()
            ?->getDossierID()
            ?->getNomenclature() ?? 'AUTRE';

        // Année de la date d'arrivée ou année courante
        $annee = ($object->getDateArriveDoc() ?? new \DateTime())->format('Y');

        return $im . '/' . $nomenclature . '/' . $annee . '/';
    }
}