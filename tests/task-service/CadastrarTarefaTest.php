<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
#[TestDox('Cadastro de tarefas')]
final class CadastrarTarefaTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/task-service/src/task_helpers.php';
    }

    #[TestDox('deve retornar erro quando o título não for informado')]
    public function testRequireFieldsReturnsErrorWhenTitleIsMissing(): void
    {
        $data = [
            'description' => 'Teste',
        ];

        $errors = taskRequireFields($data, ['title']);

        $this->assertArrayHasKey('title', $errors);
        $this->assertSame("O campo 'title' é obrigatório.", $errors['title']);
    }

    #[TestDox('deve aceitar prioridades permitidas')]
    public function testIsValidPriorityAcceptsAllowedValues(): void
    {
        $this->assertTrue(taskIsValidPriority('baixa'));
        $this->assertTrue(taskIsValidPriority('media'));
        $this->assertTrue(taskIsValidPriority('alta'));
    }

    #[TestDox('deve rejeitar prioridade inválida')]
    public function testIsValidPriorityRejectsInvalidValue(): void
    {
        $this->assertFalse(taskIsValidPriority('urgente'));
    }

    #[TestDox('deve aceitar status permitidos')]
    public function testIsValidStatusAcceptsAllowedValues(): void
    {
        $this->assertTrue(taskIsValidStatus('pendente'));
        $this->assertTrue(taskIsValidStatus('em_andamento'));
        $this->assertTrue(taskIsValidStatus('concluida'));
    }

    #[TestDox('deve rejeitar status inválido')]
    public function testIsValidStatusRejectsInvalidValue(): void
    {
        $this->assertFalse(taskIsValidStatus('finalizada'));
    }

    #[TestDox('deve aceitar data válida e data vazia')]
    public function testIsValidDateAcceptsValidDateAndEmptyDate(): void
    {
        $this->assertTrue(taskIsValidDate('2026-05-30'));
        $this->assertTrue(taskIsValidDate(''));
        $this->assertTrue(taskIsValidDate(null));
    }

    #[TestDox('deve rejeitar data inválida')]
    public function testIsValidDateRejectsInvalidDate(): void
    {
        $this->assertFalse(taskIsValidDate('2026-99-99'));
    }
}
