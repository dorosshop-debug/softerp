<?php

/**
 * Catálogos compartidos: medios de pago, tipos de gasto y canales de producto.
 */
return [
    'payment_methods' => [
        'cash' => 'Efectivo',
        'debit_card' => 'Tarjeta débito',
        'credit_card' => 'Tarjeta crédito',
        'dataphone' => 'Datáfono',
        'payment_link' => 'Link de pago',
        'transfer' => 'Transferencia',
        'card' => 'Tarjeta (genérico)', // legado
        'other' => 'Otro',
    ],
    // Métodos electrónicos → cuenta banco en contabilidad (no afectan caja física)
    'electronic_payment_methods' => [
        'card', 'debit_card', 'credit_card', 'dataphone', 'payment_link', 'transfer',
    ],
    'expense_categories' => [
        'general' => 'General',
        'fixed' => 'Gasto fijo',
        'financial' => 'Gasto financiero',
        'card_commission' => 'Comisión tarjetas',
        'dataphone_commission' => 'Comisión datáfono',
        'utilities' => 'Servicios públicos',
        'rent' => 'Arriendo',
        'payroll' => 'Nómina / honorarios',
        'marketing' => 'Marketing',
        'other' => 'Otro',
    ],
    'product_channels' => [
        'manual' => 'Manual',
        'purchase' => 'Compra a proveedor',
        'woocommerce' => 'WooCommerce',
        'mercadolibre' => 'Mercado Libre',
    ],
];
