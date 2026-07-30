// SoftNova - Caja-POS
'use strict';

(function () {
    // Toggle resumen admin (fuera del panel POS)
    (function initStatsToggle() {
        var btn = document.getElementById('cajaStatsToggle');
        var body = document.getElementById('cajaStatsBody') || document.getElementById('cajaStatsGrid');
        var section = document.getElementById('cajaStatsSection');
        if (!btn || !body || !section) return;
        var key = 'seri_caja_stats_hidden';
        function apply(hidden) {
            body.style.display = hidden ? 'none' : '';
            section.setAttribute('data-collapsed', hidden ? '1' : '0');
            btn.textContent = hidden ? 'Ver resumen' : 'Ocultar';
            try { localStorage.setItem(key, hidden ? '1' : '0'); } catch (e) {}
        }
        var saved = '0';
        try { saved = localStorage.getItem(key) || '0'; } catch (e) {}
        apply(saved === '1');
        btn.addEventListener('click', function () {
            apply(section.getAttribute('data-collapsed') !== '1');
        });
    })();

    var panel = document.getElementById('posPanel');
    if (!panel) return;

    var searchUrl = panel.getAttribute('data-search-url');
    var saleUrl = panel.getAttribute('data-sale-url');
    var csrf = panel.getAttribute('data-csrf');
    var symbol = panel.getAttribute('data-symbol') || '$';
    var decimals = parseInt(panel.getAttribute('data-decimals') || '0', 10);
    var prefix = panel.getAttribute('data-prefix') || 'FAC-';
    var invoicePreviewBase = prefix + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-XXXX';
    var taxRate = parseFloat(panel.getAttribute('data-tax-rate') || '0') || 0;
    var recentKey = 'seri_pos_recent_products';
    var offlineKey = 'seri_pos_offline_queue';

    var items = [];
    var searchTimer = null;
    var lastQuery = '';
    var paying = false;
    var flushingOffline = false;

    var elSearch = document.getElementById('posProductSearch');
    var elResults = document.getElementById('posSearchResults');
    var elBody = document.getElementById('posItemsBody');
    var elTotal = document.getElementById('posTotal');
    var elSubtotal = document.getElementById('posSubtotal');
    var elSubtotalLine = document.getElementById('posSubtotalLine');
    var elDiscountAmt = document.getElementById('posDiscountAmt');
    var elDiscountLine = document.getElementById('posDiscountLine');
    var elTaxAmt = document.getElementById('posTaxAmt');
    var elTaxLine = document.getElementById('posTaxLine');
    var elDiscountPct = document.getElementById('posDiscountPercent');
    var elCount = document.getElementById('posItemCount');
    var elPayBtn = document.getElementById('posPayBtn');
    var elPayBar = document.getElementById('posPayBar');
    var elClock = document.getElementById('posClock');
    var elClear = document.getElementById('posSearchClear');
    var elNewCustomer = document.getElementById('posNewCustomerBtn');
    var elRecentList = document.getElementById('posRecentList');
    var elRecentBlock = document.getElementById('posRecentBlock');

    function money(n) {
        var x = Number(n) || 0;
        return symbol + ' ' + x.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function tick() {
        if (!elClock) return;
        elClock.textContent = new Date().toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' });
    }
    tick();
    setInterval(tick, 30000);

    function loadRecent() {
        try {
            var raw = localStorage.getItem(recentKey);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function saveRecent(p) {
        if (!p || !p.id) return;
        var list = loadRecent().filter(function (x) { return String(x.id) !== String(p.id); });
        list.unshift({
            id: p.id,
            name: p.name,
            code: p.code || '',
            sale_price: p.sale_price,
            stock: p.stock
        });
        list = list.slice(0, 8);
        try { localStorage.setItem(recentKey, JSON.stringify(list)); } catch (e) {}
        renderRecent();
    }

    function renderRecent() {
        if (!elRecentList) return;
        var list = loadRecent();
        if (!list.length) {
            if (elRecentBlock) elRecentBlock.style.display = 'none';
            elRecentList.innerHTML = '';
            return;
        }
        if (elRecentBlock) elRecentBlock.style.display = '';
        elRecentList.innerHTML = '';
        list.forEach(function (p) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'pos-recent-chip';
            row.title = 'Agregar ' + (p.name || '');
            row.innerHTML = '<span>' + esc((p.code ? p.code + ' · ' : '') + (p.name || '')) + '</span>';
            row.addEventListener('click', function () {
                // Refrescar stock vía búsqueda si hay código
                var q = p.code || p.name || '';
                if (!q) {
                    addProduct(p, 1);
                    return;
                }
                fetch(searchUrl + '&q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        var products = (d && d.products) ? d.products : [];
                        var exact = products.find(function (x) { return String(x.id) === String(p.id); }) || products[0] || p;
                        if (addProduct(exact, 1) && typeof showAlert === 'function') {
                            showAlert(exact.name + ' agregado', 'success');
                        }
                        saveRecent(exact);
                    })
                    .catch(function () {
                        addProduct(p, 1);
                    });
            });
            elRecentList.appendChild(row);
        });
    }

    function initPosCustomerCombobox() {
        var wrap = document.getElementById('posCustomerCombobox');
        var input = document.getElementById('posCustomerSearch');
        var hidden = document.getElementById('posCustomer');
        var list = document.getElementById('posCustomerList');
        if (!wrap || !input || !hidden || !list || wrap.dataset.ready === '1') return;
        wrap.dataset.ready = '1';
        input.value = 'Cliente general';

        function openList() {
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        function closeList() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }
        function filterList() {
            var q = input.value.toLowerCase().trim();
            var options = list.querySelectorAll('.combobox-option');
            var visible = 0;
            options.forEach(function (li) {
                var match = !q || (li.getAttribute('data-search') || '').indexOf(q) !== -1
                    || (li.getAttribute('data-label') || '').toLowerCase().indexOf(q) !== -1;
                li.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (visible > 0) openList(); else closeList();
        }
        function selectOption(li) {
            hidden.value = li.getAttribute('data-id') || '';
            input.value = li.getAttribute('data-label') || li.textContent.trim();
            closeList();
        }

        input.addEventListener('focus', filterList);
        input.addEventListener('input', filterList);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeList(); return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = list.querySelector('.combobox-option:not([style*="display: none"])');
                if (first) selectOption(first);
            }
        });
        list.addEventListener('click', function (e) {
            var li = e.target.closest('.combobox-option');
            if (li) selectOption(li);
        });
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) closeList();
        });
    }

    function cartQty(productId) {
        var row = items.find(function (i) { return String(i.product_id) === String(productId); });
        return row ? row.quantity : 0;
    }

    function addProduct(p, qty) {
        qty = qty || 1;
        var stock = parseInt(p.stock, 10);
        if (isNaN(stock)) stock = 0;
        var inCart = cartQty(p.id);
        if (inCart + qty > stock) {
            if (typeof showAlert === 'function') showAlert('Stock insuficiente. Disponible: ' + stock, 'error');
            return false;
        }
        var existing = items.find(function (i) { return String(i.product_id) === String(p.id); });
        if (existing) {
            existing.quantity += qty;
            existing.subtotal = existing.quantity * existing.unit_price;
            existing.stock = stock;
        } else {
            items.push({
                product_id: p.id,
                product_name: p.name,
                code: p.code || '',
                quantity: qty,
                unit_price: parseFloat(p.sale_price) || 0,
                subtotal: (parseFloat(p.sale_price) || 0) * qty,
                stock: stock
            });
        }
        saveRecent(p);
        renderCart();
        return true;
    }

    function bumpQty(idx, delta) {
        var row = items[idx];
        if (!row) return;
        var next = row.quantity + delta;
        if (next <= 0) {
            items.splice(idx, 1);
        } else if (next > row.stock) {
            if (typeof showAlert === 'function') showAlert('Stock insuficiente. Disponible: ' + row.stock, 'error');
            return;
        } else {
            row.quantity = next;
            row.subtotal = row.quantity * row.unit_price;
        }
        renderCart();
    }

    function setQty(idx, value) {
        var row = items[idx];
        if (!row) return;
        var q = parseInt(value, 10);
        if (isNaN(q) || q < 1) q = 1;
        if (q > row.stock) {
            q = row.stock;
            if (typeof showAlert === 'function') showAlert('Stock máximo: ' + row.stock, 'error');
        }
        row.quantity = q;
        row.subtotal = row.quantity * row.unit_price;
        renderCart();
    }

    function discountPercent() {
        if (!elDiscountPct) return 0;
        var p = parseFloat(elDiscountPct.value);
        if (isNaN(p) || p < 0) p = 0;
        if (p > 100) p = 100;
        return p;
    }

    function renderCart() {
        var subtotal = 0;
        var count = 0;
        if (!items.length) {
            elBody.innerHTML = '<tr class="pos-empty-row"><td colspan="3">Escanee o busque productos a la derecha</td></tr>';
            elPayBtn.disabled = true;
            elPayBtn.textContent = 'Listo';
            elPayBar.hidden = true;
        } else {
            elBody.innerHTML = '';
            items.forEach(function (it, idx) {
                subtotal += it.subtotal;
                count += it.quantity;
                var label = (it.code ? it.code + ' — ' : '') + it.product_name +
                    ' Valor und ' + money(it.unit_price) + ' Total ' + money(it.subtotal);
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="number" min="1" class="pos-qty" data-idx="' + idx + '" value="' + it.quantity + '"></td>' +
                    '<td><div class="pos-prod-label">' + esc(label) + '</div></td>' +
                    '<td class="pos-actions">' +
                    '<button type="button" class="pos-btn-plus" data-idx="' + idx + '" title="Sumar">+1</button>' +
                    '<button type="button" class="pos-btn-del" data-idx="' + idx + '" title="Quitar">&times;</button>' +
                    '</td>';
                elBody.appendChild(tr);
            });
            elPayBtn.disabled = false;
            elPayBtn.textContent = 'Listo';
        }
        var pct = discountPercent();
        var disc = Math.round(subtotal * (pct / 100) * 100) / 100;
        var taxable = Math.max(0, subtotal - disc);
        var tax = Math.round(taxable * (taxRate / 100) * 100) / 100;
        var total = taxable + tax;
        if (elSubtotal) elSubtotal.textContent = money(subtotal);
        if (elDiscountAmt) elDiscountAmt.textContent = money(disc);
        if (elTaxAmt) elTaxAmt.textContent = money(tax);
        if (elSubtotalLine) elSubtotalLine.hidden = !((pct > 0 || taxRate > 0) && subtotal > 0);
        if (elDiscountLine) elDiscountLine.hidden = !(pct > 0 && subtotal > 0);
        if (elTaxLine) elTaxLine.hidden = !(taxRate > 0 && subtotal > 0);
        elTotal.textContent = money(total);
        elCount.textContent = String(count);
    }

    function renderResults(list, q) {
        if (!list.length) {
            elResults.innerHTML = '<p class="pos-search-hint">Sin resultados para “' + esc(q) + '”</p>';
            return;
        }
        elResults.innerHTML = '';
        list.forEach(function (p) {
            var row = document.createElement('div');
            row.className = 'pos-result-row';
            row.innerHTML =
                '<div class="pos-result-info">' +
                '<div class="pos-result-title">' + esc((p.code ? p.code + ' — ' : '') + p.name) + '</div>' +
                '<div class="pos-result-meta">Stock: ' + p.stock + ' · ' + money(p.sale_price) + '</div>' +
                '</div>' +
                '<button type="button" class="pos-result-add" title="Agregar">+</button>';
            row.querySelector('.pos-result-add').addEventListener('click', function () {
                if (addProduct(p, 1) && typeof showAlert === 'function') {
                    showAlert(p.name + ' agregado', 'success');
                }
                elSearch.focus();
            });
            elResults.appendChild(row);
        });
    }

    function search(q, opts) {
        opts = opts || {};
        q = (q || '').trim();
        lastQuery = q;
        if (!q) {
            elResults.innerHTML = '<p class="pos-search-hint">Escriba o escanee un código para encontrar productos</p>';
            renderRecent();
            return;
        }
        fetch(searchUrl + '&q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (q !== lastQuery) return;
                var products = (d && d.products) ? d.products : [];
                if (opts.autoAddExact) {
                    var exact = products.find(function (p) {
                        return String(p.code || '').toLowerCase() === q.toLowerCase();
                    });
                    if (exact) {
                        if (addProduct(exact, 1)) {
                            elSearch.value = '';
                            elResults.innerHTML = '<p class="pos-search-hint">Agregado: ' + esc(exact.name) + '. Escanee el siguiente…</p>';
                            if (typeof showAlert === 'function') showAlert(exact.name + ' agregado', 'success');
                        }
                        return;
                    }
                    if (products.length === 1) {
                        if (addProduct(products[0], 1)) {
                            elSearch.value = '';
                            elResults.innerHTML = '<p class="pos-search-hint">Agregado: ' + esc(products[0].name) + '. Escanee el siguiente…</p>';
                        }
                        return;
                    }
                }
                renderResults(products, q);
            })
            .catch(function () {
                elResults.innerHTML = '<p class="pos-search-hint">Error al buscar. Intente de nuevo.</p>';
            });
    }

    elSearch.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var q = elSearch.value;
        searchTimer = setTimeout(function () { search(q, { autoAddExact: false }); }, 220);
    });

    elSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            search(elSearch.value, { autoAddExact: true });
        }
    });

    if (elClear) {
        elClear.addEventListener('click', function () {
            elSearch.value = '';
            elResults.innerHTML = '<p class="pos-search-hint">Escriba o escanee un código para encontrar productos</p>';
            renderRecent();
            elSearch.focus();
        });
    }

    elBody.addEventListener('click', function (e) {
        var plus = e.target.closest('.pos-btn-plus');
        var del = e.target.closest('.pos-btn-del');
        if (plus) bumpQty(parseInt(plus.getAttribute('data-idx'), 10), 1);
        if (del) {
            var i = parseInt(del.getAttribute('data-idx'), 10);
            items.splice(i, 1);
            renderCart();
        }
    });

    elBody.addEventListener('change', function (e) {
        var inp = e.target.closest('.pos-qty');
        if (!inp) return;
        setQty(parseInt(inp.getAttribute('data-idx'), 10), inp.value);
    });

    elPayBtn.addEventListener('click', function () {
        if (!items.length) return;
        elPayBar.hidden = false;
    });

    document.getElementById('posCancelPay').addEventListener('click', function () {
        elPayBar.hidden = true;
    });

    function loadOfflineQueue() {
        try {
            var raw = localStorage.getItem(offlineKey);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function saveOfflineQueue(list) {
        try {
            localStorage.setItem(offlineKey, JSON.stringify(list || []));
        } catch (e) {}
        updateOfflineBadge();
    }

    function updateOfflineBadge() {
        var n = loadOfflineQueue().length;
        var el = document.getElementById('posOfflineBanner');
        if (!el) {
            el = document.createElement('div');
            el.id = 'posOfflineBanner';
            el.style.cssText = 'display:none;margin:0 0 12px;padding:10px 14px;border-radius:8px;background:#fff3cd;color:#856404;font-size:13px;border:1px solid #ffc107;';
            if (panel.parentNode) panel.parentNode.insertBefore(el, panel);
        }
        var online = typeof navigator.onLine === 'undefined' ? true : navigator.onLine;
        if (n > 0) {
            el.style.display = 'block';
            el.textContent = online
                ? ('Hay ' + n + ' venta(s) en cola offline. Sincronizando…')
                : ('Sin conexión. ' + n + ' venta(s) guardada(s) localmente; se enviarán al volver la red.');
        } else if (!online) {
            el.style.display = 'block';
            el.textContent = 'Sin conexión. Las ventas se guardarán en cola local hasta recuperar la red.';
        } else {
            el.style.display = 'none';
        }
    }

    function syncPosPaymentOptions() {
        var typeEl = document.getElementById('posPaymentType');
        var termsEl = document.getElementById('posPaymentTerms');
        var initialEl = document.getElementById('posInitialPayment');
        var confirmBtn = document.getElementById('posConfirmPay');
        if (!typeEl) return;
        var isCredit = typeEl.value === 'credit';
        if (termsEl) {
            termsEl.style.display = isCredit ? '' : 'none';
            if (!isCredit) termsEl.value = 'cash';
            else if (termsEl.value === 'cash') termsEl.value = 'net_30';
        }
        if (initialEl) {
            initialEl.style.display = isCredit ? '' : 'none';
            if (!isCredit) initialEl.value = '';
        }
        if (confirmBtn) {
            confirmBtn.textContent = isCredit ? 'Registrar crédito' : 'Cobrar ahora';
        }
    }

    function buildSalePayload() {
        var payType = (document.getElementById('posPaymentType') || {}).value || 'full';
        var termsEl = document.getElementById('posPaymentTerms');
        var paymentTerms = payType === 'credit'
            ? ((termsEl && termsEl.value) || 'net_30')
            : 'cash';
        var initialEl = document.getElementById('posInitialPayment');
        var payload = {
            csrf_token: csrf,
            customer_id: document.getElementById('posCustomer').value || '',
            payment_method: document.getElementById('posPayMethod').value || 'cash',
            payment_type: payType,
            document_type: 'invoice',
            payment_terms: paymentTerms,
            sale_date: new Date().toISOString().slice(0, 10),
            notes: 'Venta rápida desde caja',
            discount_percent: String(discountPercent()),
            initial_payment: payType === 'credit' ? String((initialEl && initialEl.value) || '0') : '0',
            items: items.map(function (it) {
                return {
                    product_id: it.product_id,
                    quantity: it.quantity,
                    unit_price: it.unit_price,
                    product_name: it.product_name,
                    subtotal: it.subtotal
                };
            })
        };
        return payload;
    }

    function payloadToFormData(payload) {
        var fd = new FormData();
        fd.append('csrf_token', payload.csrf_token || csrf);
        if (payload.customer_id) fd.append('customer_id', payload.customer_id);
        fd.append('payment_method', payload.payment_method || 'cash');
        fd.append('payment_type', payload.payment_type || 'full');
        fd.append('document_type', payload.document_type || 'invoice');
        fd.append('payment_terms', payload.payment_terms || 'cash');
        fd.append('sale_date', payload.sale_date || new Date().toISOString().slice(0, 10));
        var notes = payload.notes || 'Venta rápida desde caja';
        if (payload.queued_at) notes += ' [offline]';
        fd.append('notes', notes);
        fd.append('discount_percent', String(payload.discount_percent || '0'));
        fd.append('initial_payment', String(payload.initial_payment || '0'));
        (payload.items || []).forEach(function (it, i) {
            fd.append('items[' + i + '][product_id]', it.product_id);
            fd.append('items[' + i + '][quantity]', it.quantity);
            fd.append('items[' + i + '][unit_price]', it.unit_price);
            fd.append('items[' + i + '][product_name]', it.product_name);
            fd.append('items[' + i + '][subtotal]', it.subtotal);
        });
        return fd;
    }

    function enqueueOfflineSale(payload) {
        payload.queued_at = new Date().toISOString();
        var q = loadOfflineQueue();
        q.push(payload);
        saveOfflineQueue(q);
        if (typeof showAlert === 'function') {
            showAlert('Sin red: venta guardada en cola local (' + q.length + ')', 'warning');
        }
    }

    function postSale(payload) {
        return fetch(saleUrl, {
            method: 'POST',
            body: payloadToFormData(payload),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); });
    }

    function flushOfflineQueue() {
        if (flushingOffline) return;
        if (typeof navigator.onLine !== 'undefined' && !navigator.onLine) {
            updateOfflineBadge();
            return;
        }
        var q = loadOfflineQueue();
        if (!q.length) {
            updateOfflineBadge();
            return;
        }
        flushingOffline = true;
        updateOfflineBadge();
        var next = q[0];
        postSale(next)
            .then(function (d) {
                if (d && d.success) {
                    q.shift();
                    saveOfflineQueue(q);
                    if (typeof showAlert === 'function') {
                        showAlert('Venta offline sincronizada' + (q.length ? ' (' + q.length + ' pendientes)' : ''), 'success');
                    }
                    flushingOffline = false;
                    if (q.length) {
                        setTimeout(flushOfflineQueue, 400);
                    } else {
                        setTimeout(function () { window.location.reload(); }, 600);
                    }
                } else {
                    flushingOffline = false;
                    if (typeof showAlert === 'function') {
                        showAlert((d && d.message) || 'No se pudo sincronizar venta offline', 'error');
                    }
                    updateOfflineBadge();
                }
            })
            .catch(function () {
                flushingOffline = false;
                updateOfflineBadge();
            });
    }

    document.getElementById('posConfirmPay').addEventListener('click', function () {
        if (paying || !items.length) return;
        paying = true;
        var btn = document.getElementById('posConfirmPay');
        btn.disabled = true;

        var payload = buildSalePayload();

        if (typeof navigator.onLine !== 'undefined' && !navigator.onLine) {
            enqueueOfflineSale(payload);
            paying = false;
            btn.disabled = false;
            items = [];
            if (elDiscountPct) elDiscountPct.value = '0';
            renderCart();
            elPayBar.hidden = true;
            elSearch.value = '';
            elSearch.focus();
            return;
        }

        postSale(payload)
            .then(function (d) {
                paying = false;
                btn.disabled = false;
                if (!d || !d.success) {
                    if (typeof showAlert === 'function') showAlert((d && d.message) || 'No se pudo registrar la venta', 'error');
                    return;
                }
                if (typeof showAlert === 'function') showAlert(d.message || 'Venta registrada', 'success');
                if (d.invoice_number) {
                    var prefEl = document.getElementById('posPrefijo');
                    if (prefEl) prefEl.textContent = d.invoice_number;
                }
                items = [];
                if (elDiscountPct) elDiscountPct.value = '0';
                renderCart();
                elPayBar.hidden = true;
                elSearch.value = '';
                elSearch.focus();
                setTimeout(function () { window.location.reload(); }, 900);
            })
            .catch(function () {
                enqueueOfflineSale(payload);
                paying = false;
                btn.disabled = false;
                items = [];
                if (elDiscountPct) elDiscountPct.value = '0';
                renderCart();
                elPayBar.hidden = true;
                elSearch.value = '';
                elSearch.focus();
            });
    });

    window.addEventListener('online', flushOfflineQueue);
    window.addEventListener('offline', updateOfflineBadge);
    updateOfflineBadge();
    setTimeout(flushOfflineQueue, 800);
    setInterval(flushOfflineQueue, 30000);

    var pref = document.getElementById('posPrefijo');
    if (pref && !pref.textContent.trim()) {
        pref.textContent = invoicePreviewBase;
    }

    var payTypeEl = document.getElementById('posPaymentType');
    if (payTypeEl) {
        payTypeEl.addEventListener('change', syncPosPaymentOptions);
        syncPosPaymentOptions();
    }

    if (elDiscountPct) {
        elDiscountPct.addEventListener('input', renderCart);
        elDiscountPct.addEventListener('change', function () {
            var p = discountPercent();
            elDiscountPct.value = String(p);
            renderCart();
        });
    }

    if (elNewCustomer) {
        elNewCustomer.addEventListener('click', function () {
            if (typeof quickAddCustomer === 'function') quickAddCustomer();
            else {
                var m = document.getElementById('quickCustomerModal');
                if (m) m.style.display = 'flex';
            }
        });
    }

    window.posAddByBarcode = function (code) {
        code = String(code || '').trim();
        if (!code) return;
        fetch(searchUrl + '&q=' + encodeURIComponent(code), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var products = (d && d.products) ? d.products : [];
                var exact = products.find(function (p) {
                    return String(p.code || '').toLowerCase() === code.toLowerCase();
                });
                if (!exact && products.length === 1) exact = products[0];
                if (!exact) {
                    if (typeof showAlert === 'function') showAlert('Producto no encontrado: ' + code, 'error');
                    return;
                }
                if (addProduct(exact, 1) && typeof showAlert === 'function') {
                    showAlert(exact.name + ' agregado', 'success');
                }
                if (elSearch) {
                    elSearch.value = '';
                    elSearch.focus();
                }
            })
            .catch(function () {
                if (typeof showAlert === 'function') showAlert('Error al leer código', 'error');
            });
    };

    initPosCustomerCombobox();
    renderRecent();
    renderCart();
    elSearch.focus();
})();

