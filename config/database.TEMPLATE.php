<?php
/**
 * PLANTILLA de config/database.php para cPanel / producción.
 * Copia a config/database.php y completa con cPanel → MySQL Databases.
 * NUNCA uses root / password vacía en producción.
 * NUNCA subas config/database.php real a GitHub.
 */
return [
    'database' => [
        'default' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'USUARIO_CPANEL_nombre_bd',
            'username' => 'USUARIO_CPANEL_usuario_mysql',
            'password' => 'PASSWORD_REAL',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
    ],
];
