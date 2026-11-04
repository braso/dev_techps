<?php
    /* ============================================================
       Observações da Empresa

       Ficha operacional da empresa vinculada ao usuário logado
       (user_nb_empresa):

       - Funcionários marcados como BPO (com contato ao lado do nome)
       - Placas cadastradas na empresa e o rastreador de cada uma,
         consultado na API de integração logística
       - Contatos da empresa
       - Observações / detalhes de rota

       A tela não lista empresas. A empresa é sempre a da sessão.
       Quando o usuário não tem empresa vinculada (user_nb_empresa = 0),
       usa a empresa matriz como referência.
       ============================================================ */

    include_once "check_permission.php";
    include_once "load_env.php";

    function observacoes_garantirTabelas(): void {
        global $conn;
        if(!isset($conn) || !($conn instanceof mysqli)){ return; }

        $tabelas = [
            "CREATE TABLE IF NOT EXISTS empresa_observacao (
                obse_nb_id INT(11) NOT NULL AUTO_INCREMENT,
                obse_nb_empresa INT(11) NOT NULL,
                obse_tx_rastreador VARCHAR(150) NULL,
                obse_nb_qtdMotoristas INT(11) NULL,
                obse_tx_observacoes TEXT NULL,
                obse_nb_userCadastro INT(11) NULL,
                obse_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
                obse_nb_userAtualiza INT(11) NULL,
                obse_tx_dataAtualiza DATETIME NULL,
                PRIMARY KEY (obse_nb_id),
                UNIQUE KEY uk_empresa (obse_nb_empresa),
                KEY idx_rastreador (obse_tx_rastreador)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS empresa_observacao_contato (
                obco_nb_id INT(11) NOT NULL AUTO_INCREMENT,
                obco_nb_empresa INT(11) NOT NULL,
                obco_tx_nome VARCHAR(150) NULL,
                obco_tx_funcao VARCHAR(100) NULL,
                obco_tx_telefone VARCHAR(30) NULL,
                obco_tx_email VARCHAR(255) NULL,
                obco_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (obco_nb_id),
                KEY idx_empresa (obco_nb_empresa)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach($tabelas as $sql){
            try{
                mysqli_query($conn, $sql);
            }catch(Throwable $e){
                error_log("[observacoes_empresa] ".$e->getMessage());
            }
        }

        // Flag usada para marcar o funcionário como BPO.
        try{
            $rs = mysqli_query($conn, "SHOW COLUMNS FROM entidade LIKE 'enti_tx_bpo'");
            if($rs && mysqli_num_rows($rs) === 0){
                mysqli_query($conn, "ALTER TABLE entidade ADD COLUMN enti_tx_bpo ENUM('sim','nao') NOT NULL DEFAULT 'nao'");
            }
        }catch(Throwable $e){
            error_log("[observacoes_empresa] bpo: ".$e->getMessage());
        }
    }

    function observacoes_h($valor): string {
        return htmlspecialchars(strval($valor ?? ""), ENT_QUOTES, "UTF-8");
    }

    function observacoes_carregarEmpresa(int $id): ?array {
        if($id <= 0){ return null; }
        $rs = query("SELECT * FROM empresa WHERE empr_nb_id = ? LIMIT 1", "i", [$id]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }

    function observacoes_carregarRegistro(int $id): ?array {
        if($id <= 0){ return null; }
        $rs = query("SELECT * FROM empresa_observacao WHERE obse_nb_empresa = ? LIMIT 1", "i", [$id]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }

    function observacoes_carregarContatos(int $id): array {
        $contatos = [];
        if($id <= 0){ return $contatos; }
        $rs = query(
            "SELECT * FROM empresa_observacao_contato WHERE obco_nb_empresa = ? ORDER BY obco_nb_id ASC",
            "i",
            [$id]
        );
        while($rs && ($row = mysqli_fetch_assoc($rs))){ $contatos[] = $row; }
        return $contatos;
    }

    /**
     * Empresa da sessão. Se o usuário não tem empresa vinculada, usa a
     * matriz como referência; se não houver matriz, a primeira ativa.
     */
    function observacoes_empresaDoUsuario(): ?array {
        $id = (int)($_SESSION["user_nb_empresa"] ?? 0);
        if($id > 0){
            $empresa = observacoes_carregarEmpresa($id);
            if(!empty($empresa)){ return $empresa; }
        }

        $rs = query("SELECT * FROM empresa WHERE empr_tx_Ehmatriz = 'sim' ORDER BY empr_nb_id ASC LIMIT 1");
        $empresa = $rs ? mysqli_fetch_assoc($rs) : null;
        if(!empty($empresa)){ return $empresa; }

        $rs = query("SELECT * FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_nb_id ASC LIMIT 1");
        $empresa = $rs ? mysqli_fetch_assoc($rs) : null;
        return $empresa ?: null;
    }

    function observacoes_contatosDoPost(): array {
        $nomes = $_POST["contato_nome"] ?? [];
        $funcoes = $_POST["contato_funcao"] ?? [];
        $telefones = $_POST["contato_telefone"] ?? [];
        $emails = $_POST["contato_email"] ?? [];

        if(!is_array($nomes)){ $nomes = []; }
        if(!is_array($funcoes)){ $funcoes = []; }
        if(!is_array($telefones)){ $telefones = []; }
        if(!is_array($emails)){ $emails = []; }

        $contatos = [];
        $total = max(count($nomes), count($funcoes), count($telefones), count($emails));
        for($i = 0; $i < $total; $i++){
            $contatos[] = [
                "obco_tx_nome"     => $nomes[$i] ?? "",
                "obco_tx_funcao"   => $funcoes[$i] ?? "",
                "obco_tx_telefone" => $telefones[$i] ?? "",
                "obco_tx_email"    => $emails[$i] ?? ""
            ];
        }
        return $contatos;
    }

    function observacoes_formContatos(array $contatos): string {
        if(empty($contatos)){
            $contatos = [[
                "obco_tx_nome"     => "",
                "obco_tx_funcao"   => "",
                "obco_tx_telefone" => "",
                "obco_tx_email"    => ""
            ]];
        }

        $html = "<div class='col-sm-12 margin-bottom-5'>
            <label>Contatos da empresa</label>
            <div class='table-responsive'>
                <table class='table table-bordered table-condensed' id='tabelaContatosBpo'>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Setor/Função</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th style='width:40px'></th>
                        </tr>
                    </thead>
                    <tbody id='corpoContatosBpo'>";

        foreach($contatos as $contato){
            $html .= "<tr>
                <td><input type='text' name='contato_nome[]' class='form-control input-sm' maxlength='150' value='".observacoes_h($contato["obco_tx_nome"] ?? "")."'></td>
                <td><input type='text' name='contato_funcao[]' class='form-control input-sm' maxlength='100' value='".observacoes_h($contato["obco_tx_funcao"] ?? "")."'></td>
                <td><input type='text' name='contato_telefone[]' class='form-control input-sm' maxlength='30' value='".observacoes_h($contato["obco_tx_telefone"] ?? "")."'></td>
                <td><input type='text' name='contato_email[]' class='form-control input-sm' maxlength='255' value='".observacoes_h($contato["obco_tx_email"] ?? "")."'></td>
                <td><button type='button' class='btn btn-danger btn-xs' onclick='removerContatoBpo(this)' title='Remover contato'><i class='fa fa-trash'></i></button></td>
            </tr>";
        }

        $html .= "</tbody>
                </table>
            </div>
            <button type='button' class='btn btn-default btn-sm' onclick='adicionarContatoBpo()'><i class='fa fa-plus'></i> Adicionar contato</button>
        </div>";

        return $html;
    }

    function observacoes_contatosVisualizacao(array $contatos): string {
        if(empty($contatos)){
            return "<div class='col-sm-12 margin-bottom-5'><label>Contatos da empresa</label><p class='text-muted'>Nenhum contato cadastrado.</p></div>";
        }

        $html = "<div class='col-sm-12 margin-bottom-5'>
            <label>Contatos da empresa</label>
            <div class='table-responsive'>
                <table class='table table-bordered table-condensed'>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Setor/Função</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach($contatos as $contato){
            $html .= "<tr>
                <td>".observacoes_h($contato["obco_tx_nome"] ?? "")."</td>
                <td>".observacoes_h($contato["obco_tx_funcao"] ?? "")."</td>
                <td>".observacoes_h($contato["obco_tx_telefone"] ?? "")."</td>
                <td>".observacoes_h($contato["obco_tx_email"] ?? "")."</td>
            </tr>";
        }

        $html .= "</tbody></table></div></div>";
        return $html;
    }

    function observacoes_auditoria(?array $registro): string {
        if(empty($registro["obse_tx_dataAtualiza"])){ return ""; }
        $usuario = carregar("user", $registro["obse_nb_userAtualiza"]);
        $login = $usuario["user_tx_login"] ?? "";
        $dataAtualiza = date("d/m/Y H:i", strtotime($registro["obse_tx_dataAtualiza"]));
        return "Última atualização: ".$dataAtualiza.($login !== "" ? " por ".$login : "");
    }

    function observacoes_avisoEmpresaPadrao(array $empresa): string {
        $empresaSessao = (int)($_SESSION["user_nb_empresa"] ?? 0);
        if($empresaSessao > 0 && $empresaSessao === (int)$empresa["empr_nb_id"]){ return ""; }
        return "<div class='alert alert-info'>O usuário não tem empresa vinculada; exibindo a empresa matriz como referência.</div>";
    }

    // ---------------------------------------------------------------- BPO
    function observacoes_carregarFuncionariosBpo(int $empresaId): array {
        $funcionarios = [];
        if($empresaId <= 0){ return $funcionarios; }
        $rs = query(
            "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_ocupacao,
                    enti_tx_fone1, enti_tx_fone2, enti_tx_email
               FROM entidade
              WHERE enti_nb_empresa = ?
                AND enti_tx_bpo = 'sim'
                AND enti_tx_status = 'ativo'
              ORDER BY enti_tx_nome ASC",
            "i",
            [$empresaId]
        );
        while($rs && ($row = mysqli_fetch_assoc($rs))){ $funcionarios[] = $row; }
        return $funcionarios;
    }

    function observacoes_contatoFuncionario(array $funcionario): string {
        $partes = [];
        foreach(["enti_tx_fone1", "enti_tx_fone2", "enti_tx_email"] as $campo){
            $valor = trim(strval($funcionario[$campo] ?? ""));
            if($valor !== ""){ $partes[] = $valor; }
        }
        return !empty($partes) ? implode(" / ", $partes) : "Sem contato";
    }

    function observacoes_funcionariosBpoVisualizacao(array $funcionarios): string {
        if(empty($funcionarios)){
            return "<div class='col-sm-12 margin-bottom-5'><label>Funcionários marcados como BPO</label><p class='text-muted'>Nenhum funcionário marcado como BPO.</p></div>";
        }

        $html = "<div class='col-sm-12 margin-bottom-5'>
            <label>Funcionários marcados como BPO</label>
            <div class='table-responsive'>
                <table class='table table-bordered table-condensed'>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th>Ocupação</th>
                            <th>Matrícula</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach($funcionarios as $funcionario){
            $html .= "<tr>
                <td>".observacoes_h($funcionario["enti_tx_nome"] ?? "")."</td>
                <td>".observacoes_h(observacoes_contatoFuncionario($funcionario))."</td>
                <td>".observacoes_h($funcionario["enti_tx_ocupacao"] ?? "")."</td>
                <td>".observacoes_h($funcionario["enti_tx_matricula"] ?? "")."</td>
            </tr>";
        }

        $html .= "</tbody></table></div></div>";
        return $html;
    }

    // ------------------------------------------------------- rastreadores
    function observacoes_normalizarPlaca($placa): string {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim(strval($placa ?? ""))));
    }

    function observacoes_normalizarCnpj($cnpj): string {
        $digitos = preg_replace('/\D/', '', strval($cnpj ?? ""));
        if($digitos === ""){ return ""; }
        return str_pad($digitos, 14, "0", STR_PAD_LEFT);
    }

    /**
     * Consulta a API de integração logística. Usa cache de 10 minutos para
     * não deixar a abertura da tela lenta nem depender da API a cada acesso.
     */
    function observacoes_apiRastreadores(): array {
        $cacheFile = __DIR__."/arquivos/cache/rastreadores_api.json";
        $ttl = 10 * 60;
        $cache = null;

        if(is_file($cacheFile)){
            $raw = @file_get_contents($cacheFile);
            $json = json_decode(strval($raw), true);
            if(is_array($json) && isset($json["timestamp"], $json["dados"]) && is_array($json["dados"])){
                $cache = $json;
                if((time() - (int)$json["timestamp"]) < $ttl){
                    return ["ok" => true, "empresas" => ($json["dados"]["empresas"] ?? []), "aviso" => ""];
                }
            }
        }

        $url = trim(strval($_ENV["LOGISTICA_INTEGRACAO_URL"] ?? ""));
        if($url === ""){
            $url = "https://logistica.integracao.techpsgj.com.br/empresas-posicoes-hoje";
        }

        $resposta = false;
        $http = 0;
        $erro = "";

        if(function_exists("curl_init")){
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => ["Accept: application/json"]
            ]);
            $resposta = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);
        }else{
            $contexto = stream_context_create([
                "http" => ["timeout" => 6, "header" => "Accept: application/json\r\n"]
            ]);
            $resposta = @file_get_contents($url, false, $contexto);
            $http = $resposta !== false ? 200 : 0;
            $erro = $resposta === false ? "falha ao requisitar" : "";
        }

        if($resposta !== false && $http === 200){
            $dados = json_decode(strval($resposta), true);
            if(is_array($dados) && isset($dados["empresas"]) && is_array($dados["empresas"])){
                $dir = dirname($cacheFile);
                if(!is_dir($dir)){ @mkdir($dir, 0777, true); }
                @file_put_contents($cacheFile, json_encode(["timestamp" => time(), "dados" => $dados], JSON_UNESCAPED_UNICODE));
                return ["ok" => true, "empresas" => $dados["empresas"], "aviso" => ""];
            }
            $erro = "resposta inválida";
        }

        if(is_array($cache) && isset($cache["dados"]["empresas"]) && is_array($cache["dados"]["empresas"])){
            return [
                "ok" => true,
                "empresas" => $cache["dados"]["empresas"],
                "aviso" => "Não foi possível atualizar a API de rastreadores; usando os dados em cache."
            ];
        }

        return [
            "ok" => false,
            "empresas" => [],
            "aviso" => "Não foi possível consultar a API de rastreadores agora."
        ];
    }

    /**
     * Monta um índice placa normalizada => rastreadores encontrados.
     * Também guarda quais rastreadores pertencem ao CNPJ da empresa.
     */
    function observacoes_mapaRastreadores(array $empresas): array {
        $mapa = [];

        foreach($empresas as $empresaApi){
            if(!is_array($empresaApi)){ continue; }

            $rastreador = trim(strval($empresaApi["empresaApi"] ?? ""));
            $cnpj = observacoes_normalizarCnpj($empresaApi["cnpj"] ?? "");
            $veiculos = $empresaApi["veiculos"] ?? [];
            if(!is_array($veiculos)){ continue; }

            foreach($veiculos as $veiculo){
                if(!is_array($veiculo)){ continue; }

                $placaOriginal = strtoupper(trim(strval($veiculo["placa"] ?? "")));
                if($placaOriginal === ""){ continue; }

                $aliases = [];
                $placaNormalizada = observacoes_normalizarPlaca($placaOriginal);
                if($placaNormalizada !== ""){ $aliases[] = $placaNormalizada; }

                // A API costuma usar sufixo -1, -2... para implementos.
                // Também indexa a placa base para casar com o cadastro do sistema.
                if(preg_match('/-\d+$/', $placaOriginal)){
                    $placaBase = preg_replace('/-\d+$/', '', $placaOriginal);
                    $placaBase = observacoes_normalizarPlaca($placaBase);
                    if($placaBase !== "" && !in_array($placaBase, $aliases, true)){
                        $aliases[] = $placaBase;
                    }
                }

                foreach($aliases as $alias){
                    if(!isset($mapa[$alias])){
                        $mapa[$alias] = ["rastreadores" => [], "porCnpj" => []];
                    }
                    if($rastreador !== ""){
                        $mapa[$alias]["rastreadores"][$rastreador] = true;
                        if($cnpj !== ""){
                            $mapa[$alias]["porCnpj"][$cnpj][$rastreador] = true;
                        }
                    }
                }
            }
        }

        return $mapa;
    }

    function observacoes_rastreadorDaPlaca(string $placa, array $mapa, string $cnpjEmpresa): string {
        $placaNormalizada = observacoes_normalizarPlaca($placa);
        if($placaNormalizada === "" || !isset($mapa[$placaNormalizada])){ return ""; }

        $item = $mapa[$placaNormalizada];
        $nomes = [];

        if($cnpjEmpresa !== "" && !empty($item["porCnpj"][$cnpjEmpresa])){
            $nomes = array_keys($item["porCnpj"][$cnpjEmpresa]);
        }else{
            $nomes = array_keys($item["rastreadores"] ?? []);
        }

        sort($nomes);
        return implode(", ", $nomes);
    }

    function observacoes_rastreadorDoModelo(string $modelo): string {
        $modelo = strtoupper(trim($modelo));
        if($modelo === ""){ return ""; }

        $conhecidos = [
            "GTRACK"       => "GTRACK COMIGO RASTREAMENTO",
            "AUTOPOSITION" => "AUTOPOSITION",
            "AUTOTRAC"     => "AUTOTRAC",
            "SASCAR"       => "SASCAR",
            "POSITRON"     => "POSITRON",
            "ONIXSAT"      => "ONIXSAT",
            "COMIGO"       => "COMIGO",
            "INOVA"        => "inova segurança",
            "BRASIL TRACK" => "Brasil Track",
            "MAXTRACK"     => "Maxtrack",
            "COBLI"        => "Cobli"
        ];

        foreach($conhecidos as $trecho => $nome){
            if(strpos($modelo, $trecho) !== false){ return $nome; }
        }
        return "";
    }

    function observacoes_carregarPlacas(int $empresaId): array {
        $placas = [];
        if($empresaId <= 0){ return $placas; }
        $rs = query(
            "SELECT plac_tx_placa, plac_tx_modelo
               FROM placa
              WHERE plac_nb_empresa = ?
              ORDER BY plac_tx_placa ASC",
            "i",
            [$empresaId]
        );
        while($rs && ($row = mysqli_fetch_assoc($rs))){ $placas[] = $row; }
        return $placas;
    }

    function observacoes_placasVisualizacao(array $placas, array $mapa, string $cnpjEmpresa, string $avisoApi): string {
        $html = "<div class='col-sm-12 margin-bottom-5'>
            <label>Placas e rastreadores</label>";

        if($avisoApi !== ""){
            $html .= "<div class='alert alert-warning' style='margin-bottom:10px'>".observacoes_h($avisoApi)."</div>";
        }

        if(empty($placas)){
            return $html."<p class='text-muted'>Nenhuma placa cadastrada para a empresa.</p></div>";
        }

        $html .= "<div class='table-responsive'>
                <table class='table table-bordered table-condensed'>
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Rastreador</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach($placas as $placa){
            $numero = strtoupper(trim(strval($placa["plac_tx_placa"] ?? "")));
            $rastreador = observacoes_rastreadorDaPlaca($numero, $mapa, $cnpjEmpresa);
            $origem = "";

            if($rastreador === ""){
                $rastreadorModelo = observacoes_rastreadorDoModelo(strval($placa["plac_tx_modelo"] ?? ""));
                if($rastreadorModelo !== ""){
                    $rastreador = $rastreadorModelo;
                    $origem = " (cadastro)";
                }
            }

            if($rastreador === ""){
                $html .= "<tr><td>".observacoes_h($numero)."</td><td><span class='text-muted'>Não localizado</span></td></tr>";
            }else{
                $html .= "<tr><td>".observacoes_h($numero)."</td><td>".observacoes_h($rastreador.$origem)."</td></tr>";
            }
        }

        $html .= "</tbody></table></div></div>";
        return $html;
    }

    // ---------------------------------------------------------------- telas
    function index(){
        verificaPermissao('/observacoes_empresa.php');
        observacoes_garantirTabelas();

        $empresa = observacoes_empresaDoUsuario();

        cabecalho("Observações da Empresa");

        if(empty($empresa)){
            echo "<div class='alert alert-warning'>Nenhuma empresa encontrada. Peça ao administrador para vincular a empresa no cadastro de usuário.</div>";
            rodape();
            return;
        }

        $id = (int)$empresa["empr_nb_id"];
        $registro = observacoes_carregarRegistro($id);
        $contatos = observacoes_carregarContatos($id);
        $funcionariosBpo = observacoes_carregarFuncionariosBpo($id);
        $placas = observacoes_carregarPlacas($id);
        $cnpjEmpresa = observacoes_normalizarCnpj($empresa["empr_tx_cnpj"] ?? "");
        $auditoria = observacoes_auditoria($registro);

        $api = ["ok" => true, "empresas" => [], "aviso" => ""];
        $mapaRastreadores = [];
        if(!empty($placas)){
            $api = observacoes_apiRastreadores();
            $mapaRastreadores = observacoes_mapaRastreadores($api["empresas"] ?? []);
        }

        if(!empty($_POST["msg_status"])){
            $msgStatus = strval($_POST["msg_status"]);
            $classeStatus = (strpos($msgStatus, "ERRO") !== false) ? "alert-danger" : "alert-success";
            echo "<div class='alert ".$classeStatus."' style='margin-bottom:16px'>".$msgStatus."</div>";
        }

        echo observacoes_avisoEmpresaPadrao($empresa);

        echo "<div class='col-md-12'>
            <div class='portlet light'>
                <div class='portlet-title'>
                    <div class='caption'>
                        <span class='caption-subject font-dark bold'>Observações da Empresa</span>
                        <span class='caption-helper'>".observacoes_h($empresa["empr_tx_nome"])."</span>
                    </div>
                </div>
                <div class='portlet-body'>";

        echo "<div class='row'>";
        echo texto("Empresa", observacoes_h($empresa["empr_tx_nome"]), 5);
        echo texto("CNPJ", observacoes_h($empresa["empr_tx_cnpj"]), 3);
        echo texto("Status", observacoes_h($empresa["empr_tx_status"]), 2);
        echo texto("Código", observacoes_h($id), 2);
        echo "</div>";

        echo "<div class='row'>";
        echo texto("Funcionários BPO", observacoes_h(count($funcionariosBpo)), 2);
        echo texto("Placas cadastradas", observacoes_h(count($placas)), 2);
        echo texto("Contato do cadastro", observacoes_h(trim(
            strval($empresa["empr_tx_contato"] ?? "")." ".
            strval($empresa["empr_tx_fone1"] ?? "")." ".
            strval($empresa["empr_tx_email"] ?? "")
        )), 8);
        echo "</div>";

        echo "<div class='row'>".observacoes_funcionariosBpoVisualizacao($funcionariosBpo)."</div>";
        echo "<div class='row'>".observacoes_placasVisualizacao($placas, $mapaRastreadores, $cnpjEmpresa, strval($api["aviso"] ?? ""))."</div>";
        echo "<div class='row'>".observacoes_contatosVisualizacao($contatos)."</div>";

        echo "<div class='row'>
            <div class='col-sm-12 margin-bottom-5'>
                <label>Observações / detalhes de rota</label>
                <div class='well well-sm' style='white-space:pre-wrap; margin-bottom:0'>";
        if(!empty($registro["obse_tx_observacoes"])){
            echo nl2br(observacoes_h($registro["obse_tx_observacoes"]));
        }else{
            echo "<span class='text-muted'>Nenhuma observação cadastrada.</span>";
        }
        echo "</div></div></div>";

        if($auditoria !== ""){
            echo "<div class='row'>".texto("Auditoria", observacoes_h($auditoria), 12)."</div>";
        }

        echo "<div class='row'>
            <div class='col-sm-12'>
                <a href='observacoes_empresa.php?acao=editarObservacao' class='btn btn-primary'>
                    <i class='fa fa-pencil'></i> Editar observações
                </a>
            </div>
        </div>";

        echo "</div></div></div>";
        rodape();
    }

    function editarObservacao(){
        verificaPermissao('/observacoes_empresa.php');
        observacoes_garantirTabelas();

        $empresa = observacoes_empresaDoUsuario();

        cabecalho("Observações da Empresa");

        if(empty($empresa)){
            echo "<div class='alert alert-warning'>Nenhuma empresa encontrada. Peça ao administrador para vincular a empresa no cadastro de usuário.</div>";
            rodape();
            return;
        }

        $id = (int)$empresa["empr_nb_id"];
        $registro = observacoes_carregarRegistro($id);
        $contatos = observacoes_carregarContatos($id);

        if(isset($_POST["contato_nome"])){
            $contatos = observacoes_contatosDoPost();
        }elseif(empty($contatos) && empty($registro)){
            // Primeira abertura: aproveita o contato já existente no cadastro da empresa.
            $contatos = [[
                "obco_tx_nome"     => $empresa["empr_tx_contato"] ?? "",
                "obco_tx_funcao"   => "",
                "obco_tx_telefone" => $empresa["empr_tx_fone1"] ?? "",
                "obco_tx_email"    => $empresa["empr_tx_email"] ?? ""
            ]];
        }

        $observacoes = $_POST["observacoes"] ?? ($registro["obse_tx_observacoes"] ?? "");

        echo observacoes_avisoEmpresaPadrao($empresa);

        echo abre_form("Editar Observações");
        echo linha_form([
            campo_hidden("id", $id),
            campo_hidden("idEmpresa", $id)
        ]);
        echo linha_form([
            texto("Empresa", observacoes_h($empresa["empr_tx_nome"]), 6),
            texto("CNPJ", observacoes_h($empresa["empr_tx_cnpj"]), 3),
            texto("Status", observacoes_h($empresa["empr_tx_status"]), 3)
        ]);
        echo "<div class='alert alert-info' style='margin:10px 15px 0'>
            A quantidade de funcionários BPO e os rastreadores das placas são calculados automaticamente pelo sistema.
            Marque a flag BPO no cadastro do funcionário.
        </div>";
        echo "<div class='row'>".observacoes_formContatos($contatos)."</div>";
        echo "<div class='row'>".textarea(
            "Observações / detalhes de rota",
            "observacoes",
            observacoes_h($observacoes),
            12,
            "rows='8' placeholder='Ex.: Rota SP-Campinas, saída às 6h; cliente exige agendamento...'"
        )."</div>";

        echo fecha_form([
            botao("Salvar", "salvarObservacao", "", "", "", false, "btn btn-success"),
            botao("Voltar", "index")
        ]);

        echo "<script>
            function adicionarContatoBpo(){
                var tbody = document.getElementById('corpoContatosBpo');
                if(!tbody){ return; }
                var tr = document.createElement('tr');
                tr.innerHTML = \"<td><input type='text' name='contato_nome[]' class='form-control input-sm' maxlength='150'></td>\"
                    + \"<td><input type='text' name='contato_funcao[]' class='form-control input-sm' maxlength='100'></td>\"
                    + \"<td><input type='text' name='contato_telefone[]' class='form-control input-sm' maxlength='30'></td>\"
                    + \"<td><input type='text' name='contato_email[]' class='form-control input-sm' maxlength='255'></td>\"
                    + \"<td><button type='button' class='btn btn-danger btn-xs' onclick='removerContatoBpo(this)' title='Remover contato'><i class='fa fa-trash'></i></button></td>\";
                tbody.appendChild(tr);
            }
            function removerContatoBpo(btn){
                var tr = btn.closest('tr');
                var tbody = document.getElementById('corpoContatosBpo');
                if(!tr || !tbody){ return; }
                if(tbody.rows.length > 1){
                    tr.parentNode.removeChild(tr);
                }else{
                    var inputs = tr.querySelectorAll('input');
                    for(var i = 0; i < inputs.length; i++){ inputs[i].value = ''; }
                }
            }
        </script>";

        rodape();
    }

    function salvarObservacao(){
        global $conn;

        verificaPermissao('/observacoes_empresa.php');
        observacoes_garantirTabelas();

        $empresa = observacoes_empresaDoUsuario();
        if(empty($empresa)){
            set_status("ERRO: Empresa não encontrada.");
            index();
            exit;
        }

        // A empresa é sempre a da sessão; o id enviado pelo formulário é ignorado.
        $id = (int)$empresa["empr_nb_id"];
        $observacoes = trim(strval($_POST["observacoes"] ?? ""));

        $nomes = $_POST["contato_nome"] ?? [];
        $funcoes = $_POST["contato_funcao"] ?? [];
        $telefones = $_POST["contato_telefone"] ?? [];
        $emails = $_POST["contato_email"] ?? [];
        if(!is_array($nomes)){ $nomes = []; }
        if(!is_array($funcoes)){ $funcoes = []; }
        if(!is_array($telefones)){ $telefones = []; }
        if(!is_array($emails)){ $emails = []; }

        $contatos = [];
        $total = max(count($nomes), count($funcoes), count($telefones), count($emails));
        for($i = 0; $i < $total; $i++){
            $nome = mb_substr(trim(strval($nomes[$i] ?? "")), 0, 150);
            $funcao = mb_substr(trim(strval($funcoes[$i] ?? "")), 0, 100);
            $telefone = mb_substr(trim(strval($telefones[$i] ?? "")), 0, 30);
            $email = mb_substr(trim(strval($emails[$i] ?? "")), 0, 255);

            if($nome === "" && $funcao === "" && $telefone === "" && $email === ""){ continue; }

            if($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)){
                set_status("ERRO: E-mail inválido no contato ".($i + 1).".");
                editarObservacao();
                exit;
            }

            $contatos[] = [
                "nome" => $nome,
                "funcao" => $funcao,
                "telefone" => $telefone,
                "email" => $email
            ];
        }

        $userId = (int)($_SESSION["user_nb_id"] ?? 0);
        $registro = observacoes_carregarRegistro($id);

        try{
            mysqli_begin_transaction($conn);

            if(!empty($registro)){
                query(
                    "UPDATE empresa_observacao
                        SET obse_tx_observacoes = ?,
                            obse_nb_userAtualiza = ?,
                            obse_tx_dataAtualiza = NOW()
                      WHERE obse_nb_empresa = ?",
                    "sii",
                    [$observacoes, $userId, $id]
                );
            }else{
                query(
                    "INSERT INTO empresa_observacao
                        (obse_nb_empresa, obse_tx_observacoes, obse_nb_userCadastro,
                         obse_tx_dataCadastro, obse_nb_userAtualiza, obse_tx_dataAtualiza)
                     VALUES (?, ?, ?, NOW(), ?, NOW())",
                    "isii",
                    [$id, $observacoes, $userId, $userId]
                );
            }

            query("DELETE FROM empresa_observacao_contato WHERE obco_nb_empresa = ?", "i", [$id]);
            foreach($contatos as $contato){
                query(
                    "INSERT INTO empresa_observacao_contato
                        (obco_nb_empresa, obco_tx_nome, obco_tx_funcao, obco_tx_telefone, obco_tx_email)
                     VALUES (?, ?, ?, ?, ?)",
                    "issss",
                    [$id, $contato["nome"], $contato["funcao"], $contato["telefone"], $contato["email"]]
                );
            }

            mysqli_commit($conn);
        }catch(Throwable $e){
            mysqli_rollback($conn);
            error_log("[observacoes_empresa] ".$e->getMessage());
            set_status("ERRO: Não foi possível salvar as observações.");
            editarObservacao();
            exit;
        }

        set_status("Observações salvas com sucesso.");
        index();
        exit;
    }

    include_once "conecta.php";
