(function () {
    'use strict';

    function isPostForm(form) {
        return String(form.getAttribute('method') || 'get').toLowerCase() === 'post';
    }

    function buildFormData(form, submitter) {
        var data = new FormData(form);

        if (submitter && submitter.name) {
            data.append(submitter.name, submitter.value || '');

            if (submitter.type === 'image') {
                data.append(submitter.name + '.x', '0');
                data.append(submitter.name + '.y', '0');
            }
        }

        return data;
    }

    function setSubmitterBusy(submitter, busy) {
        if (!submitter) {
            return;
        }

        if (busy) {
            submitter.dataset.ajaxOriginalDisabled = submitter.disabled ? '1' : '0';
            submitter.disabled = true;
            return;
        }

        submitter.disabled = submitter.dataset.ajaxOriginalDisabled === '1';
        delete submitter.dataset.ajaxOriginalDisabled;
    }

    function replaceDocument(html) {
        document.open();
        document.write(html);
        document.close();
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!isPostForm(form) || event.defaultPrevented) {
            return;
        }

        if (form.dataset.ajaxSkip === '1' || form.dataset.ajaxSkip === 'true') {
            return;
        }

        event.preventDefault();

        var submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        if (form.dataset.ajaxSubmitting === '1') {
            return;
        }

        form.dataset.ajaxSubmitting = '1';
        setSubmitterBusy(submitter, true);

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: buildFormData(form, submitter),
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                var contentType = String(response.headers.get('content-type') || '').toLowerCase();

                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(function (payload) {
                        if (payload && typeof payload.redirect === 'string' && payload.redirect !== '') {
                            window.location.assign(payload.redirect);
                            return;
                        }

                        if (payload && payload.reload) {
                            window.location.reload();
                            return;
                        }

                        throw new Error(payload && payload.message ? payload.message : 'AJAX request failed.');
                    });
                }

                return response.text().then(function (html) {
                    if (response.redirected) {
                        window.location.assign(response.url);
                        return;
                    }

                    replaceDocument(html);
                });
            })
            .catch(function (error) {
                window.alert(error && error.message ? error.message : '送信に失敗しました。');
            })
            .finally(function () {
                delete form.dataset.ajaxSubmitting;
                setSubmitterBusy(submitter, false);
            });
    });
})();
