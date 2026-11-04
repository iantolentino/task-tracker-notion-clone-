// Light/dark theme. Loaded in <head> so the saved theme applies before the page paints.
(function () {
    var root = document.documentElement;
    var saved = null;
    try { saved = localStorage.getItem('mmts-theme'); } catch (err) {}
    if (saved !== 'light' && saved !== 'dark') {
        saved = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    root.setAttribute('data-theme', saved);

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
        if (!button) return;
        event.preventDefault();
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('mmts-theme', next); } catch (err) {}
    });
})();
