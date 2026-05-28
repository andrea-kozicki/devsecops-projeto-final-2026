<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class ListarTarefasTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/task-service/src/task_helpers.php';
    }

    public function testNormalizeTaskPathRemovesKnownPrefixes(): void
    {
        $this->assertSame('/tasks', taskNormalizePath('/internal-tasks/tasks'));
        $this->assertSame('/tasks', taskNormalizePath('/task-service/public/tasks'));
        $this->assertSame('/tasks', taskNormalizePath('/task-service/tasks'));
    }

    public function testTaskClientIpUsesForwardedForWhenPresent(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertSame('203.0.113.10', taskClientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testTaskClientIpFallsBackToRemoteAddr(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertSame('127.0.0.1', taskClientIp());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testTaskClientIpReturnsUnknownWhenNoServerAddressExists(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertSame('unknown', taskClientIp());
    }
}