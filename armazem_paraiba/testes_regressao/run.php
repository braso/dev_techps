<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute este arquivo via CLI.\n");
    exit(1);
}

$argumentos = getopt('', ['integration', 'endosso-runtime', 'json', 'help']);
if (isset($argumentos['help'])) {
    $ajuda = <<<TXT
Uso:
  php armazem_paraiba/testes_regressao/run.php [--integration] [--endosso-runtime] [--json]

Flags:
  --integration       Executa os testes que dependem de banco e dados reais.
  --endosso-runtime   Inclui endosso.php e valida helpers de runtime.
  --json              Exibe o resumo final em JSON.
TXT;
    fwrite(STDOUT, $ajuda . "\n");
    exit(0);
}

$moduleDir = __DIR__;
$appDir = dirname($moduleDir);
$rootDir = dirname($appDir);

$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['DOCUMENT_ROOT'] = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname($rootDir);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require_once $appDir . '/load_env.php';

function runnerFail(string $message, int $code = 1): void
{
    fwrite(STDERR, $message . "\n");
    exit($code);
}

function runnerAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runnerAssertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        $exportExpected = var_export($expected, true);
        $exportActual = var_export($actual, true);
        throw new RuntimeException($message . "\nEsperado: {$exportExpected}\nRecebido: {$exportActual}");
    }
}

function runnerAssertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . "\nTrecho esperado nao encontrado: {$needle}");
    }
}

function runnerAssertMatches(string $pattern, string $value, string $message): void
{
    if (!preg_match($pattern, $value)) {
        throw new RuntimeException($message . "\nValor recebido: {$value}");
    }
}

function runnerSkip(string $message): void
{
    throw new class($message) extends RuntimeException {
    };
}

function runnerReadFile(string $filePath): string
{
    if (!is_file($filePath)) {
        throw new RuntimeException("Arquivo nao encontrado: {$filePath}");
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new RuntimeException("Nao foi possivel ler o arquivo: {$filePath}");
    }
    return $content;
}

function runnerFetchOne(string $sql): ?array
{
    $result = query($sql);
    if (is_string($result) || !$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    return is_array($row) ? $row : null;
}

function runnerFetchAll(string $sql): array
{
    $result = query($sql);
    if (is_string($result) || !$result) {
        return [];
    }
    return mysqli_fetch_all($result, MYSQLI_ASSOC) ?: [];
}

function runnerPrintLine(string $text): void
{
    fwrite(STDOUT, $text . "\n");
}

function runnerRunCase(string $suite, string $name, callable $callback, array &$summary): void
{
    try {
        $callback();
        $summary['passed']++;
        $summary['tests'][] = [
            'suite' => $suite,
            'name' => $name,
            'status' => 'pass',
        ];
        runnerPrintLine("[PASS] {$suite} :: {$name}");
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== '' && str_starts_with($exception->getMessage(), 'SKIP: ')) {
            $summary['skipped']++;
            $summary['tests'][] = [
                'suite' => $suite,
                'name' => $name,
                'status' => 'skip',
                'message' => substr($exception->getMessage(), 6),
            ];
            runnerPrintLine("[SKIP] {$suite} :: {$name} :: " . substr($exception->getMessage(), 6));
            return;
        }
        $summary['failed']++;
        $summary['tests'][] = [
            'suite' => $suite,
            'name' => $name,
            'status' => 'fail',
            'message' => $exception->getMessage(),
        ];
        runnerPrintLine("[FAIL] {$suite} :: {$name}");
        runnerPrintLine($exception->getMessage());
    } catch (Throwable $exception) {
        $summary['failed']++;
        $summary['tests'][] = [
            'suite' => $suite,
            'name' => $name,
            'status' => 'error',
            'message' => $exception->getMessage(),
        ];
        runnerPrintLine("[ERROR] {$suite} :: {$name}");
        runnerPrintLine($exception->getMessage());
    }
}

