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
    <?=($erro)?"<div id='popupErro' class='popup popup-erro'>".htmlspecialchars($erro)."</div>":""?>
    <?=($sucesso)?"<div id='popupSucesso' class='popup popup-sucesso'>".htmlspecialchars($sucesso)."</div>":""?>

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
                        <?=$htmls["motoristas"]?>
                    </select>
                </div>


                <div class="form-group">
                    <label for="plate-search">Buscar Placa:</label>
                    <input type="text" id="plate" name="plate" class="form-control field-form"
                        placeholder="Digite a placa">
                    <ul id="plate-suggestions" class="list-group"></ul>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const searchInput = document.getElementById('plate');
                        const suggestionsList = document.getElementById('plate-suggestions');

                        // Array de placas vindo do PHP
                        const plates = <?=json_encode($plates);?>;

                        // Escuta o evento de input no campo de busca
                        searchInput.addEventListener('input', function() {
                            const filter = searchInput.value
                        .toUpperCase(); // Converte o texto digitado em maiúsculas
                            suggestionsList.innerHTML = ''; // Limpa as sugestões anteriores

                            if (filter === '') return; // Se o campo de busca estiver vazio, não mostra nada

                            // Filtra as placas com base no que foi digitado
                            const filteredPlates = plates.filter(plate => plate.toUpperCase().includes(
                                filter));

                            // Exibe as sugestões filtradas
                            filteredPlates.forEach(plate => {
                                const li = document.createElement('li');
                                li.textContent = plate;
                                li.classList.add('list-group-item');
                                suggestionsList.appendChild(li);

                                // Quando uma sugestão for clicada, preenche o campo de texto e limpa as sugestões
                                li.addEventListener('click', function() {
                                    searchInput.value = plate;
                                    suggestionsList.innerHTML =
                                    ''; // Limpa a lista de sugestões
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
        <style>
        #adjustmentTable {
            display: none;
        }

        .field-form {
            border: 1px solid #35A3BC;
            border-radius: 20px;
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
            border-radius: 20px;
            width: 200px;
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
            border-radius: 20px;
            width: 200px;



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
</script>
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>