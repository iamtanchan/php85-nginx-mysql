document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('createDialModal');
    if (!modal) {
        return;
    }

    var modalInstance = window.appModal ? window.appModal.getOrCreateInstance(modal) : null;
    if (!modalInstance) {
        return;
    }
    var input = document.getElementById('newDial');
    var title = document.getElementById('createDialTitle');
    var modeInput = document.getElementById('scheduleNameModalAction');
    var rowIdInput = document.getElementById('scheduleNameRowId');
    var startDateInput = document.getElementById('seasonStartDate');
    var endDateInput = document.getElementById('seasonEndDate');
    var submitButton = modal.querySelector('[data-modal-submit]');
    var modalContent = modal.querySelector('.app-modal-card');
    var createTitle = modal.getAttribute('data-create-title') || (title ? title.textContent : '');
    var editTitle = modal.getAttribute('data-edit-title') || createTitle;
    var createSubmit = modal.getAttribute('data-create-submit') || (submitButton ? submitButton.textContent : '');
    var editSubmit = modal.getAttribute('data-edit-submit') || createSubmit;

    function clearModalError() {
        modal.querySelectorAll('[data-modal-error]').forEach(function (errorElement) {
            errorElement.remove();
        });
    }

    function focusInput() {
        if (!input) {
            return;
        }
        window.setTimeout(function () {
            input.focus();
            input.select();
        }, 120);
    }

    function setCreateMode() {
        if (modeInput) {
            modeInput.value = 'create';
        }
        if (rowIdInput) {
            rowIdInput.value = '0';
        }
        if (title) {
            title.textContent = createTitle;
        }
        if (submitButton) {
            submitButton.textContent = createSubmit;
        }
        if (modeInput && input) {
            input.value = '';
        }
        if (startDateInput) {
            startDateInput.value = '';
        }
        if (endDateInput) {
            endDateInput.value = '';
        }
    }

    function setEditMode(rowId, rowName, startDate, endDate) {
        if (modeInput) {
            modeInput.value = 'update';
        }
        if (rowIdInput) {
            rowIdInput.value = rowId || '0';
        }
        if (title) {
            title.textContent = editTitle;
        }
        if (submitButton) {
            submitButton.textContent = editSubmit;
        }
        if (input) {
            input.value = rowName || '';
        }
        if (startDateInput) {
            startDateInput.value = startDate || '';
        }
        if (endDateInput) {
            endDateInput.value = endDate || '';
        }
    }

    function openModal(event) {
        if (event) {
            event.preventDefault();
        }
        clearModalError();
        modalInstance.show();
    }

    function closeModal(event) {
        if (event) {
            event.preventDefault();
        }
        clearModalError();
        modalInstance.hide();
    }

    document.querySelectorAll('[data-open-create]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            setCreateMode();
            openModal(event);
        });
    });

    document.querySelectorAll('[data-open-edit]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            setEditMode(
                trigger.getAttribute('data-row-id'),
                trigger.getAttribute('data-row-name'),
                trigger.getAttribute('data-row-start-date'),
                trigger.getAttribute('data-row-end-date')
            );
            openModal(event);
        });
    });

    document.querySelectorAll('[data-close-create]').forEach(function (trigger) {
        trigger.addEventListener('click', closeModal);
    });

    if (modalContent && modal.getAttribute('data-clear-error-on-leave') === '1') {
        modalContent.addEventListener('mouseleave', clearModalError);
    }

    modal.addEventListener('app-modal:shown', focusInput);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            modalInstance.hide();
        }
    });

    if (modal.getAttribute('data-show-on-load') === '1') {
        modalInstance.show();
    } else if (!modeInput || modeInput.value !== 'update') {
        setCreateMode();
    }
});
