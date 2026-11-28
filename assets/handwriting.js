'use strict';

const handwritingFontBase = new URL('handwriting-fonts/', document.currentScript.src);

function initHandwriting(insertImage) {
    const control = id => document.getElementById('ink-' + id);
    const pad = control('pad');
    const typedPad = document.createElement('canvas');
    typedPad.width = 960;
    typedPad.height = 320;
    const strokes = new SignaturePad(pad, { minDistance: 1 });
    const history = [];
    let mode = 'draw', erasing = false, activeStroke = false, beforeStroke;
    const color = control('color'), weight = control('weight');
    const name = control('name'), font = control('font'), status = control('status');
    const fonts = ['Dancing Script', 'Great Vibes', 'Allura', 'Pacifico', 'Alex Brush', 'Birthstone', 'Ephesis', 'Fleur De Leah', 'Italianno', 'Style Script'];
    const loaded = new Map();
    const caption = control('caption'), captionFont = control('caption-font'), captionOn = control('caption-on');
    const preview = control('preview');
    const styles = ['Tự nhiên', 'Thanh lịch', 'Nét mảnh', 'Đậm nét', 'Thư pháp', 'Phóng khoáng', 'Nét nghiêng', 'Cổ điển', 'Thanh thoát', 'Hoài cổ'];
    const finishes = ['plain', 'sweep', 'plain', 'double', 'wave', 'sweep', 'plain', 'double', 'wave', 'sweep'];
    const templates = fonts.map((family,i) => ({family, title: styles[i], finish: finishes[i]}));
    for (const [family,title,finish] of [
        ['Great Vibes','Đuôi hướng thượng','rise'], ['Dancing Script','Cánh cung','arc'],
        ['Allura','Sóng đôi','tide'], ['Alex Brush','Vòng ôm','loop'],
        ['Style Script','Nét vươn','rise'], ['Pacifico','Vòm đậm','arc']
    ]) templates.push({family,title,finish});
    function selectedFamily(group) { return group.querySelector(':checked').value; }
    function paintSample(canvas, value, family, finish, ink) {
        const paint = canvas.getContext('2d');
        const {width, height} = canvas;
        paint.clearRect(0, 0, width, height);
        paint.save();
        paint.font = '140px "' + family + '"';
        const m = paint.measureText(value);
        const w = m.actualBoundingBoxLeft + m.actualBoundingBoxRight;
        const h = m.actualBoundingBoxAscent + m.actualBoundingBoxDescent;
        const extra = finish === 'plain' ? 0 : 24;
        const scale = Math.min(height / 170, width * .91 / Math.max(1,w), height * .84 / Math.max(1,h + extra));
        paint.translate(width / 2, height / 2);
        paint.scale(scale, scale);
        paint.fillStyle = paint.strokeStyle = ink;
        paint.fillText(value, (m.actualBoundingBoxLeft - m.actualBoundingBoxRight) / 2, (m.actualBoundingBoxAscent - m.actualBoundingBoxDescent - extra) / 2);
        if (extra && w > 0) {
            const y = (h + extra) / 2 - 10;
            paint.lineWidth = 2.8;
            paint.lineCap = 'round';
            paint.beginPath();
            paint.moveTo(-w * .42, y);
            if (finish === 'rise') paint.quadraticCurveTo(w*.20, y+8, w*.49, y-20);
            else if (finish === 'arc') paint.quadraticCurveTo(0, y-27, w*.43, y);
            else if (finish === 'tide') {
                paint.bezierCurveTo(-w*.20,y-24,0,y+14,w*.18,y-3);
                paint.quadraticCurveTo(w*.34,y-16,w*.46,y-10);
            } else if (finish === 'loop') {
                paint.bezierCurveTo(w*.1,y+12,w*.53,y-20,w*.40,y-18);
                paint.quadraticCurveTo(w*.30,y-15,w*.34,y-4);
            } else paint.bezierCurveTo(-w * .12, y - 9, w * .15, finish === 'wave' ? y + 12 : y - 4, w * .48, y - 6);
            if (finish === 'double') {
                paint.moveTo(-w * .25, y + 7);
                paint.lineTo(w * .23, y + 3);
            }
            paint.stroke();
        }
        paint.restore();
        return w > 0 && h > 0;
    }
    function makeGallery(group, families, input, isSignature) {
        const cards = families.map((family, i) => {
            const label = document.createElement('label');
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = group.id;
            radio.value = isSignature ? String(i) : family;
            radio.checked = i === 0;
            const canvas = document.createElement('canvas');
            canvas.width = 360; canvas.height = 116;
            canvas.setAttribute('aria-hidden', 'true');
            const title = document.createElement('span');
            title.textContent = isSignature ? templates[i].title + ' · ' + family : family;
            label.append(radio, canvas, title);
            group.append(label);
            return {family, canvas};
        });
        let version = 0;
        function update() {
            if (group.closest('[hidden]')) return;
            const current = ++version;
            const value = input.value.trim().normalize('NFC') || 'Nguyễn Minh Anh';
            cards.forEach(async ({family, canvas}, i) => {
                try {
                    if (family !== 'Arial') await loadFont(family);
                    if (current === version) paintSample(canvas, value, family, isSignature ? templates[i].finish : 'plain', color.value);
                } catch { canvas.title = 'Chưa tải được phông chữ'; }
            });
        }
        return update;
    }
    const updateTemplates = makeGallery(font, templates.map(t => t.family), name, true);
    const updateCaptionFonts = makeGallery(captionFont, ['Arial', ...fonts], caption, false);
    let ready = false, busy = false, revision = 0;

    function loadFont(family) {
        if (!loaded.has(family)) {
            const dir = family.toLowerCase().replaceAll(' ', '-');
            const faces = ['latin', 'vietnamese'].map(subset => {
                const url = new URL(dir + '/' + subset + '.woff2', handwritingFontBase);
                const options = subset === 'vietnamese' ? {unicodeRange: 'U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB'} : {};
                return new FontFace(family, 'url("' + url.href + '")', options).load();
            });
            loaded.set(family, Promise.all(faces).then(result => result.forEach(face => document.fonts.add(face))).catch(error => {
                loaded.delete(family);
                throw error;
            }));
        }
        return loaded.get(family);
    }
    function buttons() {
        control('download').disabled = !ready || busy;
        control('insert').disabled = !ready || busy;
        control('undo').disabled = control('clear').disabled = busy || activeStroke || !history.length;
    }
    async function refresh() {
        const turn = ++revision;
        const typed = mode === 'type';
        pad.classList.toggle('ink-drawing', !typed);
        control('caption-fields').hidden = !captionOn.checked;
        control('weight-label').hidden = typed;

        ready = false;
        status.textContent = '';

        if (!typed) {
            ready = history.length > 0 && !activeStroke;
        } else if (name.value.trim()) {
            buttons();
            const {family, finish} = templates[Number(selectedFamily(font))];
            try { await loadFont(family); }
            catch { if (turn === revision) status.textContent = 'Không tải được phông chữ. Hãy chọn lại kiểu chữ.'; return; }
            if (turn !== revision) return;
            const value = name.value.trim().normalize('NFC');
            ready = paintSample(typedPad, value, family, finish, color.value);
        }
        const text = captionOn.checked ? caption.value.trim().normalize('NFC') : '';
        if (ready && text && selectedFamily(captionFont) !== 'Arial') {
            ready = false;
            buttons();
            try { await loadFont(selectedFamily(captionFont)); }
            catch { if (turn === revision) status.textContent = 'Không tải được phông họ tên.'; return; }
            if (turn !== revision) return;
            ready = true;
        }
        if (ready) ready = composePreview(text, typed ? typedPad : pad);
        preview.hidden = !ready;
        control('empty').hidden = ready;
        buttons();
    }
    function setTool(erase) {
        erasing = erase;
        pad.classList.toggle('ink-erasing', erase);
        control('pen').setAttribute('aria-pressed', String(!erase));
        control('eraser').setAttribute('aria-pressed', String(erase));
    }
    control('pen').onclick = () => setTool(false);
    control('eraser').onclick = () => setTool(true);
    const context = pad.getContext('2d');
    function applyInk() {
        context.save();
        context.resetTransform();
        context.globalCompositeOperation = 'source-in';
        context.fillStyle = color.value;
        context.fillRect(0, 0, pad.width, pad.height);
        context.restore();
    }
    const swatches = document.querySelectorAll('[data-ink-color]');
    for (const swatch of swatches) {
        swatch.onclick = () => {
            color.value = swatch.dataset.inkColor;
            color.dispatchEvent(new Event('input'));
        };
    }
    function showWidth() {
        control('weight-value').value = weight.value + ' px';
        control('stroke-sample').style.height = weight.value + 'px';
        control('stroke-sample').style.background = color.value;
        weight.setAttribute('aria-valuetext', weight.value + ' pixel');
    }
    weight.addEventListener('input', showWidth);
    showWidth();
    color.addEventListener('input', () => {
        for (const swatch of swatches) swatch.setAttribute('aria-pressed', String(swatch.dataset.inkColor === color.value));
        if (history.length || activeStroke) applyInk();
        strokes.penColor = color.value;
        showWidth();
    });
    function syncCaptionInput() {
        const typed = mode === 'type';
        const host = control(typed ? 'type-caption' : 'name-strip');
        if (caption.parentElement !== host) host.append(caption);
        control('type-caption').hidden = !typed;
        caption.disabled = !captionOn.checked;
        caption.style.fontFamily = '"' + selectedFamily(captionFont) + '", sans-serif';
        caption.style.color = color.value;
    }
    for (const item of [captionOn, captionFont, color]) item.addEventListener('input', syncCaptionInput);
    captionOn.addEventListener('change', () => { if (captionOn.checked) caption.focus(); });
    syncCaptionInput();
    strokes.addEventListener('beginStroke', event => {
        if (busy || mode !== 'draw') { event.preventDefault(); return; }
        activeStroke = true;
        beforeStroke = context.getImageData(0, 0, pad.width, pad.height);
        const strokeScale = pad.width / pad.getBoundingClientRect().width;
        context.setTransform(strokeScale, 0, 0, strokeScale, 0, 0);
        strokes.compositeOperation = erasing ? 'destination-out' : 'source-over';
        strokes.penColor = color.value;
        strokes.minWidth = erasing ? 10 : Number(weight.value) / 5;
        strokes.maxWidth = erasing ? 10 : Number(weight.value) / 2;
        ready = false;
        buttons();
    });
    strokes.addEventListener('endStroke', () => {
        // Keep only the pixels changed by this gesture, including erased intersections.
        const after = context.getImageData(0, 0, pad.width, pad.height);
        const from = new Uint32Array(beforeStroke.data.buffer), to = new Uint32Array(after.data.buffer);
        let left = pad.width, right = -1, top = pad.height, bottom = -1;
        for (let i = 0; i < to.length; i++) {
            if (from[i] === to[i]) continue;
            const x = i % pad.width, y = Math.floor(i / pad.width);
            left = Math.min(left,x); right = Math.max(right,x);
            top = Math.min(top,y); bottom = Math.max(bottom,y);
        }
        if (right >= left) {
            const patch = context.createImageData(right-left+1,bottom-top+1);
            for (let y=0; y<patch.height; y++) {
                const start = ((y+top)*pad.width+left)*4;
                patch.data.set(beforeStroke.data.subarray(start,start+patch.width*4),y*patch.width*4);
            }
            history.push({patch,left,top,color: strokes.penColor});
        }
        beforeStroke = null;
        activeStroke = false;
        refresh();
    });
    control('undo').onclick = () => {
        const last = history.pop();
        if (last) {
            context.putImageData(last.patch,last.left,last.top);
            if (last.color !== color.value) applyInk();
        }
        refresh();
    };
    control('clear').onclick = () => {
        history.length = 0;
        context.resetTransform();
        context.globalCompositeOperation = 'source-over';
        strokes.clear();
        refresh();
    };
    function selectMode(next) {
        if (activeStroke) return;
        mode = next;
        syncCaptionInput();
        for (const id of ['draw','type']) {
            const tab = control(id + '-tab');
            tab.tabIndex = id === mode ? 0 : -1;
            tab.setAttribute('aria-selected', String(id === mode));
            control(id + '-panel').hidden = id !== mode;
        }
        refresh();
        updateTemplates();
    }
    for (const id of ['draw','type']) {
        const tab = control(id + '-tab');
        tab.onclick = () => selectMode(id);
        tab.onkeydown = e => {
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return;
            e.preventDefault();
            const next = e.key === 'Home' ? 'draw' : e.key === 'End' ? 'type' : mode === 'draw' ? 'type' : 'draw';
            selectMode(next);
            control(next + '-tab').focus();
        };
    }
    for (const item of [color, font, name, caption, captionFont, captionOn]) item.addEventListener('input', refresh);
    for (const item of [name, color]) item.addEventListener('input', updateTemplates);
    for (const item of [captionOn, caption, color]) item.addEventListener('input', updateCaptionFonts);
    function trimCanvas(source, margin = 4) {
        const {width, height} = source;
        const pixels = source.getContext('2d').getImageData(0,0,width,height).data;
        let left = width, right = -1, top = height, bottom = -1;
        for (let i = 3; i < pixels.length; i += 4) {
            if (!pixels[i]) continue;
            const p = (i - 3) / 4, x = p % width, y = Math.floor(p / width);
            left = Math.min(left,x); right = Math.max(right,x);
            top = Math.min(top,y); bottom = Math.max(bottom,y);
        }
        if (right < left) return null;
        const out = document.createElement('canvas');
        out.width = right-left+1+2*margin;
        out.height = bottom-top+1+2*margin;
        out.getContext('2d').drawImage(source,margin-left,margin-top);
        return out;
    }
    function composePreview(text, source) {
        const cropped = trimCanvas(source,0);
        if (!cropped) return false;
        const sw = cropped.width, sh = cropped.height;
        const size = Math.max(18, Math.min(58, Math.round(sw / 9)));
        const face = size + 'px "' + selectedFamily(captionFont) + '"';
        const paint = preview.getContext('2d');
        paint.font = face;
        const m = paint.measureText(text);
        const tw = m.actualBoundingBoxLeft + m.actualBoundingBoxRight;
        const scale = Math.min(1, 960 / Math.max(1,tw));
        preview.width = Math.ceil(Math.max(sw, tw * scale)) + 24;
        preview.height = sh + 24 + (text ? Math.ceil((m.actualBoundingBoxAscent + m.actualBoundingBoxDescent) * scale) + 14 : 0);
        paint.drawImage(cropped, (preview.width - sw) / 2, 12);
        if (text) {
            paint.font = face;
            paint.fillStyle = color.value;
            paint.translate(preview.width / 2, sh + 26);
            paint.scale(scale, scale);
            paint.fillText(text, (m.actualBoundingBoxLeft - m.actualBoundingBoxRight) / 2, m.actualBoundingBoxAscent);
        }
        return true;
    }
    async function finish(insert) {
        if (!ready || busy) return;
        busy = true;
        buttons();
        try {
            const blob = await new Promise((resolve, reject) => trimCanvas(preview).toBlob(value => value ? resolve(value) : reject(new Error('Không thể tạo PNG.')), 'image/png'));
            if (insert) {
                status.textContent = 'Đang chèn chữ ký…';
                await insertImage(new File([blob], 'chu-ky.png', {type: 'image/png'}));
                status.textContent = 'Đã chèn ảnh vào chữ ký email.';
            } else {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'chu-ky.png';
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 60000);
            }
        } catch (error) { status.textContent = error.message; }
        finally { busy = false; buttons(); }
    }
    control('download').onclick = () => finish(false);
    control('insert').onclick = () => finish(true);
    refresh();
}

