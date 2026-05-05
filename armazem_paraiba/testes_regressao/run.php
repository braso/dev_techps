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
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? $rootDir;
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
        ],
        'menu.php' => [
            '/endosso.php',
            '/espelho_ponto.php',
            '/paineis/endosso.php',
        ],
        'assinatura/componentes/layout_header.php' => [
            '/cadastro_endosso.php',
            '/endosso.php',
            '/espelho_ponto.php',
            '/paineis/endosso.php',
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
        ],
    ];

    foreach ($contracts as $relativePath => $needles) {
        $filePath = $appDir . DIRECTORY_SEPARATOR . $relativePath;
        $content = runnerReadFile($filePath);
        foreach ($needles as $needle) {
            runnerAssertContains($needle, $content, "Contrato quebrado em {$relativePath}");
        }
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
        $checkPermission = runnerReadFile($appDir . '/check_permission.php');
        runnerAssertContains('/batida_ponto.php', $checkPermission, 'Regra especial de batida foi removida.');
        runnerAssertContains('/espelho_ponto.php', $checkPermission, 'Regra especial de espelho foi removida.');

        $menu = runnerReadFile($appDir . '/menu.php');
        runnerAssertContains('/endosso.php', $menu, 'Menu consultando endosso foi alterado.');
        runnerAssertContains('/espelho_ponto.php', $menu, 'Menu de espelho foi alterado.');
        runnerAssertContains('/paineis/endosso.php', $menu, 'Menu do painel de endosso foi alterado.');

        $layoutHeader = runnerReadFile($appDir . '/assinatura/componentes/layout_header.php');
        runnerAssertContains('/endosso.php', $layoutHeader, 'Header de assinatura perdeu endosso.');
        runnerAssertContains('/espelho_ponto.php', $layoutHeader, 'Header de assinatura perdeu espelho.');
        runnerAssertContains('/paineis/endosso.php', $layoutHeader, 'Header de assinatura perdeu painel.');
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
