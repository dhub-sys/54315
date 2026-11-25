/* Warranty Manager – Sales Demo: start/end, countdown, tabs, guided OTP popups, sample data. No dependencies. */
(function () {
    'use strict';
    if (typeof window.TWD === 'undefined') return;
    var CFG = TWD.demo || {};
    var root = document.querySelector('.twd-demo');
    if (!root) return;

    function T(s) { return (TWD.i18n && TWD.i18n[s]) || s; }
    function post(action, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('action', 'twd_' + action);
        return fetch(TWD.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { success: false, data: { message: T('Δεν υπάρχει σύνδεση. Δοκιμάστε ξανά.') } }; });
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }

    // ------------------------------------------------------------ popups
    var open = null;
    function popup(icon, title, html, button) {
        return new Promise(function (resolve) {
            if (open) open.remove();
            var m = document.createElement('div');
            m.className = 'twd-modal';
            m.setAttribute('role', 'dialog');
            m.setAttribute('aria-modal', 'true');
            m.innerHTML = '<div class="twd-modal-box tw-app"><div class="twd-modal-icon" aria-hidden="true">' + icon + '</div><h4></h4><div class="twd-modal-body"></div>' +
                '<p class="twd-modal-actions"><button type="button" class="tw-btn"></button></p></div>';
            m.querySelector('h4').textContent = title;
            m.querySelector('.twd-modal-body').innerHTML = html;
            var b = m.querySelector('button');
            b.textContent = button || T('Συνέχεια');
            function close() { m.remove(); open = null; document.removeEventListener('keydown', key); resolve(); }
            function key(e) { if (e.key === 'Escape' || e.key === 'Enter') { e.preventDefault(); close(); } }
            b.addEventListener('click', close);
            document.addEventListener('keydown', key);
            document.body.appendChild(m);
            open = m;
            b.focus();
        });
    }

    var OTP_TEXT = {
        register: [T('Επαλήθευση email με κωδικό μίας χρήσης'),
            T('Σε αυτό το σημείο ο πελάτης λαμβάνει στο email του έναν 6ψήφιο κωδικό, που ισχύει για λίγα λεπτά. Πληκτρολογώντας τον αποδεικνύει ότι το email είναι δικό του: μόνο ο κάτοχος του email μπορεί να ενεργοποιήσει την εγγύηση, και κάθε προϊόν παίρνει δικό του κωδικό.')],
        login: [T('Είσοδος χωρίς κωδικό πρόσβασης'),
            T('Στις «Οι εγγυήσεις μου» ο πελάτης δεν χρειάζεται κωδικό πρόσβασης: δίνει το email του και λαμβάνει έναν 6ψήφιο κωδικό μίας χρήσης. Δεν υπάρχουν κωδικοί να ξεχαστούν ή να διαρρεύσουν, και η σύνδεση λήγει αυτόματα.')],
        erase: [T('Επιβεβαίωση διαγραφής με κωδικό'),
            T('Για ασφάλεια, η διαγραφή των στοιχείων επιβεβαιώνεται με κωδικό που στέλνεται στο email του πελάτη. Έτσι κανείς άλλος δεν μπορεί να διαγράψει τα στοιχεία του.')]
    };
    var OK_TEXT = {
        register: [T('Το email επαληθεύτηκε'), T('Ο κωδικός ήταν σωστός και ο πελάτης συνεχίζει με τα στοιχεία του. Αν είναι ήδη πελάτης, τα στοιχεία κατόχου συμπληρώνονται αυτόματα από την προηγούμενη καταχώρησή του. Στην επίδειξη μπορείτε να πατήσετε «Αυτόματη συμπλήρωση στοιχείων».')],
        login: [T('Ο πελάτης συνδέθηκε'), T('Βλέπει τα προϊόντα του, τη λήξη της εγγύησης και το πρόγραμμα Service. Από εδώ υποβάλλει το Service με την απόδειξη του τεχνικού, ώστε η εγγύηση να συνεχίσει να ισχύει. Στην επίδειξη η υποβολή Service είναι ανοιχτή από τώρα.')]
    };

    /** Shows the explanation, then types the code into the digit boxes (the form submits itself when full). */
    function otp(inp, code, kind) {
        if (!inp || !code) return;
        var t = OTP_TEXT[kind] || OTP_TEXT.register;
        popup('✉️', t[0], '<p>' + esc(t[1]) + '</p><p>' + esc(T('Στην επίδειξη δεν στέλνεται email. Ο κωδικός που θα λάμβανε ο πελάτης είναι:')) + '</p><div class="twd-modal-code">' + esc(code) + '</div>',
            T('Συμπλήρωση κωδικού')).then(function () {
            var wrap = inp.nextElementSibling && inp.nextElementSibling.classList.contains('tw-otp-boxes') ? inp.nextElementSibling : null;
            var boxes = wrap ? wrap.querySelectorAll('.tw-otp-box') : [];
            if (!boxes.length) { inp.value = code; if (inp.form.requestSubmit) inp.form.requestSubmit(); return; }
            var i = 0;
            (function next() {
                if (i >= boxes.length) return;
                boxes[i].focus();
                boxes[i].value = code.charAt(i);
                boxes[i].dispatchEvent(new Event('input', { bubbles: true }));
                i++;
                setTimeout(next, 170);
            })();
        });
    }
    function verified(kind) {
        var t = OK_TEXT[kind];
        if (t) popup('✅', t[0], '<p>' + esc(t[1]) + '</p>');
    }

    // ------------------------------------------------------------ session
    var ending = false;
    function expired() {
        if (ending) return;
        ending = true;
        popup('⏱', T('Η επίδειξη ολοκληρώθηκε'), '<p>' + esc(T('Ο χρόνος της επίδειξης έληξε. Όλα όσα καταχωρήθηκαν διαγράφηκαν και ο σειριακός είναι ξανά διαθέσιμος για νέα επίδειξη.')) + '</p>', T('Νέα επίδειξη'))
            .then(function () { location.reload(); });
        post('demo_end', {});
        setTimeout(function () { location.reload(); }, 15000);
    }

    var start = root.querySelector('[data-demo-start]');
    if (start) {
        start.addEventListener('click', function () {
            start.disabled = true;
            var box = root.querySelector('.tw-alert');
            post('demo_start', { page: root.getAttribute('data-page'), _ajax_nonce: root.getAttribute('data-nonce') }).then(function (r) {
                if (r.success) { location.hash = ''; location.reload(); return; }
                start.disabled = false;
                box.textContent = (r.data && r.data.message) || T('Σφάλμα επικοινωνίας με τον server.');
                box.className = 'tw-alert tw-alert--error';
                box.hidden = false;
            });
        });
    }

    var end = root.querySelector('[data-demo-end]');
    if (end) {
        end.addEventListener('click', function () {
            if (!window.confirm(T('Να τερματιστεί η επίδειξη; Όλα όσα καταχωρήθηκαν θα διαγραφούν.'))) return;
            ending = true;
            post('demo_end', {}).then(function () { location.hash = ''; location.reload(); });
        });
    }

    var left = root.querySelector('[data-demo-left]');
    if (left) {
        var until = Date.now() + parseInt(left.getAttribute('data-left'), 10) * 1000;
        var timeBox = left.parentNode;
        var tick = function () {
            var l = Math.max(0, Math.round((until - Date.now()) / 1000));
            left.textContent = Math.floor(l / 60) + ':' + ('0' + (l % 60)).slice(-2);
            timeBox.classList.toggle('is-low', l <= 60);
            if (l === 0) { clearInterval(timer); expired(); }
        };
        var timer = setInterval(tick, 1000);
        tick();
    }

    // ------------------------------------------------------------ tabs (#twd-my = «Οι εγγυήσεις μου»)
    var tabs = root.querySelectorAll('[data-tab]');
    function showTab(name) {
        tabs.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-tab') === name); b.setAttribute('aria-selected', b.getAttribute('data-tab') === name ? 'true' : 'false'); });
        root.querySelectorAll('[data-tab-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-tab-panel') !== name; });
    }
    tabs.forEach(function (b) {
        b.addEventListener('click', function () {
            var name = b.getAttribute('data-tab');
            if (history.replaceState) history.replaceState(null, '', name === 'my' ? '#twd-my' : location.pathname + location.search);
            showTab(name);
        });
    });
    function fromHash() { showTab(location.hash === '#twd-my' ? 'my' : 'register'); }
    window.addEventListener('hashchange', fromHash);
    if (tabs.length) fromHash();
    // «Μετάβαση στις εγγυήσεις μου» on the completion step points to #twd-my of this page.
    root.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href$="#twd-my"]');
        if (!a) return;
        e.preventDefault();
        location.hash = 'twd-my';
        fromHash();
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // ------------------------------------------------------------ sample data («Αυτόματη συμπλήρωση»)
    var NAMES = ['Γιώργος Παπαδόπουλος', 'Μαρία Κωνσταντίνου', 'Νίκος Αλεξίου', 'Ελένη Γεωργίου', 'Κώστας Δημητρίου', 'Αννα Νικολάου'];
    var STREETS = ['Λεωφ. Κηφισίας 120', 'Ερμού 45', 'Αγ. Δημητρίου 18', 'Τσιμισκή 77', 'Πατησίων 210', 'Σόλωνος 9'];
    var CITIES = [['Αθήνα', '11526'], ['Θεσσαλονίκη', '54624'], ['Πάτρα', '26221'], ['Ηράκλειο', '71202'], ['Λάρισα', '41222']];
    function pick(a) { return a[Math.floor(Math.random() * a.length)]; }
    function digits(n) { var s = ''; for (var i = 0; i < n; i++) s += Math.floor(Math.random() * 10); return s; }
    function daysAgo(n) { var d = new Date(); d.setDate(d.getDate() - n); return d.toISOString().slice(0, 10); }
    function set(form, name, value) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return;
        if (el.tagName === 'SELECT' && !Array.prototype.some.call(el.options, function (o) { return o.value === value; })) return;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function samplePdf(title) {
        var text = 'BT /F1 18 Tf 60 760 Td (' + title + ') Tj ET';
        var objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' + text.length + ' >>\nstream\n' + text + '\nendstream', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        var out = '%PDF-1.4\n', offs = [];
        objs.forEach(function (o, i) { offs.push(out.length); out += (i + 1) + ' 0 obj\n' + o + '\nendobj\n'; });
        var x = out.length;
        out += 'xref\n0 ' + (objs.length + 1) + '\n0000000000 65535 f \n' + offs.map(function (o) { return ('000000000' + o).slice(-10) + ' 00000 n \n'; }).join('');
        out += 'trailer\n<< /Size ' + (objs.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + x + '\n%%EOF';
        return out;
    }
    function attach(form, name, filename, title) {
        var inp = form.querySelector('input[type=file][name="' + name + '"]');
        if (!inp || typeof DataTransfer === 'undefined') return;
        try {
            var dt = new DataTransfer();
            dt.items.add(new File([samplePdf(title)], filename, { type: 'application/pdf' }));
            inp.files = dt.files;
            inp.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {}
    }
    function flash(form) {
        form.querySelectorAll('input:not([type=hidden]):not([type=file]):not([type=checkbox]):not([readonly])').forEach(function (el) {
            if (!el.value) return;
            el.style.transition = 'background-color .6s'; el.style.backgroundColor = '#fef3c7';
            setTimeout(function () { el.style.backgroundColor = ''; }, 900);
        });
    }
    var afm = CFG.afm || ['', ''];

    root.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-demo-fill]');
        if (!b) return;
        e.preventDefault();
        var form = b.closest('form');
        if (!form) return;
        if (b.getAttribute('data-demo-fill') === 'details') {
            var c = pick(CITIES);
            set(form, 'customer_name', pick(NAMES));
            set(form, 'phone_mobile', '69' + digits(8));
            set(form, 'phone_landline', '21' + digits(8));
            set(form, 'address', pick(STREETS));
            set(form, 'postcode', c[1]);
            set(form, 'city', c[0]);
            set(form, 'partner_country', 'GR');
            set(form, 'partner_vat', afm[0]);
            set(form, 'partner_email', 'synergatis@example.com');
            set(form, 'invoice_number', 'ΤΔΑ-' + digits(5));
            set(form, 'purchase_date', CFG.today || daysAgo(0));
            attach(form, 'invoice_file', 'parastatiko-agoras.pdf', 'Demo - Parastatiko agoras');
        } else {
            set(form, 'performed_date', CFG.today || daysAgo(0));
            set(form, 'technician_country', 'GR');
            set(form, 'technician_vat', afm[1]);
            set(form, 'technician', 'Τεχνική Υποστήριξη Α.Ε.');
            set(form, 'receipt_number', 'ΑΠΥ-' + digits(4));
            set(form, 'receipt_date', CFG.today || daysAgo(0));
            set(form, 'notes', 'Ετήσιος έλεγχος και καθαρισμός');
            var g = form.querySelector('[name="genuine_parts"]'); if (g) g.checked = true;
            attach(form, 'receipt_file', 'apodeixi-service.pdf', 'Demo - Apodeixi Service');
        }
        flash(form);
    });

    window.TWDDemo = { popup: popup, otp: otp, verified: verified, expired: expired };
})();
