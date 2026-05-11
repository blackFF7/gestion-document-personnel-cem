<?php

namespace App\Form;

use App\Entity\Dossier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Champ autocomplete pour Dossier (utilisé dans la modale d'ajout de TypeDocument).
 */
#[AsEntityAutocompleteField]
class DossierAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class'             => Dossier::class,
            'label'             => 'Dossier',
            'placeholder'       => 'Rechercher un dossier…',
            'choice_label'      => 'nomDos',
            'searchable_fields' => ['nomDos', 'nomenclature'],
            'multiple'          => false,
            'required'          => true,

            'choice_attr' => function (Dossier $d) {
                return [
                    'data-nomen' => $d->getNomenclature() ?? '',
                    'data-conf'  => $d->getNiveauConf()?->value ?? '',
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
