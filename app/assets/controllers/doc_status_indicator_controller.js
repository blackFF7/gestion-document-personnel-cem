import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'indicator'];

    connect() {
        this.updateIndicator();
    }

    updateIndicator() {
        const val = this.selectTarget.value;
        this.indicatorTarget.dataset.status = val;
    }
}
