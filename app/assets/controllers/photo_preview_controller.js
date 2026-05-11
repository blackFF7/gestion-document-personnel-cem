// assets/controllers/photo_preview_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'placeholder'];

    /**
     * Appelé sur "change" de l'input file.
     * Affiche l'aperçu localement sans déclencher de re-render LiveComponent.
     */
    preview(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Vérification type
        if (!file.type.startsWith('image/')) {
            alert('Veuillez sélectionner une image (JPG, PNG, WEBP…)');
            event.target.value = '';
            return;
        }

        // Vérification taille (5 Mo max)
        if (file.size > 5 * 1024 * 1024) {
            alert('L\'image ne doit pas dépasser 5 Mo.');
            event.target.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = (e) => {
            // Afficher l'image
            if (this.hasPreviewTarget) {
                this.previewTarget.src = e.target.result;
                this.previewTarget.classList.remove('d-none');
                this.previewTarget.style.display = 'block';
            }
            // Cacher le placeholder
            if (this.hasPlaceholderTarget) {
                this.placeholderTarget.classList.add('d-none');
                this.placeholderTarget.style.display = 'none';
            }
        };

        reader.readAsDataURL(file);
    }
}
