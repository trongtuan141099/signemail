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
    if (!form) return;
    const source = document.getElementById('post-content');
    const status = document.getElementById('editor-status');
    // Keep legacy, hash-approved diagrams byte-for-byte when editing these articles.
    if (/<svg\b/i.test(source.value)) {
        status.textContent = 'Bài có sơ đồ SVG cũ. Chế độ HTML giữ nguyên cấu trúc sơ đồ.';
        return;
    }
    if (!window.tinymce) {
        status.textContent = 'Trình soạn thảo chưa tải được. Bạn có thể sửa nội dung HTML bên trên.';
        return;
    }
    async function upload(blob) {
        if (blob.size > 2 * 1024 * 1024) throw new Error('Ảnh vượt quá 2 MB. Hãy giảm dung lượng hoặc thay ảnh.');
        const data = new FormData();
        data.append('csrf', form.elements.csrf.value);
        data.append('image', blob, 'article-image');
        const response = await fetch(source.dataset.upload, { method: 'POST', body: data });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.location) throw new Error(result.error || 'Tải ảnh thất bại. Kiểm tra phiên đăng nhập và thử lại.');
        return result.location;
    }

    const base = new URL(source.dataset.base.replace(/\/?$/, '/'), location.origin).href;
    const docsImage = (url) => {
        try {
            const parsed = new URL(url);
            return parsed.protocol === 'https:' && /(^|\.)(googleusercontent\.com|docs\.google\.com)$/.test(parsed.hostname);
        } catch { return false; }
    };
    tinymce.init({
        target: source,
        license_key: 'gpl',
        document_base_url: base,
        convert_urls: false,
        height: 560,
        menubar: false,
        plugins: 'lists link image table code fullscreen wordcount',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | removeformat code fullscreen',
        toolbar_mode: 'sliding',
        block_formats: 'Đoạn văn=p;Tiêu đề 2=h2;Tiêu đề 3=h3;Tiêu đề 4=h4;Trích dẫn=blockquote',
        content_style: 'body{max-width:760px;margin:24px auto;padding:0 18px;font:16px/1.65 Arial,sans-serif;color:#213442} img{max-width:100%;height:auto} table{border-collapse:collapse} td,th{padding:5px;border:1px solid #ccd6dc}',
        paste_data_images: true,
        paste_webkit_styles: 'color background-color font-size font-family font-weight font-style text-decoration text-align',
        images_file_types: 'jpg,jpeg,png,gif,webp',
        image_caption: true,
        automatic_uploads: true,
        images_upload_handler: (info) => upload(info.blob()).catch(error => {
            status.textContent = error.message;
            return Promise.reject(error.message);
        }),
        setup(editor) {
            const imports = new Map();
            async function localize(img) {
                const url = img.getAttribute('src');
                if (!docsImage(url)) return;
                if (!imports.has(url)) {
                    imports.set(url, (async () => {
                        const response = await fetch(url, {credentials: 'omit', referrerPolicy: 'no-referrer'});
                        if (!response.ok) throw new Error();
                        return upload(await response.blob());
                    })().catch(() => {
                        imports.delete(url);
                        throw new Error('Ảnh Google Docs chưa tải được. Hãy sao chép riêng ảnh hoặc kéo tệp ảnh gốc vào thay ảnh này trước khi lưu.');
                    }));
                }
                const path = await imports.get(url);
                if (img.getAttribute('src') === url) editor.dom.setAttrib(img, 'src', path);
            }
            async function importDocsImages() {
                await Promise.all(Array.from(editor.getBody().querySelectorAll('img'), localize));
            }
            editor.on('PastePostProcess', () => {
                setTimeout(() => importDocsImages().catch(error => { status.textContent = error.message; }), 0);
            });
            let saving = false;
            let ready = false;
            form.addEventListener('submit', async event => {
                if (ready) return;
                event.preventDefault();
                if (saving) return;
                saving = true;
                status.textContent = 'Đang kiểm tra và tải ảnh trước khi lưu…';
                try {
                    await importDocsImages();
                    await editor.uploadImages();
                    const invalid = Array.from(editor.getBody().querySelectorAll('img')).find(img => {
                        const src = img.getAttribute('src') || '';
                        return !src || /^(data:|blob:|file:|cid:)/i.test(src);
                    });
                    if (invalid) throw new Error('Có ảnh chưa tải lên máy chủ. Hãy thay ảnh bằng tệp PNG, JPEG, GIF hoặc WebP rồi lưu lại.');
                    editor.save();
                    if (source.value.length > 160000) throw new Error('Nội dung vượt giới hạn 160.000 ký tự HTML.');
                    status.textContent = '';
                    ready = true;
                    form.requestSubmit(event.submitter || undefined);
                } catch (error) {
                    status.textContent = error.message || 'Chưa lưu được bài. Vui lòng thử lại.';
                } finally {
                    ready = false;
                    saving = false;
                }
            });
        }
    }).catch(() => { status.textContent = 'Không thể mở trình soạn thảo. Hãy tải lại trang hoặc chỉnh sửa HTML.'; });
})();
