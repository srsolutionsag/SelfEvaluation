/**
 * Player matrix-question layout helpers, dependency-free.
 *
 * ILIAS 11 no longer ships jQuery, so the previous jQuery/Bootstrap-tooltip
 * implementation could not run. This vanilla replacement:
 *  - truncates overflowing scale-unit header labels to a short form; the full
 *    label stays available through the native `title` tooltip already present
 *    on the element (see tpl.matrix_header.html).
 *  - aligns each matrix radio row height with its question-text height so the
 *    columns line up.
 */
(function () {
    "use strict";

    function updateScaleUnits() {
        document.querySelectorAll(".scale-units td div").forEach(function (div) {
            var full = div.getAttribute("title") || div.dataset.fullTitle || div.textContent;
            div.dataset.fullTitle = full;
            div.textContent = full;
            if (div.scrollWidth > div.clientWidth + 1) {
                div.textContent = full.substring(0, 3) + "...";
            }
        });
    }

    function scaleMatrix() {
        document.querySelectorAll(".matrix-row-input").forEach(function (row) {
            var block = row.closest(".block-question");
            if (!block) {
                return;
            }
            var question = block.querySelector(".question-text");
            if (question) {
                row.style.height = question.offsetHeight + "px";
            }
        });
    }

    function run() {
        updateScaleUnits();
        scaleMatrix();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", run);
    } else {
        run();
    }
    window.addEventListener("load", run);
    window.addEventListener("resize", run);
})();
