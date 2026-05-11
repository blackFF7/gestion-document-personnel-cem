import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        duration: { type: Number, default: 4000 } // 4 secondes
    }

    connect() {
        this.startTimer();
    }

    startTimer() {
        // Barre de progression
        const progress = this.element.querySelector(".toast-progress");
        progress.style.transition = `width ${this.durationValue}ms linear`;
        requestAnimationFrame(() => {
            progress.style.width = "0%";
        });

        // Disparition automatique
        setTimeout(() => {
            this.element.classList.add("toast-hide");
            setTimeout(() => this.element.remove(), 500);
        }, this.durationValue);
    }
}
