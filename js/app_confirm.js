(function () {
    'use strict';

    var modalElement = null;
    var modalInstance = null;
    var messageElement = null;
    var confirmButton = null;
    var cancelButton = null;
    var pendingResolve = null;
    var pendingResult = false;

    function resolveButtonClasses(className) {
        var value = String(className || '');
        var base = 'inline-flex items-center justify-center gap-2 rounded-full border font-semibold tracking-[0.01em] transition duration-200 ease-out focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none disabled:translate-y-0 min-h-[2.9rem] px-5 py-3 text-sm';

        if (value.indexOf('adm-btn-danger') !== -1) {
            return base + ' border-rose-200 bg-rose-50 text-rose-700 shadow-md shadow-rose-200/60 hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-100';
        }

        if (value.indexOf('adm-btn-soft') !== -1) {
            return base + ' border-blue-100 bg-blue-50 text-blue-700 shadow-sm shadow-blue-100/80 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-100';
        }

        if (value.indexOf('adm-btn-pink') !== -1 || value.indexOf('accent') !== -1) {
            return base + ' border-blue-600 bg-blue-600 text-white shadow-lg shadow-blue-600/20 hover:-translate-y-0.5 hover:bg-blue-500 hover:shadow-xl hover:shadow-blue-600/25';
        }

        return base + ' border-slate-200 bg-white/90 text-slate-700 shadow-md shadow-slate-950/5 hover:-translate-y-0.5 hover:border-blue-200 hover:text-blue-700 hover:shadow-lg hover:shadow-slate-950/10';
    }

    function ensureModal() {
        if (modalElement) {
            return true;
        }

        modalElement = document.getElementById('appConfirmModal');
        if (!modalElement || !window.appModal || typeof window.appModal.getOrCreateInstance !== 'function') {
            return false;
        }

        messageElement = modalElement.querySelector('[data-app-confirm-message]');
        confirmButton = modalElement.querySelector('[data-app-confirm-submit]');
        cancelButton = modalElement.querySelector('[data-app-confirm-cancel]');
        modalInstance = window.appModal.getOrCreateInstance(modalElement);

        confirmButton.addEventListener('click', function () {
            pendingResult = true;
            modalInstance.hide();
        });

        cancelButton.addEventListener('click', function () {
            pendingResult = false;
        });

        modalElement.addEventListener('app-modal:hidden', function () {
            if (!pendingResolve) {
                return;
            }

            var resolve = pendingResolve;
            var result = pendingResult;
            pendingResolve = null;
            pendingResult = false;
            resolve(result);
        });

        return true;
    }

    function openConfirmDialog(options) {
        var config = options || {};
        var message = config.message || '';

        if (!ensureModal()) {
            return Promise.resolve(window.confirm(message || 'この操作を実行しますか？'));
        }

        if (pendingResolve) {
            pendingResolve(false);
            pendingResolve = null;
        }

        pendingResult = false;
        messageElement.textContent = message;
        cancelButton.textContent = config.cancelText || 'キャンセル';
        confirmButton.textContent = config.confirmText || '実行する';
        confirmButton.className = resolveButtonClasses(config.confirmButtonClass || 'adm-btn adm-btn-danger');

        modalInstance.show();

        return new Promise(function (resolve) {
            pendingResolve = resolve;
        });
    }

    function submitConfirmedForm(form, submitter) {
        form.dataset.confirmBypass = '1';

        if (typeof form.requestSubmit === 'function' && submitter) {
            form.requestSubmit(submitter);
            return;
        }

        if (submitter && submitter.name) {
            var shadowField = document.createElement('input');
            shadowField.type = 'hidden';
            shadowField.name = submitter.name;
            shadowField.value = submitter.value;
            form.appendChild(shadowField);
        }

        form.submit();
    }

    function bindFormConfirmation() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-message')) {
                return;
            }

            if (form.dataset.confirmBypass === '1') {
                form.dataset.confirmBypass = '0';
                return;
            }

            event.preventDefault();

            openConfirmDialog({
                message: form.getAttribute('data-confirm-message') || '',
                confirmText: form.getAttribute('data-confirm-button') || '実行する',
                cancelText: form.getAttribute('data-confirm-cancel') || 'キャンセル',
                confirmButtonClass: form.getAttribute('data-confirm-button-class') || 'adm-btn adm-btn-danger'
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }
                submitConfirmedForm(form, event.submitter || null);
            });
        }, true);
    }

    window.appConfirmDialog = openConfirmDialog;
    bindFormConfirmation();
})();
