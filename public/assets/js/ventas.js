// Seri ERP - Módulo Ventas
var saleItems = [];
var curSym = document.querySelector('meta[name="currency-symbol"]')?.content || '$';
var curDec = parseInt(document.querySelector('meta[name="currency-decimals"]')?.content || '0');

function toggleCredit() {
    var s = document.getElementById('paymentType').value === 'credit';
    document.getElementById('initialPaymentGroup').style.display = s ? 'block' : 'none';
}

function addProduct() {
    var sel = document.getElementById('productSelect');
    var qty = parseInt(document.getElementById('productQty').value) || 1;
    if (!sel.value) return;
    var o = sel.options[sel.selectedIndex];
    var id = o.value, name = o.dataset.name, price = parseFloat(o.dataset.price), stock = parseInt(o.dataset.stock);
    if (qty > stock) return showAlert('Stock insuficiente: ' + stock, 'error');
    var ex = saleItems.find(function(i) { return i.product_id == id; });
    if (ex) { ex.quantity += qty; ex.subtotal = ex.quantity * price; }
    else { saleItems.push({ product_id: id, product_name: name, quantity: qty, unit_price: price, subtotal: qty * price }); }
    document.getElementById('productQty').value = 1;
    renderItems();
}

function removeItem(idx) { saleItems.splice(idx, 1); renderItems(); }

function renderItems() {
    var tb = document.querySelector('#itemsTable tbody');
    var tbl = document.getElementById('itemsTable');
    var t = 0;
    tb.innerHTML = '';
    if (saleItems.length === 0) { tbl.style.display = 'none'; return; }
    tbl.style.display = '';
    saleItems.forEach(function(it, i) {
        t += it.subtotal;
        tb.innerHTML += '<tr>' +
            '<td>' + esc(it.product_name) + '</td>' +
            '<td>' + it.quantity + '</td>' +
            '<td>' + curSym + ' ' + it.unit_price.toFixed(curDec) + '</td>' +
            '<td>' + curSym + ' ' + it.subtotal.toFixed(curDec) + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(' + i + ')">X</button></td>' +
            '</tr>';
    });
    document.getElementById('saleTotal').textContent = curSym + ' ' + t.toFixed(curDec);
    document.getElementById('submitSaleBtn').textContent = 'Completar Venta (' + curSym + ' ' + t.toFixed(curDec) + ')';
}

function prepareSaleItems() {
    if (saleItems.length === 0) { showAlert('Agregue al menos un producto', 'error'); return false; }
    var f = document.querySelector('#saleModal form');
    f.querySelectorAll('.sale-item-input').forEach(function(e) { e.remove(); });
    saleItems.forEach(function(it, i) {
        for (var k in it) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'items[' + i + '][' + k + ']';
            inp.value = it[k];
            inp.className = 'sale-item-input';
            f.appendChild(inp);
        }
    });
    return true;
}

function openSaleModal() {
    saleItems = [];
    var fromCart = window.location.search.indexOf('fromCart=1') > -1;
    if (fromCart) {
        var cartData = JSON.parse(localStorage.getItem('eva_cart') || '[]');
        cartData.forEach(function(it) {
            saleItems.push({ product_id: it.id, product_name: it.name, quantity: it.qty, unit_price: it.price, subtotal: it.price * it.qty });
        });
        localStorage.removeItem('eva_cart');
    }
    renderItems();
    toggleCredit();
    document.getElementById('saleModal').style.display = 'flex';
}

function closeSaleModal() { document.getElementById('saleModal').style.display = 'none'; }

function quickAddCustomer() { document.getElementById('quickCustomerModal').style.display = 'flex'; }

function closeQuickCustomer() { document.getElementById('quickCustomerModal').style.display = 'none'; }

function submitQuickCustomer(form) {
    var fd = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            showAlert(d.message, 'success');
            var sel = document.getElementById('customerSelect');
            var newId = d.data ? d.data.id : null;
            var firstName = fd.get('first_name') || '';
            var lastName = fd.get('last_name') || '';
            var newName = (firstName + ' ' + lastName).trim() || 'Nuevo Cliente';
            if (newId && sel) {
                var opt = document.createElement('option');
                opt.value = newId;
                opt.textContent = newName.trim();
                sel.appendChild(opt);
                sel.value = newId;
            }
            document.getElementById('quickCustomerModal').style.display = 'none';
            form.reset();
        } else {
            showAlert(d.message, 'error');
        }
    })
    .catch(function(e) {
        showAlert('Error de conexión', 'error');
    });
    return false;
}

function openPaymentModal(id, total, paid) {
    document.getElementById('paySaleId').value = id;
    document.getElementById('payAmount').value = (total - paid).toFixed(curDec);
    document.getElementById('payInfo').textContent = 'Total: ' + curSym + ' ' + total.toFixed(curDec) + ' | Pagado: ' + curSym + ' ' + paid.toFixed(curDec) + ' | Pendiente: ' + curSym + ' ' + (total - paid).toFixed(curDec);
    document.getElementById('paymentModal').style.display = 'flex';
}

function viewDetail(id) {
    fetch(window.invRouteVentasDetail + '&id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.error) return;
        var s = d.sale;
        var h = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">';
        h += '<div><strong>Factura:</strong> ' + esc(s.invoice_number) + '</div>';
        h += '<div><strong>Fecha:</strong> ' + (s.sale_date || '').substr(0, 16) + '</div>';
        h += '<div><strong>Cliente:</strong> ' + esc(s.customer_name || 'General') + '</div>';
        h += '<div><strong>Estado:</strong> ' + s.payment_status + '</div>';
        h += '<div><strong>Total:</strong> <span style="color:#10B981;font-size:18px;">' + curSym + ' ' + parseFloat(s.total).toFixed(curDec) + '</span></div>';
        h += '</div>';
        if (d.items) {
            h += '<h4>Productos</h4><table><thead><tr><th>Producto</th><th>Cant</th><th>P.Unit</th><th>Subtotal</th></tr></thead><tbody>';
            d.items.forEach(function(i) {
                h += '<tr><td>' + esc(i.product_name) + '</td><td>' + i.quantity + '</td><td>' + curSym + ' ' + parseFloat(i.unit_price).toFixed(curDec) + '</td><td>' + curSym + ' ' + parseFloat(i.subtotal).toFixed(curDec) + '</td></tr>';
            });
            h += '</tbody></table>';
        }
        if (d.payments && d.payments.length > 0) {
            h += '<h4>Abonos</h4><table><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th></tr></thead><tbody>';
            d.payments.forEach(function(p) {
                h += '<tr><td>' + (p.payment_date || '').substr(0, 16) + '</td><td>' + curSym + ' ' + parseFloat(p.amount).toFixed(curDec) + '</td><td>' + esc(p.payment_method) + '</td></tr>';
            });
            h += '</tbody></table>';
        }
        document.getElementById('saleDetailContent').innerHTML = h;
        document.getElementById('saleDetailModal').style.display = 'flex';
    });
}

function printInvoice(id) {
    viewDetail(id);
    setTimeout(function() { window.print(); }, 500);
}

// Auto-abrir modal si viene del carrito
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.search.indexOf('fromCart=1') > -1) {
        openSaleModal();
    }
});
