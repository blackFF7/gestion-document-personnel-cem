<?php

namespace App\Form;

use App\Entity\Direction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class DirectionAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Direction::class,

            'searchable_fields' => [
                'nomDir',
                'nomenDir'
            ],

            'choice_label' => 'nomenDir',
            'placeholder' => 'Choisi une direction',

            //Style autocomplete field
            'tom_select_options' => [
                'maxOptions' => 8,
                'create' => false,
                'hideSelected' => true,
                'highlight' => true,
                'closeAfterSelect' => true,
            ],
            
            // 'security' => 'ROLE_SOMETHING',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
