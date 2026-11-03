<?php

require_once __DIR__ . '/../src/banco.php';

// Cria um banco vazio e a tabela antes de cada teste.
beforeEach(function () {
    $this->banco = conectarBanco(':memory:');
    criarTabelaApontamentos($this->banco);
});

// Reúne os testes de conexão, gravação e consulta no banco.
describe('Banco e consultas', function () {
    // Abre a conexão e confere a resposta de uma consulta simples.
    test('conecta ao banco sqlite em memória', function () {
        $resultado = $this->banco->query('SELECT 1')->fetchColumn();

        expect((int) $resultado)->toBe(1);
    });

    // Confere que a tabela começa sem registros.
    test('inicia com a tabela de apontamentos vazia', function () {
        $quantidade = $this->banco
            ->query('SELECT COUNT(*) FROM apontamentos')
            ->fetchColumn();

        expect((int) $quantidade)->toBe(0);
    });

    // Grava uma ficha e compara os dados recuperados.
    test('salva um apontamento no banco', function () {
        $apontamento = [1, '2026-11-02', 60, 'Reunião'];

        salvarApontamento($this->banco, $apontamento);

        $registro = $this->banco
            ->query('SELECT funcionario, data, duracao, descricao FROM apontamentos')
            ->fetch(PDO::FETCH_NUM);

        expect($registro)->toBe($apontamento);
    });

    // Soma os registros do funcionário e dia, ignorando os demais.
    test('soma apenas os minutos do funcionário na data escolhida', function () {
        salvarApontamento($this->banco, [1, '2026-11-02', 60, 'Reunião']);
        salvarApontamento($this->banco, [1, '2026-11-02', 40, 'Documentação']);

        salvarApontamento($this->banco, [2, '2026-11-02', 30, 'Outro funcionário']);
        salvarApontamento($this->banco, [1, '2026-11-03', 50, 'Outro dia']);

        $total = somarMinutosDoDia($this->banco, 1, '2026-11-02');

        expect($total)->toBe(100);
    });
});

// Reúne os testes de cadastro com a soma diária.
describe('Cadastro', function () {
    // Confere a soma no limite exato de segunda e sexta-feira.
    test('permite registrar apontamentos até o limite diário', function (
        string $data,
        string $minutosExistentes,
        string $novosMinutos,
        int $limite
    ) {
        registrarApontamento(
            $this->banco,
            1,
            $data,
            $minutosExistentes,
            'Desenvolvimento'
        );

        registrarApontamento(
            $this->banco,
            1,
            $data,
            $novosMinutos,
            'Reunião'
        );

        $total = somarMinutosDoDia($this->banco, 1, $data);

        expect($total)->toBe($limite);
    })->with([
        'segunda-feira: 500 + 40 = 540' => ['2026-11-02', '500', '40', 540],
        'sexta-feira: 450 + 30 = 480' => ['2026-11-06', '450', '30', 480],
    ]);

    // Tenta ultrapassar o limite e confere que o total foi preservado.
    test('rejeita apontamento que faz a soma ultrapassar o limite', function (
        string $data,
        string $minutosExistentes,
        string $novosMinutos,
        int $totalAnterior
    ) {
        registrarApontamento(
            $this->banco,
            1,
            $data,
            $minutosExistentes,
            'Desenvolvimento'
        );

        expect(function () use ($data, $novosMinutos) {
            registrarApontamento(
                $this->banco,
                1,
                $data,
                $novosMinutos,
                'Reunião'
            );
        })->toThrow(
            InvalidArgumentException::class,
            'Soma dos apontamentos excede o limite diário'
        );

        $total = somarMinutosDoDia($this->banco, 1, $data);

        expect($total)->toBe($totalAnterior);
    })->with([
        'segunda-feira: 500 + 41 ultrapassa 540' => ['2026-11-02', '500', '41', 500],
        'sexta-feira: 450 + 31 ultrapassa 480' => ['2026-11-06', '450', '31', 450],
    ]);
});

