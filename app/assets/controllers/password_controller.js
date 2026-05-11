import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["input", "eyeIcon", "eyeSlashIcon"];

    toggle() {
        const isPassword = this.inputTarget.type === "password";

        // Toggle type
        this.inputTarget.type = isPassword ? "text" : "password";

        // Toggle icons
        [this.eyeIconTarget, this.eyeSlashIconTarget].forEach(el => el.classList.toggle('d-none'));

        console.log(isPassword ? "Password visible" : "Password hidden");
    }
}