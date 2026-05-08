<?php
declare(strict_types=1);

$localConfigFile = __DIR__ . '/task_config_local.php';

if (is_file($localConfigFile)) {
    return require $localConfigFile;
}

$config = [
    'app_env' => getenv('APP_ENV') ?: 'development',

    'db_host' => getenv('TASK_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('TASK_DB_PORT') ?: '3306',
    'db_name' => getenv('TASK_DB_NAME') ?: 'studyboard_tasks',
    'db_user' => getenv('TASK_DB_USER') ?: '',
    'db_pass' => getenv('TASK_DB_PASS') ?: '',

    'auth_service_url' => getenv('AUTH_SERVICE_URL') ?: 'http://studyboard.local/internal-auth',
];

if ($config['db_user'] === '') {
    throw new RuntimeException('TASK_DB_USER não configurado.');
}

return $config;