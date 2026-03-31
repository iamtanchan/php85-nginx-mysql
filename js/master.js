(function () {
    'use strict';

    function setToggleState(button, enabled) {
        var switchElement = button.querySelector('[data-language-toggle-switch]');
        var knobElement = button.querySelector('[data-language-toggle-knob]');
        var labelElement = button.querySelector('[data-language-toggle-label]');

        button.className = enabled ? button.dataset.classOn : button.dataset.classOff;
        button.setAttribute('aria-label', enabled ? 'OFF に切り替え' : 'ON に切り替え');

        if (switchElement) {
            switchElement.className = enabled ? switchElement.dataset.classOn : switchElement.dataset.classOff;
        }
        if (knobElement) {
            knobElement.className = enabled ? knobElement.dataset.classOn : knobElement.dataset.classOff;
        }
        if (labelElement) {
            labelElement.textContent = enabled ? 'ON' : 'OFF';
        }
    }

    function showMessage(container, text, isError) {
        if (!container) {
            return;
        }

        container.textContent = text;
        container.classList.remove('hidden');
        container.classList.toggle('border-emerald-200', !isError);
        container.classList.toggle('bg-emerald-50', !isError);
        container.classList.toggle('text-emerald-800', !isError);
        container.classList.toggle('border-rose-200', !!isError);
        container.classList.toggle('bg-rose-50', !!isError);
        container.classList.toggle('text-rose-800', !!isError);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-language-toggle-form]');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var button = form.querySelector('[data-language-toggle-button]');
        var messageContainer = document.getElementById('masterStatusMessage');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (button.disabled) {
                return;
            }

            button.disabled = true;

            fetch('cgi/toggle_display_language.php', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.ok) {
                        throw new Error(payload && payload.message ? payload.message : 'Request failed');
                    }

                    setToggleState(button, !!payload.enabled);
                    showMessage(messageContainer, payload.message || '', false);
                })
                .catch(function (error) {
                    showMessage(messageContainer, error && error.message ? error.message : '設定の更新に失敗しました。', true);
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });
})();
