<?php

namespace App\Namer;

use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class DocumentNamer implements NamerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function name(object $object, PropertyMapping $mapping): string
    {
        /** @var Document $object */

        $slugger = new AsciiSlugger('fr');

        $personnel = $object->getPersonnelID();

        if (!$personnel) {
            throw new \RuntimeException('Personnel introuvable.');
        }

        $typeDocument = $object->getTypeDocumentID();

        if (!$typeDocument) {
            throw new \RuntimeException('Type document introuvable.');
        }

        $dossier = $typeDocument->getDossierID();

        $im = str_pad((string) $personnel->getIM(), 3, '0', STR_PAD_LEFT);

        $nomenclature = strtoupper($dossier->getNomenclature());

        $year = date('Y');

        /**
         * REFERENCE
         */
        $reference = $object->getReference() ?? uniqid();

        $slug = $slugger
            ->slug($reference)
            ->toString();

        /**
         * EXTENSION
         */
        $file = $mapping->getFile($object);

        $ext = $file?->guessExtension() ?? 'pdf';

        /**
         * DOSSIER
         */
        $basePath = sprintf(
            '%s/%s/%s',
            $im,
            $nomenclature,
            $year
        );

        /**
         * NOM INITIAL
         */
        $finalReference = $slug;

        $filename = sprintf(
            '%s/%s.%s',
            $basePath,
            $finalReference,
            $ext
        );

        /**
         * COLLISION
         */
        $version = 1;

        while ($this->filenameExistsInDb($filename, $object)) {

            $finalReference = sprintf(
                '%s_%d',
                $slug,
                $version
            );

            $filename = sprintf(
                '%s/%s.%s',
                $basePath,
                $finalReference,
                $ext
            );

            $version++;
        }

        /**
         * IMPORTANT :
         * synchronise aussi la reference DB
         */
        $object->setReference($finalReference);

        return $filename;
    }

    private function filenameExistsInDb(
        string $filename,
        Document $current
    ): bool {

        $repo = $this->em->getRepository(Document::class);

        $existing = $repo->findOneBy([
            'fichier' => $filename
        ]);

        if (!$existing) {
            return false;
        }

        return $existing->getId() !== $current->getId();
    }
}