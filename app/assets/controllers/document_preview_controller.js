// document preview controller
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["input", "preview"]

    // Types et taille autorisés
    static ALLOWED_TYPES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    static MAX_SIZE = 50 * 1024 * 1024; // 50MB

    connect() {

    }

    async showPreview() {
        const file = this.inputTarget.files[0];

        if (!file) {
            this._hide();
            return;
        }

        // ✅ Validation taille côté client
        if (file.size > this.constructor.MAX_SIZE) {
            this._showError('⚠️ Fichier trop volumineux. Limite : 50 MB.');
            this.inputTarget.value = '';
            return;
        }

        // ✅ Validation type côté client
        if (!this.constructor.ALLOWED_TYPES.includes(file.type)) {
            this._showError('⚠️ Format non supporté. Formats acceptés : PDF, Word, Excel, Images.');
            this.inputTarget.value = '';
            return;
        }

        // Afficher loader
        this._showLoader(file);

        try {
            const formData = new FormData();
            formData.append("file", file);

            const response = await fetch("/document/preview-temp", {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            if (data.error) {
                this._showError(data.error);
                return;
            }

            this._renderPreview(data.preview);

        } catch (e) {
            console.error(e);
            this._showError('Erreur réseau. Veuillez réessayer.');
        }
    }

    _renderPreview(previewUrl) {
        const ext = previewUrl.split('.').pop().toLowerCase();
        this.previewTarget.innerHTML = '';
        this.previewTarget.style.display = 'block';

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {

            const img = document.createElement('img');
            img.src = previewUrl;
            img.className = 'img-fluid shadow rounded w-100';
            img.style.cssText = 'object-fit:contain; max-height:500px;';
            img.onload = () => this._cleanup(previewUrl);
            img.onerror = () => this._cleanup(previewUrl);
            this.previewTarget.appendChild(img);

        } else if (ext === 'pdf') {

            const iframe = document.createElement('iframe');
            iframe.src = previewUrl;
            iframe.width = '100%';
            iframe.height = '500';
            iframe.className = 'border-0 rounded';
            iframe.onload = () => this._cleanup(previewUrl);
            this.previewTarget.appendChild(iframe);

        } else if (ext === 'html') {

            const iframe = document.createElement('iframe');
            iframe.src = previewUrl;
            iframe.width = '100%';
            iframe.height = '500';
            iframe.className = 'border rounded';
            iframe.sandbox = 'allow-same-origin';
            iframe.onload = () => this._cleanup(previewUrl);
            this.previewTarget.appendChild(iframe);
        }
    }

    _showLoader(file) {
        const size = (file.size / 1024 / 1024).toFixed(2);
        this.previewTarget.style.display = 'block';
        this.previewTarget.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted small">
                    Génération aperçu...
                    <br><span class="badge bg-secondary">${file.name} — ${size} MB</span>
                </div>
            </div>`;
    }

    _showError(message) {
        this.previewTarget.style.display = 'block';
        this.previewTarget.innerHTML =
            `<div class="alert alert-warning d-flex align-items-center gap-2">
                <span>${message}</span>
             </div>`;
    }

    _hide() {
        this.previewTarget.style.display = 'none';
        this.previewTarget.innerHTML = '';
    }

    _cleanup(previewUrl) {
        fetch("/document/preview-cleanup", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ path: previewUrl })
        }).catch(() => {});
    }
}
