<?php

namespace SoftNova\Core;

/**
 * Helpers de catálogo (pagos, gastos, canales).
 */
function payment_methods(bool $includeLegacyCard = true): array
{
    $methods = config('payment_methods', []);
    if (!$includeLegacyCard) {
        unset($methods['card']);
    }
    return is_array($methods) ? $methods : [];
}

function payment_method_label(string $code): string
{
    $methods = payment_methods(true);
    return $methods[$code] ?? ucfirst(str_replace('_', ' ', $code));
}

function is_electronic_payment(string $method): bool
{
    $list = config('electronic_payment_methods', []);
    return in_array($method, is_array($list) ? $list : [], true);
}

function expense_categories(): array
{
    $cats = config('expense_categories', []);
    return is_array($cats) ? $cats : [];
}

function expense_category_label(string $code): string
{
    $cats = expense_categories();
    return $cats[$code] ?? ($code !== '' ? $code : 'General');
}

function product_channels(): array
{
    $ch = config('product_channels', []);
    return is_array($ch) ? $ch : [];
}

function product_channel_label(string $code): string
{
    $ch = product_channels();
    return $ch[$code] ?? ucfirst($code);
}

/**
 * Renderiza <option> de medios de pago.
 */
function payment_method_options(string $selected = 'cash', bool $includeLegacyCard = false): string
{
    $html = '';
    foreach (payment_methods($includeLegacyCard) as $code => $label) {
        if ($code === 'credit') {
            continue; // el crédito es payment_type, no método de cobro
        }
        $sel = $code === $selected ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
            . htmlspecialchars($label) . '</option>';
    }
    return $html;
}

function expense_category_options(string $selected = 'general'): string
{
    $html = '';
    foreach (expense_categories() as $code => $label) {
        $sel = ($code === $selected || strcasecmp($label, $selected) === 0) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
            . htmlspecialchars($label) . '</option>';
    }
    return $html;
}
