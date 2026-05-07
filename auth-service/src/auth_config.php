<?php
declare(strict_types=1);

$localConfigFile = __DIR__ . '/auth_config_local.php';

if (is_file($localConfigFile)) {
    return require $localConfigFile;
}

$config = [
    'app_env' => getenv('APP_ENV') ?: 'development',

    'db_host' => getenv('AUTH_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('AUTH_DB_PORT') ?: '3306',
    'db_name' => getenv('AUTH_DB_NAME') ?: 'studyboard_auth',
    'db_user' => getenv('AUTH_DB_USER') ?: '',
    'db_pass' => getenv('AUTH_DB_PASS') ?: '',

    'token_ttl' => (int) (getenv('AUTH_TOKEN_TTL') ?: 3600),
];

if ($config['db_user'] === '') {
    throw new RuntimeException('AUTH_DB_USER não configurado.');
}

return $config;