document.addEventListener('DOMContentLoaded', function () {
    var modal = null;
    var titleElement = null;
    var videoElement = null;

    function ensureModal() {
        if (modal) {
            return;
        }

        modal = document.createElement('div');
        modal.className = 'content-preview-modal fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md';
        modal.innerHTML =
            '<div class="content-preview-dialog w-full max-w-5xl overflow-hidden rounded-[32px] border border-white/12 bg-slate-950/95 shadow-[0_30px_120px_rgba(15,23,42,0.45)]" role="dialog" aria-modal="true" aria-label="動画プレビュー">' +
                '<div class="content-preview-header flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">' +
                    '<div class="content-preview-title text-2xl font-bold text-white">動画プレビュー</div>' +
                    '<button type="button" class="content-preview-close inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-xl font-semibold text-white transition hover:bg-white/15" aria-label="閉じる">×</button>' +
                '</div>' +
                '<div class="content-preview-body bg-black p-4 sm:p-6">' +
                    '<video class="content-preview-video aspect-video w-full rounded-[24px] bg-black" controls playsinline preload="metadata"></video>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        titleElement = modal.querySelector('.content-preview-title');
        videoElement = modal.querySelector('.content-preview-video');

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.closest('.content-preview-close')) {
                closePreview();
            }
        });
    }

    function openPreview(src, title) {
        ensureModal();
        if (!videoElement) {
            return;
        }

        titleElement.textContent = title || '動画プレビュー';
        videoElement.pause();
        videoElement.src = src;
        videoElement.currentTime = 0;
        modal.classList.add('is-open');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('content-preview-open');
        document.body.style.overflow = 'hidden';

        var playPromise = videoElement.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {
                return null;
            });
        }
    }

    function closePreview() {
        if (!modal || !videoElement) {
            return;
        }

        videoElement.pause();
        videoElement.removeAttribute('src');
        videoElement.load();
        modal.classList.remove('is-open');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        document.body.classList.remove('content-preview-open');
        document.body.style.overflow = '';
    }

    function previewTriggerFromEventTarget(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }
        return target.closest('.js-content-preview');
    }

    document.addEventListener('click', function (event) {
        var trigger = previewTriggerFromEventTarget(event.target);
        if (!trigger || trigger.getAttribute('data-preview-type') !== 'movie') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        openPreview(
            trigger.getAttribute('data-preview-src') || '',
            trigger.getAttribute('data-preview-title') || ''
        );
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePreview();
        }
    });
});
