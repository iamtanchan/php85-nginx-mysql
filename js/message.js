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

    function setToggleState(button, enabled) {
        var switchElement = button.querySelector('[data-message-toggle-switch]');
        var knobElement = button.querySelector('[data-message-toggle-knob]');
        var labelElement = button.querySelector('[data-message-toggle-label]');

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

    function showStatusMessage(container, text, isError) {
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

    document.addEventListener("DOMContentLoaded", function () {
        var modalElement = document.getElementById("messageModal");
        var newButton = document.getElementById("btnMessageNew");
        var statusMessage = document.getElementById("messageStatusMessage");
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

        document.querySelectorAll("[data-message-toggle-form]").forEach(function (form) {
            var button = form.querySelector("[data-message-toggle-button]");
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            form.addEventListener("submit", function (event) {
                event.preventDefault();

                if (button.disabled) {
                    return;
                }

                button.disabled = true;

                fetch("cgi/message_toggle_visibility.php", {
                    method: "POST",
                    body: new FormData(form),
                    credentials: "same-origin",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Request failed");
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || !payload.ok) {
                            throw new Error(payload && payload.message ? payload.message : "Request failed");
                        }

                        var enabled = !!payload.enabled;
                        setToggleState(button, enabled);

                        var messageId = form.querySelector('input[name="message_id"]');
                        var editTrigger = messageId
                            ? document.querySelector('[data-message-edit-trigger][data-message-id="' + messageId.value + '"]')
                            : null;
                        if (editTrigger) {
                            editTrigger.setAttribute("data-is-visible", enabled ? "1" : "0");
                        }

                        showStatusMessage(statusMessage, payload.message || "", false);
                    })
                    .catch(function (error) {
                        showStatusMessage(statusMessage, error && error.message ? error.message : "表示設定の更新に失敗しました。", true);
                    })
                    .finally(function () {
                        button.disabled = false;
                    });
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
