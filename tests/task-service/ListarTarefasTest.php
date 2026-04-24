<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../task-service/src/task_helpers.php';

final class ListarTarefasTest extends TestCase
{
    public function testListarApenasTarefasDoUsuarioAutenticado(): void
    {
        $tarefas = [
            [
                'id' => 1,
                'usuario_id' => 1,
                'titulo' => 'Tarefa da Andrea',
                'status' => 'pendente'
            ],
            [
                'id' => 2,
                'usuario_id' => 2,
                'titulo' => 'Tarefa de outro usuário',
                'status' => 'pendente'
            ],
            [
                'id' => 3,
                'usuario_id' => 1,
                'titulo' => 'Outra tarefa da Andrea',
                'status' => 'concluida'
            ],
        ];

        $resultado = filtrarTarefasPorUsuario($tarefas, 1);

        $this->assertCount(2, $resultado);
        $this->assertEquals(1, $resultado[0]['usuario_id']);
        $this->assertEquals(1, $resultado[1]['usuario_id']);
    }

    public function testUsuarioSemTarefasRecebeListaVazia(): void
    {
        $tarefas = [
            [
                'id' => 1,
                'usuario_id' => 1,
                'titulo' => 'Tarefa da Andrea',
                'status' => 'pendente'
            ],
        ];

        $resultado = filtrarTarefasPorUsuario($tarefas, 99);

        $this->assertEmpty($resultado);
    }
}