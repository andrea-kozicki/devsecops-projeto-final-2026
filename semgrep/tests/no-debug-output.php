<?php

$user = [
    'email' => 'teste@example.com',
    'role' => 'admin'
];

// Deve ser detectado pela regra customizada.
print "Debug temporário";

// Deve ser detectado pela regra customizada.
echo "Usuário criado";

// Deve ser detectado pela regra customizada.
var_dump($user);

// Deve ser detectado pela regra customizada.
print_r($user);

// Exemplo correto: uso de logger.
$logger = new class {
    public function info(string $message, array $context = []): void {}
};

$logger->info('Usuário criado com sucesso', ['email' => $user['email']]);