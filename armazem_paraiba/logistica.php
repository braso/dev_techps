<?php
    /*Modo Debug{
        ini_set("display_errors", 1);
        ini_set("display_startup_errors", 1);
        error_reporting(E_ALL);
    //}*/
    include_once "load_env.php";
    include_once "funcoes_ponto.php";
    include_once "conecta.php"; // Incluindo a conexão



    // Verifica se os parâmetros obrigatórios estão presentes na URL
    if (isset($_GET["motorista"], $_GET["matricula"], $_GET["data"], $_GET["cnpj"])) {
        // Parâmetros presentes, continue com o processamento
        $motorista = $_GET["motorista"];
        $matricula = $_GET["matricula"];
        $data = $_GET["data"];
        $cnpj = $_GET["cnpj"];        
        // Aqui você pode colocar o restante do seu código, usando esses valores

    } else {
        // Parâmetros ausentes, exibe uma mensagem de alerta e o botão de voltar
        echo "<script>alert('Parâmetros obrigatórios faltando');</script>";

        $_POST["HTTP_REFERER"] = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
        voltar();
    }




    if (empty($_SESSION["user_nb_id"])) {
        echo "Você precisa estar logado para acessar esta página.";
        exit;
    }
    
    $user_nb_id = $_SESSION["user_nb_id"];

    // Variáveis de erro e sucesso
    $erro = "";
    $sucesso = "";
        
        
    // Obtém o valor do CNPJ da URL, esperado como uma lista separada por vírgulas
    $cnpjList = isset($_GET["cnpj"]) ? $_GET["cnpj"] : "";

    $plates = [];
    $rsPlacas = query("SELECT plac_tx_placa FROM placa ORDER BY plac_tx_placa ASC");
    while($rsPlacas && ($r = mysqli_fetch_assoc($rsPlacas))){ $plates[] = $r["plac_tx_placa"]; }

    $pois = [];
    $rsPois = query("SELECT poi_nb_id, poi_tx_nome, poi_tx_cnpj, poi_tx_contato, poi_tx_latitude, poi_tx_longitude, poi_nb_raio, poi_tx_icone FROM poi WHERE poi_tx_status = 'ativo'");
    while($rsPois && ($r = mysqli_fetch_assoc($rsPois))){
        $pois[] = $r;
    }

    // Garante a tabela poi_tipo
    $__rsDb = query("SELECT DATABASE() AS db");
    $__dbName = "";
    if($__rsDb){ $__dbRow = mysqli_fetch_assoc($__rsDb); $__dbName = strval($__dbRow["db"] ?? ""); }
    if($__dbName !== ""){
        $__rsChkTipo = query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'poi_tipo' LIMIT 1", "s", [$__dbName]);
        $chkTipo = $__rsChkTipo ? mysqli_fetch_assoc($__rsChkTipo) : null;
        if(empty($chkTipo)){
            query("CREATE TABLE IF NOT EXISTS poi_tipo (poti_nb_id INT AUTO_INCREMENT PRIMARY KEY, poti_tx_codigo VARCHAR(50) NOT NULL UNIQUE, poti_tx_nome VARCHAR(100) NOT NULL, poti_tx_emoji VARCHAR(10) NOT NULL DEFAULT '📌', poti_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $padrao = [['fa-box','Caixa','📦'],['fa-building','Prédio','🏢'],['fa-industry','Indústria','🏭'],['fa-store','Loja','🏪'],['fa-gas-pump','Posto','⛽'],['fa-parking','Estacionamento','🅿️'],['fa-hospital','Hospital','🏥'],['fa-university','Banco','🏦'],['fa-utensils','Restaurante','🍽️'],['fa-hotel','Hotel','🏨'],['fa-warehouse','Armazém','🏭'],['fa-truck','Caminhão','🚚'],['fa-map-pin','Alfinete','📍'],['fa-flag-checkered','Ponto de Jornada','🏁'],['Posto Fiscal','Posto Fiscal','🏛️'],['PRF - Polícia Rodoviária Federal','PRF - Polícia Rodoviária Federal','👮'],['PM - Polícia Militar','PM - Polícia Militar','👮‍♂️'],['Balança Rodoviária','Balança Rodoviária','⚖️'],['Pedágios','Pedágios','🛣️'],['INÍCIO DE JORNADA','INÍCIO DE JORNADA','🏁'],['INÍCIO REFEIÇÃO','INÍCIO REFEIÇÃO','🍽️'],['FIM REFEIÇÃO','FIM REFEIÇÃO','🍽️'],['INÍCIO DE ESPERA','INÍCIO DE ESPERA','⏸️'],['FIM DE ESPERA','FIM DE ESPERA','▶️'],['INÍCIO DE DESCANSO','INÍCIO DE DESCANSO','💤'],['FIM DE DESCANSO','FIM DE DESCANSO','▶️'],['INÍCIO DE REPOUSO','INÍCIO DE REPOUSO','😴'],['FIM DE REPOUSO','FIM DE REPOUSO','▶️'],['INÍCIO DE PERNOITE','INÍCIO DE PERNOITE','🌙'],['FIM DE PERNOITE','FIM DE PERNOITE','🌅'],['FIM DE JORNADA','FIM DE JORNADA','🔚'],['Oficina','Oficina','🔧'],['Posto de Gasolina','Posto de Gasolina','⛽'],['Garagem','Garagem','🅿️'],['Base/Terminal','Base/Terminal','🏢'],['Cliente','Cliente','🤝'],['Fornecedor','Fornecedor','📦'],['Pátio','Pátio','🏭'],['Embarcadouro','Embarcadouro','⚓'],['Porto Seco','Porto Seco','🚢'],['Almoxarifado','Almoxarifado','📦'],['Centro de Distribuição','Centro de Distribuição','🏭'],['Ponto de Apoio','Ponto de Apoio','🆘'],['Parada Obrigatória','Parada Obrigatória','🛑'],['Pesagem','Pesagem','⚖️'],['Fronteira','Fronteira','🚧'],['Alfândega','Alfândega','🛃'],['Garagem Cliente','Garagem Cliente','🏠'],['Pátio Cliente','Pátio Cliente','🏭']];
            foreach($padrao as $t){ $__rsChk2 = query("SELECT 1 FROM poi_tipo WHERE poti_tx_codigo = ? LIMIT 1", "s", [$t[0]]); $chk = $__rsChk2 ? mysqli_fetch_assoc($__rsChk2) : null; if(empty($chk)) query("INSERT INTO poi_tipo (poti_tx_codigo, poti_tx_nome, poti_tx_emoji) VALUES (?, ?, ?)", "sss", $t); }
        }
    }
    // Garante que todos os tipos (incluindo antigos) tenham os emojis corretos
    $todosTipos = [['fa-box','Caixa','📦'],['fa-building','Prédio','🏢'],['fa-industry','Indústria','🏭'],['fa-store','Loja','🏪'],['fa-gas-pump','Posto','⛽'],['fa-parking','Estacionamento','🅿️'],['fa-hospital','Hospital','🏥'],['fa-university','Banco','🏦'],['fa-utensils','Restaurante','🍽️'],['fa-hotel','Hotel','🏨'],['fa-warehouse','Armazém','🏭'],['fa-truck','Caminhão','🚚'],['fa-map-pin','Alfinete','📍'],['fa-flag-checkered','Ponto de Jornada','🏁'],['Posto Fiscal','Posto Fiscal','🏛️'],['PRF - Polícia Rodoviária Federal','PRF - Polícia Rodoviária Federal','👮'],['PM - Polícia Militar','PM - Polícia Militar','👮‍♂️'],['Balança Rodoviária','Balança Rodoviária','⚖️'],['Pedágios','Pedágios','🛣️'],['INÍCIO DE JORNADA','INÍCIO DE JORNADA','🏁'],['INÍCIO REFEIÇÃO','INÍCIO REFEIÇÃO','🍽️'],['FIM REFEIÇÃO','FIM REFEIÇÃO','🍽️'],['INÍCIO DE ESPERA','INÍCIO DE ESPERA','⏸️'],['FIM DE ESPERA','FIM DE ESPERA','▶️'],['INÍCIO DE DESCANSO','INÍCIO DE DESCANSO','💤'],['FIM DE DESCANSO','FIM DE DESCANSO','▶️'],['INÍCIO DE REPOUSO','INÍCIO DE REPOUSO','😴'],['FIM DE REPOUSO','FIM DE REPOUSO','▶️'],['INÍCIO DE PERNOITE','INÍCIO DE PERNOITE','🌙'],['FIM DE PERNOITE','FIM DE PERNOITE','🌅'],['FIM DE JORNADA','FIM DE JORNADA','🔚'],['Oficina','Oficina','🔧'],['Posto de Gasolina','Posto de Gasolina','⛽'],['Garagem','Garagem','🅿️'],['Base/Terminal','Base/Terminal','🏢'],['Cliente','Cliente','🤝'],['Fornecedor','Fornecedor','📦'],['Pátio','Pátio','🏭'],['Embarcadouro','Embarcadouro','⚓'],['Porto Seco','Porto Seco','🚢'],['Almoxarifado','Almoxarifado','📦'],['Centro de Distribuição','Centro de Distribuição','🏭'],['Ponto de Apoio','Ponto de Apoio','🆘'],['Parada Obrigatória','Parada Obrigatória','🛑'],['Pesagem','Pesagem','⚖️'],['Fronteira','Fronteira','🚧'],['Alfândega','Alfândega','🛃'],['Garagem Cliente','Garagem Cliente','🏠'],['Pátio Cliente','Pátio Cliente','🏭']];
    foreach($todosTipos as $t){
        $__rsChk3 = query("SELECT 1 FROM poi_tipo WHERE poti_tx_codigo = ? LIMIT 1", "s", [$t[0]]);
        $chk3 = $__rsChk3 ? mysqli_fetch_assoc($__rsChk3) : null;
        if(empty($chk3)){
            query("INSERT INTO poi_tipo (poti_tx_codigo, poti_tx_nome, poti_tx_emoji) VALUES (?, ?, ?)", "sss", $t);
        }else{
            query("UPDATE poi_tipo SET poti_tx_emoji = ?, poti_tx_nome = ? WHERE poti_tx_codigo = ?", "sss", [$t[2], $t[1], $t[0]]);
        }
    }
    $tiposPoi = [];
    $rsTipos = query("SELECT poti_tx_codigo, poti_tx_nome, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo' ORDER BY poti_tx_nome ASC");
    while($rsTipos && ($r = mysqli_fetch_assoc($rsTipos))){ $tiposPoi[] = $r; }


    // Função para buscar pontos
    function buscarPontos($matricula, $data) {
        global $conn;
        
        // Definir o intervalo de datas para o dia inteiro
        $dataInicio = $data." 00:00:00";
        $dataFim = $data." 23:59:59";
        
        // Prepare a consulta SQL
        $sql = "SELECT pont_nb_id, pont_tx_data, macr_tx_nome, moti_tx_nome, moti_tx_legenda, pont_tx_justificativa, user_tx_login, pont_tx_dataCadastro, pont_tx_latitude, pont_tx_longitude,pont_tx_placa FROM ponto
                JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
                JOIN user ON ponto.pont_nb_userCadastro = user.user_nb_id
                LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
                WHERE ponto.pont_tx_status = 'ativo'
                AND macroponto.macr_tx_fonte = 'positron'
                AND ponto.pont_tx_matricula = ?
                AND ponto.pont_tx_data BETWEEN ? AND ?
                ORDER BY ponto.pont_tx_data ASC";
        
        // Prepare a declaração
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            die("Erro na preparação da consulta: ".mysqli_error($conn));
        }

        // Bind dos parâmetros
        mysqli_stmt_bind_param($stmt, "sss", $matricula, $dataInicio, $dataFim);
        
        // Executar a declaração
        mysqli_stmt_execute($stmt);
        
        // Obter o resultado
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            die("Erro ao executar a consulta: ".mysqli_error($conn));
        }
        
        // Armazenar os resultados
        $pontos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Formatar a data pont_tx_data no formato dd/mm/yyyy
            $row["pont_tx_data"] = date("d/m/Y H:i:s", strtotime($row["pont_tx_data"]));
            $row["pont_tx_dataCadastro"] = date("d/m/Y H:i:s", strtotime($row["pont_tx_dataCadastro"]));
            $pontos[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        // Filtrar para garantir que "Fim de Jornada" não apareça antes do "Início de Jornada"
        $inicioEncontrado = false;
        $pontosFiltrados = [];
        
        foreach ($pontos as $ponto) {
            if ($ponto["macr_tx_nome"] === "Inicio de Jornada") {
                $inicioEncontrado = true;
            }
            
            if ($inicioEncontrado || $ponto["macr_tx_nome"] !== "Fim de Jornada") {
                $pontosFiltrados[] = $ponto;
            }
        }
        
        return $pontosFiltrados;
    }

    // Recuperar os parâmetros da URL
    $matricula = isset($_GET["matricula"]) ? $_GET["matricula"] : "";
    $data = isset($_GET["data"]) ? $_GET["data"] : "";

    // Buscar pontos com os parâmetros fornecidos
    $pontos = buscarPontos($matricula, $data);

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        

    function carregarTipos() {
        global $conn;
        // Atualize a consulta para obter apenas os tipos específicos
        $sql = "SELECT macr_tx_codigoInterno, macr_tx_nome FROM macroponto 
            WHERE macr_tx_codigoInterno IN (1,2,3,5,7,9) AND macr_tx_fonte = 'positron';"
        ;
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Erro ao consultar tipos: ".mysqli_error($conn));
        }

        $tipos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tipos[] = $row;
        }
        return $tipos;
    }


    // Função para carregar motivos
    function carregarMotivos() {
        global $conn;
        // Atualize a consulta para obter apenas os motivos específicos
    $sql = "SELECT moti_nb_id, moti_tx_nome 
            FROM motivo 
            WHERE moti_tx_tipo = 'Ajuste'";

        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Erro ao consultar motivos: ".mysqli_error($conn));
        }

        $motivos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $motivos[] = $row;
        }
        return $motivos;
    }


        // Função para carregar motoristas
        function carregarMotoristas() {
            global $conn;
            $sql = "SELECT DISTINCT enti_tx_matricula, enti_tx_nome
                    FROM entidade 
                    ORDER BY enti_tx_matricula ASC";

            $result = mysqli_query($conn, $sql);

            if (!$result) {
                die("Erro ao consultar motoristas: ".mysqli_error($conn));
            }

            $motoristas = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $motoristas[] = $row;
                
            }
            return $motoristas;
        }

        // Função para carregar os últimos ajustes
        function carregarUltimosAjustes() {
            global $conn;
            $sql = "
                SELECT p.pont_tx_matricula, p.pont_tx_dataCadastro, p.pont_tx_tipo, p.pont_nb_motivo, p.pont_tx_descricao, 
                    t.macr_tx_nome AS tipo_nome, m.moti_tx_nome AS motivo_nome
                FROM ponto p
                INNER JOIN macroponto t ON p.pont_tx_tipo = t.macr_tx_codigoInterno
                INNER JOIN motivo m ON p.pont_nb_motivo = m.moti_nb_id
                WHERE p.pont_nb_userCadastro = ?
                ORDER BY p.pont_tx_dataCadastro DESC
                LIMIT 10";  // Ajuste o limite conforme necessário

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $_SESSION["user_nb_id"]);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                $ajustes = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $ajustes[] = $row;
                }
                mysqli_stmt_close($stmt);
                return $ajustes;
            } else {
                die("Erro na preparação da consulta: ".mysqli_error($conn));
            }
        }

        // Função para contar o número total de motoristas
        function contarMotoristas() {
            global $conn;
            $sql = "SELECT COUNT(DISTINCT enti_tx_matricula) AS total_motoristas FROM entidade";
            $result = mysqli_query($conn, $sql);

            if (!$result) {
                die("Erro ao contar motoristas: ".mysqli_error($conn));
            }

            $row = mysqli_fetch_assoc($result);
            return $row["total_motoristas"];
        }

    





    // Processa salvamento de POI via AJAX
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "salvar_poi") {
        // Garante que a tabela poi existe (sem incluir cadastro_poi.php para evitar output extra)
        query("CREATE TABLE IF NOT EXISTS poi (
            poi_nb_id INT AUTO_INCREMENT PRIMARY KEY,
            poi_tx_nome VARCHAR(150) NOT NULL,
            poi_tx_cnpj VARCHAR(20) NOT NULL DEFAULT '',
            poi_tx_contato VARCHAR(100) NOT NULL DEFAULT '',
            poi_tx_latitude DECIMAL(10,7) NOT NULL,
            poi_tx_longitude DECIMAL(10,7) NOT NULL,
            poi_nb_raio INT NOT NULL DEFAULT 50,
            poi_tx_icone VARCHAR(50) NOT NULL DEFAULT '',
            poi_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            poi_nb_userCadastro INT DEFAULT NULL,
            poi_tx_dataCadastro DATETIME NOT NULL,
            UNIQUE KEY uniq_poi_latlong (poi_tx_latitude, poi_tx_longitude),
            KEY idx_poi_status (poi_tx_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Garante colunas em tabelas antigas
        $rsCheck = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_icone'");
        if($rsCheck && !mysqli_fetch_assoc($rsCheck)){
            query("ALTER TABLE poi ADD COLUMN poi_tx_icone VARCHAR(50) NOT NULL DEFAULT '' AFTER poi_nb_raio");
        }
        $rsCheck2 = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_endereco'");
        if($rsCheck2 && !mysqli_fetch_assoc($rsCheck2)){
            query("ALTER TABLE poi ADD COLUMN poi_tx_endereco VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_icone");
            query("ALTER TABLE poi ADD COLUMN poi_tx_cep VARCHAR(10) NOT NULL DEFAULT '' AFTER poi_tx_endereco");
        }
        $rsCheckImg = query("SHOW COLUMNS FROM poi LIKE 'poi_tx_imagem'");
        if($rsCheckImg && !mysqli_fetch_assoc($rsCheckImg)){
            query("ALTER TABLE poi ADD COLUMN poi_tx_imagem VARCHAR(255) NOT NULL DEFAULT '' AFTER poi_tx_cep");
        }
        header("Content-Type: application/json");
        $erro = "";
        $novoId = null;

        $nome = trim($_POST["nome"] ?? "");
        $cnpj = preg_replace('/[^0-9]/', '', (string)($_POST["cnpj"] ?? ""));
        $contato = trim($_POST["contato"] ?? "");
        $endereco = trim($_POST["endereco"] ?? "");
        $cep = trim($_POST["cep"] ?? "");
        $latitude = str_replace(",", ".", trim($_POST["latitude"] ?? ""));
        $longitude = str_replace(",", ".", trim($_POST["longitude"] ?? ""));
        $raio = intval($_POST["raio"] ?? 50);
        $icone = trim($_POST["icone"] ?? "");
        $caminhoImagem = "";

        // Upload de imagem
        if(!empty($_FILES["imagem"]) && $_FILES["imagem"]["error"] === UPLOAD_ERR_OK){
            $dir = __DIR__ . "/arquivos/poi";
            if(!is_dir($dir)){ @mkdir($dir, 0755, true); }
            $ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
            $extsPermitidas = ["jpg","jpeg","png","gif","webp"];
            if(in_array($ext, $extsPermitidas)){
                $nomeUnico = "poi_".time()."_".bin2hex(random_bytes(4)).".".$ext;
                $destino = $dir."/".$nomeUnico;
                if(move_uploaded_file($_FILES["imagem"]["tmp_name"], $destino)){
                    $caminhoImagem = "arquivos/poi/".$nomeUnico;
                }
            }
        }

        if(empty($nome)){
            echo json_encode(["sucesso" => false, "erro" => "Nome é obrigatório"]);
            exit;
        }
        if(!is_numeric($latitude) || !is_numeric($longitude)){
            echo json_encode(["sucesso" => false, "erro" => "Latitude e Longitude inválidas"]);
            exit;
        }
        if($raio <= 0){ $raio = 50; }

        $userId = !empty($_SESSION["user_nb_id"]) ? (int)$_SESSION["user_nb_id"] : 0;
        $editId = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;

        // Verifica duplicidade de nome
        $dupCheck = query("SELECT poi_nb_id FROM poi WHERE poi_tx_nome = ? AND poi_tx_status = 'ativo' LIMIT 1", "s", [$nome]);
        $dupRow = $dupCheck ? mysqli_fetch_assoc($dupCheck) : null;
        if($dupRow && (!empty($dupRow["poi_nb_id"]) && intval($dupRow["poi_nb_id"]) !== $editId)){
            echo json_encode(["sucesso" => false, "erro" => "Já existe um POI com este nome."]);
            exit;
        }

        if($editId > 0){
            $dados = [
                "poi_tx_nome"       => $nome,
                "poi_tx_cnpj"       => $cnpj,
                "poi_tx_contato"    => $contato,
                "poi_tx_endereco"   => $endereco,
                "poi_tx_cep"        => $cep,
                "poi_tx_latitude"   => $latitude,
                "poi_tx_longitude"  => $longitude,
                "poi_nb_raio"       => $raio,
                "poi_tx_icone"      => $icone
            ];
            if($caminhoImagem){
                $dados["poi_tx_imagem"] = $caminhoImagem;
                $antigo = mysqli_fetch_assoc(query("SELECT poi_tx_imagem FROM poi WHERE poi_nb_id = ?", "i", [$editId]));
                if(!empty($antigo["poi_tx_imagem"]) && file_exists($antigo["poi_tx_imagem"])){
                    @unlink($antigo["poi_tx_imagem"]);
                }
            }
            atualizar("poi", array_keys($dados), array_values($dados), strval($editId));
            $dados["poi_nb_id"] = $editId;
            echo json_encode(["sucesso" => true, "id" => $editId, "poi" => $dados]);
            exit;
        }

        $dados = [
            "poi_tx_nome"       => $nome,
            "poi_tx_cnpj"       => $cnpj,
            "poi_tx_contato"    => $contato,
            "poi_tx_endereco"   => $endereco,
            "poi_tx_cep"        => $cep,
            "poi_tx_latitude"   => $latitude,
            "poi_tx_longitude"  => $longitude,
            "poi_nb_raio"       => $raio,
            "poi_tx_icone"      => $icone,
            "poi_tx_status"     => "ativo",
            "poi_nb_userCadastro" => $userId,
            "poi_tx_dataCadastro" => date("Y-m-d H:i:s")
        ];
        if($caminhoImagem){
            $dados["poi_tx_imagem"] = $caminhoImagem;
        }

        $res = inserir("poi", array_keys($dados), array_values($dados));

        if(gettype($res[0] ?? null) === "object"){
            echo json_encode(["sucesso" => false, "erro" => $res[0]->getMessage()]);
            exit;
        }

        $novoId = $res[0] ?? null;

        echo json_encode(["sucesso" => true, "id" => $novoId, "poi" => $dados]);
        exit;
    }

    // Processar o formulário quando enviado
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajustes"])) {
        $ajustes = json_decode($_POST["ajustes"], true); // Decodifica o JSON para um array PHP

        if (is_array($ajustes) && !empty($ajustes)) {
            foreach ($ajustes as $ajuste) {
                $id = $ajuste["motorista"];
                $data = $ajuste["data"];
                $hora = $ajuste["hora"];
                $idMacro = $ajuste["idMacro"];
                $motivo = $ajuste["motivo"];
                $descricao = $ajuste["descricao"];
                $plate = $ajuste["plate"]; // Adiciona a placa
                $latitude = $ajuste["latitude"]; // Latitude
                $longitude = $ajuste["longitude"]; // Longitude

                // Verifica se a placa está sendo capturada corretamente
                error_log("Placa recebida: ".$plate); 

                // Inserir dados na tabela
                $sql = "INSERT INTO ponto (
                            pont_nb_userCadastro, 
                            pont_tx_matricula, 
                            pont_tx_data, 
                            pont_tx_tipo, 
                            pont_nb_motivo, 
                            pont_tx_justificativa, 
                            pont_tx_placa,  
                            pont_tx_status, 
                            pont_tx_dataCadastro,
                            pont_tx_latitude,  -- Adiciona latitude
                            pont_tx_longitude  -- Adiciona longitude
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo', NOW(), ?, ?)";

                if ($stmt = mysqli_prepare($conn, $sql)) {
                    $dataHora = $data." ".$hora;
                    mysqli_stmt_bind_param($stmt, "sssssssss", 
                        $_SESSION["user_nb_id"],  // Tipo s
                        $id,                    // Tipo s
                        $dataHora,              // Tipo s
                        $idMacro,               // Tipo s
                        $motivo,                // Tipo s
                        $descricao,             // Tipo s
                        $plate,                 // Tipo s
                        $latitude,              // Tipo s
                        $longitude              // Tipo s
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $sucesso = "Ajustes enviados com sucesso!";
                    } else {
                        $erro = "Erro ao registrar ajuste : ".mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $erro = "Erro na preparação da consulta: ".mysqli_error($conn);
                }
            }
            // Recarregar os últimos ajustes após o sucesso
            $ultimosAjustes = carregarUltimosAjustes();
        } else {
            $erro = "Nenhum ajuste para enviar.";
        }
    }








        // Carregar dados para o formulário e para a tabela de últimos ajustes
        $motoristas = carregarMotoristas();
        $tipos = carregarTipos();
        $motivos = carregarMotivos();
        $ultimosAjustes = carregarUltimosAjustes();

        // Obter as contagens
        $totalMotoristas = contarMotoristas();











    cabecalho("");


    $htmls = [
        "motoristas" => "",
        "pontos" => "",
        "tipos" => "",
        "motivos" => "",
    ];
    foreach($motoristas as $motorista){
        $htmls["motoristas"] .= "<option value='".htmlspecialchars($motorista["enti_tx_matricula"])."'>
                ".htmlspecialchars($motorista["enti_tx_nome"])."
            </option>"
        ;
    };
    foreach ($pontos as $ponto){
        $htmls["pontos"] .=
            "<tr>
                <td>".htmlspecialchars($ponto["pont_tx_data"])."</td>
                <td>".htmlspecialchars($ponto["macr_tx_nome"])."</td>
                <td>".htmlspecialchars($ponto["pont_tx_placa"])."</td>
                <td>".htmlspecialchars($ponto["pont_tx_legenda"])."</td>
                <td>".((!empty($ponto["pont_tx_latitude"]) && !empty($ponto["pont_tx_longitude"]))? 
                    "<a href='https://www.google.com/maps?q={$ponto["pont_tx_latitude"]},{$ponto["pont_tx_longitude"]} target='_blank' title='Ver no Google Maps'>
                        <i class='fa fa-map' style='color: #183153; font-size: 1.5em;'></i>
                    </a>": 
                    "<span>Sem localização</span>"
                )."
                </td>
            </tr>"
        ;
    }
    foreach ($tipos as $tipo){
        $htmls["tipos"] .= 
            "<option value='".htmlspecialchars($tipo["macr_tx_codigoInterno"])."'>
                ".htmlspecialchars($tipo["macr_tx_nome"])."
            </option>"
        ;
    }
    foreach ($motivos as $motivo){
        $htmls["motivos"] .= 
            "<option value='".htmlspecialchars($motivo["moti_nb_id"])."'>
                ".htmlspecialchars($motivo["moti_tx_nome"])."
            </option>"
        ;
    }

    include "logistica_html.php";
    rodape();
