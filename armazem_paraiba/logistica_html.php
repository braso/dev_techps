<script>
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
        // Formata a data da URL para "YYYY-MM-DD"
        var formattedDate = data;

        // Define a data e hora de início como 00:00:00
        var dateStart = formattedDate + "T00:00";

        // Define a data e hora de fim como 23:59
        var dateEnd = formattedDate + "T23:59";
        
        document.getElementById('date_start').value = dateStart;
        document.getElementById('date_end').value = dateEnd;
    }
});
</script>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel de Ajuste e Não Conformidades.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/logistica_modal.css">
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js" type="module"></script>
<!-- Adicione isso ao head do seu HTML -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js" nomodule></script>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<!-- Leaflet Routing Machine CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<!-- Leaflet Routing Machine JS -->
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>



</head>

<body>

    <!-- Exibe mensagens de erro ou sucesso -->
    <?=($erro)?"<div id='popupErro' class='popup popup-erro'>".htmlspecialchars($erro)."</div>":""?>
    <?=($sucesso)?"<div id='popupSucesso' class='popup popup-sucesso'>".htmlspecialchars($sucesso)."</div>":""?>

    <div id="loading-screen">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Buscando dados, por favor, aguarde...</p>
    </div>

<!-- Adicione isso ao final do seu body -->
<!--<button  id="novoBotao" style="position:fixed; bottom:200px; left:5px; z-index:1000; background-color:#004173; color:white; border:none; padding:10px 5px; border-radius:5px;">Map Grafico 🗺️</button>-->
<button id="mapButton" style="display:none; position:fixed; bottom:150px; left:5px; z-index:1000; background-color:#004173; color:white; border:none; padding:10px 5px; border-radius:5px;">Posiçóes 📍</button>
<div id="mapPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:80%; height:80%; background-color:white; border:1px solid #ccc; z-index:1001;">
    <div id="map" style="width:100%; height:100%; position:relative; z-index:1;"></div>
    <button id="closeMapButton" style="position:absolute; top:2px; left:-42px; background-color:#004173; color:white; border:none; padding:10px 20px; border-radius:5px; z-index:1002;">X</button>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
    const novoBotao = document.getElementById("novoBotao");

    novoBotao.addEventListener("click", function() {
        // Abre a página teste.html em uma nova guia
        window.open("teste.html", "_blank");
    });
});
</script>


<div class="container">
    <div id="form_header" class="form_title">
        <img src="imagens/LGC.png" alt="Logo" class="logo">
        <h2 class="title-section">Painel de Não Conformidades Logísticas.</h2>
        <button type="button" class="btn btn-primary" id="toggleFormBtn">✒️</button>
    </div>

    <div class="table-container">
        <form id="filterForm" method="post">
            <div class="form-group">
                <label class="label-form" for="id">Motorista:</label>
                <select class="form-control field-form" id="id" name="id" disabled>
                    <?=$htmls["motoristas"]?>
                </select>
            </div>

            <div class="form-group">
                <label for="plate-search">Placa:</label>
                <input type="text" id="plate" name="plate" class="form-control field-form"
                       placeholder="Digite a placa" maxlength="7">
                <ul id="plate-suggestions" class="list-group"></ul>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('plate');
                    const suggestionsList = document.getElementById('plate-suggestions');

                    const plates = <?=json_encode($plates);?>;

                    searchInput.addEventListener('input', function() {
                        const filter = searchInput.value.toUpperCase();
                        suggestionsList.innerHTML = '';

                        if (filter === '') return;

                        const filteredPlates = plates.filter(plate => plate.toUpperCase().includes(filter));

                        filteredPlates.forEach(plate => {
                            const li = document.createElement('li');
                            li.textContent = plate;
                            li.classList.add('list-group-item');
                            suggestionsList.appendChild(li);

                            li.addEventListener('click', function() {
                                searchInput.value = plate;
                                suggestionsList.innerHTML = '';
                            });
                        });
                    });
                });
            </script>

<div class="form-group">
    <label class="label-form" for="date_start">Data e Hora Início:</label>
    <input type="datetime-local" class="form-control field-form" id="date_start" name="date_start" step="1">
</div>