const inkPage = document.querySelector('.ink-page');
async function insertImage(file) {
    const form = new FormData();
    form.append('kind', 'handwriting_url');
    form.append('image', file);
    const response = await fetch(inkPage.dataset.upload, { method: 'POST', headers: {'X-SignEmail-Upload':'brand'}, body: form });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Không thể tải chữ ký lên.');
    const filename = new URL(result.url, location.href).pathname.split('/').pop();
    if (!/^[a-f0-9]{32}\.png$/.test(filename)) throw new Error('Đường dẫn ảnh chữ ký không hợp lệ.');
    if (window.opener && !window.opener.closed) {
        try {
            const delivered = !window.opener.document.dispatchEvent(new CustomEvent('signemail:handwriting', {detail: filename, cancelable: true}));
            if (delivered) { window.opener.focus(); window.close(); return; }
        } catch { /* The originating tab may have navigated to another site. */ }
    }
    location.assign(inkPage.dataset.editor + '#ink=' + filename);
}
if (window.SignaturePad) initHandwriting(insertImage);
else {
    const script = document.createElement('script');
    script.src = document.currentScript.dataset.padFallback;
    script.onload = () => initHandwriting(insertImage);
    script.onerror = () => { document.getElementById('ink-status').textContent = 'Không tải được bảng ký. Vui lòng tải lại trang.'; };
    document.head.append(script);
}
