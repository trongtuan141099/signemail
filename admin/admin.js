(() => {
    'use strict';

    for (const button of document.querySelectorAll('[data-confirm]')) {
        button.addEventListener('click', (event) => {
            if (!window.confirm(button.dataset.confirm)) event.preventDefault();
        });
    }

    for (const form of document.querySelectorAll('[data-ad-form]')) {
        const mode = form.elements.mode;
        const panels = form.querySelectorAll('[data-ad-mode]');
        const update = () => {
            for (const panel of panels) panel.hidden = panel.dataset.adMode !== mode.value;
        };
        update();
        mode.addEventListener('change', update);
    }

    const form = document.getElementById('post-form');
    if (!form || !window.Quill) return;
    const textarea = document.getElementById('post-content');
    const editor = document.getElementById('post-quill');
    editor.hidden = false;
    const BlockEmbed = Quill.import('blots/block/embed');
    const Delta = Quill.import('delta');
    const guideSource = /^assets\/guides\/[a-z0-9-]+-[1-9][0-9]*\.webp$/;

    class EditorialFigure extends BlockEmbed {
        static blotName = 'editorialFigure';
        static tagName = 'figure';
        static className = 'ql-editorial-figure';

        static create(value) {
            const figure = super.create();
            const image = document.createElement('img');
            image.src = new URL('../' + value.src, window.location.href).href;
            image.alt = value.alt;
            figure.dataset.guideSrc = value.src;
            const caption = document.createElement('figcaption');
            caption.textContent = value.caption;
            figure.append(image, caption);
            return figure;
        }

        static value(figure) {
            return {
                src: figure.dataset.guideSrc,
                alt: figure.querySelector('img').alt,
                caption: figure.querySelector('figcaption').textContent
            };
        }

        html() {
            const figure = this.domNode.cloneNode(true);
            figure.querySelector('img').setAttribute('src', figure.dataset.guideSrc);
            return figure.outerHTML;
        }
    }
    Quill.register(EditorialFigure);

    const quill = new Quill(editor, {
        theme: 'snow',
        modules: { toolbar: [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] },
        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'blockquote', 'link', 'image', 'editorialFigure']
    });
    quill.root.setAttribute('aria-label', 'Nội dung bài viết');
    quill.clipboard.addMatcher('FIGURE', (node, delta) => {
        const image = node.querySelector('img');
        const src = image?.getAttribute('src');
        if (!src || !guideSource.test(src)) return delta;
        return new Delta().insert({ editorialFigure: {
            src, alt: image.alt, caption: node.querySelector('figcaption')?.textContent || ''
        } });
    });
    if (textarea.value) {
        const initial = quill.clipboard.convert({ html: textarea.value, text: '' });
        quill.setContents(initial, 'silent');
    }
    const sourceButton = document.createElement('button');
    sourceButton.type = 'button';
    sourceButton.className = 'button button-secondary';
    sourceButton.textContent = 'Dán hoặc sửa mã HTML';
    editor.after(sourceButton);
    function showSource() {
        textarea.hidden = false;
        editor.hidden = true;
        quill.getModule('toolbar').container.hidden = true;
        sourceButton.hidden = true;
        textarea.focus();
    }
    textarea.hidden = true;
    sourceButton.addEventListener('click', () => {
        textarea.value = quill.getSemanticHTML();
        showSource();
    });
    if (/<(?:svg|table)\b|assets\/guides\/(?:outlook-|gmail-signature-)/i.test(textarea.value)) showSource();
    textarea.previousElementSibling.htmlFor = '';
    form.addEventListener('submit', () => {
        if (!textarea.hidden) return;
        const output = document.createElement('template');
        output.innerHTML = quill.getSemanticHTML();
        const walker = document.createTreeWalker(output.content, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (!node.parentElement?.closest('pre,code')) node.data = node.data.replace(/(\S)\u00a0(?=\S)/g, '$1 ');
        }
        textarea.value = output.innerHTML;
    });
})();