<div class="form-group">
    <label class="label-form" for="date_end">Data e Hora Fim:</label>
    <input type="datetime-local" class="form-control field-form" id="date_end" name="date_end" step="1">
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
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">

                        Ver Ponto registrado pelo colaborador <i class="fa-solid fa-arrow-down"></i>
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                    data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Placa</th>
                                    <th>Legenda</th>
                                    <th>Local</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?=$htmls["pontos"]?>
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
                    <?=$htmls["motoristas"]?>
                </select>
            </div>

            <div class="form-group">
                <label for="data">Data:</label>
                <input type="date" class="form-control field-form" id="data" name="data"
                    value="<?=date("Y-m-d")?>" disabled>
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
                <input type="text" class="form-control field-form" id="latitude" name="latitude" placeholder="Latitude"
                    disabled>
            </div>
            <div class="form-group">
                <label for="longitude">Longitude:</label>
                <input type="text" class="form-control field-form" id="longitude" name="longitude"
                    placeholder="Longitude" disabled>
            </div>

            <div class="form-group">
                <label for="idMacro">Tipo de Registro:</label>
                <select class="form-control field-form" id="idMacro" name="idMacro" required>
                    <option value="" disabled selected>Selecionar</option>
                    <?=$htmls["tipos"]?>
                </select>
            </div>
            <div class="form-group">
                <label for="motivo">Motivo:</label>
                <select class="form-control field-form" id="motivo" name="motivo" required>
                    <option value="" disabled selected>Selecionar</option>
                    <?=$htmls["motivos"]?>
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


<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('plate');
    const suggestionsList = document.getElementById('plate-suggestions');

    // Array de placas vindo do PHP
    const plates = <?=json_encode($plates)?>;

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


document.addEventListener('DOMContentLoaded', function() {
    // Exemplo de dados, substitua pelos dados reais
    const kmPercorrida = 120; // km
    const velocidadeMaxima = 80; // km/h
    const velocidadeMedia = 60; // km/h
    const tempoPercorrido = 2; // horas

    // Atualiza os valores dos cards
    document.getElementById('kmPercorrida').innerText = `${kmPercorrida} km`;
    document.getElementById('velocidadeMaxima').innerText = `${velocidadeMaxima} km/h`;
    document.getElementById('velocidadeMedia').innerText = `${velocidadeMedia} km/h`;
    document.getElementById('tempoPercorrido').innerText = `${tempoPercorrido} h`;
});


</script>
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>


<style>

#plate {
        width: 150px; /* Ajuste este valor conforme necessário */
    }
        #adjustmentTable {
            display: none;
        }

        .field-form {
            border: 1px solid #35A3BC;
            border-radius: 10px;
            padding: 1rem;
            width: 250px;
            height: 40px;
        }

        #plate-suggestions {
            border: none;
        }

        .form-control[disabled] {
            background-color: white;
        }

        .label-form {
            padding: 10px;
        }

        .row div label {
            margin: 0;
            padding: 10px;
            text-transform: uppercase;
        }

        #consultarBtn {
            margin-top: 2.6rem;
            background: #35A3BC;
            border-radius: 10px;
            width: 100px;
            text-alight: center;
        }

        #toggleFormBtn {
            position: fixed;
            bottom: 9rem;
            /* Ajuste a posição conforme necessário */
            left: 0.5rem;
            /* Ajuste a posição conforme necessário */
            margin-top: 0;
            /* Remova o margin-top, pois a posição é fixa */
            background: #192942;
            border-radius: 5px;
            width: 60px;
            z-index: 1000;
            /* Garante que fique acima de outros elementos */
        }

        #toggleFormBtn:hover {
            background: #35A3BC;
            border-radius: 10px;
            width: 60px;
            transition: 0.5s ease;
            /* Ajustado para uma transição mais rápida e suave */
        }

        #consultarBtn:hover {
            margin-top: 2.6rem;
            background: #35A3BC;
            border-radius: 10px;
            width: 100px;
            text-alight: center;



        }

        .accordion-button {
            border: none;
            background: none;
            font-size: 24px;
            display: flex;
            justify-content: space-between;
            flex-wrap: nowrap;
            flex-direction: row;
            width: 100%;
        }

        .title-section {
            font-size: 24px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .title-section button {
            font-size: 24px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .fa-arrow-down {
            color: white;
            padding: 10px;
            background-color: #35A3BC;
            border-radius: 50px;
            font-size: 20px;
        }


 

        </style>