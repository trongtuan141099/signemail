(() => {
    'use strict';

    const endpoint = document.body.dataset.activityUrl;
    const page = document.body.dataset.activityPage;
    const picked = new Set();
    function record(event, entity) {
        if (!endpoint || !entity) return;
        fetch(endpoint, {
            method: 'POST',
            body: new URLSearchParams({ event, entity }),
            headers: { 'X-SignEmail-Event': '1' },
            keepalive: true
        }).catch(() => {});
    }
    record('view', page);
    document.addEventListener('signemail:template', (event) => {
        const id = String(event.detail);
        if (picked.has(id)) return;
        picked.add(id);
        record('select', id);
    });

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    for (const carousel of document.querySelectorAll('[data-carousel]')) {
        const images = Array.from(carousel.querySelectorAll('.partner-media img'));
        const controls = carousel.querySelector('.partner-controls');
        const pause = controls.querySelector('[data-slide="pause"]');
        const count = controls.querySelector('[data-slide-count]');
        let current = 0;
        let visible = !('IntersectionObserver' in window);
        let paused = reduced.matches;

        function show(index) {
            images[current].hidden = true;
            current = (index + images.length) % images.length;
            images[current].hidden = false;
            count.textContent = (current + 1) + ' / ' + images.length;
        }

        function updatePause() {
            if (!pause) return;
            pause.textContent = paused ? '▶' : 'Ⅱ';
            pause.setAttribute('aria-label', paused ? 'Tiếp tục chuyển ảnh' : 'Tạm dừng chuyển ảnh');
        }

        controls.hidden = false;
        controls.querySelector('[data-slide="prev"]').addEventListener('click', () => show(current - 1));
        controls.querySelector('[data-slide="next"]').addEventListener('click', () => show(current + 1));
        if (pause) {
            pause.addEventListener('click', () => {
                paused = !paused;
                updatePause();
            });
            reduced.addEventListener('change', (event) => {
                paused = event.matches;
                updatePause();
            });
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; }, { threshold: .2 }).observe(carousel);
            }
            updatePause();
            window.setInterval(() => {
                if (!paused && visible && !document.hidden && !carousel.matches(':hover, :focus-within')) show(current + 1);
            }, 5000);
        }
    }
})();
