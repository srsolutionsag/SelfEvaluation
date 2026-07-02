/**
 * Print helper for the feedback / results view, dependency-free.
 *
 * ILIAS 11 no longer ships jQuery. Temporarily narrow the layout for a cleaner
 * print, then restore it. Exposed globally because it is triggered via an
 * inline onclick="printFeedback()" set in FeedbackChartGUI.
 */
window.printFeedback = function () {
    var body = document.body;
    var mainbar = document.querySelector('.il-maincontrols-mainbar');
    var originalBodyWidth = body.style.width;
    var originalMainbarWidth = mainbar ? mainbar.style.width : '';

    function restore() {
        body.style.width = originalBodyWidth;
        if (mainbar) {
            mainbar.style.width = originalMainbarWidth;
        }
    }

    window.onafterprint = restore;

    // Make sure the mainbar slates are not too big while printing.
    if (mainbar) {
        mainbar.style.width = '80px';
    }
    body.style.width = '800px';

    setTimeout(function () {
        window.print();
        // Fallback for browsers that do not fire onafterprint.
        setTimeout(restore, 1000);
    }, 500);
};
