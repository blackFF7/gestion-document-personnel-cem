<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class ServiceAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class'             => Service::class,
            'label'             => 'Service',
            'placeholder'       => 'Rechercher un service…',
            'choice_label'      => 'nomSer',
            'searchable_fields' => ['nomSer', 'nomenSer'],
            'multiple'          => false,
            'required'          => false,
            // Ces attributs sont rendus sur le <option> HTML ET transmis
            // dans la réponse JSON Ajax par ux-autocomplete (clés "data-*").
            // Tom Select les stocke dans son cache : ts.options[uuid]['data-direction-nom']
            'choice_attr' => function (Service $service): array {
                $direction = $service->getDirectionID();
                return [
                    'data-direction-nom'   => $direction?->getNomDir()   ?? '',
                    'data-direction-nomen' => $direction?->getNomenDir() ?? '',
                    'data-direction-id'    => (string) ($direction?->getId() ?? ''),
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}