function runnerRequireDatabase(array $requiredKeys): void
{
    if (!function_exists('mysqli_connect')) {
        runnerFail('A extensao mysqli nao esta disponivel neste ambiente.');
    }

    $missingKeys = [];
    foreach ($requiredKeys as $keyName) {
        $value = $_ENV[$keyName] ?? getenv($keyName);
        if ($value === false || $value === null || $value === '') {
            $missingKeys[] = $keyName;
        }
    }

    if (!empty($missingKeys)) {
        runnerFail('Variaveis de ambiente ausentes: ' . implode(', ', $missingKeys));
    }

    $connection = @mysqli_connect(
        (string)($_ENV['DB_HOST'] ?? getenv('DB_HOST')),
        (string)($_ENV['DB_USER'] ?? getenv('DB_USER')),
        (string)($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD')),
        (string)($_ENV['DB_NAME'] ?? getenv('DB_NAME'))
    );

    if (!$connection) {
        runnerFail('Falha ao conectar no banco para executar os testes: ' . mysqli_connect_error());
    }

    mysqli_close($connection);
}

function runnerLoadAppCore(): void
{
    global $appDir;
    $interno = true;
    $_POST['interno'] = 1;
    ob_start();
    require_once $appDir . '/funcoes_ponto.php';
    ob_end_clean();
}

function runnerLoadEndossoRuntime(): void
{
    global $appDir;
    $interno = true;
    $_POST['interno'] = 1;
    ob_start();
    require_once $appDir . '/endosso.php';
    ob_end_clean();
}

function runnerSourceContracts(): void
{
    global $appDir;

    $contracts = [
        'check_permission.php' => [
            '/batida_ponto.php',
            '/espelho_ponto.php',
            '/ajuste_pontofuncionario.php',
        ],
        // Secoes e paginas do menu ficam em menu_estrutura.php (fonte unica). menu.php e o
        // cabecalho da assinatura so desenham, e o cadastro de perfil sincroniza menu_item
        // lendo esse arquivo — os tres precisam continuar apontando para ele.
        'menu_estrutura.php' => [
            '/cadastro_endosso.php',
            '/endosso.php',
            '/espelho_ponto.php',
            '/paineis/endosso.php',
            '/ajuste_pontofuncionario.php',
        ],
        'menu.php' => [
            'menu_estrutura_do_nivel(',
        ],
        'assinatura/componentes/layout_header.php' => [
            'menu_estrutura_do_nivel(',
        ],
        'cadastro_perfil_acesso.php' => [
            '/menu_estrutura.php',
        ],
        'batida_ponto.php' => [
            'verificaPermissao(\'/batida_ponto.php\')',
            '/ajuste_pontofuncionario.php',
        ],
        'espelho_ponto.php' => [
            'include "funcoes_ponto.php"',
            'verificaPermissao(\'/espelho_ponto.php\')',
            'buscarEspelho()',
            'ajuste_ponto.php',
            'ajuste_pontofuncionario.php',
            'montarEndossoMes(',
            'diaDetalhePonto(',
            'setTotalResumo(',
            'somarTotais(',
            'montarTabelaPonto(',
            'MASCARA_PERIODO_MES_ATUAL',
        ],
        'relatorio_pontos.php' => [
            'buscarEspelho()',
            'MASCARA_PERIODO_MES_ATUAL',
        ],
        'endosso.php' => [
            'include "funcoes_ponto.php"',
            'verificaPermissao(\'/endosso.php\')',
            'buscarEndosso()',
            'calcularHorasAPagar(',
            'montarEndossoMes(',
            'imprimir_relatorio()',
        ],
        'paineis/endosso.php' => [
            'require __DIR__."/../funcoes_ponto.php"',
            'verificaPermissao(\'/paineis/endosso.php\')',
            'carregarJS(',
            'json_decode(',
            'arquivos/endossos',
        ],
        'funcoes_ponto.php' => [
            'function calcularHorasAPagar',
            'function calcularJornadaPrevista',
            'function calcularAbono',
            'function calcularAdicNot',
            'function diaDetalhePonto',
            'function montarEndossoMes',
            'function setTotalResumo',
            'function somarTotais',
            'trocadeturno/helpers_troca_turno.php',
            'tt_jornadaTrocaDia(',
        ],
        'trocadeturno/helpers_troca_turno.php' => [
            'function tt_jornadaEntidadeDia',
            'function tt_buscarTrocasAprovadasDia',
            'function tt_jornadaTrocaDia',
            'function tt_validarConflitosTroca',
        ],
        'trocadeturno/gestao_troca_turno.php' => [
            'tt_validarConflitosTroca(',
            'tt_trocaPodeSerExcluida(',
            'function excluirTrocaAprovada()',
        ],
        'trocadeturno/solicitar_troca_turno.php' => [
            'tt_ensureSchema()',
            'tt_redirecionarComErro(',
            'function excluirSolicitacao()',
        ],
    ];

    foreach ($contracts as $relativePath => $needles) {
        $filePath = $appDir . DIRECTORY_SEPARATOR . $relativePath;
        $content = runnerReadFile($filePath);
        foreach ($needles as $needle) {
            runnerAssertContains($needle, $content, "Contrato quebrado em {$relativePath}");
        }
    }

    // O dispatcher do contex20/funcoes.php executa a acao do POST durante o include do
    // conecta.php; por isso o helper de troca precisa ser carregado antes do conecta.
    $funcoesPonto = runnerReadFile($appDir . '/funcoes_ponto.php');
    $posHelperTroca = strpos($funcoesPonto, '__DIR__."/trocadeturno/helpers_troca_turno.php"');
    $posConecta = strpos($funcoesPonto, '__DIR__."/conecta.php"');
    runnerAssertTrue(
        $posHelperTroca !== false && $posConecta !== false && $posHelperTroca < $posConecta,
        'O helper de troca de turno deve ser incluido antes do conecta.php em funcoes_ponto.php.'
    );
    runnerAssertContains(
        'function_exists("tt_jornadaTrocaDia")',
        $funcoesPonto,
        'diaDetalhePonto deve tolerar a ausencia do helper de troca de turno.'
    );

    // Consulta de ponto/relatorio limitada ao mes atual (pode ver o mes inteiro, nao so ate hoje).
    foreach (['espelho_ponto.php', 'relatorio_pontos.php'] as $arquivoPeriodo) {
        $conteudoPeriodo = runnerReadFile($appDir . '/' . $arquivoPeriodo);
        runnerAssertContains(
            'MASCARA_PERIODO_MES_ATUAL',
            $conteudoPeriodo,
            "{$arquivoPeriodo} deve permitir consultar o mes atual inteiro."
        );
        runnerAssertTrue(
            strpos($conteudoPeriodo, '$_POST["busca_periodo"][1] > date("Y-m-d")') === false,
            "{$arquivoPeriodo} ainda limita a consulta ao dia de hoje."
        );
    }
}

function runnerCoreFunctionTests(array &$summary): void
{
    runnerRunCase('core', 'operarHorarios soma corretamente', function (): void {
        runnerAssertSame('01:30', operarHorarios(['01:00', '00:30'], '+'), 'Soma de horarios incorreta.');
        runnerAssertSame('00:00', operarHorarios(['00:00', '00:00'], '+'), 'Soma zero incorreta.');
    }, $summary);

    runnerRunCase('core', 'calcularJornadaPrevista aplica feriado e abono', function (): void {
        runnerAssertSame('00:00', calcularJornadaPrevista('08:00', true), 'Feriado deve zerar a jornada.');
        runnerAssertSame('07:00', calcularJornadaPrevista('08:00', false, '01:00'), 'Abono deve subtrair da jornada.');
        runnerAssertSame('00:00', calcularJornadaPrevista('08:00', false, '09:00'), 'Abono maior que jornada deve zerar.');
    }, $summary);

    runnerRunCase('core', 'calcularAbono respeita saldo negativo', function (): void {
        runnerAssertSame('02:00', calcularAbono('-03:00', '02:00'), 'Abono deve respeitar teto do saldo negativo.');
        runnerAssertSame('01:00', calcularAbono('-01:00', '02:00'), 'Abono deve limitar ao saldo restante.');
        runnerAssertSame('00:00', calcularAbono('03:00', '02:00'), 'Saldo positivo nao gera abono.');
    }, $summary);

    runnerRunCase('core', 'getSaldoDiario calcula saldo liquido', function (): void {
        runnerAssertSame('02:00', getSaldoDiario('08:00', '10:00'), 'Saldo positivo calculado incorretamente.');
        runnerAssertSame('-02:00', getSaldoDiario('08:00', '06:00'), 'Saldo negativo calculado incorretamente.');
    }, $summary);

    runnerRunCase('core', 'calcularHorasAPagar prioriza HE100 e respeita limite total', function (): void {
        $resultado = calcularHorasAPagar('03:00', '03:00', '01:00', '03:00', '02:00');
        runnerAssertSame(['00:00', '02:00'], $resultado, 'HE100 deve consumir o limite total primeiro.');

        $resultado = calcularHorasAPagar('03:00', '03:00', '01:00', '01:00', '02:00');
        runnerAssertSame(['01:00', '01:00'], $resultado, 'HE100 e HE50 devem dividir o limite restante.');
    }, $summary);

    runnerRunCase('core', 'calcularHorasAPagar trata saldo negativo', function (): void {
        $resultado = calcularHorasAPagar('-03:00', '03:00', '01:00', '01:00', '02:00', 'nao');
        runnerAssertSame(['00:00', '00:00'], $resultado, 'Saldo negativo sem permissao nao deve pagar.');

        $resultado = calcularHorasAPagar('-03:00', '03:00', '01:00', '01:00', '02:00', 'sim');
        runnerAssertSame(['00:00', '02:00'], $resultado, 'Saldo negativo com permissao paga apenas HE100 (prioridade).');
    }, $summary);

    runnerRunCase('core', 'matriz de pagamento por saldo e prioridade', function (): void {
        $cenarios = [
            [
                'nome' => 'saldo positivo suficiente',
                'saldoPeriodo' => '14:00',
                'saldoBruto' => '14:00',
                'he50' => '04:00',
                'he100' => '10:00',
                'limite' => '14:00',
                'pagarHEExComPerNeg' => 'nao',
                'esperadoPagamento' => ['04:00', '10:00'],
                'esperadoSaldoFinal' => '00:00',
            ],
            [
                'nome' => 'saldo positivo insuficiente para he100 integral',
                'saldoPeriodo' => '02:00',
                'saldoBruto' => '02:00',
                'he50' => '01:00',
                'he100' => '10:00',
                'limite' => '02:00',
                'pagarHEExComPerNeg' => 'nao',
                'esperadoPagamento' => ['00:00', '02:00'],
                'esperadoSaldoFinal' => '00:00',
            ],
            [
                'nome' => 'saldo negativo sem permissao de pagamento',
                'saldoPeriodo' => '-08:00',
                'saldoBruto' => '-08:00',
                'he50' => '01:00',
                'he100' => '10:00',
                'limite' => '02:00',
                'pagarHEExComPerNeg' => 'nao',
                'esperadoPagamento' => ['00:00', '00:00'],
                'esperadoSaldoFinal' => '-08:00',
            ],
            [
                'nome' => 'saldo negativo com permissao de pagamento',
                'saldoPeriodo' => '-08:00',
                'saldoBruto' => '-08:00',
                'he50' => '01:00',
                'he100' => '10:00',
                'limite' => '10:00',
                'pagarHEExComPerNeg' => 'sim',
                'esperadoPagamento' => ['00:00', '10:00'],
                'esperadoSaldoFinal' => '-18:00',
            ],
            [
                'nome' => 'banco de horas com saldo anterior positivo',
                'saldoPeriodo' => '04:00',
                'saldoBruto' => '04:00',
                'he50' => '01:00',
                'he100' => '10:00',
                'limite' => '10:00',
                'pagarHEExComPerNeg' => 'sim',
                'esperadoPagamento' => ['00:00', '10:00'],
                'esperadoSaldoFinal' => '-06:00',
            ],
            [
                'nome' => 'banco de horas com saldo anterior e periodo suficiente',
                'saldoPeriodo' => '18:00',
                'saldoBruto' => '18:00',
                'he50' => '04:00',
                'he100' => '10:00',
                'limite' => '10:00',
                'pagarHEExComPerNeg' => 'sim',
                'esperadoPagamento' => ['00:00', '10:00'],
                'esperadoSaldoFinal' => '08:00',
            ],
            [
                'nome' => 'desconto por atrasos nao justificados',
                'saldoPeriodo' => '10:00',
                'saldoBruto' => '10:00',
                'he50' => '01:00',
                'he100' => '04:00',
                'limite' => '10:00',
                'pagarHEExComPerNeg' => 'sim',
                'descontoAtrasos' => '02:00',
                'esperadoPagamento' => ['01:00', '04:00'],
                'esperadoSaldoFinal' => '03:00',
            ],
        ];

        foreach ($cenarios as $cenario) {
            $pagamento = calcularHorasAPagar(
                $cenario['saldoPeriodo'],
                $cenario['saldoBruto'],
                $cenario['he50'],
                $cenario['he100'],
                $cenario['limite'],
                $cenario['pagarHEExComPerNeg']
            );

            runnerAssertSame(
                $cenario['esperadoPagamento'],
                $pagamento,
                'Pagamento divergente no cenario: ' . $cenario['nome']
            );

            $saldoFinal = $cenario['saldoBruto'];
            if ($pagamento[0] !== '00:00') {
                $saldoFinal = operarHorarios([$saldoFinal, $pagamento[0]], '-');
            }
            if ($pagamento[1] !== '00:00') {
                $saldoFinal = operarHorarios([$saldoFinal, $pagamento[1]], '-');
            }

            runnerAssertSame(
                $cenario['esperadoSaldoFinal'],
                $saldoFinal,
                'Saldo final divergente no cenario: ' . $cenario['nome']
            );
        }
    }, $summary);
}

function runnerOptionalEndossoRuntimeTests(array &$summary): void
{
    if (!isset($_SERVER['argv']) || !in_array('--endosso-runtime', $_SERVER['argv'], true)) {
        return;
    }

    runnerRunCase('endosso-runtime', 'helpers de endosso carregam', function (): void {
        runnerAssertSame('2026-05-01', endosso_mes_sql('2026-05'), 'Conversao mes/ano incorreta.');
        runnerAssertMatches('/^\d{4}-\d{2}-01$/', endosso_mes_sql('invalid'), 'Fallback de mes invalido incorreto.');
    }, $summary);

    runnerRunCase('endosso-runtime', 'condicao de empresa usa coluna quando disponivel', function (): void {
        if (!function_exists('endosso_has_empresa_col')) {
            runnerSkip('SKIP: helper endosso_has_empresa_col nao esta disponivel.');
        }

        if (endosso_has_empresa_col()) {
            $condicao = endosso_empresa_cond(7);
            runnerAssertContains('endo_nb_empresa = 7', $condicao, 'Condicao de empresa nao foi montada corretamente.');
        } else {
            runnerAssertSame('', endosso_empresa_cond(7), 'Sem coluna de empresa a condicao deve ser vazia.');
        }
    }, $summary);

    runnerRunCase('endosso-runtime', 'ids de endosso por mes retornam array unico', function (): void {
        $empresa = runnerFetchOne("SELECT empr_nb_id FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_nb_id ASC LIMIT 1");
        if (empty($empresa['empr_nb_id'])) {
            runnerSkip('SKIP: nao foi encontrada empresa ativa para validar ids de endosso.');
        }

        $registro = runnerFetchOne(
            "SELECT endo_nb_entidade, endo_tx_mes FROM endosso WHERE endo_tx_status = 'ativo' AND endo_nb_empresa = " . (int)$empresa['empr_nb_id'] . " ORDER BY endo_tx_mes DESC LIMIT 1"
        );
        if (empty($registro['endo_nb_entidade']) || empty($registro['endo_tx_mes'])) {
            runnerSkip('SKIP: nao existe endosso para a empresa selecionada.');
        }

        $ids = endosso_ids_mes((int)$empresa['empr_nb_id'], (string)$registro['endo_tx_mes']);
        runnerAssertTrue(is_array($ids), 'endosso_ids_mes deve retornar array.');
        runnerAssertTrue(in_array((int)$registro['endo_nb_entidade'], $ids, true), 'endosso_ids_mes deve conter a entidade do registro selecionado.');
        runnerAssertSame(array_values(array_unique($ids)), $ids, 'endosso_ids_mes deve retornar ids unicos e reindexados.');
    }, $summary);
}

function runnerIntegrationTests(array &$summary): void
{
    global $appDir;

    runnerRunCase('integration', 'diaDetalhePonto retorna estrutura completa', function (): void {
        $motorista = runnerFetchOne(
            "SELECT * FROM entidade WHERE enti_tx_status = 'ativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário', 'Terceirizado', 'Tercerizado') ORDER BY enti_nb_id ASC LIMIT 1"
        );
        if (empty($motorista)) {
            runnerSkip('SKIP: nao foi encontrado funcionario elegivel para testar diaDetalhePonto.');
        }

        $dataTeste = !empty($motorista['enti_tx_admissao']) ? substr((string)$motorista['enti_tx_admissao'], 0, 10) : date('Y-m-d');
        $resultado = diaDetalhePonto($motorista, $dataTeste);

        $chavesObrigatorias = [
            'data',
            'diaSemana',
            'inicioJornada',
            'fimJornada',
            'jornadaPrevista',
            'diffJornadaEfetiva',
            'diffSaldo',
            'adicionalNoturno',
        ];

        foreach ($chavesObrigatorias as $chave) {
            runnerAssertTrue(array_key_exists($chave, $resultado), 'Chave ausente em diaDetalhePonto: ' . $chave);
        }

        runnerAssertTrue(is_string($resultado['diffSaldo']), 'diffSaldo deve ser string.');
        runnerAssertTrue(is_string($resultado['jornadaPrevista']), 'jornadaPrevista deve ser string.');
    }, $summary);

    runnerRunCase('integration', 'montarEndossoMes nao quebra para registro real', function (): void {
        $registro = runnerFetchOne(
            "SELECT endo_nb_entidade, endo_tx_ate FROM endosso WHERE endo_tx_status = 'ativo' ORDER BY endo_tx_ate DESC LIMIT 1"
        );
        if (empty($registro['endo_nb_entidade']) || empty($registro['endo_tx_ate'])) {
            runnerSkip('SKIP: nao foi encontrado endosso ativo para testar montarEndossoMes.');
        }

        $motorista = runnerFetchOne(
            "SELECT * FROM entidade WHERE enti_nb_id = " . (int)$registro['endo_nb_entidade'] . " LIMIT 1"
        );
        if (empty($motorista)) {
            runnerSkip('SKIP: nao foi possivel recuperar a entidade do endosso selecionado.');
        }

        $dataMes = new DateTime(substr((string)$registro['endo_tx_ate'], 0, 7) . '-01');
        $resultado = montarEndossoMes($dataMes, $motorista);

        runnerAssertTrue(is_array($resultado), 'montarEndossoMes deve retornar array.');
        if (!empty($resultado)) {
            runnerAssertTrue(array_key_exists('totalResumo', $resultado), 'Resultado de montarEndossoMes deve conter totalResumo quando ha dados.');
            runnerAssertTrue(array_key_exists('endo_tx_pontos', $resultado), 'Resultado de montarEndossoMes deve conter endo_tx_pontos quando ha dados.');
        }
    }, $summary);

    runnerRunCase('integration', 'contratos de permissao e menu continuam coerentes', function (): void {
        global $appDir;

        $checkPermission = runnerReadFile($appDir . '/check_permission.php');
        runnerAssertContains('/batida_ponto.php', $checkPermission, 'Regra especial de batida foi removida.');
        runnerAssertContains('/espelho_ponto.php', $checkPermission, 'Regra especial de espelho foi removida.');

        $menu = runnerReadFile($appDir . '/menu_estrutura.php');
        runnerAssertContains('/endosso.php', $menu, 'Menu consultando endosso foi alterado.');
        runnerAssertContains('/espelho_ponto.php', $menu, 'Menu de espelho foi alterado.');
        runnerAssertContains('/paineis/endosso.php', $menu, 'Menu do painel de endosso foi alterado.');

        // menu.php e o cabecalho da assinatura nao tem mais lista propria: leem a mesma estrutura.
        $menuRender = runnerReadFile($appDir . '/menu.php');
        runnerAssertContains('menu_estrutura_do_nivel(', $menuRender, 'menu.php deixou de usar a estrutura compartilhada do menu.');
        $layoutHeader = runnerReadFile($appDir . '/assinatura/componentes/layout_header.php');
        runnerAssertContains('menu_estrutura_do_nivel(', $layoutHeader, 'Header de assinatura deixou de usar a estrutura compartilhada do menu.');
    }, $summary);

    runnerDiaSemanaPercentualTests($summary);
}

/**
 * DSR e percentual por dia da semana.
 *
 * A regra antiga: dia sem jornada prevista pagava tudo na faixa extraordinaria
 * (he100). Era isso que fazia um sabado de folga pagar 100%, sem jeito de dizer
 * que naquele parametro ele vale 50%.
 *
 * Os casos batem ponto de verdade num dia sem jornada prevista, chamam o
 * diaDetalhePonto e conferem em qual faixa as horas cairam. No fim devolvem a
 * configuracao do parametro e apagam o ponto que criaram.
 */
function runnerDiaSemanaPercentualTests(array &$summary): void
{
    runnerRunCase('integration', 'dia da semana: tabela de configuracao existe e e idempotente', function (): void {
        for ($vez = 0; $vez < 2; $vez++) {
            query("CREATE TABLE IF NOT EXISTS parametro_dia_semana (
                pdsm_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                pdsm_nb_parametro INT NOT NULL,
                pdsm_nb_diaSemana TINYINT NOT NULL,
                pdsm_tx_ehDsr ENUM('sim','nao') NOT NULL DEFAULT 'nao',
                pdsm_nb_percTrabalhado TINYINT UNSIGNED NULL DEFAULT NULL,
                UNIQUE KEY uq_parametro_dia (pdsm_nb_parametro, pdsm_nb_diaSemana),
                KEY idx_parametro (pdsm_nb_parametro)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        runnerAssertTrue(
            (bool) runnerFetchOne("SHOW TABLES LIKE 'parametro_dia_semana'"),
            'A tabela parametro_dia_semana nao foi criada.'
        );
    }, $summary);

    /* Um funcionario ativo com parametro, para bater ponto num dia sem
       jornada prevista. */
    $alvo = runnerFetchOne(
        "SELECT en.enti_nb_id, en.enti_tx_matricula, en.enti_nb_parametro
            FROM entidade en
            JOIN parametro p ON p.para_nb_id = en.enti_nb_parametro
            WHERE en.enti_tx_status = 'ativo'
              AND en.enti_tx_matricula IS NOT NULL
              AND en.enti_tx_matricula <> ''
            ORDER BY en.enti_nb_id ASC
            LIMIT 1"
    );

    if (empty($alvo)) {
        runnerRunCase('integration', 'dia da semana: percentual do dia de folga', function (): void {
            runnerSkip('SKIP: nenhum funcionario ativo com parametro e matricula no banco.');
        }, $summary);
        return;
    }

    $idParametro = intval($alvo['enti_nb_parametro']);
    $matricula = (string) $alvo['enti_tx_matricula'];

    /* Snapshot da configuracao inteira deste parametro, uma vez. Antes cada
       caso guardava e devolvia a sua propria linha; interleavados, um devolvia
       o estado que o outro tinha criado e a suite deixava lixo no banco. */
    $snapshot = runnerFetchAll(
        "SELECT pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado
            FROM parametro_dia_semana WHERE pdsm_nb_parametro = {$idParametro}
            ORDER BY pdsm_nb_diaSemana"
    );

    $devolverSnapshot = function () use ($idParametro, $snapshot): void {
        query("DELETE FROM parametro_dia_semana WHERE pdsm_nb_parametro = {$idParametro}");
        foreach ($snapshot as $linha) {
            $p = $linha['pdsm_nb_percTrabalhado'];
            query(
                "INSERT INTO parametro_dia_semana (pdsm_nb_parametro, pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado)"
                . " VALUES ({$idParametro}, " . intval($linha['pdsm_nb_diaSemana']) . ", '"
                . ($linha['pdsm_tx_ehDsr'] === 'sim' ? 'sim' : 'nao') . "', "
                . ($p === null ? 'NULL' : intval($p)) . ")"
            );
        }
        carregarDiasSemanaParametro($idParametro, true);
    };

    $motoristaDe = function () use ($alvo): array {
        $m = runnerFetchOne(
            "SELECT * FROM entidade
                LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
                LEFT JOIN escala ON para_nb_id = esca_nb_parametro
                WHERE enti_nb_id = " . intval($alvo['enti_nb_id']) . "
                LIMIT 1"
        );
        return is_array($m) ? $m : [];
    };

    /* Procura uma data sem jornada prevista: e nela que a regra atua. */
    $dataFolga = null;
    $diaSemanaFolga = null;
    $motoristaBase = $motoristaDe();
    for ($i = 0; $i < 21; $i++) {
        $candidata = date('Y-m-d', strtotime('2026-06-01 +' . $i . ' day'));
        $r = diaDetalhePonto($motoristaBase, $candidata);
        if (($r['jornadaPrevista'] ?? '') === '00:00') {
            $dataFolga = $candidata;
            $diaSemanaFolga = intval(date('w', strtotime($candidata)));
            break;
        }
    }

    if ($dataFolga === null) {
        runnerRunCase('integration', 'dia da semana: percentual do dia de folga', function (): void {
            runnerSkip('SKIP: nao foi encontrado dia sem jornada prevista para o funcionario.');
        }, $summary);
        return;
    }

    $gravarDia = function (?int $perc, string $ehDsr) use ($idParametro, $diaSemanaFolga): void {
        query(
            "INSERT INTO parametro_dia_semana (pdsm_nb_parametro, pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado)
                VALUES ({$idParametro}, {$diaSemanaFolga}, '{$ehDsr}', " . ($perc === null ? 'NULL' : intval($perc)) . ")
                ON DUPLICATE KEY UPDATE pdsm_tx_ehDsr = VALUES(pdsm_tx_ehDsr),
                    pdsm_nb_percTrabalhado = VALUES(pdsm_nb_percTrabalhado)"
        );
        /* O carregarDiasSemanaParametro() tem cache por parametro, porque o
           espelho pergunta uma vez por dia do mes. Quem grava pelo
           salvarDiasSemanaParametro() tem o cache invalidado de brinde; este
           teste escreve direto no SQL, entao precisa invalidar na mao. */
        carregarDiasSemanaParametro($idParametro, true);
    };

    $limparPonto = function () use ($matricula, $dataFolga): void {
        query(
            "DELETE FROM ponto WHERE pont_tx_matricula = '" . $matricula . "'"
            . " AND DATE(pont_tx_data) = '" . $dataFolga . "'"
            . " AND pont_tx_descricao = 'TESTE_REGRESSAO_DIA_SEMANA'"
        );
    };

    $limparPonto();
    foreach ([['1', '08:00:00'], ['2', '16:00:00']] as $batida) {
        query(
            "INSERT INTO ponto (pont_tx_matricula, pont_tx_data, pont_tx_tipo, pont_tx_status, pont_tx_descricao, pont_tx_dataCadastro)"
            . " VALUES ('" . $matricula . "', '" . $dataFolga . " " . $batida[1] . "', '" . $batida[0] . "', 'ativo', 'TESTE_REGRESSAO_DIA_SEMANA', NOW())"
        );
    }

    try {
        runnerRunCase('integration', 'dia da semana: sem percentual paga a faixa extraordinaria', function () use ($gravarDia, $motoristaDe, $dataFolga): void {
            $gravarDia(null, 'nao');
            $r = diaDetalhePonto($motoristaDe(), $dataFolga);

            runnerAssertSame('00:00', $r['jornadaPrevista'], 'O dia escolhido deveria estar sem jornada prevista.');
            runnerAssertTrue($r['he100'] !== '00:00', 'Sem percentual, as horas deveriam cair em he100.');
            runnerAssertSame('00:00', $r['he50'], 'Sem percentual, he50 deveria ficar zerado.');
        }, $summary);

        runnerRunCase('integration', 'dia da semana: a 50% paga a faixa comum, nao 100%', function () use ($gravarDia, $motoristaDe, $dataFolga): void {
            $gravarDia(null, 'nao');
            $antesCfg = diaDetalhePonto($motoristaDe(), $dataFolga);

            $gravarDia(50, 'nao');
            $depois = diaDetalhePonto($motoristaDe(), $dataFolga);

            runnerAssertSame($antesCfg['he100'], $depois['he50'], 'As horas de he100 deveriam ter ido para he50.');
            runnerAssertSame('00:00', $depois['he100'], 'he100 deveria ficar zerado com o dia a 50%.');
            runnerAssertSame(
                $antesCfg['diffSaldo'],
                $depois['diffSaldo'],
                'O saldo do dia nao pode mudar: muda a faixa, nao o total.'
            );
        }, $summary);

        runnerRunCase('integration', 'dia da semana: a 100% continua na faixa extraordinaria', function () use ($gravarDia, $motoristaDe, $dataFolga): void {
            $gravarDia(100, 'nao');
            $r = diaDetalhePonto($motoristaDe(), $dataFolga);

            runnerAssertTrue($r['he100'] !== '00:00', 'Com 100%, as horas deveriam continuar em he100.');
            runnerAssertSame('00:00', $r['he50'], 'Com 100%, he50 deveria ficar zerado.');
        }, $summary);

        runnerRunCase('integration', 'dia da semana: DSR e percentual saem por parametroDiaSemana', function () use ($gravarDia, $motoristaDe, $dataFolga, $diaSemanaFolga): void {
            $gravarDia(50, 'sim');

            /* Sem a configuracao pre-carregada: a funcao tem de buscar sozinha. */
            $m = $motoristaDe();
            unset($m['diasSemana']);
            $cfg = parametroDiaSemana($m, $dataFolga);

            runnerAssertSame($diaSemanaFolga, $cfg['diaSemana'], 'O dia da semana resolvido esta errado.');
            runnerAssertTrue($cfg['ehDsr'] === true, 'parametroDiaSemana deveria marcar o dia como DSR.');
            runnerAssertSame(50, $cfg['perc'], 'parametroDiaSemana deveria devolver o percentual do dia.');

            $gravarDia(50, 'nao');
        }, $summary);

        runnerRunCase('integration', 'dia da semana: retorno do calculo mantem a forma da linha', function () use ($motoristaDe, $dataFolga): void {
            $r = diaDetalhePonto($motoristaDe(), $dataFolga);
            foreach (['ehDsr', 'percDiaSemana', 'diaSemanaConfig'] as $chave) {
                runnerAssertTrue(
                    !array_key_exists($chave, $r),
                    'A chave ' . $chave . ' entrou no retorno do diaDetalhePonto: o espelho e o '
                    . 'endosso transformam esse array em linha e ela viraria coluna.'
                );
            }
        }, $summary);

        runnerRunCase('integration', 'dia da semana: dia COM jornada prevista nao muda de faixa', function () use ($gravarDia, $motoristaDe, $idParametro): void {
            /* Procura um dia escalado e confere que o percentual de outro dia
               da semana nao interfere nele. */
            $motorista = $motoristaDe();
            $dataCheia = null;
            for ($i = 0; $i < 21; $i++) {
                $candidata = date('Y-m-d', strtotime('2026-06-01 +' . $i . ' day'));
                $r = diaDetalhePonto($motorista, $candidata);
                if (($r['jornadaPrevista'] ?? '00:00') !== '00:00') {
                    $dataCheia = $candidata;
                    break;
                }
            }
            if ($dataCheia === null) {
                runnerSkip('SKIP: o funcionario nao tem dia com jornada prevista no periodo.');
            }

            $diaCheio = intval(date('w', strtotime($dataCheia)));
            query(
                "INSERT INTO parametro_dia_semana (pdsm_nb_parametro, pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado)
                    VALUES ({$idParametro}, {$diaCheio}, 'nao', 50)
                    ON DUPLICATE KEY UPDATE pdsm_tx_ehDsr = 'nao', pdsm_nb_percTrabalhado = 50"
            );
            carregarDiasSemanaParametro($idParametro, true);

            $r = diaDetalhePonto($motoristaDe(), $dataCheia);
            runnerAssertTrue(
                ($r['jornadaPrevista'] ?? '00:00') !== '00:00',
                'O dia escolhido deveria ter jornada prevista.'
            );
            /* Nao devolve nada aqui: o snapshot do inicio cobre tudo. */
        }, $summary);
    } finally {
        $devolverSnapshot();
        $limparPonto();
    }

    runnerRunCase('integration', 'dia da semana: teste devolveu a configuracao e limpou o ponto', function () use ($idParametro, $snapshot, $matricula, $dataFolga): void {
        /* A tabela tem de voltar exatamente ao estado do snapshot: a suite nao
           pode deixar configuracao de parametro inventada no banco. */
        $atual = runnerFetchAll(
            "SELECT pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado
                FROM parametro_dia_semana WHERE pdsm_nb_parametro = {$idParametro}
                ORDER BY pdsm_nb_diaSemana"
        );
        runnerAssertSame(
            json_encode($snapshot),
            json_encode($atual),
            'A configuracao do parametro nao voltou ao estado em que estava antes dos testes.'
        );

        $sobrou = runnerFetchOne(
            "SELECT COUNT(*) AS n FROM ponto WHERE pont_tx_matricula = '" . $matricula . "'"
            . " AND DATE(pont_tx_data) = '" . $dataFolga . "' AND pont_tx_descricao = 'TESTE_REGRESSAO_DIA_SEMANA'"
        );
        runnerAssertSame('0', strval($sobrou['n'] ?? '-1'), 'O ponto criado pelo teste nao foi apagado.');
    }, $summary);

    /* O salvar e a tela vivem no funcoes_dia_semana.php, compartilhado com o
       calculo, e por isso dao para ser exercitados de verdade aqui. */
    runnerRunCase('integration', 'dia da semana: salvar grava os sete dias e o DSR escolhido', function () use ($idParametro, $devolverSnapshot): void {
        try {
            /* Escala 5x2: dois dias de descanso, sabado e domingo. */
            $gravados = salvarDiasSemanaParametro($idParametro, [
                'diaDsr' => ['0', '6'],
                'percDiaSemana' => [0 => '100', 6 => '50'],
            ]);
            runnerAssertSame(7, $gravados, 'O salvar deveria gravar os sete dias da semana.');

            $config = carregarDiasSemanaParametro($idParametro, true);
            runnerAssertSame(7, count($config), 'A configuracao lida deveria ter sete dias.');
            runnerAssertSame('sim', $config[0]['ehDsr'], 'O domingo deveria ter ficado como DSR.');
            runnerAssertSame('sim', $config[6]['ehDsr'], 'O sabado deveria ter ficado como DSR (5x2 tem dois).');
            runnerAssertSame('nao', $config[3]['ehDsr'], 'A quarta nao deveria ser DSR.');
            runnerAssertSame(100, intval($config[0]['perc']), 'O percentual do domingo nao foi gravado.');
            runnerAssertSame(50, intval($config[6]['perc']), 'O percentual do sabado nao foi gravado.');
            runnerAssertTrue($config[3]['perc'] === null, 'Dia sem escolha deveria ficar NULL, nao 0.');

            /* Salvar de novo com um dia so: o outro tem de deixar de ser DSR. */
            salvarDiasSemanaParametro($idParametro, [
                'diaDsr' => ['6'],
                'percDiaSemana' => [],
            ]);
            $config = carregarDiasSemanaParametro($idParametro, true);
            runnerAssertSame(7, count($config), 'Salvar duas vezes duplicou linhas.');
            runnerAssertSame('sim', $config[6]['ehDsr'], 'O sabado deveria continuar como DSR.');
            runnerAssertSame('nao', $config[0]['ehDsr'], 'O domingo continuou como DSR depois de ser desmarcado.');
            runnerAssertTrue($config[6]['perc'] === null, 'O percentual deveria ter voltado para o padrao.');

            /* Nenhum dia marcado: nenhum DSR. */
            salvarDiasSemanaParametro($idParametro, ['diaDsr' => [], 'percDiaSemana' => []]);
            $config = carregarDiasSemanaParametro($idParametro, true);
            foreach (array_keys(diasDaSemanaParametro()) as $d) {
                runnerAssertSame('nao', $config[$d]['ehDsr'], 'Sem marcar nada, o dia ' . $d . ' nao deveria ser DSR.');
            }

            /* Formato antigo, de valor unico, continua aceito. */
            salvarDiasSemanaParametro($idParametro, ['diaDsr' => '2', 'percDiaSemana' => []]);
            $config = carregarDiasSemanaParametro($idParametro, true);
            runnerAssertSame('sim', $config[2]['ehDsr'], 'O formato antigo de valor unico deixou de funcionar.');
        } finally {
            $devolverSnapshot();
        }
    }, $summary);

    runnerRunCase('integration', 'dia da semana: tela mostra os sete dias e marca o escolhido', function (): void {
        /* Escala 5x2: sabado e domingo marcados. */
        $html = blocoDiasSemanaParametro([
            'para_tx_percHESemanal' => '50',
            'para_tx_percHEEx' => '100',
            'diasSemana' => [
                0 => ['ehDsr' => 'sim', 'perc' => 100],
                6 => ['ehDsr' => 'sim', 'perc' => 50],
            ],
        ]);

        foreach (diasDaSemanaParametro() as $nome) {
            runnerAssertContains($nome, $html, 'O dia ' . $nome . ' nao aparece na tela.');
        }

        /* Caixa de selecao, nao radio: da para marcar mais de um dia. */
        runnerAssertSame(7, substr_count($html, "name='diaDsr[]'"), 'A tela deveria ter sete caixas de DSR.');
        runnerAssertSame(0, substr_count($html, "type='radio'"), 'O DSR nao pode mais ser radio: so deixaria marcar um dia.');
        runnerAssertSame(7, substr_count($html, "name='percDiaSemana["), 'A tela deveria ter sete campos de percentual.');

        /* Os dois dias do 5x2 vem marcados ao reabrir. */
        runnerAssertSame(2, substr_count($html, 'checked'), 'A tela deveria reabrir com os dois dias marcados.');
        runnerAssertContains("value='0' checked", $html, 'O domingo deveria estar marcado.');
        runnerAssertContains("value='6' checked", $html, 'O sabado deveria estar marcado.');
        runnerAssertContains("value='50' selected", $html, 'O sabado deveria estar com 50% selecionado.');

        /* Alvo de clique: o nome do dia e um label da propria caixa. */
        foreach (array_keys(diasDaSemanaParametro()) as $num) {
            runnerAssertContains("for='dsr_" . $num . "'", $html, 'O dia ' . $num . ' nao tem label ligado a caixa.');
            runnerAssertContains("id='dsr_" . $num . "'", $html, 'A caixa do dia ' . $num . ' nao tem id para o label.');
        }
        runnerAssertSame(
            14,
            substr_count($html, "for='dsr_"),
            'Deveriam ser dois labels por dia (o nome e a celula da caixa), para o clique pegar em qualquer um.'
        );

        /* Os percentuais oferecidos sao os do proprio parametro. */
        $html110 = blocoDiasSemanaParametro([
            'para_tx_percHESemanal' => '60',
            'para_tx_percHEEx' => '110',
            'diasSemana' => [],
        ]);
        runnerAssertContains('60% — H.E. semanal', $html110, 'A tela ignorou o percentual semanal do parametro.');
        runnerAssertContains('110% — H.E. extraordinária', $html110, 'A tela ignorou o percentual extraordinario do parametro.');
        runnerAssertContains('padrão (110%)', $html110, 'O rotulo do padrao deveria citar o percentual do parametro.');
    }, $summary);

    runnerRunCase('integration', 'dia da semana: faixa comum e decidida pelo percentual do parametro', function (): void {
        /* Extraordinaria em 110%: 100% passa a ser faixa comum. */
        $motorista110 = ['para_tx_percHEEx' => '110'];
        runnerAssertTrue(diaSemanaFaixaComum($motorista110, 100), '100% com extraordinaria em 110% deveria ser faixa comum.');
        runnerAssertTrue(!diaSemanaFaixaComum($motorista110, 110), '110% com extraordinaria em 110% nao e faixa comum.');

        $motorista100 = ['para_tx_percHEEx' => '100'];
        runnerAssertTrue(diaSemanaFaixaComum($motorista100, 50), '50% deveria ser faixa comum.');
        runnerAssertTrue(!diaSemanaFaixaComum($motorista100, 100), '100% nao e faixa comum.');
        runnerAssertTrue(!diaSemanaFaixaComum($motorista100, null), 'Sem percentual nao ha troca de faixa.');

        /* Parametro sem percentual configurado cai em 100. */
        runnerAssertTrue(diaSemanaFaixaComum([], 50), 'Sem percHEEx deveria assumir 100 e tratar 50 como faixa comum.');
    }, $summary);

    runnerRunCase('integration', 'dia da semana: tela sem dados nao derruba a pagina', function (): void {
        /* Num GET sem POST o $a_mod da tela ainda nao existe. Com o parametro
           tipado so como "array" isso virava TypeError e o cadastro de
           parametro renderizava so o titulo. */
        foreach (['null' => null, 'vazio' => []] as $rotulo => $arg) {
            $html = blocoDiasSemanaParametro($arg);
            runnerAssertTrue(
                strpos($html, 'Descanso semanal') !== false,
                'blocoDiasSemanaParametro(' . $rotulo . ') nao renderizou o bloco.'
            );
            runnerAssertSame(
                7,
                substr_count($html, "name='percDiaSemana["),
                'blocoDiasSemanaParametro(' . $rotulo . ') deveria render os sete dias.'
            );
        }

        /* Sem argumento nenhum tambem. */
        $html = blocoDiasSemanaParametro();
        runnerAssertSame(7, substr_count($html, "name='diaDsr[]'"), 'Chamada sem argumento nao rendeu as sete caixas.');
        runnerAssertSame(0, substr_count($html, 'checked'), 'Sem dados, nenhum dia deveria vir marcado.');
    }, $summary);

    runnerRunCase('integration', 'dia da semana: require vem antes do include do conecta', function (): void {
        global $appDir;

        /* O contex20/funcoes.php, que o conecta inclui, executa a acao do
           $_POST["acao"] ali dentro. Funcao carregada depois do conecta nao
           existe na hora em que a acao roda: a tela do parametro ficava so com
           o titulo, e o espelho quebraria igual ao chamar parametroDiaSemana
           dentro do diaDetalhePonto. */
        $casos = [
            'cadastro_parametro.php' => 'include "conecta.php"',
            'funcoes_ponto.php'      => 'include_once __DIR__."/conecta.php"',
        ];

        foreach ($casos as $arquivo => $marcaConecta) {
            $fonte = runnerReadFile($appDir . '/' . $arquivo);

            $posRequire = strpos($fonte, 'funcoes_dia_semana.php');
            $posConecta = strpos($fonte, $marcaConecta);

            runnerAssertTrue($posRequire !== false, $arquivo . ' deixou de incluir o funcoes_dia_semana.php.');
            runnerAssertTrue($posConecta !== false, $arquivo . ' mudou a forma de incluir o conecta.php.');
            runnerAssertTrue(
                $posRequire < $posConecta,
                'Em ' . $arquivo . ' o require do funcoes_dia_semana.php precisa vir ANTES do include do '
                . 'conecta.php: o dispatcher do POST roda dentro desse include.'
            );
        }
    }, $summary);

    runnerRunCase('integration', 'dia da semana: a tela e o calculo usam a mesma regra', function (): void {
        global $appDir;

        /* Os dois lados tem de incluir o compartilhado, senao a regra volta a
           existir em duas versoes que divergem com o tempo. */
        $cadastro = runnerReadFile($appDir . '/cadastro_parametro.php');
        runnerAssertContains('funcoes_dia_semana.php', $cadastro, 'O cadastro deixou de usar a regra compartilhada.');
        runnerAssertContains('salvarDiasSemanaParametro(', $cadastro, 'O cadastro deixou de gravar a configuracao.');
        runnerAssertContains('blocoDiasSemanaParametro(', $cadastro, 'O bloco saiu da tela do parametro.');

        $ponto = runnerReadFile($appDir . '/funcoes_ponto.php');
        runnerAssertContains('funcoes_dia_semana.php', $ponto, 'O calculo deixou de usar a regra compartilhada.');
        runnerAssertContains('diaSemanaFaixaComum(', $ponto, 'O calculo deixou de consultar a faixa do dia.');

        $espelho = runnerReadFile($appDir . '/espelho_ponto.php');
        runnerAssertContains('parametroDiaSemana(', $espelho, 'O espelho deixou de marcar o DSR.');
    }, $summary);
}

function runnerPrintSummary(array $summary, bool $jsonMode): void
{
    if ($jsonMode) {
        fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        return;
    }

    runnerPrintLine('');
    runnerPrintLine('Resumo final');
    runnerPrintLine('  Passou : ' . $summary['passed']);
    runnerPrintLine('  Falhou : ' . $summary['failed']);
    runnerPrintLine('  Pulou  : ' . $summary['skipped']);
}

runnerRequireDatabase(['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME']);
runnerLoadAppCore();

if (isset($argumentos['endosso-runtime'])) {
    runnerLoadEndossoRuntime();
}

$summary = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'tests' => [],
];

runnerPrintLine('Executando contratos de fonte...');
runnerRunCase('source-contracts', 'arquivos principais mantem contratos sensiveis', function (): void {
    runnerSourceContracts();
}, $summary);

runnerPrintLine('Executando testes de funcoes puras...');
runnerCoreFunctionTests($summary);

if (isset($argumentos['integration'])) {
    runnerPrintLine('Executando testes de integracao...');
    runnerIntegrationTests($summary);
} else {
    runnerPrintLine('Testes de integracao ignorados. Use --integration para executa-los.');
}

if (isset($argumentos['endosso-runtime'])) {
    runnerPrintLine('Executando testes de runtime do endosso...');
    runnerOptionalEndossoRuntimeTests($summary);
} else {
    runnerPrintLine('Testes de runtime do endosso ignorados. Use --endosso-runtime para executa-los.');
}

runnerPrintSummary($summary, isset($argumentos['json']));

if ($summary['failed'] > 0) {
    exit(1);
}

exit(0);
