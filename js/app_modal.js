(function () {
    "use strict";

    var modalRegistry = new WeakMap();

    function getFocusableElements(root) {
        return Array.prototype.slice.call(
            root.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter(function (element) {
            return !element.hasAttribute("hidden") && element.offsetParent !== null;
        });
    }

    function triggerModalEvent(element, type) {
        element.dispatchEvent(new CustomEvent(type, { bubbles: true }));
        if (window.jQuery) {
            window.jQuery(element).trigger(type);
        }
    }

    function syncBodyState() {
        var hasOpenModal = document.querySelector(".app-modal.is-open") !== null;
        document.body.classList.toggle("app-modal-open", hasOpenModal);
        document.body.style.overflow = hasOpenModal ? "hidden" : "";
    }

    function AppModal(element) {
        if (!(element instanceof HTMLElement)) {
            throw new Error("AppModal requires an element");
        }
        if (modalRegistry.has(element)) {
            return modalRegistry.get(element);
        }

        this.element = element;
        this.panel = element.querySelector(".app-modal-card");
        this.isOpen = false;
        this.lastFocusedElement = null;

        this.handleClick = this.handleClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);

        element.addEventListener("click", this.handleClick);
        this.isOpen = element.classList.contains("is-open") && element.getAttribute("aria-hidden") === "false";
        element.hidden = !this.isOpen;
        element.setAttribute("aria-hidden", this.isOpen ? "false" : "true");
        element.classList.toggle("hidden", !this.isOpen);
        element.classList.toggle("flex", this.isOpen);
        modalRegistry.set(element, this);
    }

    AppModal.prototype.handleClick = function (event) {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        if (event.target === this.element || event.target.closest("[data-modal-close]")) {
            event.preventDefault();
            this.hide();
        }
    };

    AppModal.prototype.handleKeydown = function (event) {
        if (!this.isOpen) {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            this.hide();
            return;
        }

        if (event.key !== "Tab") {
            return;
        }

        var focusable = getFocusableElements(this.element);
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    AppModal.prototype.show = function () {
        if (this.isOpen) {
            return;
        }

        this.lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        triggerModalEvent(this.element, "app-modal:show");
        this.element.hidden = false;
        this.element.setAttribute("aria-hidden", "false");
        this.element.classList.remove("hidden");
        this.element.classList.add("flex");
        this.element.classList.add("is-open");
        this.isOpen = true;
        document.addEventListener("keydown", this.handleKeydown, true);
        syncBodyState();

        var focusable = getFocusableElements(this.element);
        if (focusable.length > 0) {
            focusable[0].focus();
        } else if (this.panel) {
            this.panel.setAttribute("tabindex", "-1");
            this.panel.focus();
        }

        triggerModalEvent(this.element, "app-modal:shown");
    };

    AppModal.prototype.hide = function () {
        if (!this.isOpen) {
            return;
        }

        triggerModalEvent(this.element, "app-modal:hide");
        this.element.classList.remove("is-open");
        this.element.setAttribute("aria-hidden", "true");
        this.element.classList.remove("flex");
        this.element.classList.add("hidden");
        this.element.hidden = true;
        this.isOpen = false;
        document.removeEventListener("keydown", this.handleKeydown, true);
        syncBodyState();

        if (this.lastFocusedElement) {
            this.lastFocusedElement.focus();
        }

        triggerModalEvent(this.element, "app-modal:hidden");
    };

    function getOrCreateInstance(element) {
        if (modalRegistry.has(element)) {
            return modalRegistry.get(element);
        }
        return new AppModal(element);
    }

    window.AppModal = AppModal;
    window.appModal = {
        getOrCreateInstance: getOrCreateInstance
    };
})();
