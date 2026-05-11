import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    dragover(event) {
        event.preventDefault();
        this.element.classList.add('drag-over');
    }

    dragleave() {
        this.element.classList.remove('drag-over');
    }

    drop(event) {
        event.preventDefault();
        this.element.classList.remove('drag-over');
        const input = this.element.querySelector('input[type="file"]');
        if (!input || !event.dataTransfer.files.length) return;
        // Transférer les fichiers vers l'input
        const dt = new DataTransfer();
        dt.items.add(event.dataTransfer.files[0]);
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
