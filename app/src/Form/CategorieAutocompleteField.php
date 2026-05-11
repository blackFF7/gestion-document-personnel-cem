<?php

namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Champ autocomplete pour Catégorie (utilisé dans la modale de création de Fonction).
 */
#[AsEntityAutocompleteField]
class CategorieAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class'             => Categorie::class,
            'label'             => 'Catégorie',
            'placeholder'       => 'Rechercher une catégorie…',
            'choice_label'      => 'designation',
            'searchable_fields' => ['designation'],
            'multiple'          => false,
            'required'          => false,
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
