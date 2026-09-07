<?php

// Простой вход в портал: один общий логин и пароль из .env.
// Задаются в PORTAL_LOGIN и PORTAL_PASSWORD; без них вход невозможен.
return [
    'login' => env('PORTAL_LOGIN'),
    'password' => env('PORTAL_PASSWORD'),
];
