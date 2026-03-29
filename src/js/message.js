(function () {
    "use strict";

    function clampSpeedLevel(value) {
        var speed = parseInt(value, 10);
        if (isNaN(speed)) {
            return 4;
        }
        if (speed < 1) {
            return 1;
        }
        if (speed > 10) {
            return 10;
        }
        return speed;
    }

    function speedLevelToPixels(speedLevel) {
        return 20 + ((clampSpeedLevel(speedLevel) - 1) * 20);
    }

    document.addEventListener("DOMContentLoaded", function () {
        var modalElement = document.getElementById("messageModal");
        var newButton = document.getElementById("btnMessageNew");
        var modalTitle = document.getElementById("messageModalTitle");
        var messageIdField = document.querySelector("#messageForm input[name=\"message_id\"]");
        var messageField = document.getElementById("messageBody");
        var messageEnglishField = document.getElementById("messageBodyEn");
        var visibleField = document.querySelector("#messageForm input[name=\"is_visible\"]");
        var submitButton = document.querySelector("[data-message-submit]");
        var speedField = document.getElementById("dragSpeed");
        var speedValue = document.getElementById("dragSpeedValue");
        var previewStage = document.getElementById("messagePreviewStage");
        var previewText = document.getElementById("messagePreviewText");
        var animationFrame = null;
        var x = 0;
        var lastTs = 0;
        var messageModal = null;

        if (modalElement && window.appModal) {
            messageModal = window.appModal.getOrCreateInstance(modalElement);
        }

        function openCreateModal() {
            if (!messageModal || !messageIdField || !messageField || !visibleField || !modalTitle || !submitButton) {
                return;
            }
            modalTitle.textContent = "メッセージ追加";
            messageIdField.value = "0";
            messageField.value = "";
            if (messageEnglishField) {
                messageEnglishField.value = "";
            }
            visibleField.checked = true;
            submitButton.textContent = "追加";
            syncText(true);
            messageModal.show();
            window.setTimeout(function () {
                messageField.focus();
            }, 120);
        }

        function openEditModal(trigger) {
            if (!messageModal || !trigger || !messageIdField || !messageField || !visibleField || !modalTitle || !submitButton) {
                return;
            }
            modalTitle.textContent = "メッセージ更新";
            messageIdField.value = trigger.getAttribute("data-message-id") || "0";
            messageField.value = trigger.getAttribute("data-message") || "";
            if (messageEnglishField) {
                messageEnglishField.value = trigger.getAttribute("data-message-e") || "";
            }
            visibleField.checked = (trigger.getAttribute("data-is-visible") || "0") === "1";
            submitButton.textContent = "更新";
            syncText(true);
            messageModal.show();
            window.setTimeout(function () {
                messageField.focus();
            }, 120);
        }

        if (newButton) {
            newButton.addEventListener("click", openCreateModal);
        }

        document.querySelectorAll("[data-open-message-edit]").forEach(function (button) {
            button.addEventListener("click", function () {
                openEditModal(button);
            });
        });

        if (modalElement && modalElement.getAttribute("data-show-on-load") === "1" && messageModal) {
            messageModal.show();
        }

        if (!messageField || !speedField || !speedValue || !previewStage || !previewText) {
            return;
        }

        function previewContent() {
            var raw = String(messageField.value || "").replace(/\s+/g, " ").trim();
            return raw === "" ? "プレビュー" : raw;
        }

        function syncText(resetPosition) {
            previewText.textContent = previewContent();
            speedValue.textContent = String(clampSpeedLevel(speedField.value));
            if (resetPosition) {
                x = previewStage.clientWidth;
                lastTs = 0;
                previewText.style.transform = "translateX(" + x + "px) translateY(-50%)";
            }
        }

        function step(ts) {
            if (!previewStage.isConnected || !previewText.isConnected) {
                if (animationFrame) {
                    window.cancelAnimationFrame(animationFrame);
                }
                return;
            }

            if (!lastTs) {
                lastTs = ts;
            }

            var dt = (ts - lastTs) / 1000;
            lastTs = ts;
            x -= speedLevelToPixels(speedField.value) * dt;
            previewText.style.transform = "translateX(" + x + "px) translateY(-50%)";

            if (x + previewText.offsetWidth < 0) {
                x = previewStage.clientWidth;
                previewText.style.transform = "translateX(" + x + "px) translateY(-50%)";
                lastTs = ts;
            }

            animationFrame = window.requestAnimationFrame(step);
        }

        syncText(true);
        animationFrame = window.requestAnimationFrame(step);

        messageField.addEventListener("input", function () {
            syncText(true);
        });

        speedField.addEventListener("input", function () {
            speedField.value = String(clampSpeedLevel(speedField.value));
            syncText(false);
        });
    });
})();
