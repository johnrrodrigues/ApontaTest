<?php
require_once __DIR__ . '/../src/jornada.php';

// Reúne os testes dos limites diários e das datas inválidas.
describe('Jornada', function () {
    // Confere o limite de 540 minutos em uma segunda-feira.
    test('retorna limite de 540 minutos para segunda-feira', function () {
        $limite = limiteDaJornada('2026-11-02');

        expect($limite)->toBe(540);
    });

    // Confere o limite de 480 minutos em uma sexta-feira.
    test('retorna limite de 480 minutos para sexta-feira', function () {
        $limite = limiteDaJornada('2026-11-06');

        expect($limite)->toBe(480);
    });

    // Confere que um sábado não possui minutos disponíveis.
    test('retorna limite de 0 minutos no fim de semana', function () {
        $limite = limiteDaJornada('2026-11-07');

        expect($limite)->toBe(0);
    });

    // Tenta usar 30 de fevereiro e confere a exceção.
    test('rejeita uma data inexistente', function () {
        limiteDaJornada('2026-02-30');
    })->throws(
        InvalidArgumentException::class,
        'Data inválida'
    );

    // Tenta usar letras como data e confere a exceção.
    test('rejeita um texto no lugar da data', function () {
        limiteDaJornada('aaa');
    })->throws(
        InvalidArgumentException::class,
        'Data inválida'
    );
});

// Reúne os testes dos minutos aceitos e rejeitados.
describe('Duração', function () {
    // Confere que o texto recebido retorna como minutos inteiros.
    test('aceita duração positiva', function () {
        $tempo = tempoDeApontamento('1');

        expect($tempo)->toBe(1);
    });

    // Confere a rejeição de cada duração inválida da lista.
    test('rejeita duração zerada, negativa, com casas decimais e caracteres inválidos', function (string $duracao) {
        tempoDeApontamento($duracao);
    })->with([
        'zero' => '0',
        'negativo' => '-2',
        'decimal' => '1.5',
        'letras' => 'aaa',
        'símbolos' => '$$',
    ])
        ->throws(
                InvalidArgumentException::class,
                'Duração inválida'
        );
});

// Reúne os testes dos dados obrigatórios e do limite individual.
describe('Validação de apontamentos', function () {
    // Compara a ficha criada com os quatro valores esperados.
    test('cria apontamento com dados corretos', function () {
        $funcionario = 1;
        $data = '2026-10-29';
        $duracao = '540';
        $descricao = 'Reunião';
        $apontamento = criarApontamento($funcionario, $data, $duracao, $descricao);

        expect($apontamento)->toBe([1, '2026-10-29', 540, 'Reunião']);
    });

    // Tenta registrar 481 minutos em uma sexta-feira.
    test('rejeita apontamento que excede o limite da jornada', function () {
        $funcionario = 2;
        $data = '2026-10-30';
        $duracao = '481';
        $descricao = 'Reunião';
        criarApontamento($funcionario, $data, $duracao, $descricao);
    })->throws(
        InvalidArgumentException::class,
        'Apontamento excede o limite da jornada'
    );

    // Tenta criar um apontamento em um sábado.
    test('rejeita apontamento no fim de semana', function () {
        $funcionario = 2;
        $data = '2026-10-31';
        $duracao = '540';
        $descricao = 'Reunião';
        criarApontamento($funcionario, $data, $duracao, $descricao);
    })->throws(
        InvalidArgumentException::class,
        'Apontamentos de fim de semana não são permitidos'
    );

    // Tenta criar um apontamento com uma data malformada.
    test('rejeita apontamento com data inválida', function () {
        $funcionario = 2;
        $data = '2026-31-10';
        $duracao = '60';
        $descricao = 'Reunião';
        criarApontamento($funcionario, $data, $duracao, $descricao);
    })->throws(
        InvalidArgumentException::class,
        'Data inválida'
    );

    // Confere a rejeição de descrição vazia ou só com espaços.
    test('rejeita apontamento com descrição vazia', function (string $descricao) {
        criarApontamento(1, '2026-11-02', '60', $descricao);
    })->with([
        'vazia' => '',
        'só espaços' => '   ',
    ])
        ->throws(
            InvalidArgumentException::class,
            'Apontamentos devem possuir uma descrição'
        );

    // Confere a rejeição de identificadores zero e negativos.
    test('rejeita apontamento com id de funcionário menor ou igual a zero', function (int $funcionario) {
        criarApontamento($funcionario, '2026-11-02', '60', 'Reunião');
    })->with([
        'id zero' => 0,
        'id negativo' => -1,
    ])->throws(
        InvalidArgumentException::class,
        'Apontamento com ID menor ou igual a 0'
    );
});
