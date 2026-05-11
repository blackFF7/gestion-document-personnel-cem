<?php

namespace App\Form;

use App\Entity\Fonction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class FonctionAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([

            /*
            |--------------------------------------------------------------------------
            | Entity
            |--------------------------------------------------------------------------
            */
            'class' => Fonction::class,

            'searchable_fields' => [
                'nomFon',
            ],

            /*
            |--------------------------------------------------------------------------
            | Display
            |--------------------------------------------------------------------------
            */
            'choice_label' => 'nomFon',

            'placeholder' => 'Choisir une fonction...',

            /*
            |--------------------------------------------------------------------------
            | HTML ATTRIBUTES
            |--------------------------------------------------------------------------
            */
            'attr' => [
                'class' => 'pf-select pf-tom-select',
                'data-pf' => 'autocomplete',
            ],

            /*
            |--------------------------------------------------------------------------
            | Tom Select Styling + UX
            |--------------------------------------------------------------------------
            */
            'tom_select_options' => [

                // UX
                'maxOptions'       => 8,
                'create'           => false,
                'hideSelected'     => true,
                'highlight'        => true,
                'closeAfterSelect' => true,
                'allowEmptyOption' => true,

                // Placeholder
                'placeholder' => '🔍 Rechercher une fonction...',

                // Plugins
                'plugins' => [
                    'clear_button' => [
                        'title' => 'Effacer',
                    ],
                ],

                /*
                |--------------------------------------------------------------------------
                | Custom Render
                |--------------------------------------------------------------------------
                */
                'render' => [

                    // Option dans la liste
                    'option' => <<<JS
                        function(data, escape) {
                            return `
                                <div class="pf-ts-option">
                                    <div class="pf-ts-option-icon">
                                        <i class="bi bi-briefcase-fill"></i>
                                    </div>

                                    <div class="pf-ts-option-content">
                                        <div class="pf-ts-option-title">
                                            \${escape(data.text)}
                                        </div>

                                        <div class="pf-ts-option-sub">
                                            Fonction disponible
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    JS,

                    // Élément sélectionné
                    'item' => <<<JS
                        function(data, escape) {
                            return `
                                <div class="pf-ts-item">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    \${escape(data.text)}
                                </div>
                            `;
                        }
                    JS,
                ],
            ],

            // 'security' => 'ROLE_SOMETHING',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}