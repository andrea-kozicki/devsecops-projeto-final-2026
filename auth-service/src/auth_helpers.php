<?php

function validarDadosCadastroUsuario(array $dados): bool
{
    $nome = trim($dados['nome'] ?? '');
    $email = trim($dados['email'] ?? '');
    $senha = $dados['senha'] ?? '';

    if ($nome === '') {
        return false;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (strlen($senha) < 6) {
        return false;
    }

    return true;
}

function gerarHashSenha(string $senha): string
{
    return password_hash($senha, PASSWORD_DEFAULT);
}

function verificarSenha(string $senha, string $hash): bool
{
    return password_verify($senha, $hash);
}