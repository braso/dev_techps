<?php
// Ativar relatórios de erros

/*Modo Debug{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
//}*/
session_start();

include_once 'load_env.php';
include_once 'funcoes_ponto.php';
include_once 'conecta.php'; // Incluindo a conexão

if (isset($_SESSION['user_nb_id']) && !empty($_SESSION['user_nb_id'])) {
    $user_nb_id = $_SESSION['user_nb_id'];

    // Variáveis de erro e sucesso
    $erro = "";
    $sucesso = "";
    
    
// Obtém o valor do CNPJ da URL, esperado como uma lista separada por vírgulas
$cnpjList = isset($_GET['cnpj']) ? $_GET['cnpj'] : '';

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
    $dataInicio = $data . ' 00:00:00';
    $dataFim = $data . ' 23:59:59';
    
    // Prepare a consulta SQL
    $sql = "SELECT pont_nb_id, pont_tx_data, macr_tx_nome, moti_tx_nome, moti_tx_legenda, pont_tx_justificativa, user_tx_login, pont_tx_dataCadastro, pont_tx_latitude, pont_tx_longitude FROM ponto
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
    
    // Verifica se a preparação foi bem-sucedida
    if (!$stmt) {
        die("Erro na preparação da consulta: " . mysqli_error($conn));
    }

    $fonte = "positron";
    
    // Bind dos parâmetros
    mysqli_stmt_bind_param($stmt, 'sss', $matricula, $dataInicio, $dataFim);
    
    // Executar a declaração
    mysqli_stmt_execute($stmt);
    
    // Obter o resultado
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        die("Erro ao executar a consulta: " . mysqli_error($conn));
    }
    
    // Armazenar os resultados
    $pontos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Formatar a data pont_tx_data no formato dd/mm/yyyy
        $row['pont_tx_data'] = date('d/m/Y H:i:s', strtotime($row['pont_tx_data']));
        $row['pont_tx_dataCadastro'] = date('d/m/Y H:i:s', strtotime($row['pont_tx_dataCadastro']));
        
        $pontos[] = $row;
    }
    
    // Fechar a declaração
    mysqli_stmt_close($stmt);
    
    return $pontos;
}



// Recuperar os parâmetros da URL
$matricula = isset($_GET['matricula']) ? $_GET['matricula'] : '';
$data = isset($_GET['data']) ? $_GET['data'] : '';

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
        die("Erro ao consultar tipos: " . mysqli_error($conn));
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
        die("Erro ao consultar motivos: " . mysqli_error($conn));
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
            die("Erro ao consultar motoristas: " . mysqli_error($conn));
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
            mysqli_stmt_bind_param($stmt, 's', $_SESSION['user_nb_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $ajustes = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $ajustes[] = $row;
            }
            mysqli_stmt_close($stmt);
            return $ajustes;
        } else {
            die("Erro na preparação da consulta: " . mysqli_error($conn));
        }
    }

    // Função para contar o número total de motoristas
    function contarMotoristas() {
        global $conn;
        $sql = "SELECT COUNT(DISTINCT enti_tx_matricula) AS total_motoristas FROM entidade";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Erro ao contar motoristas: " . mysqli_error($conn));
        }

        $row = mysqli_fetch_assoc($result);
        return $row['total_motoristas'];
    }

  





