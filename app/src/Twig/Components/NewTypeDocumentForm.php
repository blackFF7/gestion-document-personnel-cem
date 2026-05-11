<?php
// src/Twig/Components/NewTypeDocumentForm.php
namespace App\Twig\Components;

use App\Entity\Dossier;
use App\Entity\TypeDocument;
use App\Repository\DossierRepository;
use App\Repository\TypeDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

#[AsLiveComponent]
final class NewTypeDocumentForm
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le nom du type de document est requis.')]
    #[Assert\Length(min: 2, max: 255)]
    public string $nomTypeDoc = '';

    #[LiveProp(writable: true)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un dossier.')]
    public ?int $dossierId = null;

    #[LiveProp]
    public ?string $errorMessage = null;

    #[LiveProp]
    public bool $success = false;

    public function __construct(
        private DossierRepository $dossierRepository,
        private TypeDocumentRepository $typeDocumentRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return Dossier[]
     */
    public function getDossiers(): array
    {
        return $this->dossierRepository->findAll();
    }

    #[LiveAction]
    public function saveTypeDocument(): void
    {
        $this->validate();

        // Vérifier unicité
        $existing = $this->typeDocumentRepository->findOneBy(['nomTypeDoc' => $this->nomTypeDoc]);
        if ($existing) {
            $this->errorMessage = 'Ce type de document existe déjà.';
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('Déjà existant.');
        }

        $dossier = $this->dossierRepository->find($this->dossierId);
        if (!$dossier) {
            $this->errorMessage = 'Dossier introuvable.';
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('Dossier invalide.');
        }

        $typeDocument = new TypeDocument();
        $typeDocument->setNomTypeDoc($this->nomTypeDoc);
        $typeDocument->setDossierID($dossier);

        $this->em->persist($typeDocument);
        $this->em->flush();

        // Émettre l'événement pour mettre à jour le select du formulaire parent
        $this->emit('typeDocument:created', [
            'id'          => (string) $typeDocument->getId(),
            'nomTypeDoc'  => $typeDocument->getNomTypeDoc(),
            'dossierId'   => $dossier->getId(),
            'dossierNom'  => $dossier->getNomDos(),
        ]);

        // Fermer le modal
        $this->dispatchBrowserEvent('modal:close');

        // Réinitialiser
        $this->nomTypeDoc    = '';
        $this->dossierId     = null;
        $this->errorMessage  = null;
        $this->success       = true;
        $this->resetValidation();
    }
}