function printClosing(id) {
    var row = (typeof event !== 'undefined' && event.target) ? event.target.closest('tr') : null;
    if (!row) return;
    var cells = row.querySelectorAll('td');
    var printWin = window.open('', '_blank', 'width=400,height=600');
    printWin.document.write('<!DOCTYPE html><html><head><title>Cierre de Caja</title>' +
        '<style>body{font-family:Arial;padding:20px;font-size:12px;}h2{text-align:center;}table{width:100%;border-collapse:collapse;}td{padding:6px;border-bottom:1px solid #ddd;}.right{text-align:right;}</style></head>' +
        '<body><h2>Cierre de Caja #' + id + '</h2><p style="text-align:center;color:#666;">' + getTenantName() + ' &mdash; ' + new Date().toLocaleDateString() + '</p>' +
        '<table><tr><td>Apertura:</td><td class="right">' + cells[0].textContent + '</td></tr>' +
        '<tr><td>Cierre:</td><td class="right">' + cells[1].textContent + '</td></tr>' +
        '<tr><td>Usuario:</td><td>' + cells[2].textContent + '</td></tr>' +
        '<tr><td>Monto Inicial:</td><td class="right">' + cells[3].textContent + '</td></tr>' +
        '<tr><td>Monto Final:</td><td class="right">' + cells[4].textContent + '</td></tr>' +
        '<tr><td>Diferencia:</td><td class="right">' + cells[5].textContent + '</td></tr></table>' +
        '<p style="text-align:center;margin-top:20px;color:#999;">SoftNova ERP — Osgo Support 2026</p></body></html>');
    printWin.document.close();
    setTimeout(function () { printWin.print(); }, 300);
}

function getTenantName() {
    return document.querySelector('meta[name="tenant-name"]')?.content || 'Empresa';
}
