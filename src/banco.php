<?php
require_once __DIR__ . '/jornada.php';

function conectarBanco(string $caminho): PDO
{
    return new PDO('sqlite:' . $caminho);
}

function criarTabelaApontamentos(PDO $banco): void
{
    $banco->exec('
        CREATE TABLE IF NOT EXISTS apontamentos (
            id INTEGER PRIMARY KEY,
            funcionario INTEGER NOT NULL,
            data TEXT NOT NULL,
            duracao INTEGER NOT NULL,
            descricao TEXT NOT NULL
        )
    ');
}

function salvarApontamento(PDO $banco, array $apontamento): int
{
    $consulta = $banco->prepare('
        INSERT INTO apontamentos (funcionario, data, duracao, descricao)
        VALUES (?, ?, ?, ?)
    ');

    $consulta->execute($apontamento);

    return (int) $banco->lastInsertId();
}

function somarMinutosDoDia(PDO $banco, int $funcionario, string $data): int
{
    $consulta = $banco->prepare('
        SELECT SUM(duracao)
        FROM apontamentos
        WHERE funcionario = ? AND data = ?
    ');

    $consulta->execute([$funcionario, $data]);

    return (int) $consulta->fetchColumn();
}

function registrarApontamento(
    PDO $banco,
    int $funcionario,
    string $data,
    string $duracao,
    string $descricao
): void {
    $apontamento = criarApontamento(
        $funcionario,
        $data,
        $duracao,
        $descricao
    );

    $totalAtual = somarMinutosDoDia($banco, $funcionario, $data);
    $minutos = $apontamento[2];
    $limite = limiteDaJornada($data);

    if ($totalAtual + $minutos > $limite) {
        throw new InvalidArgumentException(
            'Soma dos apontamentos excede o limite diário'
        );
    }

    salvarApontamento($banco, $apontamento);
}

function editarApontamento(
    PDO $banco,
    int $id,
    string $duracao,
    string $descricao
): void {
    $consulta = $banco->prepare('
        SELECT funcionario, data, duracao
        FROM apontamentos
        WHERE id = ?
    ');
    $consulta->execute([$id]);

    $atual = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($atual === false) {
        throw new InvalidArgumentException('Apontamento não encontrado');
    }

    $validado = criarApontamento(
        $atual['funcionario'],
        $atual['data'],
        $duracao,
        $descricao
    );

    $totalAtual = somarMinutosDoDia(
        $banco,
        $atual['funcionario'],
        $atual['data']
    );

    $novoTotal = $totalAtual - $atual['duracao'] + $validado[2];

    if ($novoTotal > limiteDaJornada($atual['data'])) {
        throw new InvalidArgumentException(
            'Soma dos apontamentos excede o limite diário'
        );
    }

    $consulta = $banco->prepare('
        UPDATE apontamentos
        SET duracao = ?, descricao = ?
        WHERE id = ?
    ');
    $consulta->execute([$validado[2], $validado[3], $id]);
}

function excluirApontamento(PDO $banco, int $id): void
{
    $consulta = $banco->prepare('
        DELETE FROM apontamentos WHERE id = ?
    ');

    $consulta->execute([$id]);
    if ($consulta->rowCount() === 0) {
        throw new InvalidArgumentException('Apontamento não encontrado');
    }
}
