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
    const quill = new Quill(editor, {
        theme: 'snow',
        modules: { toolbar: [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] },
        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'blockquote', 'link', 'image']
    });
    quill.root.setAttribute('aria-label', 'Nội dung bài viết');
    if (textarea.value) {
        const initial = quill.clipboard.convert({ html: textarea.value, text: '' });
        quill.setContents(initial, 'silent');
    }
    textarea.hidden = true;
    textarea.previousElementSibling.htmlFor = '';
    form.addEventListener('submit', () => {
        textarea.value = quill.getSemanticHTML();
    });
})();
