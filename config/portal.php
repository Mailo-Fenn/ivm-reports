<?php

// Простой вход в портал: один общий логин и пароль из .env.
// Задаются в PORTAL_LOGIN и PORTAL_PASSWORD; без них вход невозможен.
return [
    'login' => env('PORTAL_LOGIN'),
    'password' => env('PORTAL_PASSWORD'),

    // интерпретатор Python для telegram/stats.py; по умолчанию берётся telegram/.venv, иначе python3
    'python' => env('PYTHON_BIN'),
];
