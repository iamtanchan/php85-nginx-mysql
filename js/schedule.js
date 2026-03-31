(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modalElement = document.getElementById('scheduleCreateModal');
        if (!modalElement || !window.appModal || typeof window.appModal.getOrCreateInstance !== 'function') {
            return;
        }

        var modalInstance = window.appModal.getOrCreateInstance(modalElement);
        var timeInput = document.getElementById('scheduleCreateDepartureTime');

        function openModal(event) {
            if (event) {
                event.preventDefault();
            }
            modalInstance.show();
        }

        document.querySelectorAll('[data-open-schedule-create]').forEach(function (trigger) {
            trigger.addEventListener('click', openModal);
        });

        modalElement.addEventListener('app-modal:shown', function () {
            if (!timeInput) {
                return;
            }
            window.setTimeout(function () {
                timeInput.focus();
            }, 120);
        });

        if (modalElement.getAttribute('data-show-on-load') === '1') {
            modalInstance.show();
        }
    });
})();
