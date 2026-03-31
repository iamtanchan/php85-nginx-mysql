(function ($) {
    'use strict';

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function normalizeTime(rawValue) {
        var value = (rawValue || '').trim();
        if (/^\d{4}$/.test(value)) {
            value = value.slice(0, 2) + ':' + value.slice(2);
        }
        if (!/^\d{1,2}:\d{2}$/.test(value)) {
            return '';
        }
        var parts = value.split(':');
        var hour = parseInt(parts[0], 10);
        var minute = parseInt(parts[1], 10);
        if (Number.isNaN(hour) || Number.isNaN(minute)) {
            return '';
        }
        if (hour < 0 || hour > 23 || minute < 0 || minute > 59) {
            return '';
        }
        return pad2(hour) + ':' + pad2(minute);
    }

    function isValidBoardingOption(value) {
        return value === 15 || value === 10 || value === 5;
    }

    function isValidBlinkOption(value) {
        return !Number.isNaN(value) && value >= 0 && value <= 10;
    }

    function updateClock() {
        var now = new Date();
        $('#timenow').text(pad2(now.getHours()) + ':' + pad2(now.getMinutes()));
    }

    function postJson(url, data, onSuccess, onError) {
        $.ajax({
            type: 'POST',
            url: url,
            dataType: 'json',
            data: data,
            success: function (response) {
                if (response && response.ok === false) {
                    if (typeof onError === 'function') {
                        onError(response);
                        return;
                    }
                    alert(response.message || '処理に失敗しました');
                    return;
                }
                onSuccess(response || {});
            },
            error: function (xhr) {
                var message = '通信に失敗しました';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                if (typeof onError === 'function') {
                    onError({
                        ok: false,
                        message: message
                    });
                    return;
                }
                alert(message);
            }
        });
    }

    function requestDeleteConfirmation(message, onConfirm) {
        if (typeof window.appConfirmDialog !== 'function') {
            if (window.confirm(message)) {
                onConfirm();
            }
            return;
        }

        window.appConfirmDialog({
            message: message,
            confirmText: '削除する',
            confirmButtonClass: 'adm-btn adm-btn-danger'
        }).then(function (confirmed) {
            if (confirmed) {
                onConfirm();
            }
        });
    }

    function getRowValues($row) {
        return {
            time: normalizeTime($row.find('.field-time').val()),
            shipId: parseInt($row.find('.field-ship').val(), 10),
            destinationId: parseInt($row.find('.field-destination').val(), 10),
            badgeId: parseInt($row.find('.field-badge').val(), 10),
            boardingValue: parseInt($row.find('.field-boarding').val(), 10),
            blinkValue: parseInt($row.find('.field-blink').val(), 10)
        };
    }

    $(function () {
        var stationId = parseInt($('#station').text(), 10) || 0;
        var $dialSelect = $('#dialSelect');
        var $status = $('#timetableStatus');
        var $saveForm = $('#saveForm');
        var $createModalElement = $('#ttCreateModal');
        var $createForm = $('#ttCreateForm');
        var $createStatus = $('#ttCreateStatus');
        var $createSubmit = $('#ttCreateSubmit');
        var $createTimeField = $('#ttCreateTime');
        var rowFieldSelector = '.field-time, .field-ship, .field-destination, .field-badge, .field-boarding, .field-blink';
        var hasPendingSeasonSelection = false;
        var createModal = null;

        if ($createModalElement.length && window.appModal) {
            createModal = window.appModal.getOrCreateInstance($createModalElement[0]);
        }

        function alertClasses(type) {
            var base = 'rounded-[24px] border px-5 py-4 text-sm font-medium shadow-[0_12px_30px_rgba(15,23,42,0.06)]';
            if (type === 'error') {
                return base + ' border-rose-200 bg-rose-50 text-rose-800';
            }
            return base + ' border-emerald-200 bg-emerald-50 text-emerald-800';
        }

        function renderStatus(type, message) {
            if (!$status.length) {
                return;
            }
            if (!message) {
                $status.empty();
                return;
            }
            $status.html(
                $('<div>')
                    .addClass(alertClasses(type || 'success'))
                    .attr('role', type === 'error' ? 'alert' : 'status')
                    .text(message)
            );
        }
        function getPersistedRows() {
            return $('.tt-row').not('.tt-row-empty');
        }
        function renderCreateStatus(type, message) {
            if (!$createStatus.length) {
                return;
            }
            if (!message) {
                $createStatus.empty();
                return;
            }
            $createStatus.html(
                $('<div>')
                    .addClass(alertClasses(type || 'success'))
                    .attr('role', type === 'error' ? 'alert' : 'status')
                    .text(message)
            );
        }
        function resetCreateForm() {
            if (!$createForm.length) {
                return;
            }
            $createForm.find('.field-time').val('');
            $createForm.find('.field-ship').val('0');
            $createForm.find('.field-destination').val('0');
            $createForm.find('.field-badge').val('0');
            $createForm.find('.field-boarding').val('10');
            $createForm.find('.field-blink').val('5');
            $createForm.data('submitting', false);
            $createSubmit.prop('disabled', false);
            renderCreateStatus('', '');
        }
        function openCreateModal() {
            if (!createModal) {
                return;
            }
            resetCreateForm();
            createModal.show();
        }
        function closeCreateModal() {
            if (!createModal) {
                return;
            }
            createModal.hide();
        }
        function getSelectedSeasonId() {
            return parseInt($('#saveSeasonId').val(), 10) || 0;
        }
        function hasPendingRowChanges() {
            return getPersistedRows().filter(function () {
                return $(this).data('dirty') === true;
            }).length > 0;
        }
        function syncSaveButtonState() {
            var isSubmitting = $saveForm.data('submitting') === true;
            $('#saveButton').prop('disabled', isSubmitting || !(hasPendingSeasonSelection || hasPendingRowChanges()));
        }
        function setPendingSeasonSelection(isDirty) {
            hasPendingSeasonSelection = !!isDirty && getSelectedSeasonId() > 0;
            syncSaveButtonState();
        }
        function isPreviewMode() {
            return getSelectedSeasonId() > 0;
        }
        function reportRowError(options, message, useAlert) {
            if (typeof options.onError === 'function') {
                options.onError({
                    ok: false,
                    message: message
                });
            }
            if (useAlert) {
                alert(message);
            }
        }
        function validateBoardingAndBlink(values, options, useAlert) {
            if (!isValidBoardingOption(values.boardingValue)) {
                reportRowError(options, '乗船案内は 15分前 / 10分前 / 5分前 から選択してください。', useAlert);
                return false;
            }
            if (!isValidBlinkOption(values.blinkValue)) {
                reportRowError(options, '点灯時間は 10分前〜0分前 の範囲で選択してください。', useAlert);
                return false;
            }
            return true;
        }
        function buildRowPayload(values, extraData) {
            return $.extend({}, extraData || {}, {
                station_id: stationId,
                time: values.time,
                ship_id: values.shipId,
                destination_id: values.destinationId,
                badge_id: Number.isNaN(values.badgeId) ? 0 : values.badgeId,
                boarding_minutes: values.boardingValue,
                blink_minutes: values.blinkValue
            });
        }
        function handleRowSaveSuccess($row, stateKey, options, nextSeasonId, shouldRefresh) {
            $row.data(stateKey, false);
            $row.data('dirty', false);
            if (isPreviewMode()) {
                setPendingSeasonSelection(false);
            }
            if (shouldRefresh) {
                refreshTableData(nextSeasonId, false, options.onSuccess);
                return;
            }
            if (typeof options.onSuccess === 'function') {
                options.onSuccess();
            }
            syncSaveButtonState();
        }
        function handleRowSaveError($row, stateKey, options, response) {
            $row.data(stateKey, false);
            if (typeof options.onError === 'function') {
                options.onError(response || {});
                return;
            }
            renderStatus('error', response && response.message ? response.message : '保存に失敗しました。');
        }
        function validateCreateValues(values, options) {
            if (!values.time || !values.shipId || !values.destinationId) {
                reportRowError(options, '出発時刻・艇名・行先を入力してください。', false);
                return false;
            }
            if (!validateBoardingAndBlink(values, options, false)) {
                return false;
            }
            return true;
        }
        function createRow(values, onSuccess, onError) {
            if (isPreviewMode()) {
                postJson('cgi/schedule_row_create.php', buildRowPayload(values, {
                    season_id: getSelectedSeasonId()
                }), onSuccess, onError);
                return;
            }

            postJson('cgi/timetable_row_create.php', buildRowPayload(values), onSuccess, onError);
        }
        function setCreateSubmitting(isSubmitting) {
            if (!$createForm.length) {
                return;
            }
            $createForm.data('submitting', !!isSubmitting);
            $createSubmit.prop('disabled', !!isSubmitting);
        }
        function saveCreateForm() {
            if (!$createForm.length || $createForm.data('submitting') === true) {
                return;
            }

            var values = getRowValues($createForm);
            renderCreateStatus('', '');
            if (!validateCreateValues(values, {
                onError: function (response) {
                    renderCreateStatus('error', response && response.message ? response.message : '登録に失敗しました。');
                }
            })) {
                return;
            }

            setCreateSubmitting(true);
            createRow(values, function () {
                var seasonId = isPreviewMode() ? String(getSelectedSeasonId()) : '';
                setCreateSubmitting(false);
                if (isPreviewMode()) {
                    setPendingSeasonSelection(false);
                }
                closeCreateModal();
                refreshTableData(seasonId, false, function () {
                    renderStatus('success', '行を追加しました。');
                });
            }, function (response) {
                setCreateSubmitting(false);
                renderCreateStatus(response && response.status_type ? response.status_type : 'error', response && response.message ? response.message : '登録に失敗しました。');
            });
        }

        updateClock();
        setInterval(updateClock, 1000);

        function saveRow($row, options) {
            options = options || {};
            var rowId = parseInt($row.data('row-id'), 10) || 0;
            if (rowId <= 0 || $row.data('saving') === true) {
                return;
            }

            var values = getRowValues($row);
            if (!values.time) {
                reportRowError(options, '出発時刻は HH:MM 形式で入力してください。', true);
                return;
            }

            $row.data('saving', true);
            if (!validateBoardingAndBlink(values, options, true)) {
                $row.data('saving', false);
                return;
            }

            if (isPreviewMode()) {
                postJson('cgi/schedule_row_update.php', buildRowPayload(values, {
                    season_id: getSelectedSeasonId(),
                    row_id: rowId
                }), function () {
                    handleRowSaveSuccess($row, 'saving', options, String(getSelectedSeasonId()), options.refreshAfterSave !== false);
                }, function (response) {
                    handleRowSaveError($row, 'saving', options, response);
                });
                return;
            }

            postJson('cgi/timetable_row_update.php', buildRowPayload(values, {
                row_id: rowId
            }), function () {
                handleRowSaveSuccess($row, 'saving', options, '', options.refreshAfterSave !== false);
                renderStatus('', '');
            }, function (response) {
                handleRowSaveError($row, 'saving', options, response);
            });
        }

        function validateRowBeforeSave($row) {
            if (!$row.length) {
                return '';
            }

            var values = getRowValues($row);
            if (!values.time) {
                return '出発時刻は HH:MM 形式で入力してください。';
            }

            if (!isValidBoardingOption(values.boardingValue)) {
                return '乗船案内は 15分前 / 10分前 / 5分前 から選択してください。';
            }
            if (!isValidBlinkOption(values.blinkValue)) {
                return '点灯時間は 10分前〜0分前 の範囲で選択してください。';
            }

            return '';
        }

        function saveDirtyRows(onSuccess, onError) {
            var rows = getDirtyRows();
            var hasSavedRows = false;
            var validationMessage = '';

            rows.some(function ($row) {
                validationMessage = validateRowBeforeSave($row);
                return validationMessage !== '';
            });

            if (validationMessage !== '') {
                onError({
                    ok: false,
                    message: validationMessage
                });
                return;
            }

            function next() {
                if (rows.length === 0) {
                    onSuccess(hasSavedRows);
                    return;
                }

                saveRow(rows.shift(), {
                    refreshAfterSave: false,
                    onSuccess: function () {
                        hasSavedRows = true;
                        next();
                    },
                    onError: onError
                });
            }

            next();
        }

        function getDirtyRows() {
            var rows = [];

            getPersistedRows().each(function () {
                var $row = $(this);
                if ($row.data('dirty') === true) {
                    rows.push($row);
                }
            });

            return rows;
        }

        function bindRowActions() {
            $('#btnAddRow').off('click').on('click', function () {
                if ($(this).prop('disabled')) {
                    return;
                }
                if (hasPendingRowChanges()) {
                    renderStatus('error', '既存の変更を保存してから新規登録してください。');
                    return;
                }
                openCreateModal();
            });

            getPersistedRows().find('.btn-delete').off('click').on('click', function () {
                var $row = $(this).closest('.tt-row');
                var rowId = parseInt($row.data('row-id'), 10) || 0;
                if (rowId <= 0 || $(this).prop('disabled')) {
                    return;
                }
                requestDeleteConfirmation('この行を削除しますか?', function () {
                    if (isPreviewMode()) {
                        postJson('cgi/schedule_row_delete.php', {
                            station_id: stationId,
                            season_id: getSelectedSeasonId(),
                            row_id: rowId
                        }, function () {
                            refreshTableData(String(getSelectedSeasonId()), false);
                        });
                        return;
                    }

                    postJson('cgi/timetable_row_delete.php', {
                        station_id: stationId,
                        row_id: rowId
                    }, function () {
                        refreshTableData('');
                    });
                });
            });

            getPersistedRows().find(rowFieldSelector)
                .off('input.previewDirty change.previewDirty')
                .on('input.previewDirty change.previewDirty', function () {
                    var $row = $(this).closest('.tt-row');
                    if (!$row.length) {
                        return;
                    }
                    $row.data('dirty', true);
                    syncSaveButtonState();
                });

            getPersistedRows().find('.field-time')
                .off('keydown.manualSave')
                .on('keydown.manualSave', function (event) {
                    if (event.key !== 'Enter') {
                        return;
                    }
                    event.preventDefault();
                    saveRow($(this).closest('.tt-row'));
                });
        }

        function refreshTableData(seasonId, pendingSeasonSelectionAfterRefresh, onComplete) {
            var requestData = {
                s: stationId
            };
            if (seasonId !== '' && seasonId !== null && typeof seasonId !== 'undefined') {
                requestData.season_id = seasonId;
            }
            $.ajax({
                type: 'GET',
                url: 'timetable.php',
                data: requestData,
                dataType: 'html',
                success: function (html) {
                    var $nextPage = $('<div>').append($.parseHTML(html));
                    var $nextWrap = $nextPage.find('.tt-table-wrap').first();
                    var $currentWrap = $('.tt-table-wrap').first();
                    var nextSeasonId = $nextPage.find('#saveSeasonId').val() || '';
                    var addDisabled = $nextPage.find('#btnAddRow').prop('disabled');

                    if (!$nextWrap.length || !$currentWrap.length) {
                        alert('時刻表データの更新に失敗しました');
                        return;
                    }

                    $currentWrap.replaceWith($nextWrap);
                    $('#saveSeasonId').val(nextSeasonId);
                    $('#btnAddRow').prop('disabled', addDisabled);

                    hasPendingSeasonSelection = !!pendingSeasonSelectionAfterRefresh && (parseInt(nextSeasonId, 10) || 0) > 0;
                    syncSaveButtonState();
                    bindRowActions();
                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                },
                error: function () {
                    alert('時刻表データの取得に失敗しました');
                }
            });
        }

        if ($dialSelect.length) {
            $dialSelect.on('change', function () {
                renderStatus('', '');
                refreshTableData($(this).val(), ($(this).val() || '') !== '');
            });
        }

        if ($createForm.length) {
            $createForm.on('submit', function (event) {
                event.preventDefault();
                saveCreateForm();
            });
        }

        if ($createModalElement.length) {
            $createModalElement.on('app-modal:shown', function () {
                if ($createTimeField.length) {
                    $createTimeField.trigger('focus');
                }
            });

            $createModalElement.on('app-modal:hidden', function () {
                resetCreateForm();
            });
        }

        if ($saveForm.length) {
            $saveForm.on('submit', function (event) {
                var seasonId = getSelectedSeasonId();

                event.preventDefault();

                if (seasonId <= 0) {
                    if (!hasPendingRowChanges()) {
                        renderStatus('error', 'ダイヤ期間を選択してください。');
                        return;
                    }
                }
                if (!$saveForm.data('submitting') && !(hasPendingSeasonSelection || hasPendingRowChanges())) {
                    syncSaveButtonState();
                    return;
                }
                if ($saveForm.data('submitting') === true) {
                    return;
                }
                $saveForm.data('submitting', true);
                syncSaveButtonState();

                saveDirtyRows(function (hasSavedRows) {
                    if (getSelectedSeasonId() > 0 && hasPendingSeasonSelection) {
                        postJson('cgi/timetable_save_from_schedule.php', {
                            station_id: stationId,
                            season_id: getSelectedSeasonId()
                        }, function (response) {
                            $saveForm.data('submitting', false);
                            setPendingSeasonSelection(false);
                            if (hasSavedRows) {
                                refreshTableData(String(getSelectedSeasonId()), false, function () {
                                    renderStatus(response.status_type || 'success', response.message || '保存しました。');
                                });
                                return;
                            }
                            renderStatus(response.status_type || 'success', response.message || '保存しました。');
                        }, function (response) {
                            $saveForm.data('submitting', false);
                            syncSaveButtonState();
                            renderStatus(response.status_type || 'error', response.message || '登録に失敗しました。');
                        });
                        return;
                    }

                    $saveForm.data('submitting', false);
                    if (hasSavedRows) {
                        refreshTableData(getSelectedSeasonId() > 0 ? String(getSelectedSeasonId()) : '', false, function () {
                            renderStatus('success', '変更を保存しました。');
                        });
                        return;
                    }
                    setPendingSeasonSelection(false);
                    renderStatus('success', '変更を保存しました。');
                }, function (response) {
                    $saveForm.data('submitting', false);
                    syncSaveButtonState();
                    renderStatus(response.status_type || 'error', response.message || '登録に失敗しました。');
                });
            });
        }

        syncSaveButtonState();
        bindRowActions();

        $('#btnDisplayPage').on('click', function (event) {
            event.preventDefault();
            var href = $(this).attr('href') || '';
            if (!href || href === '#') {
                href = 'display.php?id=' + stationId;
            }

            postJson('cgi/savetimetable_dsp.php', {
                id: stationId
            }, function () {
                window.open(href, '_blank');
            });
        });
    });
})(jQuery);
