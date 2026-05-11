<?php

namespace App\Twig\Components;

use App\Entity\Document;
use App\Enum\StatusDoc;
use App\Repository\PersonnelRepository;
use App\Repository\TypeDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

/**
 * Live Component pour créer/éditer un Document.
 *
 * La référence est générée automatiquement avec le format :
 *   IM_idDossier_Titulaire_TypeDocument_DateArrive
 * Les parties Titulaire et DateArrive sont optionnelles.
 */
#[AsLiveComponent]
class DocumentForm extends AbstractController
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    // ── Champs du document ────────────────────────────────────────────────────
    #[LiveProp(writable: true)]
    public string $personnelId = '';

    #[LiveProp(writable: true)]
    public string $typeDocumentId = '';

    #[LiveProp(writable: true)]
    public string $titre = '';

    #[LiveProp(writable: true)]
    public string $titulaire = '';          // optionnel

    #[LiveProp(writable: true)]
    public string $dateArriveDoc = '';      // optionnel

    #[LiveProp(writable: true)]
    public string $description = '';

    #[LiveProp(writable: true)]
    public string $statucDoc = 'Brouillon';

    // ── Référence auto-générée (calculée dynamiquement) ───────────────────────
    #[LiveProp(writable: true)]
    public string $referenceManuelle = '';  // si l'utilisateur veut override

    #[LiveProp(writable: true)]
    public bool $referenceOverride = false;

    // ── État ─────────────────────────────────────────────────────────────────
    #[LiveProp]
    public ?string $editId = null;

    public function __construct(
        private PersonnelRepository $personnelRepository,
        private TypeDocumentRepository $typeDocumentRepository,
    ) {}

    // ── Données pour les selects ──────────────────────────────────────────────

    public function getPersonnels(): array
    {
        return $this->personnelRepository->findAll();
    }

    public function getTypeDocuments(): array
    {
        return $this->typeDocumentRepository->findAll();
    }

    public function getStatusChoices(): array
    {
        return StatusDoc::cases();
    }

    /**
     * Génère la référence automatiquement.
     *
     * Format : IM_NomDossier_Titulaire_NomTypeDoc_DateArrive
     * - Titulaire : inclus seulement si non vide
     * - DateArrive : inclus seulement si non vide (format YYYYMMDD)
     */
    public function getAutoReference(): string
    {
        $parts = [];

        // IM du personnel
        if ($this->personnelId !== '') {
            $p = $this->personnelRepository->find($this->personnelId);
            if ($p) {
                $parts[] = $p->getIMFormatted();
            }
        } else {
            $parts[] = 'IM';
        }

        // Identifiant du dossier (via TypeDocument → Dossier)
        if ($this->typeDocumentId !== '') {
            $td = $this->typeDocumentRepository->find($this->typeDocumentId);
            if ($td) {
                $parts[] = strtoupper(preg_replace('/\s+/', '', $td->getDossierID()->getNomDos()));
                $parts[] = strtoupper(preg_replace('/\s+/', '', $td->getNomTypeDoc()));
            }
        } else {
            $parts[] = 'DOSSIER';
            $parts[] = 'TYPE';
        }

        // Titulaire (optionnel)
        if (trim($this->titulaire) !== '') {
            $parts[] = strtoupper(preg_replace('/\s+/', '_', trim($this->titulaire)));
        }

        // Date d'arrivée (optionnel)
        if (trim($this->dateArriveDoc) !== '') {
            try {
                $d = new \DateTime($this->dateArriveDoc);
                $parts[] = $d->format('Ymd');
            } catch (\Exception) {
                // date invalide, on ignore
            }
        }

        return implode('_', $parts);
    }

    /**
     * Référence effective utilisée lors de la sauvegarde.
     */
    public function getReferenceEffective(): string
    {
        if ($this->referenceOverride && trim($this->referenceManuelle) !== '') {
            return trim($this->referenceManuelle);
        }
        return $this->getAutoReference();
    }

    // ── Sauvegarde ───────────────────────────────────────────────────────────

    #[LiveAction]
    public function save(EntityManagerInterface $em, Request $request): Response
    {
        $this->validate();

        if ($this->editId) {
            $document = $em->find(Document::class, $this->editId);
            if (!$document) {
                throw $this->createNotFoundException();
            }
        } else {
            $document = new Document();
        }

        $document->setReference($this->getReferenceEffective());
        $document->setTitre($this->titre ?: null);
        $document->setTitulaire($this->titulaire ?: null);
        $document->setDescription($this->description ?: null);
        $document->setStatucDoc(StatusDoc::from($this->statucDoc));

        if ($this->dateArriveDoc !== '') {
            $document->setDateArriveDoc(new \DateTime($this->dateArriveDoc));
        } else {
            $document->setDateArriveDoc(null);
        }

        if ($this->personnelId !== '') {
            $p = $em->find(\App\Entity\Personnel::class, $this->personnelId);
            $document->setPersonnelID($p);
        }

        if ($this->typeDocumentId !== '') {
            $td = $em->find(\App\Entity\TypeDocument::class, $this->typeDocumentId);
            $document->setTypeDocumentID($td);
        }

        // Fichier (VichUploader)
        $fichier = $request->files->get('fichier_upload');
        if ($fichier instanceof UploadedFile) {
            $document->setFichierUpload($fichier);
        }

        $em->persist($document);
        $em->flush();

        $this->addFlash('success', 'Document enregistré avec succès !');
        return $this->redirectToRoute('app_document_index');
    }
}
