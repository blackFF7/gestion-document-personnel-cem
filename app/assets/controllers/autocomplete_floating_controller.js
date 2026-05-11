import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    connect() {
        this.init();
    }

    init() {
        this.tsWrapper = this.element.querySelector('.ts-wrapper');

        if (!this.tsWrapper) return;

        this.updateState = this.updateState.bind(this);

        // 🔥 Observer TomSelect (DOM dynamique)
        this.observer = new MutationObserver(this.updateState);
        this.observer.observe(this.tsWrapper, {
            childList: true,
            subtree: true,
            attributes: true
        });

        // focus / blur fallback (sécurité UX)
        this.tsWrapper.addEventListener('focusin', this.updateState);
        this.tsWrapper.addEventListener('focusout', this.updateState);

        // init
        this.updateState();
    }
    

    updateState() {
        const hasValue =
            this.tsWrapper.classList.contains('focus') ||
            this.tsWrapper.classList.contains('has-items') ||
            this.tsWrapper.querySelector('.item');

        this.element.classList.toggle('active', !!hasValue);
    }

    disconnect() {
        if (this.observer) this.observer.disconnect();

        if (this.tsWrapper) {
            this.tsWrapper.removeEventListener('focusin', this.updateState);
            this.tsWrapper.removeEventListener('focusout', this.updateState);
        }
    }
}