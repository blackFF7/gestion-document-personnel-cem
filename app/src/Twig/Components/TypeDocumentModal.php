<?php

namespace App\Twig\Components;

use App\Entity\Dossier;
use App\Entity\TypeDocument;
use App\Enum\NiveauConfidentiel;
use App\Repository\DossierRepository;
use App\Repository\TypeDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modale Live Component pour ajouter un TypeDocument (et éventuellement un Dossier).
 *
 * Émet l'événement "typeDocument:created" avec l'UUID du nouveau type.
 */
#[AsLiveComponent('TypeDocumentModal', template: 'components/type_document_modal.html.twig')]
class TypeDocumentModal
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ValidatableComponentTrait;

    // ── Champs TypeDocument ───────────────────────────────────────────────────

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le nom du type de document est requis.')]
    #[Assert\Length(max: 255)]
    public string $nomTypeDoc = '';

    #[LiveProp(writable: true)]
    public ?int $dossierId = null;

    // ── Champs Dossier (si création inline) ───────────────────────────────────

    #[LiveProp(writable: true)]
    public bool $createNewDossier = false;

    #[LiveProp(writable: true)]
    public string $nomDos = '';

    #[LiveProp(writable: true)]
    public string $nomenclature = '';

    #[LiveProp(writable: true)]
    public string $niveauConf = 'Public';

    // ── Messages ──────────────────────────────────────────────────────────────

    #[LiveProp]
    public ?string $errorMessage = null;

    #[LiveProp]
    public ?string $successMessage = null;

    public function __construct(
        private DossierRepository $dossierRepo,
        private TypeDocumentRepository $typeDocRepo,
        private EntityManagerInterface $em,
    ) {}

    // ── Données pour les selects ──────────────────────────────────────────────

    /** @return Dossier[] */
    public function getDossiers(): array
    {
        return $this->dossierRepo->findBy([], ['nomDos' => 'ASC']);
    }

    /** @return NiveauConfidentiel[] */
    public function getNiveauxConf(): array
    {
        return NiveauConfidentiel::cases();
    }

    // ── Action principale ─────────────────────────────────────────────────────

    #[LiveAction]
    public function save(): void
    {
        $this->errorMessage   = null;
        $this->successMessage = null;

        // ── 1. Vérifier unicité du nom de type document ───────────────────────
        if ($this->typeDocRepo->findOneBy(['nomTypeDoc' => $this->nomTypeDoc])) {
            $this->errorMessage = sprintf('Le type de document « %s » existe déjà.', $this->nomTypeDoc);
            return;
        }

        // ── 2. Résoudre le dossier ────────────────────────────────────────────
        $dossier = null;

        if ($this->createNewDossier) {
            // Vérifier unicité du dossier
            if ($this->dossierRepo->findOneBy(['nomDos' => $this->nomDos])) {
                $this->errorMessage = sprintf('Le dossier « %s » existe déjà.', $this->nomDos);
                return;
            }
            if ($this->dossierRepo->findOneBy(['nomenclature' => $this->nomenclature])) {
                $this->errorMessage = sprintf('La nomenclature « %s » est déjà utilisée.', $this->nomenclature);
                return;
            }

            $dossier = new Dossier();
            $dossier->setNomDos($this->nomDos);
            $dossier->setNomenclature($this->nomenclature);
            $dossier->setNiveauConf(NiveauConfidentiel::from($this->niveauConf));
            $this->em->persist($dossier);
        } else {
            if (!$this->dossierId) {
                $this->errorMessage = 'Veuillez sélectionner ou créer un dossier.';
                return;
            }
            $dossier = $this->dossierRepo->find($this->dossierId);
            if (!$dossier) {
                $this->errorMessage = 'Dossier introuvable.';
                return;
            }
        }

        // ── 3. Créer le TypeDocument ──────────────────────────────────────────
        $typeDoc = new TypeDocument();
        $typeDoc->setNomTypeDoc($this->nomTypeDoc);
        $typeDoc->setDossierID($dossier);

        $this->em->persist($typeDoc);
        $this->em->flush();

        $this->successMessage = sprintf('Type « %s » créé avec succès.', $this->nomTypeDoc);

        // ── 4. Fermer la modale et notifier le formulaire parent ──────────────
        $this->dispatchBrowserEvent('modal:close');
        $this->emit('typeDocument:created', [
            'typeDocumentId' => (string) $typeDoc->getId(),
        ]);

        // Reset
        $this->nomTypeDoc       = '';
        $this->dossierId        = null;
        $this->createNewDossier = false;
        $this->nomDos           = '';
        $this->nomenclature     = '';
        $this->niveauConf       = 'Public';
    }
}
