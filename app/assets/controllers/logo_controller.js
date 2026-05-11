import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["image"]

    connect() {
        this.angle = 0;
        this.scale = 1;
        this.zoomDirection = 1;
        this.paused = false;

        // hover events
        this.imageTarget.addEventListener("mouseenter", () => this.pause());
        this.imageTarget.addEventListener("mouseleave", () => this.resume());

        this.animate();
    }

    animate = () => {

        if (!this.paused) {

            // rotation constante
            this.angle += 0.3;

            // effet zoom doux
            this.scale += 0.0008 * this.zoomDirection;

            if (this.scale >= 1.08) this.zoomDirection = -1;
            if (this.scale <= 1) this.zoomDirection = 1;

            this.imageTarget.style.transform =
                `rotate(${this.angle}deg) scale(${this.scale})`;
        }

        this.frame = requestAnimationFrame(this.animate);
    }

    pause() {
        this.paused = true;
    }

    resume() {
        this.paused = false;
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
    }
}