// app/assets/controllers/upload_zone_controller.js
//
// Contrôleur Stimulus pour la zone de dépôt de fichier.
// Responsabilités :
//   - Afficher le nom + la taille du fichier sélectionné
//   - Gérer le drag & drop avec la classe CSS .drag-over
//   - Mettre à jour l'icône selon l'état (upload / fichier sélectionné)
//
// Targets :
//   - input    : l'<input type="file"> caché
//   - icon     : le conteneur de l'icône Bootstrap
//   - filename : le badge d'affichage du nom de fichier
//
// Usage dans Twig :
//   data-controller="upload-zone"
//   data-upload-zone-target="zone|icon|input|filename"
//   data-action="change->upload-zone#onFileChange"

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ['input', 'icon', 'filename'];

    // ── Lifecycle ──────────────────────────────────────────────────────────

    connect() {
        this.element.addEventListener('dragover',  this.#onDragOver.bind(this));
        this.element.addEventListener('dragleave', this.#onDragLeave.bind(this));
        this.element.addEventListener('drop',      this.#onDrop.bind(this));
    }

    disconnect() {
        this.element.removeEventListener('dragover',  this.#onDragOver.bind(this));
        this.element.removeEventListener('dragleave', this.#onDragLeave.bind(this));
        this.element.removeEventListener('drop',      this.#onDrop.bind(this));
    }

    // ── Actions publiques ──────────────────────────────────────────────────

    /**
     * Déclenché par data-action="change->upload-zone#onFileChange"
     */
    onFileChange() {
        const file = this.inputTarget.files?.[0];
        file ? this.#showFile(file) : this.#reset();
    }

    // ── Drag & drop ────────────────────────────────────────────────────────

    #onDragOver(event) {
        event.preventDefault();
        this.element.classList.add('drag-over');
    }

    #onDragLeave() {
        this.element.classList.remove('drag-over');
    }

    #onDrop(event) {
        event.preventDefault();
        this.element.classList.remove('drag-over');

        const files = event.dataTransfer?.files;
        if (!files?.length) return;

        // Injecter les fichiers dans l'input et déclencher les autres contrôleurs
        this.inputTarget.files = files;
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ── Rendu ──────────────────────────────────────────────────────────────

    #showFile(file) {
        const size = (file.size / 1024 / 1024).toFixed(2);

        if (this.hasFilenameTarget) {
            this.filenameTarget.textContent = `${file.name} (${size} Mo)`;
            this.filenameTarget.hidden = false;
        }

        if (this.hasIconTarget) {
            this.iconTarget.innerHTML = '<i class="bi bi-file-earmark-check text-success"></i>';
        }
    }

    #reset() {
        if (this.hasFilenameTarget) {
            this.filenameTarget.textContent = '';
            this.filenameTarget.hidden = true;
        }

        if (this.hasIconTarget) {
            this.iconTarget.innerHTML = '<i class="bi bi-cloud-upload"></i>';
        }
    }
}