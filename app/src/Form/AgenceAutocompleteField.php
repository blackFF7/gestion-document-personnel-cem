<?php

namespace App\Form;

use App\Entity\Agence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class AgenceAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Agence::class,
            'searchable_fields' => [
                'nomAgc',
                'nomenAgc'
            ],

            'choice_label' => 'nomenAgc',
            'placeholder' => 'Choisi une Agence',

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
