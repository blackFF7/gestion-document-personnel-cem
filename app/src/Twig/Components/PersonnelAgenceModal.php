<?php
namespace App\Twig\Components;

use App\Entity\Agence;
use App\Repository\AgenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\Component\Validator\Constraints as Assert;

#[AsLiveComponent]
class PersonnelAgenceModal
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank(message: 'Le nom de l\'agence est requis')]
    public string $nomAgc = '';

    #[LiveProp(writable: true)]
    public string $nomenAgc = '';

    public function __construct(private EntityManagerInterface $em, private AgenceRepository $repo) {}

    #[LiveAction]
    public function save(): void
    {
        $this->validate();

        if ($this->repo->findOneBy(['nomAgc' => $this->nomAgc])) {
            $this->addError('nomAgc', 'Cette agence existe déjà.');
            return;
        }

        $agence = new Agence();
        $agence->setNomAgc($this->nomAgc);
        $agence->setNomenAgc($this->nomenAgc ?: strtoupper(substr($this->nomAgc, 0, 6)));
        $this->em->persist($agence);
        $this->em->flush();

        $this->dispatchBrowserEvent('modal:close');
        $this->emit('agence:created', ['agence' => $agence->getId()]);
        $this->nomAgc   = '';
        $this->nomenAgc = '';
        $this->resetValidation();
    }
}