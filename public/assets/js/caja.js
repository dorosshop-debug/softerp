// Seri ERP - Módulo Caja
'use strict';

function printClosing(id) {
    var row = event.target.closest('tr');
    var cells = row.querySelectorAll('td');
    var printWin = window.open('', '_blank', 'width=400,height=600');
    printWin.document.write('<!DOCTYPE html><html><head><title>Cierre de Caja</title>' +
        '<style>body{font-family:Arial;padding:20px;font-size:12px;}h2{text-align:center;}table{width:100%;border-collapse:collapse;}td{padding:6px;border-bottom:1px solid #ddd;}.right{text-align:right;}.green{color:green;}.red{color:red;}</style></head>' +
        '<body><h2>Cierre de Caja #' + id + '</h2><p style="text-align:center;color:#666;">' + getTenantName() + ' &mdash; ' + new Date().toLocaleDateString() + '</p>' +
        '<table><tr><td>Apertura:</td><td class="right">' + cells[0].textContent + '</td></tr>' +
        '<tr><td>Cierre:</td><td class="right">' + cells[1].textContent + '</td></tr>' +
        '<tr><td>Usuario:</td><td>' + cells[2].textContent + '</td></tr>' +
        '<tr><td>Monto Inicial:</td><td class="right">' + cells[3].textContent + '</td></tr>' +
        '<tr><td>Monto Final:</td><td class="right">' + cells[4].textContent + '</td></tr>' +
        '<tr><td>Diferencia:</td><td class="right">' + cells[5].textContent + '</td></tr></table>' +
        '<p style="text-align:center;margin-top:20px;color:#999;">Seri ERP &copy; 2026</p></body></html>');
    printWin.document.close();
    setTimeout(function() { printWin.print(); }, 300);
}

function getTenantName() {
    return document.querySelector('meta[name="tenant-name"]')?.content || 'Empresa';
}
