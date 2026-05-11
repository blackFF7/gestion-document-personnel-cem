<?php
namespace App\Twig\Components;

use App\Entity\Categorie;
use App\Entity\Fonction;
use App\Repository\FonctionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\Component\Validator\Constraints as Assert;

#[AsLiveComponent]
class PersonnelFonctionModal
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    public string $nomFon = '';

    #[LiveProp(writable: true)]
    public ?Categorie $categorie = null;

    public function __construct(private EntityManagerInterface $em, private FonctionRepository $repo) {}

    #[LiveAction]
    public function save(): void
    {
        $this->validate();

        if ($this->repo->findOneBy(['nomFon' => $this->nomFon])) {
            $this->addError('nomFon', 'Cette fonction existe déjà.');
            return;
        }

        $fonction = new Fonction();
        $fonction->setNomFon($this->nomFon);
        $fonction->setCategorieID($this->categorie);
        $this->em->persist($fonction);
        $this->em->flush();

        $this->dispatchBrowserEvent('modal:close');
        $this->emit('fonction:created', ['fonction' => $fonction->getId()]);
        $this->nomFon   = '';
        $this->categorie = null;
        $this->resetValidation();
    }
}