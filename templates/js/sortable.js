/**
 * Dependency-free drag & drop row sorting.
 *
 * ILIAS 10 no longer ships jQuery UI, so the previous `$(...).sortable()`
 * implementation stopped working. This is a small vanilla replacement that
 * reorders the sortable items of a container via the mouse. Because the
 * hidden `position[]` inputs live inside the rows, reordering the DOM keeps
 * the POST order in sync automatically and the existing `saveSorting`
 * handlers work unchanged.
 *
 * It also exposes a minimal `window.Sortable` constructor so the inline
 * `new Sortable(...)` usage in tpl.multiple_input.html keeps working.
 */
(function () {
    "use strict";

    function Sortable(container, options) {
        if (!container || container.__fsxSortable) {
            return;
        }
        options = options || {};
        this.container = container;
        this.itemSelector = options.itemSelector || options.draggable || ".fsxSortable";
        this.handle = options.handle || null;
        this.dragging = null;

        container.__fsxSortable = this;
        this._onMouseDown = this._onMouseDown.bind(this);
        this._onMouseMove = this._onMouseMove.bind(this);
        this._onMouseUp = this._onMouseUp.bind(this);
        container.addEventListener("mousedown", this._onMouseDown);
    }

    Sortable.prototype._items = function () {
        var self = this;
        return Array.prototype.filter.call(this.container.children, function (el) {
            return el.matches && el.matches(self.itemSelector);
        });
    };

    Sortable.prototype._onMouseDown = function (e) {
        if (e.button !== 0) {
            return;
        }
        var item = e.target.closest(this.itemSelector);
        if (!item || item.parentNode !== this.container) {
            return;
        }
        if (this.handle) {
            var handle = e.target.closest(this.handle);
            if (!handle || !item.contains(handle)) {
                return;
            }
        } else if (e.target.closest("input, textarea, select, a, button")) {
            // do not hijack clicks on interactive elements when no handle is set
            return;
        }

        e.preventDefault();
        this.dragging = item;
        item.classList.add("fsx-dragging");
        document.body.classList.add("fsx-sorting");
        document.addEventListener("mousemove", this._onMouseMove);
        document.addEventListener("mouseup", this._onMouseUp);
    };

    Sortable.prototype._onMouseMove = function (e) {
        if (!this.dragging) {
            return;
        }
        var dragging = this.dragging;
        var siblings = this._items().filter(function (el) {
            return el !== dragging;
        });

        for (var i = 0; i < siblings.length; i++) {
            var rect = siblings[i].getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                this.container.insertBefore(dragging, siblings[i]);
                return;
            }
        }
        var last = siblings[siblings.length - 1];
        if (last) {
            last.after(dragging);
        }
    };

    Sortable.prototype._onMouseUp = function () {
        document.removeEventListener("mousemove", this._onMouseMove);
        document.removeEventListener("mouseup", this._onMouseUp);
        document.body.classList.remove("fsx-sorting");
        if (this.dragging) {
            this.dragging.classList.remove("fsx-dragging");
            this.dragging = null;
        }
    };

    window.Sortable = Sortable;

    var style = document.createElement("style");
    style.textContent =
        'tr.fsxSortable img[src*="move.png"] { cursor: move; }' +
        "body.fsx-sorting { user-select: none; -webkit-user-select: none; }" +
        "tr.fsx-dragging { opacity: 0.5; }";
    document.head.appendChild(style);

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("table tbody").forEach(function (tbody) {
            if (tbody.querySelector(":scope > tr.fsxSortable")) {
                new Sortable(tbody, {
                    itemSelector: "tr.fsxSortable",
                    handle: 'img[src*="move.png"]'
                });
            }
        });
    });
})();
