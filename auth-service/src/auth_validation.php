<?php
declare(strict_types=1);

function validateStrongPassword(string $password, bool $required = true): ?string
{
    if ($password === '') {
        return $required ? 'A senha é obrigatória.' : null;
    }

    if (mb_strlen($password) < 10) {
        return 'A senha deve ter pelo menos 10 caracteres.';
    }

    if (mb_strlen($password) > 255) {
        return 'A senha deve ter no máximo 255 caracteres.';
    }

    if (
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        return 'A senha deve conter letra maiúscula, minúscula, número e símbolo.';
    }

    return null;
}

function validateSixDigitCode(string $code): ?string
{
    return preg_match('/^\d{6}$/', trim($code))
        ? null
        : 'Informe um código de 6 dígitos.';
}