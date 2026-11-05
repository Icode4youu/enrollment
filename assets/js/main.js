// ---------- File upload rows (bindable for dynamically loaded content) ----------
function initUploads(root) {
    (root || document).querySelectorAll('input[type=file]').forEach(function (inp) {
        if (inp.dataset.bound) return;
        inp.dataset.bound = '1';
        inp.addEventListener('change', function () {
            var row = inp.closest('.upload-row');
            var tag = row ? row.querySelector('.chosen-file') : null;
            var file = inp.files[0];
            if (tag) tag.textContent = file ? file.name + ' (' + formatSize(file.size) + ')' : '';
            if (row) row.classList.toggle('has-file', !!file);
        });
    });
    (root || document).querySelectorAll('.upload-row').forEach(function (row) {
        if (row.dataset.dnd) return;
        row.dataset.dnd = '1';
        var inp = row.querySelector('input[type=file]');
        if (!inp) return;
        ['dragenter', 'dragover'].forEach(function (ev) {
            row.addEventListener(ev, function (e) { e.preventDefault(); row.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            row.addEventListener(ev, function (e) { e.preventDefault(); row.classList.remove('dragover'); });
        });
        row.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length) {
                inp.files = e.dataTransfer.files;
                inp.dispatchEvent(new Event('change'));
            }
        });
    });
}
initUploads(document);

function formatSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024)    return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
}

// ---------- Inline step view (loads enrollment steps inside the dashboard) ----------
var dashContent, stepView, stepBody, stepUrl;

function openInline(url) {
    dashContent = document.getElementById('dashContent');
    stepView    = document.getElementById('stepView');
    stepBody    = document.getElementById('stepBody');
    if (!stepView) { window.location.href = url; return; }
    dashContent.hidden = true;
    stepView.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    loadStep(url);
}

async function loadStep(url) {
    stepUrl = url;
    stepBody.innerHTML = '<div class="step-loading"></div>';
    try {
        var res = await fetch(url, { credentials: 'same-origin' });
        var html = await res.text();
        stepUrl = res.url || url;
        injectStep(html);
    } catch (e) {
        stepBody.innerHTML = '<div class="alert alert-error">Could not load this step. Please try again.</div>';
    }
}

function injectStep(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var main = doc.querySelector('main');
    stepBody.innerHTML = main ? main.innerHTML : html;
    // Scripts inside injected HTML are inert — re-create them so they run
    stepBody.querySelectorAll('script').forEach(function (s) {
        var n = document.createElement('script');
        if (s.src) n.src = s.src; else n.textContent = s.textContent;
        s.replaceWith(n);
    });
    initUploads(stepBody);
    bindStep(stepBody);
}

function bindStep(root) {
    // Form posts go through fetch — PHP redirects are followed automatically,
    // so every step (uploads, payment, blocking) stays inside the dashboard.
    root.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            stepBody.innerHTML = '<div class="step-loading"></div>';
            fetch(stepUrl, {
                method: 'POST',
                body: new FormData(f),
                credentials: 'same-origin'
            }).then(function (r) {
                stepUrl = r.url;
                // A step may redirect outside the wizard (dashboard, done page)
                // — that must be a full navigation, not an inline injection.
                if (/dashboard\.php|done\.php/.test(r.url)) {
                    window.location.href = r.url;
                    return null;
                }
                return r.text();
            }).then(function (html) { if (html !== null) injectStep(html); });
        });
    });
    // Step links load inline; dashboard/logout/home navigate normally
    root.querySelectorAll('a').forEach(function (a) {
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return;
        a.addEventListener('click', function (e) {
            if (/dashboard\.php|logout\.php|index\.php/.test(href)) {
                e.preventDefault();
                window.location.href = href;
            } else if (/\.php/.test(href)) {
                e.preventDefault();
                var m = document.querySelector('.modal-overlay.show');
                if (m) m.classList.remove('show');
                loadStep(href);
            }
        });
    });
}

// Any link marked data-inline opens inside the dashboard instead of navigating
document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-inline]');
    if (!a) return;
    e.preventDefault();
    openInline(a.getAttribute('href'));
});

// ---------- Notifications dropdown ----------
var notifBell = document.getElementById('notifBell');
var notifPanel = document.getElementById('notifPanel');
if (notifBell && notifPanel) {
    notifBell.addEventListener('click', function (e) {
        e.stopPropagation();
        var opening = !notifPanel.classList.contains('show');
        notifPanel.classList.toggle('show');
        if (opening && document.getElementById('notifBadge')) {
            fetch((window.BASE_URL || '') + '/notifications_mark.php', { method: 'POST', credentials: 'same-origin' })
                .then(function () {
                    var b = document.getElementById('notifBadge');
                    if (b) b.remove();
                    notifPanel.querySelectorAll('.notif-item.unread').forEach(function (i) { i.classList.remove('unread'); });
                });
        }
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.notif-wrap')) notifPanel.classList.remove('show');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') notifPanel.classList.remove('show');
    });
}

// ---------- Dark mode toggle ----------
var themeBtn = document.getElementById('themeToggle');
if (themeBtn) {
    themeBtn.addEventListener('click', function () {
        var next = document.documentElement.dataset.theme === 'dark' ? '' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('seait-theme', next); } catch (e) {}
    });
}

// ---------- Count-up animation for stat numbers ----------
document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.dataset.count, 10) || 0;
    var dur = 900, t0 = null;
    function tick(ts) {
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
});

// Modal helpers
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
