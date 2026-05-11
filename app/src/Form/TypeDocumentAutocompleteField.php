<?php

namespace App\Form;

use App\Entity\TypeDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class TypeDocumentAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => TypeDocument::class,
            'placeholder' => 'Choisir un type de document',
            
            // PERSONNALISATION TOMSELECT
            'tom_select_options' => [
                'maxOptions' => 8,
                'create' => false,
                'hideSelected' => true,
                'highlight' => true,
                'closeAfterSelect' => true,
            ],
            
            // 'choice_label' => 'Type_document',

            // choose which fields to use in the search
            // if not passed, *all* fields are used
            // 'searchable_fields' => ['name'],

            // 'security' => 'ROLE_SOMETHING',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
