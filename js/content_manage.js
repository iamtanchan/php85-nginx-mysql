(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var modalElement = document.getElementById("contentCreateModal");
        var titleInput = document.getElementById("title");
        var targetSelect = document.getElementById("itemStation");
        var slotSelect = document.getElementById("slotNo");
        var contentTypeSelect = document.getElementById("contentType");
        var contentFile = document.getElementById("contentFile");
        var statusElement = document.getElementById("contentCreateStatus");
        var createModal = null;

        function clearStatus() {
            if (statusElement) {
                statusElement.innerHTML = "";
            }
        }

        function dispatchChange(element) {
            if (!element) {
                return;
            }
            element.dispatchEvent(new Event("change", { bubbles: true }));
        }

        function applyTriggerState(trigger) {
            if (!trigger) {
                return;
            }

            if (targetSelect && trigger.hasAttribute("data-target")) {
                targetSelect.value = trigger.getAttribute("data-target") || "";
                dispatchChange(targetSelect);
            }
            if (slotSelect && trigger.hasAttribute("data-slot")) {
                slotSelect.value = trigger.getAttribute("data-slot") || "1";
            }
            if (titleInput) {
                titleInput.value = "";
            }
            if (contentTypeSelect) {
                contentTypeSelect.value = "movie";
                dispatchChange(contentTypeSelect);
            }
            if (contentFile) {
                contentFile.value = "";
            }
            clearStatus();
        }

        if (modalElement && window.appModal) {
            createModal = window.appModal.getOrCreateInstance(modalElement);
        }

        document.querySelectorAll("[data-open-content-modal]").forEach(function (trigger) {
            trigger.addEventListener("click", function (event) {
                if (!createModal) {
                    return;
                }
                event.preventDefault();
                applyTriggerState(trigger);
                createModal.show();
            });
        });

        if (!createModal || !modalElement) {
            return;
        }

        modalElement.addEventListener("app-modal:shown", function () {
            if (titleInput) {
                titleInput.focus();
            }
        });

        if (modalElement.getAttribute("data-show-on-load") === "1") {
            createModal.show();
        }
    });
})();
