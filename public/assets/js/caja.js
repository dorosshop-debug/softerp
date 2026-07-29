// SoftNova - Caja-POS
'use strict';

(function () {
    var panel = document.getElementById('posPanel');
    if (!panel) return;

    var searchUrl = panel.getAttribute('data-search-url');
    var saleUrl = panel.getAttribute('data-sale-url');
    var csrf = panel.getAttribute('data-csrf');
    var symbol = panel.getAttribute('data-symbol') || '$';
    var decimals = parseInt(panel.getAttribute('data-decimals') || '0', 10);
    var prefix = panel.getAttribute('data-prefix') || 'FAC-';

    var items = [];
    var searchTimer = null;
    var lastQuery = '';
    var paying = false;

    var elSearch = document.getElementById('posProductSearch');
    var elResults = document.getElementById('posSearchResults');
    var elBody = document.getElementById('posItemsBody');
    var elTotal = document.getElementById('posTotal');
    var elSubtotal = document.getElementById('posSubtotal');
    var elSubtotalLine = document.getElementById('posSubtotalLine');
    var elDiscountAmt = document.getElementById('posDiscountAmt');
    var elDiscountLine = document.getElementById('posDiscountLine');
    var elDiscountPct = document.getElementById('posDiscountPercent');
    var elCount = document.getElementById('posItemCount');
    var elPayBtn = document.getElementById('posPayBtn');
    var elPayBar = document.getElementById('posPayBar');
    var elClock = document.getElementById('posClock');
    var elClear = document.getElementById('posSearchClear');
    var elNewCustomer = document.getElementById('posNewCustomerBtn');

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
        }
        var pct = discountPercent();
        var disc = Math.round(subtotal * (pct / 100) * 100) / 100;
        var total = Math.max(0, subtotal - disc);
        if (elSubtotal) elSubtotal.textContent = money(subtotal);
        if (elDiscountAmt) elDiscountAmt.textContent = money(disc);
        if (elSubtotalLine) elSubtotalLine.hidden = !(pct > 0 && subtotal > 0);
        if (elDiscountLine) elDiscountLine.hidden = !(pct > 0 && subtotal > 0);
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

    document.getElementById('posConfirmPay').addEventListener('click', function () {
        if (paying || !items.length) return;
        paying = true;
        var btn = document.getElementById('posConfirmPay');
        btn.disabled = true;

        var fd = new FormData();
        fd.append('csrf_token', csrf);
        var customerId = document.getElementById('posCustomer').value;
        if (customerId) fd.append('customer_id', customerId);
        fd.append('payment_method', document.getElementById('posPayMethod').value || 'cash');
        fd.append('payment_type', 'full');
        fd.append('document_type', 'invoice');
        fd.append('payment_terms', 'cash');
        fd.append('sale_date', new Date().toISOString().slice(0, 10));
        fd.append('notes', 'Venta rápida desde caja');
        fd.append('discount_percent', String(discountPercent()));

        items.forEach(function (it, i) {
            fd.append('items[' + i + '][product_id]', it.product_id);
            fd.append('items[' + i + '][quantity]', it.quantity);
            fd.append('items[' + i + '][unit_price]', it.unit_price);
            fd.append('items[' + i + '][product_name]', it.product_name);
            fd.append('items[' + i + '][subtotal]', it.subtotal);
        });

        fetch(saleUrl, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                paying = false;
                btn.disabled = false;
                if (!d || !d.success) {
                    if (typeof showAlert === 'function') showAlert((d && d.message) || 'No se pudo registrar la venta', 'error');
                    return;
                }
                if (typeof showAlert === 'function') showAlert(d.message || 'Venta registrada', 'success');
                items = [];
                if (elDiscountPct) elDiscountPct.value = '0';
                renderCart();
                elPayBar.hidden = true;
                elSearch.value = '';
                elSearch.focus();
                setTimeout(function () { window.location.reload(); }, 700);
            })
            .catch(function () {
                paying = false;
                btn.disabled = false;
                if (typeof showAlert === 'function') showAlert('Error de conexión al cobrar', 'error');
            });
    });

    var pref = document.getElementById('posPrefijo');
    if (pref) pref.textContent = prefix + '…';

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

    /** Agregar por pistola aunque el foco no esté en el buscador */
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
