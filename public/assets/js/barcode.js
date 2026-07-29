/**
 * SoftNova — Lectura de códigos de barras con pistola (HID teclado).
 * Las pistolas escriben rápido y terminan con Enter.
 */
(function (window, document) {
    'use strict';

    var GAP_MS = 55;
    var MIN_LEN = 3;
    var MAX_SCAN_MS = 800;
    var handlers = [];
    var buffer = '';
    var lastKeyAt = 0;
    var scanStartedAt = 0;
    var enabled = true;

    function lookupUrl() {
        var meta = document.querySelector('meta[name="barcode-lookup-url"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function shouldIgnoreTarget(el) {
        if (!el || !el.tagName) return false;
        var tag = el.tagName.toUpperCase();
        if (tag === 'TEXTAREA') return true;
        if (el.isContentEditable) return true;
        if (tag === 'INPUT') {
            var type = (el.type || 'text').toLowerCase();
            if (type === 'password' || type === 'email' || type === 'number' || type === 'date' || type === 'datetime-local' || type === 'file' || type === 'checkbox' || type === 'radio' || type === 'range' || type === 'color') {
                return true;
            }
        }
        if (el.getAttribute && el.getAttribute('data-barcode-input') === 'true') return true;
        if (el.closest && el.closest('[data-barcode-ignore="true"]')) return true;
        return false;
    }

    function stripFromTarget(el, code) {
        if (!el || !code) return;
        var tag = (el.tagName || '').toUpperCase();
        if (tag !== 'INPUT' && tag !== 'TEXTAREA') return;
        if (typeof el.value !== 'string' || !el.value) return;
        if (el.value.endsWith(code)) {
            el.value = el.value.slice(0, -code.length);
        } else {
            var idx = el.value.lastIndexOf(code);
            if (idx !== -1) {
                el.value = el.value.slice(0, idx) + el.value.slice(idx + code.length);
            }
        }
    }

    function flash(code) {
        var existing = document.getElementById('barcodeScanFlash');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'barcodeScanFlash';
        el.className = 'barcode-scan-flash';
        el.textContent = 'Código: ' + code;
        document.body.appendChild(el);
        setTimeout(function () {
            el.classList.add('is-visible');
        }, 10);
        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () { el.remove(); }, 280);
        }, 1400);
    }

    function emit(code) {
        code = String(code || '').trim();
        if (!code) return;
        flash(code);
        document.dispatchEvent(new CustomEvent('softnova:barcode', { detail: { code: code } }));
        for (var i = 0; i < handlers.length; i++) {
            try {
                if (handlers[i](code) === true) return;
            } catch (err) {
                console.error('Barcode handler error', err);
            }
        }
        defaultAction(code);
    }

    function lookup(code) {
        var url = lookupUrl();
        if (!url) {
            return Promise.resolve(null);
        }
        return fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'code=' + encodeURIComponent(code), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.success && d.product) return d.product;
                return null;
            })
            .catch(function () { return null; });
    }

    function defaultAction(code) {
        var input = document.getElementById('globalSearchInput');
        if (input) {
            input.value = code;
            if (document.getElementById('searchClear')) {
                document.getElementById('searchClear').style.display = 'flex';
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        }
        lookup(code).then(function (p) {
            if (!p) {
                if (typeof showAlert === 'function') showAlert('Código no encontrado: ' + code, 'error');
                return;
            }
            if (typeof showAlert === 'function') {
                showAlert(p.name + ' · Stock: ' + (p.stock != null ? p.stock : '—'), 'success');
            }
        });
    }

    function onKeyDown(e) {
        if (!enabled) return;
        if (e.ctrlKey || e.altKey || e.metaKey) return;

        var now = Date.now();
        var gap = now - lastKeyAt;
        lastKeyAt = now;

        if (shouldIgnoreTarget(e.target)) {
            buffer = '';
            scanStartedAt = 0;
            return;
        }

        if (e.key === 'Enter') {
            var elapsed = scanStartedAt ? (now - scanStartedAt) : 9999;
            if (buffer.length >= MIN_LEN && elapsed <= MAX_SCAN_MS) {
                e.preventDefault();
                e.stopPropagation();
                var code = buffer;
                buffer = '';
                scanStartedAt = 0;
                stripFromTarget(e.target, code);
                emit(code);
            } else {
                buffer = '';
                scanStartedAt = 0;
            }
            return;
        }

        if (e.key.length === 1) {
            if (gap > GAP_MS || !buffer) {
                buffer = '';
                scanStartedAt = now;
            }
            buffer += e.key;
            if (buffer.length > 128) {
                buffer = buffer.slice(-64);
            }
            return;
        }

        if (e.key !== 'Shift') {
            buffer = '';
            scanStartedAt = 0;
        }
    }

    document.addEventListener('keydown', onKeyDown, true);

    window.SoftNovaBarcode = {
        onScan: function (fn) {
            if (typeof fn !== 'function') return function () {};
            handlers.push(fn);
            return function unsubscribe() {
                handlers = handlers.filter(function (h) { return h !== fn; });
            };
        },
        lookup: lookup,
        emit: emit,
        setEnabled: function (v) { enabled = !!v; },
        isEnabled: function () { return enabled; }
    };
})(window, document);
