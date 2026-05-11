<?php

namespace App\Form;

use App\Entity\Personnel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class PersonnelAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Personnel::class,

            // recherche multi champs
            'searchable_fields' => [
                'IM',
                'nomAg',
                'prenomAg'
            ],

            //  affichage personnalisé
            'choice_label' => function (Personnel $personnel) {
                return $personnel->getFullName();
            },

            'placeholder' => 'Rechercher par IM ou Nom ou Prénom',

            // PERSONNALISATION TOMSELECT
            'tom_select_options' => [
                'maxOptions' => 8,
                'create' => false,
                'hideSelected' => true,
                'highlight' => true,
                'closeAfterSelect' => true,
            ],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}