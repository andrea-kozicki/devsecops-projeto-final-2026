<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class CadastrarTarefaTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/task-service/src/task_helpers.php';
    }

    public function testRequireFieldsReturnsErrorWhenTitleIsMissing(): void
    {
        $data = [
            'description' => 'Teste',
        ];

        $errors = taskRequireFields($data, ['title']);

        $this->assertArrayHasKey('title', $errors);
        $this->assertSame("O campo 'title' é obrigatório.", $errors['title']);
    }

    public function testIsValidPriorityAcceptsAllowedValues(): void
    {
        $this->assertTrue(taskIsValidPriority('baixa'));
        $this->assertTrue(taskIsValidPriority('media'));
        $this->assertTrue(taskIsValidPriority('alta'));
    }

    public function testIsValidPriorityRejectsInvalidValue(): void
    {
        $this->assertFalse(taskIsValidPriority('urgente'));
    }

    public function testIsValidStatusAcceptsAllowedValues(): void
    {
        $this->assertTrue(taskIsValidStatus('pendente'));
        $this->assertTrue(taskIsValidStatus('em_andamento'));
        $this->assertTrue(taskIsValidStatus('concluida'));
    }

    public function testIsValidStatusRejectsInvalidValue(): void
    {
        $this->assertFalse(taskIsValidStatus('finalizada'));
    }

    public function testIsValidDateAcceptsValidDateAndEmptyDate(): void
    {
        $this->assertTrue(taskIsValidDate('2026-05-30'));
        $this->assertTrue(taskIsValidDate(''));
        $this->assertTrue(taskIsValidDate(null));
    }

    public function testIsValidDateRejectsInvalidDate(): void
    {
        $this->assertFalse(taskIsValidDate('2026-99-99'));
    }
}
