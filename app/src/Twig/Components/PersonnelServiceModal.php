<?php
namespace App\Twig\Components;

use App\Entity\Direction;
use App\Entity\Service;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\Component\Validator\Constraints as Assert;

#[AsLiveComponent]
class PersonnelServiceModal
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank]
    public string $nomSer = '';

    #[LiveProp(writable: true)]
    public string $nomenSer = '';

    #[LiveProp(writable: true)]
    public ?Direction $direction = null;

    public function __construct(private EntityManagerInterface $em, private ServiceRepository $repo) {}

    #[LiveAction]
    public function save(): void
    {
        $this->validate();

        if ($this->repo->findOneBy(['nomSer' => $this->nomSer])) {
            $this->addError('nomSer', 'Ce service existe déjà.');
            return;
        }

        $service = new Service();
        $service->setNomSer($this->nomSer);
        $service->setNomenSer($this->nomenSer ?: strtoupper(substr($this->nomSer, 0, 6)));
        $service->setDirectionID($this->direction);
        $this->em->persist($service);
        $this->em->flush();

        $this->dispatchBrowserEvent('modal:close');
        $this->emit('service:created', ['service' => $service->getId()]);
        $this->nomSer   = '';
        $this->nomenSer = '';
        $this->direction = null;
        $this->resetValidation();
    }
}