(function () {
    'use strict';
    var opts = window.WPIWM_Protection || {};

    // Right-click protection on images
    if (opts.rightClick) {
        document.addEventListener('contextmenu', function (e) {
            if (e.target && e.target.tagName === 'IMG') {
                e.preventDefault();
                return false;
            }
        });
    }

    // DevTools detection (experimental)
    if (opts.devTools) {
        var devtoolsOpen = false;
        var threshold    = 160;
        function check() {
            var widthDiff  = window.outerWidth  - window.innerWidth  > threshold;
            var heightDiff = window.outerHeight - window.innerHeight > threshold;
            devtoolsOpen   = widthDiff || heightDiff;
            var imgs = document.querySelectorAll('img');
            imgs.forEach(function (img) {
                img.style.visibility = devtoolsOpen ? 'hidden' : '';
            });
        }
        setInterval(check, 1000);
    }
})();
