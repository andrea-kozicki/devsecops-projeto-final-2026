<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../task-service/src/task_helpers.php';

final class CadastrarTarefaTest extends TestCase
{
    public function testCadastroTarefaComDadosValidos(): void
    {
        $dados = [
            'usuario_id' => 1,
            'titulo' => 'Estudar DevSecOps',
            'descricao' => 'Fazer testes unitários com PHPUnit',
            'prioridade' => 'alta',
            'prazo' => '2026-04-30',
            'status' => 'pendente'
        ];

        $resultado = validarDadosTarefa($dados);

        $this->assertTrue($resultado);
    }

    public function testCadastroTarefaSemTituloDeveFalhar(): void
    {
        $dados = [
            'usuario_id' => 1,
            'titulo' => '',
            'descricao' => 'Fazer testes unitários',
            'prioridade' => 'alta',
            'prazo' => '2026-04-30',
            'status' => 'pendente'
        ];

        $resultado = validarDadosTarefa($dados);

        $this->assertFalse($resultado);
    }

    public function testCadastroTarefaComPrioridadeInvalidaDeveFalhar(): void
    {
        $dados = [
            'usuario_id' => 1,
            'titulo' => 'Estudar',
            'descricao' => 'Descrição da tarefa',
            'prioridade' => 'urgente',
            'prazo' => '2026-04-30',
            'status' => 'pendente'
        ];

        $resultado = validarDadosTarefa($dados);

        $this->assertFalse($resultado);
    }
}