# ApontaTest — Demonstração do Pest

## 1. Sobre o projeto

Sistema simples de apontamento de minutos, feito em PHP puro para demonstrar o **Pest**, um framework de testes automatizados. O sistema fornece o cenário para explorar a ferramenta e verificar regras de negócio.

É possível cadastrar, consultar a soma diária, editar a duração e a descrição e excluir apontamentos.

### Regras implementadas

- De segunda a quinta-feira, o limite diário é de **540 minutos**.
- Na sexta-feira, o limite é de **480 minutos**.
- Finais de semana não aceitam apontamentos.
- A soma dos apontamentos é calculada por funcionário e por data, aceitando exatamente o limite diário.
- O funcionário deve ter um identificador inteiro positivo.
- A data deve existir e seguir o formato `YYYY-MM-DD`.
- A duração deve representar minutos inteiros positivos.
- A descrição não pode estar vazia ou conter somente espaços.
- A edição altera apenas duração e descrição e deve respeitar a soma diária.
- A exclusão remove o apontamento escolhido pelo ID e libera seus minutos no total diário.
- Edição e exclusão de um apontamento inexistente são rejeitadas.

Os testes também verificam se as operações rejeitadas por dados inválidos ou por excesso de minutos preservam os registros anteriores.

## 2. O que é o Pest

O Pest é um framework de testes para PHP construído sobre o PHPUnit. Ele permite escrever cenários com `test()` e verificações com `expect()`, facilitando a leitura da intenção de cada teste.

Os testes automatizados ajudam a identificar quando uma alteração no código modifica um comportamento esperado. Neste projeto, por exemplo, uma mudança que rejeite o limite exato da jornada deve ser percebida pelos testes de cadastro e edição.

Uma comparação da escrita de uma verificação:

```php
// PHPUnit
$this->assertSame(480, $limite);

// Pest
expect($limite)->toBe(480);
```

As duas verificações comparam o valor e o tipo do resultado.

### Quadrante de testes ágeis

Nesta demonstração, os testes se enquadram no **Q1: voltados à tecnologia e usados para apoiar o desenvolvimento**.

- Os testes de `jornada.php` são **unitários**: verificam as funções de validação e os limites da jornada.
- Os testes de `banco.php` são de **integração**: verificam as funções junto de um banco SQLite real em memória.

## 3. Recursos do Pest utilizados

| Recurso | Como é utilizado | Onde aparece |
| --- | --- | --- |
| `test()` | Define um cenário com um nome que descreve o comportamento esperado. | `tests/JornadaTest.php` e `tests/ApontamentoTest.php` |
| `expect()->toBe()` | Compara limites, totais e registros com os valores e tipos esperados. | Ambos os arquivos de teste |
| `throws(classe, mensagem)` | Confere a rejeição de entradas ou operações inválidas, identificando a regra pela mensagem. | Ambos os arquivos de teste |
| `expect(function () { ... })->toThrow()` | Confere uma exceção e permite consultar o banco depois para verificar que os dados anteriores foram preservados. | `tests/ApontamentoTest.php`, nos cenários de cadastro e edição |
| `with()` e datasets nomeados | Executam o mesmo procedimento com entradas diferentes, como duração zero, letras e limites de segunda e sexta-feira. | Ambos os arquivos de teste |
| `beforeEach()` | Abre uma conexão SQLite em memória e cria a tabela antes de cada teste. | `tests/ApontamentoTest.php` |
| `describe()` | Agrupa os testes por assunto: Jornada, Duração, Validação de apontamentos, Banco e consultas, Cadastro, Edição e Exclusão. | Ambos os arquivos de teste |
| `toHaveCount()`, `toContain()` e `not` | Conferem quantos registros restaram, qual ID foi mantido e qual foi excluído. | `tests/ApontamentoTest.php`, no bloco Exclusão |

Cada caso de dataset é executado separadamente. Nos testes com banco, cada execução recebe um banco novo e vazio pelo `beforeEach()`, evitando que os dados de um teste interfiram nos demais.

## 4. Estrutura do projeto

| Arquivo | Responsabilidade |
| --- | --- |
| `src/jornada.php` | Valida data, duração e dados do apontamento e determina o limite da jornada. |
| `src/banco.php` | Abre a conexão, cria a tabela, salva registros, soma minutos e executa cadastro, edição e exclusão. |
| `tests/JornadaTest.php` | Testa os limites da jornada e as validações dos dados. |
| `tests/ApontamentoTest.php` | Testa consultas e operações com SQLite em memória. |
| `composer.json` | Declara os requisitos, as dependências e o comando `composer test`. |
| `composer.lock` | Registra as versões das dependências para instalação em outro ambiente. |
| `phpunit.xml` | Configura o carregamento do Composer e a pasta de testes. |
| `Dockerfile` e `compose.yaml` | Definem o ambiente de execução com PHP e Composer. |

O banco dos testes existe somente na memória durante o uso da conexão. Não é necessário criar um arquivo de banco nem executar um script de inicialização antes dos testes.

## 5. Como executar

### Requisito

Docker Desktop instalado e iniciado, com suporte a containers Linux. O PHP 8.4 e o Composer são executados dentro do container.

### Primeira execução

Abra um terminal na pasta do projeto, onde está o arquivo `compose.yaml`, e execute:

```powershell
docker compose build
docker compose run --rm php composer install
docker compose run --rm php composer test
```

- `build` prepara a imagem do ambiente.
- `composer install` instala as dependências em `vendor/`, utilizando as versões registradas em `composer.lock`.
- `composer test` executa o Pest por meio do comando definido em `composer.json`.

### Próximas execuções

Com o ambiente preparado, basta executar:

```powershell
docker compose run --rm php composer test
```

O Pest exibe os cenários executados, os resultados e a quantidade de verificações realizadas. Não é necessário executar `docker compose up` antes desse comando.

## 6. Fora do escopo

Interface web, autenticação, cadastro completo de funcionários, banco persistente em arquivo, mocks e relatório de cobertura de código. O foco desta demonstração é o uso dos recursos do Pest para testar as regras e operações implementadas.

## Referências

- [Documentação do Pest](https://pestphp.com/docs/writing-tests)
- [Comparações e expectativas](https://pestphp.com/docs/expectations)
- [Datasets](https://pestphp.com/docs/datasets)
- [Hooks e preparação dos testes](https://pestphp.com/docs/hooks)
- [Verificação de exceções](https://pestphp.com/docs/exceptions)
