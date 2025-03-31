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
    $plateCount = 0;

    if ($cnpjList) {
        // Codifica os CNPJs para garantir que estejam no formato correto para a URL
        $cnpj_encoded = urlencode($cnpjList);
        
        // URL da API
        $url = "https://logistica.logsyncwebservice.techps.com.br/plates?cnpj={$cnpj_encoded}";
        
        // Faz a requisição à API
        $response = file_get_contents($url);
        
        // Decodifica a resposta JSON
        $plates = json_decode($response, true);
        $plateCount = count($plates);
    }

    

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
            WHERE macr_tx_codigoInterno IN (3,5 ,7,9) AND macr_tx_fonte = 'positron';"
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
