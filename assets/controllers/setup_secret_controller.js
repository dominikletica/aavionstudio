import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'feedback'];

    static values = {
        message: { type: String, default: 'Secret generated' },
        hideDelay: { type: Number, default: 3000 },
        length: { type: Number, default: 32 },
    };

    generate(event) {
        event.preventDefault();

        const input = this.hasInputTarget ? this.inputTarget : null;
        if (!input) {
            return;
        }

        const secret = this.#randomHex(this.lengthValue);
        input.value = secret;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (this.hasFeedbackTarget) {
            this.feedbackTarget.textContent = this.messageValue;
            this.feedbackTarget.classList.remove('hidden');
            window.setTimeout(() => this.feedbackTarget.classList.add('hidden'), this.hideDelayValue);
        }
    }

    #randomHex(length) {
        const bytes = new Uint8Array(length);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (let i = 0; i < length; i += 1) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }

        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('').slice(0, length * 2);
    }
}
