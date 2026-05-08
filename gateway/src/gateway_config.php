<?php
declare(strict_types=1);

$localConfigFile = __DIR__ . '/gateway_config_local.php';

if (is_file($localConfigFile)) {
    return require $localConfigFile;
}

return [
    'app_env' => getenv('APP_ENV') ?: 'development',

    'auth_service_url' => getenv('AUTH_SERVICE_URL') ?: 'http://studyboard.local/internal-auth',
    'task_service_url' => getenv('TASK_SERVICE_URL') ?: 'http://studyboard.local/internal-tasks',
];