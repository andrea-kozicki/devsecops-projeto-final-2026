<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../auth-service/src/auth_helpers.php';

final class CadastrarUsuarioTest extends TestCase
{
    public function testCadastroUsuarioComDadosValidos(): void
    {
        $dados = [
            'nome' => 'Andrea',
            'email' => 'andrea@email.com',
            'senha' => 'Senha123'
        ];

        $resultado = validarDadosCadastroUsuario($dados);

        $this->assertTrue($resultado);
    }

    public function testCadastroUsuarioSemEmailDeveFalhar(): void
    {
        $dados = [
            'nome' => 'Andrea',
            'email' => '',
            'senha' => 'Senha123'
        ];

        $resultado = validarDadosCadastroUsuario($dados);

        $this->assertFalse($resultado);
    }

    public function testSenhaNaoPodeSerArmazenadaEmTextoPuro(): void
    {
        $senha = 'Senha123';

        $hash = gerarHashSenha($senha);

        $this->assertNotEquals($senha, $hash);
        $this->assertTrue(password_verify($senha, $hash));
    }
}