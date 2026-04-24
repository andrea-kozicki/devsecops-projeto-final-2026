<?php

function validarDadosTarefa(array $dados): bool
{
    $usuarioId = $dados['usuario_id'] ?? null;
    $titulo = trim($dados['titulo'] ?? '');
    $prioridade = $dados['prioridade'] ?? '';
    $prazo = $dados['prazo'] ?? '';
    $status = $dados['status'] ?? '';

    $prioridadesPermitidas = ['baixa', 'media', 'alta'];
    $statusPermitidos = ['pendente', 'em_andamento', 'concluida'];

    if (!is_int($usuarioId) || $usuarioId <= 0) {
        return false;
    }

    if ($titulo === '') {
        return false;
    }

    if (!in_array($prioridade, $prioridadesPermitidas, true)) {
        return false;
    }

    if (!validarData($prazo)) {
        return false;
    }

    if (!in_array($status, $statusPermitidos, true)) {
        return false;
    }

    return true;
}

function validarData(string $data): bool
{
    $formato = DateTime::createFromFormat('Y-m-d', $data);

    return $formato && $formato->format('Y-m-d') === $data;
}

function filtrarTarefasPorUsuario(array $tarefas, int $usuarioId): array
{
    $tarefasFiltradas = array_filter($tarefas, function ($tarefa) use ($usuarioId) {
        return isset($tarefa['usuario_id']) && $tarefa['usuario_id'] === $usuarioId;
    });

    return array_values($tarefasFiltradas);
}