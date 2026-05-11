import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'toggle'];

    toggle() {
        const panel   = this.panelTarget;
        const button  = this.toggleTarget;
        const label   = button.querySelector('.toggle-label');

        const isOpen  = panel.classList.contains('open');

        panel.classList.toggle('open', !isOpen);
        button.setAttribute('aria-expanded', String(!isOpen));

        if (label) {
            label.textContent = isOpen
                ? 'Afficher le preview'
                : 'Masquer le preview';
        }
    }
}
