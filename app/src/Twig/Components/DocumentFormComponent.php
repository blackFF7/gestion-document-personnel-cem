<?php

namespace App\Twig\Components;

use App\Entity\Document;
use App\Entity\TypeDocument;
use App\Entity\Personnel;
use App\Enum\StatusDoc;
use App\Repository\TypeDocumentRepository;
use App\Repository\DossierRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
final class DocumentFormComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ValidatableComponentTrait;

    // ── Props ────────────────────────────────────────────────────────────────

    /** Document existant (mode édition) */
    #[LiveProp]
    public ?Document $document = null;

    /** Référence auto-générée (non saisissable) */
    #[LiveProp]
    public string $reference = '';

    /** Titre (pré-rempli par le type de document) */
    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le titre est requis')]
    public string $titre = '';

    /** Titulaire (optionnel) */
    #[LiveProp(writable: true)]
    public string $titulaire = '';

    /** Date d'arrivée (optionnel) */
    #[LiveProp(writable: true)]
    public string $dateArriveDoc = '';

    /** Description */
    #[LiveProp(writable: true)]
    public string $description = '';

    /** Type de document sélectionné (ID) */
    #[LiveProp(writable: true)]
    public ?string $typeDocumentId = null;

    /** Personnel sélectionné (ID) - uniquement pour SAP/ADMIN */
    #[LiveProp(writable: true)]
    public ?string $personnelId = null;

    /** Statut */
    #[LiveProp(writable: true)]
    public string $statut = 'Brouillon';

    // ── Modale TypeDocument ──────────────────────────────────────────────────

    #[LiveProp(writable: true)]
    public bool $showTypeDocModal = false;

    #[LiveProp(writable: true)]
    public string $newTypeDocName = '';

    #[LiveProp(writable: true)]
    public ?int $newTypeDocDossierId = null;

    #[LiveProp]
    public string $typeDocError = '';

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct(
        private TypeDocumentRepository $typeDocumentRepo,
        private DossierRepository      $dossierRepo,
        private PersonnelRepository    $personnelRepo,
        private EntityManagerInterface $em,
    ) {}

    // ── Mount ────────────────────────────────────────────────────────────────

    public function mount(?Document $document = null): void
    {
        $this->document = $document;

        if ($document) {
            // Mode édition : pré-remplir les champs
            $this->titre          = $document->getTitre() ?? '';
            $this->titulaire      = $document->getTitulaire() ?? '';
            $this->dateArriveDoc  = $document->getDateArriveDoc()?->format('Y-m-d') ?? '';
            $this->description    = $document->getDescription() ?? '';
            $this->statut         = $document->getStatucDoc()?->value ?? 'Brouillon';
            $this->reference      = $document->getReference() ?? '';

            if ($document->getTypeDocumentID()) {
                $this->typeDocumentId = (string) $document->getTypeDocumentID()->getId();
            }
            if ($document->getPersonnelID()) {
                $this->personnelId = (string) $document->getPersonnelID()->getId();
            }
        } else {
            // Mode création : personnel par défaut = utilisateur connecté
            $user = $this->getUser();
            if ($user instanceof Personnel) {
                $this->personnelId = (string) $user->getId();
            }
        }

        $this->buildReference();
    }

    // ── Expose ───────────────────────────────────────────────────────────────

    #[ExposeInTemplate]
    public function getTypeDocuments(): array
    {
        return $this->typeDocumentRepo->findAll();
    }

    #[ExposeInTemplate]
    public function getDossiers(): array
    {
        return $this->dossierRepo->findAll();
    }

    #[ExposeInTemplate]
    public function getPersonnels(): array
    {
        if (!$this->isSapOrAdmin()) {
            return [];
        }
        return $this->personnelRepo->findAll();
    }

    #[ExposeInTemplate]
    public function isSapOrAdmin(): bool
    {
        return $this->isGranted('ROLE_SAP') || $this->isGranted('ROLE_ADMIN');
    }

    #[ExposeInTemplate]
    public function getCurrentPersonnel(): ?Personnel
    {
        $user = $this->getUser();
        return $user instanceof Personnel ? $user : null;
    }

    #[ExposeInTemplate]
    public function getSelectedTypeName(): string
    {
        if (!$this->typeDocumentId) return '';
        $td = $this->typeDocumentRepo->find($this->typeDocumentId);
        return $td?->getNomTypeDoc() ?? '';
    }

    #[ExposeInTemplate]
    public function getReference(): string
    {
        return $this->reference;
    }

    // ── Watchers ─────────────────────────────────────────────────────────────

    /** Quand typeDocument change → auto-remplir titre */
    public function onTypeDocumentIdUpdated(): void
    {
        if ($this->typeDocumentId) {
            $td = $this->typeDocumentRepo->find($this->typeDocumentId);
            if ($td) {
                $this->titre = $td->getNomTypeDoc();
            }
        }
        $this->buildReference();
    }

    /** Recalcul référence quand titulaire change */
    public function onTitulaireUpdated(): void
    {
        $this->buildReference();
    }

    /** Recalcul référence quand date change */
    public function onDateArriveDocUpdated(): void
    {
        $this->buildReference();
    }

    /** Recalcul référence quand personnel change */
    public function onPersonnelIdUpdated(): void
    {
        $this->buildReference();
    }

    // ── Build Reference ──────────────────────────────────────────────────────

    private function buildReference(): void
    {
        // Format : IM_IDdossier_Titulaire_TypeDocument_du_DateArrive
        // ou      : IM_IDdossier_TypeDocument   (si pas de titulaire ni date)
        // etc.

        // Récupérer l'IM du personnel
        $personnelId = $this->personnelId;
        if (!$personnelId && !$this->isSapOrAdmin()) {
            $user = $this->getUser();
            if ($user instanceof Personnel) {
                $personnelId = (string) $user->getId();
            }
        }

        if (!$personnelId || !$this->typeDocumentId) {
            $this->reference = '';
            return;
        }

        $personnel = $this->personnelRepo->find($personnelId);
        $typeDoc   = $this->typeDocumentRepo->find($this->typeDocumentId);

        if (!$personnel || !$typeDoc) {
            $this->reference = '';
            return;
        }

        $im       = $personnel->getIMFormatted();
        $dossierId = $typeDoc->getDossierID()?->getId() ?? '0';
        $typeNom   = strtoupper(
            preg_replace('/\s+/', '', $typeDoc->getNomTypeDoc())
        );

        $parts = [$im, $dossierId];

        if ($this->titulaire) {
            $parts[] = strtoupper(preg_replace('/\s+/', '_', trim($this->titulaire)));
        }

        $parts[] = $typeNom;

        if ($this->dateArriveDoc) {
            $parts[] = 'du_' . str_replace('-', '', $this->dateArriveDoc);
        }

        $this->reference = implode('_', $parts);
    }

    // ── Actions Modale TypeDocument ──────────────────────────────────────────

    #[LiveAction]
    public function openTypeDocModal(): void
    {
        $this->showTypeDocModal = true;
        $this->newTypeDocName    = '';
        $this->newTypeDocDossierId = null;
        $this->typeDocError      = '';
    }

    #[LiveAction]
    public function closeTypeDocModal(): void
    {
        $this->showTypeDocModal  = false;
        $this->newTypeDocName    = '';
        $this->newTypeDocDossierId = null;
        $this->typeDocError      = '';
    }

    #[LiveAction]
    public function saveTypeDocument(): void
    {
        $name = trim($this->newTypeDocName);

        if (empty($name)) {
            $this->typeDocError = 'Le nom du type de document est requis.';
            return;
        }

        if (!$this->newTypeDocDossierId) {
            $this->typeDocError = 'Veuillez sélectionner un dossier.';
            return;
        }

        // Vérifier unicité
        $existing = $this->typeDocumentRepo->findOneBy(['nomTypeDoc' => $name]);
        if ($existing) {
            $this->typeDocError = "Le type de document « {$name} » existe déjà.";
            return;
        }

        $dossier = $this->dossierRepo->find($this->newTypeDocDossierId);
        if (!$dossier) {
            $this->typeDocError = 'Dossier introuvable.';
            return;
        }

        $td = new \App\Entity\TypeDocument();
        $td->setNomTypeDoc($name);
        $td->setDossierID($dossier);

        $this->em->persist($td);
        $this->em->flush();

        // Sélectionner automatiquement le nouveau type
        $this->typeDocumentId = (string) $td->getId();
        $this->titre = $name;
        $this->buildReference();

        $this->showTypeDocModal  = false;
        $this->newTypeDocName    = '';
        $this->newTypeDocDossierId = null;
        $this->typeDocError      = '';

        $this->dispatchBrowserEvent('typeDoc:created');
    }
}
