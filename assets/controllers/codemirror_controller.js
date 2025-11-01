import { Controller } from '@hotwired/stimulus';
import { EditorState } from '@codemirror/state';
import { EditorView, basicSetup } from 'codemirror';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        language: String,
        placeholder: String,
        readonly: Boolean,
    };

    async connect() {
        this.textarea = this.element instanceof HTMLTextAreaElement ? this.element : this.element.querySelector('textarea');

        if (!this.textarea) {
            console.warn('codemirror controller requires a <textarea> element.');

            return;
        }

        if (this.editor) {
            return;
        }

        this.textarea.classList.add('hidden');
        this.container = document.createElement('div');
        this.container.classList.add('cm-wrapper');
        this.textarea.after(this.container);

        const extensions = [
            basicSetup,
            EditorView.updateListener.of((update) => {
                if (!update.docChanged) {
                    return;
                }

                this.textarea.value = update.state.doc.toString();
                this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }),
        ];

        if (this.readonlyValue) {
            extensions.push(EditorState.readOnly.of(true));
        }

        const languageExtension = await this.resolveLanguageExtension(this.languageValue);

        if (languageExtension) {
            extensions.push(languageExtension);
        }

        this.editor = new EditorView({
            state: EditorState.create({
                doc: this.textarea.value,
                extensions,
            }),
            parent: this.container,
        });
    }

    disconnect() {
        if (this.editor) {
            this.editor.destroy();
            this.editor = null;
        }

        if (this.container) {
            this.container.remove();
            this.container = null;
        }

        if (this.textarea) {
            this.textarea.classList.remove('hidden');
        }
    }

    async resolveLanguageExtension(language) {
        if (!language) {
            return null;
        }

        try {
            switch (language.toLowerCase()) {
                case 'json': {
                    const { json } = await import('@codemirror/lang-json');

                    return json();
                }
                case 'markdown': {
                    const { markdown } = await import('@codemirror/lang-markdown');

                    return markdown();
                }
                case 'html': {
                    const { html } = await import('@codemirror/lang-html');

                    return html();
                }
                case 'twig': {
                    const { html } = await import('@codemirror/lang-html');

                    return html({
                        matchClosingTags: true,
                        selfClosingTags: true,
                    });
                }
                default:
                    console.warn(`codemirror controller: unsupported language "${language}", falling back to plain text.`);
            }
        } catch (error) {
            console.warn(`codemirror controller: failed to load language "${language}".`, error);
        }

        return null;
    }
}
