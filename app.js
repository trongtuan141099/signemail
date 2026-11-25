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
    const popularIds = new Set(config.popular);
    const ranked = config.popular.map((id) => templates.find((model) => model.id === id)).filter(Boolean);
    const curated = [...new Set([...ranked, ...highlights, ...templates])];
    const wideMotions = new Set(config.wideMotions);
    const motionDirectory = new URL(config.motionDirectory, window.location.href);
    const iconRenderer = new URL(config.iconRenderer, window.location.href);
    const exampleFill = byId('example-fill');
    const components = Object.fromEntries([...document.querySelectorAll('[data-component]')].map((input) => [input.dataset.component, input]));
    const touched = new Set();
    const groups = {
        name: ['full_name'], role: ['job_title'], company: ['company'],
        phone: ['phone','phone_href'], email: ['email'], website: ['website'], address: ['address'],
        logo: ['logo_url'], avatar: ['avatar_url'],
        social: ['social_icons'], cta: ['cta_url'], banner: ['banner_url'], motion: ['motion_url'],
        greeting: ['greeting']
    };

    let selected = curated[0];
    const initialLimit = () => window.matchMedia('(max-width: 540px)').matches ? 2 : 4;
    let visibleLimit = initialLimit();
    let exampleMode = true;
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

    function setMotion(values, key) {
        values.motion_url = !config.motionKeys.includes(key) ? ''
            : key === 'dots' ? new URL(config.motion, window.location.href).href : new URL(key + '.gif', motionDirectory).href;
        [values.motion_width, values.motion_height] = wideMotions.has(key) ? [108,28] : key === 'dots' ? [36,10] : [52,18];
        return values;
    }

    function exampleFields(model, thumbnail = false) {
        const asset = (name) => new URL(config[name], window.location.href).href;
        return setMotion({
            full_name: 'Minh Anh', job_title: 'Quản lý thương hiệu', company: 'Studio North',
            phone: '090 123 4567', phone_href: '0901234567', email: 'minh@example.com',
            website: 'https://example.com', address: 'TP. Hồ Chí Minh',
            avatar_url: asset('demoAvatar'), logo_url: asset('demoLogo'), logo_width: thumbnail ? 33 : 52,
            social_icons: '1', social_facebook: 'https://example.com/facebook', social_zalo: 'https://example.com/zalo',
            social_linkedin: 'https://example.com/linkedin', social_motion: !thumbnail && model.animated && !prefersStill.matches ? 'animated' : 'static',
            cta_label: 'Đặt lịch hẹn', cta_url: 'https://example.com/booking',
            banner_url: asset('demoBanner'), banner_link: 'https://example.com/studio',
            primary_color: model.accent, contrast_color: contrastColor(model.accent),
            font_family: 'Arial, sans-serif', font_size: '13', name_size: '18', avatar_radius: '50%',
        }, model.animated && !prefersStill.matches ? model.motion : 'off');
    }

    function readFields(model) {
        const values = Object.fromEntries(fields.map((field) => [field.dataset.field, field.value.trim()]));
        return renderValues(model, values);
    }

    function renderValues(model, values) {
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
        values.social_icons = Object.keys(socialNetworks).some((key) => httpUrl(values['social_' + key])) ? '1' : '';
        setMotion(values, motion);
        for (const [key, names] of Object.entries(groups)) {
            if (!components[key].checked) for (const name of names) values[name] = '';
        }
        return values;
    }

    function draftPreview(model, data) {
        if (!exampleFill.checked) return data;
        const sample = exampleFields(model);
        const merged = {};
        const content = ['full_name','job_title','company','phone','email','website','address','avatar_url','logo_url','banner_url','banner_link',
            'cta_url', ...Object.keys(socialNetworks).map((key) => 'social_' + key)];
        for (const key of content) {
            merged[key] = data[key] || (touched.has(key) ? '' : (sample[key] || ''));
        }
        return renderValues(model, { ...data, ...merged });
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
            const source = new URL(iconRenderer);
            source.searchParams.set('network', key);
            if (values.icon_tone !== 'network') source.searchParams.set('color', values.primary_color.slice(1));
            if (values.social_motion === 'animated') source.searchParams.set('animated', '1');
            icon.src = source.href;
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
                if (name === 'src' && value === '{{motion_url}}') {
                    element.width = values.motion_width;
                    element.height = values.motion_height;
                    element.style.width = values.motion_width + 'px';
                    element.style.height = values.motion_height + 'px';
                }
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
            const nested = Array.from(table.querySelectorAll('table')).reverse();
            for (const inner of nested) {
                if (Array.from(inner.rows).some((row) => row.cells.length > 0)) continue;
                const cell = inner.parentElement;
                inner.remove();
                if (cell.tagName === 'TD' && !cell.childElementCount && !cell.textContent.trim()) {
                    const row = cell.parentElement;
                    cell.remove();
                    if (!row.cells.length) row.remove();
                }
            }
            if (values.logo_url && !model.html.includes('{{logo_url}}')) {
                appendSignatureImage(table, values.logo_url, Number(values.logo_width), 0, 'Logo ' + values.company, 0);
            }
            if (values.greeting) {
                const cell = table.insertRow(0).insertCell();
                cell.style.cssText = 'padding:0 0 8px;font-family:' + values.font_family + ';font-size:' + values.font_size + 'px';
                cell.textContent = values.greeting;
            }
            if (values.motion_url && !model.html.includes('{{motion_url}}')) {
                appendSignatureImage(table, values.motion_url, values.motion_width, values.motion_height, '', -1);
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
        const personal = selected && readFields(selected);
        const data = personal ? (exampleMode ? exampleFields(selected) : draftPreview(selected, personal)) : null;
        if (data) signature.append(buildSignature(selected, data));
        const hasProfile = personal && ['full_name','company','job_title','phone','email','website','address','logo_url','avatar_url','banner_url','social_icons','cta_url','greeting'].some((key) => personal[key]);
        byId('mail-signoff').hidden = !!data?.greeting;
        byId('example-fill-label').hidden = exampleMode;
        byId('selected-preview').textContent = selected ? selected.name + ' · ' + selected.style : 'Chưa có mẫu để xem';
        byId('preview-mode').textContent = exampleMode ? 'Bản minh họa đầy đủ' : exampleFill.checked ? 'Xem trước có dữ liệu mẫu' : hasProfile ? 'Chữ ký để sao chép' : 'Thêm thông tin để sao chép';
        byId('example-toggle').textContent = exampleMode ? 'Dùng mẫu này' : 'Xem mẫu gốc';
        byId('example-toggle').disabled = !selected;
        copy.disabled = exampleMode || !hasProfile || !signature.firstElementChild || pendingUploads.size > 0;
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
            miniature.append(buildSignature(model, exampleFields(model, true)));
            for (const node of miniature.querySelectorAll('a')) node.removeAttribute('href');
            artwork.append(miniature);
            const label = document.createElement('span');
            label.className = 'template-name';
            label.textContent = model.name;
            const detail = document.createElement('span');
            detail.className = 'template-detail';
            detail.textContent = model.style + (popularIds.has(model.id) ? ' · Được chọn nhiều' : '');
            if (model.animated) {
                const tag = document.createElement('span');
                tag.className = 'template-motion-tag';
                tag.textContent = 'GIF';
                artwork.append(tag);
            }
            button.append(artwork, label, detail);
            button.addEventListener('click', () => {
                selected = model;
                document.dispatchEvent(new CustomEvent('signemail:template', { detail: model.id }));
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
                visibleLimit += 4;
                drawTemplates();
                grid.querySelector('[data-id="' + next + '"]')?.focus();
            });
            grid.append(more);
        }
        fitGallery();
    }

    function fitGallery() {
        for (const art of grid.querySelectorAll('.template-art')) {
            const mini = art.querySelector('.template-mini');
            mini.style.zoom = '1';
            const width = mini.getBoundingClientRect().width;
            if (width && art.clientWidth) mini.style.zoom = String(Math.min(1, (art.clientWidth - 20) / width));
        }
    }
    if ('ResizeObserver' in window) new ResizeObserver(fitGallery).observe(grid);

    for (const control of [search, styleFilter, roleFilter]) {
        control.addEventListener(control === search ? 'input' : 'change', () => {
            visibleLimit = initialLimit();
            drawTemplates();
        });
    }
    const motionFilters = Array.from(document.querySelectorAll('[data-template-motion]'));
    byId('hero-search').addEventListener('submit', (event) => {
        event.preventDefault();
        search.value = byId('hero-query').value.trim();
        styleFilter.value = '';
        roleFilter.value = '';
        motionFilters[0].click();
        selectTab(byId('tab-templates'), true);
        search.focus({ preventScroll: true });
    });
    for (const button of motionFilters) {
        button.addEventListener('click', () => {
            motionFilter = button.dataset.templateMotion;
            visibleLimit = initialLimit();
            motionFilters.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
            drawTemplates();
        });
    }

    for (const field of fields) field.addEventListener('input', () => {
        touched.add(field.dataset.field);
        exampleMode = false;
        render();
    });
    for (const input of Object.values(components)) {
        input.addEventListener('change', () => {
            exampleMode = false;
            render();
        });
    }
    exampleFill.addEventListener('change', render);
    colorField.addEventListener('input', () => { customColor = true; });
    byId('template-color').addEventListener('click', () => {
        if (!selected) return;
        customColor = false;
        colorField.value = selected.accent;
        render();
    });
    prefersStill.addEventListener('change', () => {
        drawTemplates();
        render();
    });
    signature.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || !selected || (!exampleMode && !exampleFill.checked)) return;
        const fields = readFields(selected);
        const providedLinks = ['website','cta_url','banner_link', ...Object.keys(socialNetworks).map((key) => 'social_' + key)]
            .map((key) => httpUrl(fields[key]));
        providedLinks.push('mailto:' + fields.email, 'tel:' + fields.phone_href);
        if (exampleMode || !providedLinks.includes(link.getAttribute('href'))) event.preventDefault();
    });

    async function compressRaster(file, key) {
        const url = URL.createObjectURL(file);
        try {
            const source = new Image();
            source.src = url;
            await source.decode();
            const maxEdge = key === 'banner_url' ? 1100 : 640;
            const ratio = Math.min(1, maxEdge / Math.max(source.naturalWidth, source.naturalHeight));
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            for (const reduction of [1, .75, .5]) {
                canvas.width = Math.max(1, Math.round(source.naturalWidth * ratio * reduction));
                canvas.height = Math.max(1, Math.round(source.naturalHeight * ratio * reduction));
                context.drawImage(source, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
                if (blob && blob.size <= config.uploadLimit) return blob;
            }
            throw new Error('Tệp ảnh vượt giới hạn tải lên của máy chủ.');
        } finally {
            URL.revokeObjectURL(url);
        }
    }

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
            const gif = file.type === 'image/gif' || /\.gif$/i.test(file.name);
            if (gif && file.size > Math.min(config.uploadLimit, 3 * 1024 * 1024)) {
                status.textContent = 'GIF vượt giới hạn tải. Bạn có thể dùng URL GIF công khai.';
                return;
            }
            const control = new AbortController();
            exampleMode = false;
            pendingUploads.set(key, control);
            status.textContent = 'Đang tải ảnh…';
            render();
            async function requestImage(image, filename) {
                const form = new FormData();
                form.append('kind', key);
                form.append('image', image, filename);
                const response = await fetch(config.upload, {
                    method: 'POST', body: form, signal: control.signal,
                    headers: { 'X-SignEmail-Upload': 'brand' }
                });
                const data = await response.json().catch(() => ({
                    code: response.status === 413 ? 'size' : 'server',
                    error: response.status === 413 ? 'Tệp vượt giới hạn của máy chủ.' : 'Máy chủ chưa nhận được ảnh.'
                }));
                return { ok: response.ok && !!data.url, data };
            }

            try {
                const needsResize = file.size > config.uploadLimit;
                let result;
                if (!needsResize) result = await requestImage(file, file.name);
                if (!gif && (needsResize || !result.ok && ['size','dimensions','format','decode'].includes(result.data.code))) {
                    status.textContent = 'Đang chuẩn bị ảnh…';
                    const compressed = await compressRaster(file, key).catch(() => {
                        throw new Error(result?.data.error || 'Không đọc được ảnh này. Hãy thử với PNG hoặc JPG.');
                    });
                    result = await requestImage(compressed, 'image.png');
                }
                if (!result.ok) throw new Error(result.data.error || 'Không thể tải ảnh.');
                const { data } = result;
                if (pendingUploads.get(key) !== control) return;
                urlField.value = new URL(data.url, window.location.href).href;
                touched.add(key);
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
        if (step > 0) exampleMode = false;
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
        if (active.id === 'tab-templates') fitGallery();
        render();
        if (moveFocus) {
            active.focus({ preventScroll: true });
            byId('editor').scrollIntoView({ block: 'start' });
        }
    }

    byId('example-toggle').addEventListener('click', () => {
        if (exampleMode) {
            exampleMode = false;
            if (step === 0) {
                if (selected) document.dispatchEvent(new CustomEvent('signemail:template', { detail: selected.id }));
                selectTab(tabs[1], true);
            } else render();
            return;
        }
        exampleMode = true;
        render();
    });

    const motionChoice = document.querySelector('[data-field="motion_choice"]');
    const motionTiles = Array.from(document.querySelectorAll('[data-motion-choice]'));
    function syncMotionTiles() {
        motionTiles.forEach((tile) => tile.setAttribute('aria-pressed', String(tile.dataset.motionChoice === motionChoice.value)));
    }
    motionChoice.addEventListener('input', syncMotionTiles);
    for (const tile of motionTiles) {
        tile.addEventListener('click', () => {
            motionChoice.value = tile.dataset.motionChoice;
            motionChoice.dispatchEvent(new Event('input'));
        });
    }

    previousStep.addEventListener('click', () => selectTab(tabs[step - 1], true));
    nextStep.addEventListener('click', () => {
        if (step < tabs.length - 1) {
            if (step === 0 && selected) document.dispatchEvent(new CustomEvent('signemail:template', { detail: selected.id }));
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

    dialog.addEventListener('close', () => byId('install-video').pause());

    function legacyCopy(exportArea, html, plain) {
        const selection = window.getSelection();
        if (!selection || !document.execCommand) return false;
        const range = document.createRange();
        range.selectNodeContents(exportArea);
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
        if (copy.disabled) return;
        const exportArea = document.createElement('div');
        exportArea.className = 'signature-export';
        exportArea.setAttribute('aria-hidden', 'true');
        exportArea.append(buildSignature(selected, readFields(selected)));
        document.body.append(exportArea);
        const html = exportArea.innerHTML;
        const plain = exportArea.innerText.trim();
        let copied = false;
        if (window.isSecureContext && navigator.clipboard?.write && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' })
                })]);
                copied = true;
            } catch {
                copied = legacyCopy(exportArea, html, plain);
            }
        } else {
            copied = legacyCopy(exportArea, html, plain);
        }
        exportArea.remove();
        if (!copied) {
            exampleFill.checked = false;
            render();
        }
        byId('copy-title').textContent = copied ? 'Đã sao chép chữ ký' : 'Hãy chọn chữ ký trong khung xem trước và sao chép';
        dialog.showModal();
    });

    if (selected) colorField.value = selected.accent;
    drawTemplates();
    render();
})();
