<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
#[TestDox('Cadastro de usuários')]
final class CadastrarUsuarioTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/auth-service/src/auth_helpers.php';
    }

    #[TestDox('deve retornar erros quando campos obrigatórios não forem informados')]
    public function testRequireFieldsReturnsErrorsForMissingFields(): void
    {
        $data = [
            'name' => 'Andrea',
        ];

        $errors = authRequireFields($data, ['name', 'email', 'password']);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertSame("O campo 'email' é obrigatório.", $errors['email']);
        $this->assertSame("O campo 'password' é obrigatório.", $errors['password']);
    }

    #[TestDox('deve aceitar endereço de e-mail válido')]
    public function testValidateEmailAddressReturnsTrueForValidEmail(): void
    {
        $this->assertTrue(authValidateEmailAddress('meunome40@exemplo.com'));
    }

    #[TestDox('deve rejeitar endereço de e-mail inválido')]
    public function testValidateEmailAddressReturnsFalseForInvalidEmail(): void
    {
        $this->assertFalse(authValidateEmailAddress('email_invalido'));
    }

    #[TestDox('deve remover dados sensíveis da saída do usuário')]
    public function testSanitizeUserOutputReturnsExpectedStructure(): void
    {
        $user = [
            'id' => '10',
            'name' => 'Teste Perfil',
            'email' => 'meunome40@exemplo.com',
            'role' => 'admin',
            'is_active' => '1',
            'password_hash' => 'nao_deve_sair',
            'created_at' => '2026-05-08 16:44:01',
            'updated_at' => '2026-05-21 16:35:37',
        ];

        $result = authSanitizeUserOutput($user);

        $this->assertSame([
            'id' => 10,
            'name' => 'Teste Perfil',
            'email' => 'meunome40@exemplo.com',
            'role' => 'admin',
            'is_active' => 1,
            'created_at' => '2026-05-08 16:44:01',
            'updated_at' => '2026-05-21 16:35:37',
        ], $result);

        $this->assertArrayNotHasKey('password_hash', $result);
    }

    #[TestDox('deve normalizar rotas de autenticação removendo prefixos conhecidos')]
    public function testNormalizeAuthPathRemovesKnownPrefixes(): void
    {
        $this->assertSame('/auth/login', authNormalizePath('/internal-auth/auth/login'));
        $this->assertSame('/auth/login', authNormalizePath('/auth-service/public/auth/login'));
        $this->assertSame('/auth/login', authNormalizePath('/auth-service/auth/login'));
    }
}