// Processar o formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajustes'])) {
    $ajustes = json_decode($_POST['ajustes'], true); // Decodifica o JSON para um array PHP

    if (is_array($ajustes) && !empty($ajustes)) {
        foreach ($ajustes as $ajuste) {
            $id = $ajuste['motorista'];
            $data = $ajuste['data'];
            $hora = $ajuste['hora'];
            $idMacro = $ajuste['idMacro'];
            $motivo = $ajuste['motivo'];
            $descricao = $ajuste['descricao'];
            $plate = $ajuste['plate']; // Adiciona a placa
            $latitude = $ajuste['latitude']; // Latitude
            $longitude = $ajuste['longitude']; // Longitude

            // Verifica se a placa está sendo capturada corretamente
            error_log("Placa recebida: " . $plate); 

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
                $dataHora = $data . ' ' . $hora;
                mysqli_stmt_bind_param($stmt, 'sssssssss', 
                    $_SESSION['user_nb_id'],  // Tipo s
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
                    $erro = "Erro ao registrar ajuste: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $erro = "Erro na preparação da consulta: " . mysqli_error($conn);
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











cabecalho('');
?>






 <script>
 //CAPTURA OS PARAMETROS DA URL PARA COLCOAR NOS INPUTS VINDO DO AJUSTE DE PONTO
        document.addEventListener('DOMContentLoaded', function() {
            function getParameter(theParameter) {
                var params = window.location.search.substr(1).split('&');
                for (var i = 0; i < params.length; i++) {
                    var p = params[i].split('=');
                    if (p[0] === theParameter) {
                        return decodeURIComponent(p[1]);
                    }
                }
                return false;
            }

            var matricula = getParameter('matricula');
            var id = getParameter('id');
            var data = getParameter('data');

            if (matricula) {
                var motoristaSelect = document.getElementById('id');
                for (var i = 0; i < motoristaSelect.options.length; i++) {
                    if (motoristaSelect.options[i].value === matricula) {
                        motoristaSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            if (data) {
                document.getElementById('date').value = data;
            }
        });
    </script>
    
    
    

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel de Ajuste e Não Conformidades</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     <link rel="stylesheet" href="css/logistica_modal.css">
     <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js" type="module"></script>
     
  <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js" nomodule></script>
</head>
<body>
    
<!-- Exibe mensagens de erro ou sucesso -->
<?php if ($erro): ?>
    <div id="popupErro" class="popup popup-erro">
        <?php echo htmlspecialchars($erro); ?>
    </div>
<?php endif; ?>
<?php if ($sucesso): ?>
    <div id="popupSucesso" class="popup popup-sucesso">
        <?php echo htmlspecialchars($sucesso); ?>
    </div>
<?php endif; ?>





    <div id="loading-screen">
    <i class="fas fa-spinner fa-spin"></i>
    <p>Buscando dados, por favor, aguarde...</p>
</div>





       <div class="container">
        <div id="form_header" class="form_title">
			<img src="imagens/LGC.png" alt="Logo" class="logo">
            <h2 class="title-section">Painel de Não Conformidades Logísticas</h2>
            <button type="button" class="btn btn-primary" id="toggleFormBtn">✒️</button>
        </div>
  


      
        <div class="table-container">
            <form id="filterForm" method="post">
                <div class="form-group">
                    <label class="label-form" for="id">Motorista:</label>
                    <select class="form-control field-form" id="id" name="id" disabled>
                        <?php foreach ($motoristas as $motorista): ?>
                            <option value="<?= htmlspecialchars($motorista['enti_tx_matricula']) ?>">
                                <?= htmlspecialchars($motorista['enti_tx_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                           
<div class="form-group">
    <label for="plate-search">Buscar Placa:</label>
    <input type="text" id="plate" name="plate" class="form-control field-form" placeholder="Digite a placa">
    <ul id="plate-suggestions" class="list-group"></ul>
       

</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('plate');
    const suggestionsList = document.getElementById('plate-suggestions');

    // Array de placas vindo do PHP
    const plates = <?php echo json_encode($plates); ?>;

    // Escuta o evento de input no campo de busca
    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toUpperCase(); // Converte o texto digitado em maiúsculas
        suggestionsList.innerHTML = ''; // Limpa as sugestões anteriores

        if (filter === '') return; // Se o campo de busca estiver vazio, não mostra nada

        // Filtra as placas com base no que foi digitado
        const filteredPlates = plates.filter(plate => plate.toUpperCase().includes(filter));

        // Exibe as sugestões filtradas
        filteredPlates.forEach(plate => {
            const li = document.createElement('li');
            li.textContent = plate;
            li.classList.add('list-group-item');
            suggestionsList.appendChild(li);

            // Quando uma sugestão for clicada, preenche o campo de texto e limpa as sugestões
            li.addEventListener('click', function() {
                searchInput.value = plate;
                suggestionsList.innerHTML = ''; // Limpa a lista de sugestões
            });
        });
    });
});

</script>
                <div class="form-group">
                    <label class="label-form" for="date">Período a consultar</label>
                    <input type="date" class="form-control field-form" id="date" name="date">
                </div>
                
                <div class="form-group text-end button-search">
                    <div class="btn-group">
                        <button type="submit" id="consultarBtn" class="btn btn-dark button-consulta">Consultar</button>
                     
                    </div>
                </div>
            </form>
        </div>
    </div>
            
            
            
            
            <div id="messageDiv"></div>
                 <div class="container">
    <div class="accordion" id="accordionExample">
        <!-- Accordion -->
        <div class="accordion-item">
            <h2 class="title-section" class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                 
                    Ver Ponto registrado pelo colaborador    <i class="fa-solid fa-arrow-down"></i>
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pontos as $ponto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ponto['pont_tx_data']); ?></td>
                                    <td><?php echo htmlspecialchars($ponto['macr_tx_nome']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    <div class="container" id="results">
        <h2 class="title-section">Histórico de paradas</h2>

    </div>

   
        
        
        
        
        
        <div class="form-container" id="formContainer">
    <h3 id="formContainer">Inserir Ajuste de Ponto</h3>



    <!-- Formulário de Ajuste de Ponto -->
    <form id="adjustmentForm">
        <div class="form-group">
            <label for="motorista">Motorista:</label>
            <select class="form-control field-form  " id="motorista" name="motorista" disabled>
                <?php foreach ($motoristas as $motorista): ?>
                    <option value="<?= htmlspecialchars($motorista['enti_tx_matricula']) ?>">
                        <?= htmlspecialchars($motorista['enti_tx_nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="data">Data:</label>
            <input type="date" class="form-control field-form" id="data" name="data" value="<?php echo date('Y-m-d'); ?>" disabled >
        </div>
        <div class="form-group">
            <label for="hora">Hora Inicio:</label>
            <input type="time" class="form-control field-form" id="hora" name="hora" required>
        </div>
        <div class="form-group">
    <label for="horaFim">Hora Fim:</label>
    <input type="time" class="form-control field-form" id="horaFim" name="horaFim" required>
</div>
  <div class="form-group">
        <label for="latitude">Latitude:</label>
        <input type="text" class="form-control field-form" id="latitude" name="latitude" placeholder="Latitude" disabled>
    </div>
    <div class="form-group">
        <label for="longitude">Longitude:</label>
        <input type="text" class="form-control field-form" id="longitude" name="longitude" placeholder="Longitude" disabled>
    </div>
    
        <div class="form-group">
            <label for="idMacro">Tipo de Registro:</label>
            <select class="form-control field-form" id="idMacro" name="idMacro" required>
                 <option value="" disabled selected>Selecionar</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= htmlspecialchars($tipo['macr_tx_codigoInterno']) ?>">
                        <?= htmlspecialchars($tipo['macr_tx_nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="motivo">Motivo:</label>
            <select class="form-control field-form" id="motivo" name="motivo" required>
                 <option value="" disabled selected>Selecionar</option>
                <?php foreach ($motivos as $motivo): ?>
                    <option value="<?= htmlspecialchars($motivo['moti_nb_id']) ?>">
                        <?= htmlspecialchars($motivo['moti_tx_nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="descricao">Justificativa:</label>
            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
        </div>
        
         <div class="form-group">
            <label for="coment">Comentário:</label>
            <textarea class="form-control" id="coment" name="coment" rows="3"></textarea>
        </div>
     
        <button type="button" class="btn btn-primary" id="addAdjustmentBtn">Adicionar à Lista</button>
    </form>
<!-- Resumo -->
<div class="resumo" id="resumoContainer">
  
</div>
<style>
    #adjustmentTable{
        display:none;
    }
    
    .field-form{
        border: 1px solid #35A3BC;
        border-radius: 20px;
        padding: 1rem;
        width: 250px;
        height: 40px;
    }
    #plate-suggestions{
        border: none;
    }
    .form-control[disabled]{
        background-color: white;
    }
    .label-form{
        padding: 10px;
    }
    .row div label{
        margin: 0;
        padding: 10px;
        text-transform: uppercase;
    }
    #consultarBtn{
        margin-top: 2.6rem;
        background: #35A3BC;
        border-radius: 20px;
        width: 200px;
    }
    #toggleFormBtn {
    position: fixed;
    bottom: 9rem; /* Ajuste a posição conforme necessário */
    left: 0.5rem; /* Ajuste a posição conforme necessário */
    margin-top: 0; /* Remova o margin-top, pois a posição é fixa */
    background: #192942;
    border-radius: 5px;
    width: 60px;
    z-index: 1000; /* Garante que fique acima de outros elementos */
}
#toggleFormBtn:hover {
    background: #35A3BC;
    border-radius: 10px;
    width: 60px;
    transition: 0.5s ease; /* Ajustado para uma transição mais rápida e suave */
}
     #consultarBtn:hover{
        margin-top: 2.6rem;
        background: #35A3BC;
        border-radius: 20px;
                width: 200px;

    
         
     }
    .accordion-button{
    border: none;
    background: none;
    font-size: 24px;
        display: flex;
    justify-content: space-between;
    flex-wrap: nowrap;
    flex-direction: row;
    width: 100%;
    }
   .title-section{
       font-size: 24px;
       font-weight: 500;
       text-transform: uppercase;
   }
    .title-section button{
       font-size: 24px;
       font-weight: 500;
       text-transform: uppercase;
   }
   .fa-arrow-down{
       color: white;
       padding: 10px;
       background-color: #35A3BC;
       border-radius: 50px;
    font-size: 20px;
   }
  
  
  
</style>
    <!-- Lista de Ajustes -->
    <div class="adjustment-list">
        <h3>Lista de Ajustes</h3>
        <table id="adjustmentTable">
            <thead>
                <tr>
                    <th>Motorista</th>
                    
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Lat</th>
                    <th>Long</th>
                    <th>Tipo de Registro</th>
                    <th>Motivo</th>
                    <th>Justificativa</th> 
                     <th>Placa</th>       
                    <th>Excluir</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <button type="button" class="btn btn-success" id="submitAdjustmentsBtn">Salvar Ajustes</button>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="js/logistica.js"></script>
    <script src="js/logistica_modal.js"></script>

</body>
</html>

    </style>
</style>
<?php
} else {
    echo "Você precisa estar logado para acessar esta página.";
}
?>


<script>

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('plate');
    const suggestionsList = document.getElementById('plate-suggestions');

    // Array de placas vindo do PHP
    const plates = <?php echo json_encode($plates); ?>;

    // Escuta o evento de input no campo de busca
    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toUpperCase(); // Converte o texto digitado em maiúsculas
        suggestionsList.innerHTML = ''; // Limpa as sugestões anteriores

        if (filter === '') return; // Se o campo de busca estiver vazio, não mostra nada

        // Filtra as placas com base no que foi digitado
        const filteredPlates = plates.filter(plate => plate.toUpperCase().includes(filter));

        // Exibe as sugestões filtradas
        filteredPlates.forEach(plate => {
            const li = document.createElement('li');
            li.textContent = plate;
            li.classList.add('list-group-item');
            suggestionsList.appendChild(li);

            // Quando uma sugestão for clicada, preenche o campo de texto e limpa as sugestões
            li.addEventListener('click', function() {
                searchInput.value = plate;
                suggestionsList.innerHTML = ''; // Limpa a lista de sugestões
            });
        });
    });
});




    // Função para ocultar as mensagens após 5 segundos
    function hideMessageAfterDelay(messageId) {
        var messageElement = document.getElementById(messageId);
        if (messageElement) {
            setTimeout(function() {
                messageElement.style.display = 'none';
            }, 5000); // 5000 milissegundos = 5 segundos
        }
    }

    // Chama a função para esconder mensagens de erro e sucesso
    hideMessageAfterDelay('popupErro');
    hideMessageAfterDelay('popupSucesso');
</script>
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
<?php
rodape();

?>