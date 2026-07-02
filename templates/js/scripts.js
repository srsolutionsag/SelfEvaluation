/**
 * Clicking anywhere in a matrix option cell selects its radio input.
 *
 * Dependency-free replacement for the former jQuery `ul.matrix li` handler
 * (the markup is now `.matrix-row-input` table rows). ILIAS 11 no longer ships
 * jQuery.
 */
document.addEventListener('click', function (e) {
    var cell = e.target.closest('.matrix-row-input td');
    if (!cell) {
        return;
    }
    var radio = cell.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
    }
});
