<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
#[TestDox('Listagem de tarefas')]
final class ListarTarefasTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/task-service/src/task_helpers.php';
    }

    #[TestDox('deve normalizar rotas de tarefas removendo prefixos conhecidos')]
    public function testNormalizeTaskPathRemovesKnownPrefixes(): void
    {
        $this->assertSame('/tasks', taskNormalizePath('/internal-tasks/tasks'));
        $this->assertSame('/tasks', taskNormalizePath('/task-service/public/tasks'));
        $this->assertSame('/tasks', taskNormalizePath('/task-service/tasks'));
    }

    #[TestDox('deve obter o IP do cliente pelo cabeçalho X-Forwarded-For quando disponível')]
    public function testTaskClientIpUsesForwardedForWhenPresent(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertSame('203.0.113.10', taskClientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    #[TestDox('deve usar REMOTE_ADDR quando X-Forwarded-For não estiver disponível')]
    public function testTaskClientIpFallsBackToRemoteAddr(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertSame('127.0.0.1', taskClientIp());

        unset($_SERVER['REMOTE_ADDR']);
    }

    #[TestDox('deve retornar unknown quando não houver endereço do cliente disponível')]
    public function testTaskClientIpReturnsUnknownWhenNoServerAddressExists(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertSame('unknown', taskClientIp());
    }
}