// Reúne os testes de edição válida e rejeitada.
describe('Edição', function () {
    // Altera duração e descrição, preservando funcionário e data.
    test('edita duração e descrição de um apontamento', function () {
        salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 500, 'Desenvolvimento']
        );

        $id = salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 40, 'Reunião']
        );

        editarApontamento($this->banco, $id, '30', 'Reunião revisada');

        $consulta = $this->banco->prepare('
            SELECT funcionario, data, duracao, descricao
            FROM apontamentos
            WHERE id = ?
        ');
        $consulta->execute([$id]);

        expect($consulta->fetch(PDO::FETCH_NUM))
            ->toBe([1, '2026-11-02', 30, 'Reunião revisada']);

        expect(somarMinutosDoDia($this->banco, 1, '2026-11-02'))
            ->toBe(530);
    });

    // Tenta exceder o limite e confere o registro e a soma originais.
    test('rejeita edição acima do limite e mantém os dados anteriores', function () {
        salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 500, 'Desenvolvimento']
        );

        $id = salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 40, 'Reunião']
        );

        expect(function () use ($id) {
            editarApontamento($this->banco, $id, '41', 'Descrição alterada');
        })->toThrow(
            InvalidArgumentException::class,
            'Soma dos apontamentos excede o limite diário'
        );

        $consulta = $this->banco->prepare('
            SELECT duracao, descricao FROM apontamentos WHERE id = ?
        ');
        $consulta->execute([$id]);

        expect($consulta->fetch(PDO::FETCH_NUM))
            ->toBe([40, 'Reunião']);

        expect(somarMinutosDoDia($this->banco, 1, '2026-11-02'))
            ->toBe(540);
    });

    // Tenta editar um ID ausente e confere a exceção.
    test('rejeita edição de apontamento inexistente', function () {
        editarApontamento(
            $this->banco,
            999,
            '30',
            'Reunião'
        );
    })->throws(
        InvalidArgumentException::class,
        'Apontamento não encontrado'
    );

    // Aumenta a duração até a soma atingir exatamente 540 minutos.
    test('permite editar até exatamente o limite diário', function () {
        salvarApontamento($this->banco, [1, '2026-11-02', 500, 'Desenvolvimento']);
        $id = salvarApontamento($this->banco, [1, '2026-11-02', 30, 'Reunião']);

        editarApontamento($this->banco, $id, '40', 'Reunião');

        expect(somarMinutosDoDia($this->banco, 1, '2026-11-02'))->toBe(540);
    });

    // Tenta edições inválidas e confere que os dados foram preservados.
    test('rejeita edição com dados inválidos e mantém o registro', function (string $duracao, string $descricao, string $mensagem) {
        $id = salvarApontamento($this->banco, [1, '2026-11-02', 40, 'Reunião']);

        expect(fn() => editarApontamento($this->banco, $id, $duracao, $descricao))
            ->toThrow(InvalidArgumentException::class, $mensagem);

        $consulta = $this->banco->prepare('
            SELECT duracao, descricao
            FROM apontamentos
            WHERE id = ?
        ');
        $consulta->execute([$id]);

        expect($consulta->fetch(PDO::FETCH_NUM))
            ->toBe([40, 'Reunião']);

        expect(somarMinutosDoDia($this->banco, 1, '2026-11-02'))->toBe(40);
    })->with([
        'duração zero'    => ['0', 'Reunião', 'Duração inválida'],
        'duração letras'  => ['aaa', 'Reunião', 'Duração inválida'],
        'descrição vazia' => ['30', '   ', 'Apontamentos devem possuir uma descrição'],
    ]);
});

// Reúne os testes de remoção e de ID inexistente.
describe('Exclusão', function () {
    // Exclui pelo ID e confere os registros restantes e a soma.
    test('exclui apenas o apontamento escolhido e libera seus minutos', function () {
        $idMantido = salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 500, 'Desenvolvimento']
        );

        $idExcluido = salvarApontamento(
            $this->banco,
            [1, '2026-11-02', 40, 'Reunião']
        );

        excluirApontamento($this->banco, $idExcluido);

        $idsRestantes = $this->banco
            ->query('SELECT id FROM apontamentos ORDER BY id')
            ->fetchAll(PDO::FETCH_COLUMN);

        expect($idsRestantes)
            ->toHaveCount(1)
            ->toContain($idMantido)
            ->not->toContain($idExcluido);

        expect(somarMinutosDoDia($this->banco, 1, '2026-11-02'))
            ->toBe(500);
    });

    // Tenta excluir um ID ausente e confere a exceção.
    test('rejeita exclusão de apontamento inexistente', function () {
        excluirApontamento(
            $this->banco,
            999
        );
    })->throws(
        InvalidArgumentException::class,
        'Apontamento não encontrado'
    );
});
