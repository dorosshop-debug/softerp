<?php

/**
 * Defaults de proveedores de facturación electrónica.
 * Las credenciales reales viven por tenant en integration_settings (UI).
 */

return [
    'integrations' => [
        'active_provider' => null, // alegra | siigo | factus | dian
        'providers' => [
            'alegra' => [
                'label' => 'Alegra',
                'base_url' => 'https://api.alegra.com/api/v1',
                'timeout' => 30,
            ],
            'siigo' => [
                'label' => 'Siigo',
                'base_url' => 'https://api.siigo.com',
                'partner_id' => 'SeriERP',
                'timeout' => 30,
            ],
            'factus' => [
                'label' => 'Factus',
                'base_url' => 'https://api-sandbox.factus.com.co',
                'timeout' => 30,
            ],
            'dian' => [
                'label' => 'DIAN (directo)',
                'base_url' => 'https://vpfe.dian.gov.co',
                'timeout' => 45,
            ],
        ],
    ],
];
