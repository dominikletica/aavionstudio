import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['output'];
    static values = {
        count: Number,
    };

    connect() {
        if (Number.isNaN(this.countValue)) {
            this.countValue = 0;
        }

        this.render();
    }

    increment() {
        this.countValue += 1;
        this.render();
    }

    reset() {
        this.countValue = 0;
        this.render();
    }

    render() {
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = String(this.countValue);
        }
    }
}
