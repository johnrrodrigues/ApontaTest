<?php

// Valida a data e informa o limite diário em minutos.
function limiteDaJornada(string $data): int
{
    $dataInformada = DateTime::createFromFormat('!Y-m-d', $data);

    if ($dataInformada === false || $dataInformada->format('Y-m-d') !== $data) {
        throw new InvalidArgumentException('Data inválida');
    }

    $diaSemana = $dataInformada->format('N');

    if ($diaSemana <= 4) {
        return 540;
    } elseif ($diaSemana == 5) {
        return 480;
    } else {
        return 0;
    }
}

// Aceita apenas minutos inteiros positivos.
function tempoDeApontamento(string $duracao): int
{
    $tempoInformado = $duracao;

    if (ctype_digit($tempoInformado) && $tempoInformado >= 1) {
        return $tempoInformado;
    } else {
        throw new InvalidArgumentException('Duração inválida');
    }
}

// Valida os dados e devolve a ficha do apontamento.
function criarApontamento(int $funcionario, string $data, string $duracao, string $descricao): array
{
    $limite = limiteDaJornada($data);

    if ($funcionario <= 0) {
        throw new InvalidArgumentException('Apontamento com ID menor ou igual a 0');
    }

    if ($limite === 0) {
        throw new InvalidArgumentException('Apontamentos de fim de semana não são permitidos');
    }

    if (trim($descricao) === '') {
        throw new InvalidArgumentException('Apontamentos devem possuir uma descrição');
    }

    $minutos = tempoDeApontamento($duracao);

    if ($limite >= $minutos) {
        $apontamento = array(
            $funcionario,
            $data,
            $minutos,
            $descricao
        );
        return $apontamento;
    } else {
        throw new InvalidArgumentException('Apontamento excede o limite da jornada');
    }
}
