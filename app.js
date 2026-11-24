(() => {
    'use strict';

    const byId = (id) => document.getElementById(id);
    const templates = JSON.parse(byId('template-data').textContent);
    const config = JSON.parse(byId('editor-config').textContent);
    const grid = byId('template-grid');
    const signature = byId('signature');
    const copy = byId('copy-signature');
    const dialog = byId('copy-dialog');
    const fields = Array.from(document.querySelectorAll('[data-field]'));
    const socialNetworks = {
        linkedin: 'LinkedIn', facebook: 'Facebook', zalo: 'Zalo', site: 'Website',
        tiktok: 'TikTok', instagram: 'Instagram', youtube: 'YouTube'
    };
    const aliases = { name: 'full_name', title: 'job_title', avatar: 'avatar_url' };
    const urlAttributes = new Set(['href', 'src']);
    const tokens = /\{\{([a-z_]+)\}\}/g;
    const defaultSize = 13;
    const minimumSize = 10;
    const maximumSize = 22;

    const colorField = document.querySelector('[data-field="primary_color"]');
    const prefersStill = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pendingUploads = new Map();
    const featuredStyles = new Set();
    const highlights = templates.filter((model) => {
        if (!model.html.includes('{{logo_url}}') || featuredStyles.has(model.style)) return false;
        featuredStyles.add(model.style);
        return true;
    });
    const curated = [...highlights, ...templates.filter((model) => !highlights.includes(model))];
    const demoLogo = new URL(config.demoLogo, window.location.href).href;
    const motionDirectory = new URL(config.motionDirectory, window.location.href);
    const iconDirectory = new URL(config.icons, window.location.href);

    let selected = curated[0];
    let visibleLimit = 6;
    let motionFilter = '';
    let customColor = false;

    function httpUrl(value) {
        try {
            const url = new URL(value);
            return (url.protocol === 'https:' || url.protocol === 'http:') && !url.username && !url.password
                ? url.href : '';
        } catch {
            return '';
        }
    }

    function safeUrl(value, attribute) {
        if (attribute === 'href' && (/^mailto:[^\s<>@]+@[^\s<>@]+\.[^\s<>@]+$/i.test(value) || /^tel:\+?[0-9]+$/i.test(value))) {
            return value;
        }
        return httpUrl(value);
    }

    function contrastColor(color) {
        const channels = color.slice(1).match(/../g).map((channel) => {
            const value = parseInt(channel, 16) / 255;
            return value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4;
        });
        const luminance = .2126 * channels[0] + .7152 * channels[1] + .0722 * channels[2];
        return luminance > .179 ? '#000000' : '#ffffff';
    }

    function readFields(model) {
        const values = Object.fromEntries(fields.map((field) => [field.dataset.field, field.value.trim()]));
        for (const name of ['website', 'avatar_url', 'logo_url', 'banner_url', 'banner_link', 'cta_url']) {
            values[name] = httpUrl(values[name]);
        }
        if (values.cta_url && !values.cta_label) values.cta_label = 'Đặt lịch hẹn';
        values.phone_href = values.phone.replace(/[^\d+]/g, '');
        values.primary_color = /^#[\da-f]{6}$/i.test(values.primary_color) ? values.primary_color : '#18634f';
        values.contrast_color = contrastColor(values.primary_color);
        values.font_size = String(Math.min(maximumSize, Math.max(minimumSize, Number(values.font_size) || defaultSize)));
        values.name_size = String(Number(values.font_size) + 5);
        let motion = values.motion_choice;
        if (motion === 'auto') motion = model.animated && !prefersStill.matches ? model.motion : 'off';
        values.motion_url = !config.motionKeys.includes(motion) ? ''
            : motion === 'dots' ? new URL(config.motion, window.location.href).href : new URL(motion + '.gif', motionDirectory).href;
        values.social_icons = Object.keys(socialNetworks).some((key) => httpUrl(values['social_' + key])) ? '1' : '';
        return values;
    }

    function socialTable(values) {
        const box = document.createElement('template');
        box.innerHTML = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tbody><tr></tr></tbody></table>';
        const row = box.content.querySelector('tr');
        for (const [key, label] of Object.entries(socialNetworks)) {
            const url = httpUrl(values['social_' + key]);
            if (!url) continue;
            const cell = row.insertCell();
            cell.style.cssText = 'padding:0 5px 0 0;vertical-align:middle';
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('style', 'text-decoration:none');
            const icon = document.createElement('img');
            icon.src = values.social_motion === 'animated'
                ? new URL('social-' + key + '.gif', motionDirectory).href
                : new URL(key + '.png', iconDirectory).href;
            icon.alt = label;
            icon.width = 22;
            icon.height = 22;
            icon.setAttribute('style', 'display:block;border:0;width:22px;height:22px');
            link.append(icon);
            cell.append(link);
        }
        return box.content;
    }

    function buildSignature(model, values) {
        const box = document.createElement('template');
        box.innerHTML = model.html;
        const fragment = box.content;
        const replaceValues = (text) => text.replace(tokens, (_, key) => {
            const field = aliases[key] || key;
            return Object.hasOwn(values, field) ? values[field] : '';
        });

        for (const element of fragment.querySelectorAll('[data-if]')) {
            const key = element.getAttribute('data-if');
            element.removeAttribute('data-if');
            if (!values[key]) element.remove();
        }

        for (const element of fragment.querySelectorAll('*')) {
            for (const { name, value } of Array.from(element.attributes)) {
                if (!value.includes('{{') && !urlAttributes.has(name)) continue;
                let resolved = replaceValues(value);
                if (name === 'href' && value === 'tel:{{phone}}') resolved = 'tel:' + values.phone_href;
                if (urlAttributes.has(name)) {
                    resolved = safeUrl(resolved, name);
                    if (!resolved) {
                        if (element.tagName === 'IMG') element.remove();
                        if (element.tagName === 'A') element.replaceWith(...element.childNodes);
                        continue;
                    }
                }
                element.setAttribute(name, resolved);
            }
        }

        const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_TEXT);
        const placeholders = [];
        while (walker.nextNode()) placeholders.push(walker.currentNode);
        for (const node of placeholders) {
            const parts = node.data.split('{{social_icons}}');
            if (parts.length === 1) {
                node.data = replaceValues(node.data);
                continue;
            }
            const content = document.createDocumentFragment();
            parts.forEach((part, index) => {
                if (index && values.social_icons) content.append(socialTable(values));
                content.append(document.createTextNode(replaceValues(part)));
            });
            node.replaceWith(content);
        }
        const table = fragment.firstElementChild;
        if (table?.tagName === 'TABLE') {
            if (values.logo_url && !model.html.includes('{{logo_url}}')) {
                appendSignatureImage(table, values.logo_url, Number(values.logo_width), 0, 'Logo ' + values.company, 0);
            }
            if (values.motion_url && !model.html.includes('{{motion_url}}')) {
                appendSignatureImage(table, values.motion_url, 52, 18, '', -1);
            }
        }
        return fragment;
    }

    function appendSignatureImage(table, src, width, height, label, position) {
        const target = table.insertRow(position).insertCell();
        target.setAttribute('style', position === 0 ? 'padding-bottom:8px' : 'padding-top:6px');
        const image = document.createElement('img');
        image.src = src;
        image.alt = label.trim();
        image.width = width;
        if (height) image.height = height;
        image.setAttribute('style', 'display:block;border:0;width:' + width + 'px;height:' + (height ? height + 'px' : 'auto'));
        target.append(image);
    }

    function render() {
        signature.replaceChildren();
        if (selected) signature.append(buildSignature(selected, readFields(selected)));
        byId('selected-preview').textContent = selected ? selected.name + ' · ' + selected.style : 'Chưa có mẫu để xem';
        copy.disabled = !signature.firstElementChild || pendingUploads.size > 0;
    }

    function searchable(value) {
        return value.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
    }

    const styleFilter = byId('style-filter');
    const roleFilter = byId('role-filter');
    const search = byId('template-search');
    for (const [filter, key] of [[styleFilter, 'style'], [roleFilter, 'role']]) {
        const values = [...new Set(templates.map((template) => template[key]))].sort((a, b) => a.localeCompare(b, 'vi'));
        for (const value of values) filter.add(new Option(value, value));
    }

    function drawTemplates() {
        const terms = searchable(search.value).trim().split(/\s+/);
        const matches = curated.filter(({ name, style, role, animated }) =>
            terms.every((term) => searchable([name, style, role].join(' ')).includes(term)) &&
            (!styleFilter.value || style === styleFilter.value) && (!roleFilter.value || role === roleFilter.value) &&
            (!motionFilter || animated === (motionFilter === 'animated'))
        );
        byId('template-count').textContent = matches.length + ' mẫu';
        grid.replaceChildren();
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'empty';
            empty.textContent = 'Không tìm thấy mẫu phù hợp.';
            grid.append(empty);
        }
        for (const model of matches.slice(0, visibleLimit)) {
            const button = document.createElement('button');
            button.className = 'template-card';
            button.type = 'button';
            button.dataset.id = model.id;
            button.setAttribute('aria-pressed', String(model === selected));
            const artwork = document.createElement('div');
            artwork.className = 'template-art';
            artwork.setAttribute('aria-hidden', 'true');
            const miniature = document.createElement('div');
            miniature.className = 'template-mini';
            miniature.append(buildSignature(model, {
                full_name: 'Minh Anh', job_title: 'Brand Manager', company: 'Studio North',
                phone: '+84 901 234 567', phone_href: '+84901234567', email: 'hello@example.com',
                website: 'https://example.com', address: '', avatar_url: '', social_icons: '',
                logo_url: model.html.includes('{{logo_url}}') ? demoLogo : '', logo_width: '33',
                primary_color: model.accent, contrast_color: contrastColor(model.accent),
                font_family: 'Arial, sans-serif', font_size: '13', name_size: '18', avatar_radius: '50%',
                cta_url: '', banner_url: '', banner_link: '', motion_url: ''
            }));
            for (const node of miniature.querySelectorAll('a')) node.removeAttribute('href');
            artwork.append(miniature);
            const label = document.createElement('span');
            label.className = 'template-name';
            label.textContent = model.name;
            const detail = document.createElement('span');
            detail.className = 'template-detail';
            detail.textContent = model.style;
            if (model.animated) {
                const tag = document.createElement('span');
                tag.className = 'template-motion-tag';
                tag.textContent = 'GIF';
                artwork.append(tag);
            }
            button.append(artwork, label, detail);
            button.addEventListener('click', () => {
                selected = model;
                if (!customColor) colorField.value = selected.accent;
                grid.querySelector('[aria-pressed="true"]')?.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-pressed', 'true');
                render();
            });
            grid.append(button);
        }
        if (visibleLimit < matches.length) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'button button-secondary load-more';
            more.textContent = 'Xem thêm mẫu';
            more.addEventListener('click', () => {
                const next = matches[visibleLimit].id;
                visibleLimit += 6;
                drawTemplates();
                grid.querySelector('[data-id="' + next + '"]')?.focus();
            });
            grid.append(more);
        }
    }

    for (const control of [search, styleFilter, roleFilter]) {
        control.addEventListener(control === search ? 'input' : 'change', () => {
            visibleLimit = 6;
            drawTemplates();
        });
    }
    const motionFilters = Array.from(document.querySelectorAll('[data-template-motion]'));
    for (const button of motionFilters) {
        button.addEventListener('click', () => {
            motionFilter = button.dataset.templateMotion;
            visibleLimit = 6;
            motionFilters.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
            drawTemplates();
        });
    }

    for (const field of fields) field.addEventListener('input', render);
    colorField.addEventListener('input', () => { customColor = true; });
    byId('template-color').addEventListener('click', () => {
        if (!selected) return;
        customColor = false;
        colorField.value = selected.accent;
        render();
    });
    prefersStill.addEventListener('change', render);

    for (const box of document.querySelectorAll('[data-upload-box]')) {
        const key = box.dataset.uploadBox;
        const urlField = box.querySelector('[data-field]');
        const fileField = box.querySelector('[data-upload]');
        const thumb = box.querySelector('img');
        const thumbPlaceholder = box.querySelector('.asset-preview span');
        const clear = box.querySelector('[data-clear-image]');
        const status = box.querySelector('.upload-status');

        function syncThumbnail() {
            const url = httpUrl(urlField.value);
            if (url) {
                if (thumb.getAttribute('src') !== url) thumb.src = url;
            } else {
                thumb.removeAttribute('src');
            }
            thumb.hidden = !url;
            thumbPlaceholder.hidden = !!url;
            clear.hidden = !urlField.value;
        }

        function cancelUpload() {
            pendingUploads.get(key)?.abort();
            pendingUploads.delete(key);
            fileField.value = '';
            status.textContent = '';
            render();
        }

        urlField.addEventListener('input', () => {
            cancelUpload();
            syncThumbnail();
        });
        clear.addEventListener('click', () => {
            urlField.value = '';
            urlField.dispatchEvent(new Event('input'));
            fileField.focus();
        });
        thumb.addEventListener('error', () => {
            thumb.hidden = true;
            thumbPlaceholder.hidden = false;
        });

        async function upload(file) {
            cancelUpload();
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                status.textContent = 'Chọn ảnh không quá 2 MB.';
                return;
            }
            const control = new AbortController();
            pendingUploads.set(key, control);
            status.textContent = 'Đang tải ảnh…';
            render();
            const form = new FormData();
            form.append('image', file);
            try {
                const response = await fetch(config.upload, {
                    method: 'POST', body: form, signal: control.signal,
                    headers: { 'X-SignEmail-Upload': 'brand' }
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Không thể tải ảnh.');
                if (pendingUploads.get(key) !== control) return;
                urlField.value = new URL(data.url, window.location.href).href;
                status.textContent = 'Đã tải ảnh.';
                syncThumbnail();
            } catch (error) {
                if (!control.signal.aborted) status.textContent = error instanceof SyntaxError ? 'Không thể tải ảnh. Hãy thử lại.' : error.message;
            } finally {
                if (pendingUploads.get(key) === control) pendingUploads.delete(key);
                render();
            }
        }
        fileField.addEventListener('change', () => upload(fileField.files[0]));
        box.addEventListener('dragover', (event) => {
            if (!event.dataTransfer.types.includes('Files')) return;
            event.preventDefault();
            box.classList.add('dragging');
        });
        box.addEventListener('dragleave', (event) => {
            if (!box.contains(event.relatedTarget)) box.classList.remove('dragging');
        });
        box.addEventListener('drop', (event) => {
            event.preventDefault();
            box.classList.remove('dragging');
            upload(event.dataTransfer.files[0]);
        });
        syncThumbnail();
    }

    const tabs = Array.from(document.querySelectorAll('[role="tab"]'));
    const previousStep = byId('step-back');
    const nextStep = byId('step-next');
    let step = 0;

    function selectTab(active, moveFocus = false) {
        step = tabs.indexOf(active);
        byId('step-status').textContent = 'Bước ' + (step + 1) + ' / ' + tabs.length;
        byId('step-name').textContent = active.lastElementChild.textContent;
        byId('step-progress').value = step + 1;
        previousStep.hidden = step === 0;
        nextStep.textContent = step === tabs.length - 1 ? 'Xem chữ ký' : 'Tiếp tục';
        for (const tab of tabs) {
            tab.setAttribute('aria-selected', String(tab === active));
            tab.tabIndex = tab === active ? 0 : -1;
            byId(tab.getAttribute('aria-controls')).hidden = tab !== active;
        }
        if (moveFocus) {
            active.focus({ preventScroll: true });
            byId('editor').scrollIntoView({ block: 'start' });
        }
    }

    previousStep.addEventListener('click', () => selectTab(tabs[step - 1], true));
    nextStep.addEventListener('click', () => {
        if (step < tabs.length - 1) {
            selectTab(tabs[step + 1], true);
            return;
        }
        byId('preview').scrollIntoView({ block: 'start' });
        copy.focus({ preventScroll: true });
    });
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', (event) => {
            const next = { ArrowLeft: (index + tabs.length - 1) % tabs.length,
                ArrowRight: (index + 1) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            selectTab(tabs[next]);
            tabs[next].focus();
        });
    });

    const themeButtons = Array.from(document.querySelectorAll('[data-theme]'));
    themeButtons.forEach((button) => button.addEventListener('click', () => {
        byId('mail-preview').classList.toggle('dark-mail', button.dataset.theme === 'dark');
        themeButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    }));

    byId('mail-client').addEventListener('change', (event) => {
        const outlook = event.target.value === 'Outlook';
        byId('mail-preview').classList.toggle('outlook-mail', outlook);
        byId('mail-label').textContent = outlook ? 'Thư mới · Outlook' : 'Thư mới';
    });

    byId('copy-help').addEventListener('click', () => {
        byId('copy-title').textContent = 'Cài chữ ký email';
        dialog.showModal();
    });

    function legacyCopy(html, plain) {
        const selection = window.getSelection();
        if (!selection || !document.execCommand) return false;
        const range = document.createRange();
        range.selectNodeContents(signature);
        selection.removeAllRanges();
        selection.addRange(range);
        const setFormats = (event) => {
            event.clipboardData.setData('text/html', html);
            event.clipboardData.setData('text/plain', plain);
            event.preventDefault();
        };
        document.addEventListener('copy', setFormats);
        try {
            return document.execCommand('copy');
        } catch {
            return false;
        } finally {
            document.removeEventListener('copy', setFormats);
            selection.removeAllRanges();
        }
    }

    copy.addEventListener('click', async () => {
        const html = signature.innerHTML;
        const plain = signature.innerText.trim();
        let copied = false;
        if (window.isSecureContext && navigator.clipboard?.write && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' })
                })]);
                copied = true;
            } catch {
                copied = legacyCopy(html, plain);
            }
        } else {
            copied = legacyCopy(html, plain);
        }
        byId('copy-title').textContent = copied ? 'Đã sao chép chữ ký' : 'Hãy chọn chữ ký trong khung xem trước và sao chép';
        dialog.showModal();
    });

    if (selected) colorField.value = selected.accent;
    drawTemplates();
    render();
})();
