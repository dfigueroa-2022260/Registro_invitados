<?php

// La app no usa usuarios de base de datos: el acceso va por sesión (ver config/registro.php).
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => 'App\\Models\\User']],
    'passwords' => [],
    'password_timeout' => 10800,
];
