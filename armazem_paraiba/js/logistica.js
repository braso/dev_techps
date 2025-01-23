document.addEventListener("DOMContentLoaded", () => {
    const filterForm = document.getElementById("filterForm");
    const resultsDiv = document.getElementById("results");
    const messageDiv = document.getElementById("messageDiv");


    filterForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const plate = document.getElementById("plate").value;
        const date = document.getElementById("date").value; // Obtém a data diretamente como string
        const motoristaSelect = document.getElementById("id"); // Obtém o elemento select
        const motoristaNome = motoristaSelect.options[motoristaSelect.selectedIndex].text; // Obtém o texto da opção selecionada

        console.log(motoristaNome); // Verifica o nome do motorista no console



        const speed = 99;
    
        if (!date) {
            messageDiv.innerHTML = "Por favor, selecione uma data.";
            return;
        }

        const formattedDate = formatDate(date); // Se precisar formatar para o formato da API

        axios.post("https://logistica.logsyncwebservice.techps.com.br/data", {
            plate,
            date: formattedDate,
            speed
        }).then((response) => {
            const resultData = response.data;
            if (resultData.length === 0) {
                resultsDiv.textContent = "Nenhum resultado encontrado para a data e velocidade selecionadas.";
            } else {
                displayResults(resultData, plate, date, speed, motoristaNome); // Passa a data original
                messageDiv.innerHTML = "";
            }
        }).catch((error) => {
            console.error("Erro ao buscar dados:", error);
        });
    });

    function formatDate(dateString) {
        const [year, month, day] = dateString.split("-");
        return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
    }

    function formatDateBR(dateString) {
        const [year, month, day] = dateString.split("-");
        return `${day.padStart(2, "0")}/${month.padStart(2, "0")}/${year}`;
    }

    function getCurrentDateTime() {
        const now = new Date();
        const date = now.toLocaleDateString();
        const time = now.toLocaleTimeString();
        return `${date}-${time}`;
    }

    function formatTime(seconds) {
        const hours = Math.floor(seconds/3600);
        const minutes = Math.floor((seconds%3600)/60);
        const sec = Math.floor(seconds%60);
        return `${hours}h ${minutes}min ${sec}s`;
    }











    function displayResults(data, plate, formattedDate, speed, motoristaNome) {
        resultsDiv.innerHTML = "";
    
        console.log(data);
    
        let totalTrueTime = 0;
        let totalFalseTime = 0;
        let totalStopsIgnitionOn = 0;
        let totalStopsIgnitionOff = 0;
    
        const table = document.createElement("table");
        table.classList.add("table", "table-bordered");
        table.id = "resultsTable"; // Adiciona um ID à tabela para fácil seleção
        table.innerHTML = 
            `<thead class="thead-dark">
                <tr>
                    <th></th>
                    <th>Início de Parada</th>
                    <th>Fim de Parada</th>
                    <th>Endereço</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Ignição</th>
                    <th>Total de Parada</th>
                    <th>Mapa</th>
                    <th>KM</th>
                    <th>Diferença KM</th>
                </tr>
            </thead>
            <tbody>
            </tbody>`;
    
        const tbody = table.querySelector("tbody");
    
        let stopStart = null;
        let stopEnd = null;
        let isStopped = false;
        let currentIgnition = null;
    
        data.forEach((row, index) => {
            if (parseInt(row.speed) <= 5) {
                // Quando a velocidade é <= 5, verifica se houve mudança na ignição
                if (!isStopped || currentIgnition !== row.ignition) {
                    if (isStopped) {
                        // Calcula o tempo total da parada
                        const totalTime = (new Date(row.moduleTime) - stopStart) / 1000;
        
                        // Registra o grupo de parada se for maior ou igual a 5 minutos
                        if (totalTime >= 5 * 60) {
                            appendStopRow(
                                tbody,
                                data[index - 1], // Último registro antes da mudança
                                stopStart,
                                new Date(row.moduleTime), // Primeiro registro da ignição diferente
                                currentIgnition,
                                totalTime
                            );
        
                            // Incrementa o contador de paradas
                            if (currentIgnition === "true") {
                                totalStopsIgnitionOn++;
                                totalTrueTime += totalTime;
                            } else {
                                totalStopsIgnitionOff++;
                                totalFalseTime += totalTime;
                            }
                        }
                    }
        
                    // Reinicia o ciclo da parada
                    stopStart = new Date(row.moduleTime);
                    isStopped = true;
                    currentIgnition = row.ignition;
                }
        
                // Atualiza o último horário da ignição ativa
                stopEnd = new Date(row.moduleTime);
            } else {
                // Quando velocidade > 5, finaliza o grupo de parada atual
                if (isStopped) {
                    const totalTime = (stopEnd - stopStart) / 1000;
        
                    // Registra o grupo de parada se for maior ou igual a 5 minutos
                    if (totalTime >= 5 * 60) {
                        appendStopRow(
                            tbody,
                            data[index - 1], // Último registro antes da mudança
                            stopStart,
                            stopEnd,
                            currentIgnition,
                            totalTime
                        );
        
                        // Incrementa o contador de paradas
                        if (currentIgnition === "true") {
                            totalStopsIgnitionOn++;
                            totalTrueTime += totalTime;
                        } else {
                            totalStopsIgnitionOff++;
                            totalFalseTime += totalTime;
                        }
                    }
        
                    // Finaliza o estado de parada
                    isStopped = false;
                }
            }
        });
        
        // Verifica se há uma parada pendente no final do loop
        if (isStopped) {
            const totalTime = (stopEnd - stopStart) / 1000;
        
            if (totalTime >= 5 * 60) {
                appendStopRow(
                    tbody,
                    data[data.length - 1], // Último registro no dataset
                    stopStart,
                    stopEnd,
                    currentIgnition,
                    totalTime
                );
        
                // Incrementa o contador de paradas
                if (currentIgnition === "true") {
                    totalStopsIgnitionOn++;
                    totalTrueTime += totalTime;
                } else {
                    totalStopsIgnitionOff++;
                    totalFalseTime += totalTime;
                }
            }
        }
        
    
        const summaryDiv = document.createElement("div");
        summaryDiv.innerHTML = 
            `<h2 class="title-section">Resumo da pesquisa</h2>
            <div class="summary">
                <div class="summary-column">
                    <h6><i class="fas fa-user"></i> <b>Nome Funcionário:&nbsp; </b> ${motoristaNome}</h6>
                    <h6><i class="fas fa-search"></i> <b>Data de Consulta:&nbsp; </b> ${getCurrentDateTime()}</h6>
                    <h6><i class="fas fa-id-card"></i> <b>Placa:&nbsp; </b> ${plate}</h6>
                    <h6><i class="fas fa-calendar"></i> <b>Período de Consulta:&nbsp; </b> 24H </h6>
                </div>
                <div class="summary-column">
                    <h6><i class="fas fa-power-off" style="color: green;"></i> <b>${formatTime(totalTrueTime)} </b> &nbsp; com ignição ligada. Totalizando &nbsp;  <b>${totalStopsIgnitionOn} </b> &nbsp;  paradas.</h6>
                    <h6><i class="fas fa-power-off" style="color: red;"></i> <b>${formatTime(totalFalseTime)} </b>&nbsp; com ignição desligada. Totalizando  &nbsp; <b>${totalStopsIgnitionOff} </b> &nbsp;  paradas.</h6>
                </div>
            </div>
    
            <style>
                .summary {
                    display: flex;
                    justify-content: center;
                    margin-top: 20px;
                    border:none;
                    width: 100%;
                    max-width: 100%;
                }
    
                .summary-column {
                    padding: 10px;
                    border:none;
                }
                .summary-column i {
                    color:#35A3BC;
                }
    
                .summary-column h6 {
                    font-size: 14px;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    border:none;
                }
    
                .summary-column i {
                    margin-right: 10px;
                    border:none;
                }
                .fas{
                    color:#35A3BC;
                }
            </style>`;
    
        resultsDiv.appendChild(summaryDiv);
    
        table.style.display = "none";
        resultsDiv.appendChild(table);
    
        const secondTable = document.createElement("table");
        secondTable.classList.add("table", "table-bordered");
        secondTable.innerHTML = 
            `<thead class="thead-dark">
                <tr> 
                    <th></th>
                    <th>Início de Parada</th>
                    <th>Fim de Parada</th>
                    <th>Endereço</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Ignição</th>
                    <th>Total de Parada</th>
                    <th>Mapa</th>
                    <th>KM</th>
                    <th>Diferença KM</th>
                </tr>
            </thead>
            <tbody>
            </tbody>`;
    
        const secondTbody = secondTable.querySelector("tbody");
    
        tbody.querySelectorAll("tr").forEach((tr) => {
            if (!tr.classList.contains("speed-5")) {
                secondTbody.appendChild(tr.cloneNode(true));
            }
        });
    
        secondTable.style.display = "block";
        resultsDiv.appendChild(secondTable);
    
        // Chama a função para adicionar o event listener aos ícones após a tabela ser inserida
        addRowClickListeners();
    }











// Variáveis para armazenar o estado
var startTime = null;
var startRow = null;
var logoState = false; // Para acompanhar o estado das logos (true = colorido, false = cinza)
var selectedRows = []; // Para armazenar as linhas selecionadas
var coordinates = []; // Para armazenar as coordenadas das linhas selecionadas

// Adiciona listener de clique para preencher o formulário com dados da linha
function addRowClickListeners() {
    // Seleciona todas as imagens com a classe 'row-img'
    var images = document.getElementsByClassName("row-img");

    // Adiciona um event listener a cada imagem
    for (var i = 0; i < images.length; i++) {
        images[i].addEventListener("click", function () {
            // Obtém a linha pai da imagem
            var row = this.parentNode.parentNode;
            var cells = row.getElementsByTagName("td");

            // Captura os valores das células
            var start = cells[1].innerText.trim();
            var end = cells[2].innerText.trim();
            var address = cells[3].innerText.trim();
            var latitude = cells[4].innerText.trim(); // Adiciona captura da latitude
            var longitude = cells[5].innerText.trim(); // Adiciona captura da longitude

            // Captura o valor da placa do formulário
            var plate = document.getElementById("plate").value.trim();

            // Captura o valor do campo de comentário
            var comment = document.getElementById("coment").value.trim();

            // Função para formatar a hora no formato HH:mm
            function formatTime(timeString) {
                if (!timeString || timeString === "undefined" || (timeString.split(":")).length < 2) {
                    return "";
                }
                var timeParts = timeString.split(":");
                var hours = timeParts[0];
                var minutes = timeParts[1];
                if (hours >= 0 && hours < 24 && minutes >= 0 && minutes < 60) {
                    return `${hours.padStart(2, "0")}:${minutes.padStart(2, "0")}`;
                }
                return "";
            }

            // Função para converter a hora no formato HH:mm para minutos totais
            function timeToMinutes(timeString) {
                var timeParts = timeString.split(":");
                var hours = parseInt(timeParts[0]);
                var minutes = parseInt(timeParts[1]);
                return hours * 60 + minutes;
            }

            if (startTime === null) {
                // Primeira seleção (início)
                startTime = start;
                startRow = row;
                logoState = true; // Muda o estado das logos para colorido

                // Remove a cor de fundo e a cor do texto de todas as linhas e restaura as logos
                var allRows = document.querySelectorAll("table tr");
                allRows.forEach(function (row) {
                    row.style.backgroundColor = ""; // Remove cor de fundo
                    row.style.color = ""; // Remove cor do texto
                    // Restaura a logo cinza
                    var img = row.querySelector(".row-img");
                    if (img) img.src = "imagens/LGS.png";
                });

                // Pinta a linha inicial de verde com texto preto e muda a logo para colorido
                row.style.backgroundColor = "#dde8cb";
                row.style.color = "black";
                this.src = "imagens/LGC.png"; // Altera a logo para colorido
                console.log("Hora de início capturada:", startTime);

                // Adiciona a linha selecionada ao array
                selectedRows.push(row);
                coordinates.push({ latitude: parseFloat(latitude), longitude: parseFloat(longitude), startTime: start, endTime: end, address: address });

            } else if (logoState) {
                // Segunda seleção (fim)
                var endTime = end;

                // Verifica se a hora de fim é maior que a hora de início
                if (timeToMinutes(endTime) <= timeToMinutes(startTime)) {
                    alert("A hora de fim deve ser maior que a hora de início.");
                    return;
                }

                // Pinta todas as linhas entre a linha de início e a linha de fim e altera a logo
                var allRows = document.querySelectorAll("table tr");
                var startIndex = Math.min(Array.from(allRows).indexOf(startRow), Array.from(allRows).indexOf(row));
                var endIndex = Math.max(Array.from(allRows).indexOf(startRow), Array.from(allRows).indexOf(row));

                for (var i = startIndex; i <= endIndex; i++) {
                    var currentRow = allRows[i];
                    if (currentRow !== startRow) { // Não pinta a linha inicial
                        currentRow.style.backgroundColor = "#fcc7c7"; // Pinta as linhas de vermelho
                        currentRow.style.color = "black";
                        // Altera a logo para colorido
                        var img = currentRow.querySelector(".row-img");
                        if (img) img.src = "imagens/LGC.png";

                        // Adiciona a linha selecionada ao array
                        selectedRows.push(currentRow);
                        var currentCells = currentRow.getElementsByTagName("td");
                        var currentLatitude = parseFloat(currentCells[4].innerText.trim());
                        var currentLongitude = parseFloat(currentCells[5].innerText.trim());
                        var currentStartTime = currentCells[1].innerText.trim();
                        var currentEndTime = currentCells[2].innerText.trim();
                        var currentAddress = currentCells[3].innerText.trim();
                        coordinates.push({ latitude: currentLatitude, longitude: currentLongitude, startTime: currentStartTime, endTime: currentEndTime, address: currentAddress });
                    }
                }

                // Pinta a linha final de vermelho com texto preto
                row.style.backgroundColor = "#fcc7c7";
                row.style.color = "black";
                console.log("Hora de fim capturada:", endTime);

                // Preenche o formulário com as horas de início e fim
                document.getElementById("hora").value = formatTime(startTime);
                document.getElementById("horaFim").value = formatTime(endTime);

                // Atualiza a descrição com o valor do comentário e os dados adicionais
                document.getElementById("descricao").innerHTML = `Registro de parada identificada no histórico de posição do sistema de rastreamento instalado no veículo, local: ${address} | Placa: ${plate} | Latitude: ${latitude} | Longitude: ${longitude} | ${comment}`;

                // Seleciona o motorista, se necessário
                var motoristaSelect = document.getElementById("motorista");
                for (var j = 0; j < motoristaSelect.options.length; j++) {
                    if (motoristaSelect.options[j].value === plate) {
                        motoristaSelect.selectedIndex = j;
                        break;
                    }
                }

                // Preenche latitude e longitude no formulário
                document.getElementById("latitude").value = latitude;
                document.getElementById("longitude").value = longitude;

                logoState = false; // Prepara para o terceiro clique (reset)

                // Exibe o botão flutuante
                document.getElementById("mapButton").style.display = "block";

            } else {
                // Terceiro clique: restaura o estado original
                startTime = null;
                startRow = null;

                // Remove todas as cores e restaura as logos para cinza
                var allRows = document.querySelectorAll("table tr");
                allRows.forEach(function (row) {
                    row.style.backgroundColor = ""; // Remove cor de fundo
                    row.style.color = ""; // Remove cor do texto
                    // Restaura a logo cinza
                    var img = row.querySelector(".row-img");
                    if (img) img.src = "imagens/LGS.png";
                });

                logoState = true; // Prepara para um novo ciclo de seleção
                console.log("Reset realizado, pronto para nova seleção.");

                // Esconde o botão flutuante
                document.getElementById("mapButton").style.display = "none";

                // Limpa o array de linhas selecionadas e coordenadas
                selectedRows = [];
                coordinates = [];
            }
        });
    }
}

// Adiciona os listeners de clique ao carregar a página
document.addEventListener("DOMContentLoaded", addRowClickListeners);















var map; // Variável global para o mapa

// Função para abrir o popup do mapa
function openMapPopup() {
    document.getElementById("mapPopup").style.display = "block";

    // Verifica se o mapa já foi inicializado e remove-o se necessário
    if (map) {
        map.remove();
    }

    // Inicializa o mapa
    map = L.map('map').setView([0, 0], 2);

    // Adiciona o tile layer padrão ao mapa
    var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    // Adiciona o tile layer de satélite ao mapa
    var satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains:['mt0','mt1','mt2','mt3'],
        attribution: '&copy; <a href="https://www.google.com/maps">Google Maps</a>'
    }).addTo(map);

    // Adiciona controle de camadas para alternar entre a visão padrão e a visão de satélite
    var baseMaps = {
        "Rua": streetLayer,
        "Satélite": satelliteLayer
    };
    L.control.layers(baseMaps).addTo(map);

 // Adiciona os marcadores ao mapa
coordinates.forEach(function (coord) {
    if (!isNaN(coord.latitude) && !isNaN(coord.longitude)) {
        L.marker([coord.latitude, coord.longitude]).addTo(map)
            .bindPopup(`<div style="font-size: 16px;"><strong>Hora de Início:</strong> ${coord.startTime}<br><strong>Hora de Fim:</strong> ${coord.endTime}<br><strong>Endereço:</strong> ${coord.address}</div>`)
            .openPopup();
    }
});

    // Ajusta a visualização do mapa para mostrar todos os marcadores
    var group = new L.featureGroup(coordinates.map(function (coord) {
        return L.marker([coord.latitude, coord.longitude]);
    }));
    map.fitBounds(group.getBounds());
}

// Função para resetar a seleção das linhas
function resetSelection() {
    var allRows = document.querySelectorAll("table tr");
    allRows.forEach(function (row) {
        row.style.backgroundColor = ""; // Remove cor de fundo
        row.style.color = ""; // Remove cor do texto
        // Restaura a logo cinza
        var img = row.querySelector(".row-img");
        if (img) img.src = "imagens/LGS.png";
    });

    // Limpa o array de linhas selecionadas e coordenadas
    selectedRows = [];
    coordinates = [];
    startTime = null;
    startRow = null;
    logoState = false;
}

// Adiciona o listener de clique ao botão flutuante
document.getElementById("mapButton").addEventListener("click", openMapPopup);

// Função para fechar o popup do mapa
function closeMapPopup() {
    document.getElementById("mapPopup").style.display = "none";
    resetSelection(); // Reseta a seleção das linhas quando o popup é fechado
}

// Adiciona o listener de clique ao botão de fechar o popup
document.getElementById("closeMapButton").addEventListener("click", closeMapPopup);











// Função para formatar quilômetros
function formatKilometers(kilometers) {
    return kilometers.toLocaleString("pt-BR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// Função para adicionar uma linha à tabela
function appendRow(tbody, row) {
    const tr = document.createElement("tr");

    // Convertendo o tempo do módulo para uma data e hora legível
    const moduleDateTime = new Date(row.moduleTime);
    const moduleDate = moduleDateTime.toLocaleDateString();
    const moduleTime = moduleDateTime.toLocaleTimeString();

    // Calculando a diferença do hodômetro
    let currentHodometro = row.hodometro;
    let hodometroDifference = currentHodometro - previousHodometro;

    // Formatando os quilômetros
    let formattedCurrentHodometro = formatKilometers(currentHodometro);
    let formattedHodometroDifference = formatKilometers(hodometroDifference);

    // Inserindo os dados na tabela
    tr.innerHTML = 
        `<td><img src="imagens/LGS.png" alt="Ícone"  class="row-img" /></td>
        <td>${moduleDate}</td>
        <td>${moduleTime}</td>
        <td>${row.endereco}</td>
        <td>${row.latitude}</td>
        <td>${row.longitude}</td>
        <td>${row.ignition}</td>
        <td></td>
        <td><a href="https://www.google.com/maps/place/${row.latitude},${row.longitude}" target="_blank"><ion-icon name="map-outline"></ion-icon></a></td>
        <td>${formattedCurrentHodometro}</td>
        <td>${formattedHodometroDifference}</td>`;

    tbody.appendChild(tr);

    // Atualizando o hodômetro anterior para o próximo loop
    previousHodometro = currentHodometro;
}

// Função para formatar a distância
function formatDistance(distance) {
    // Converte a distância em metros
    const meters = parseFloat(distance);

    // Calcula a quantidade de quilômetros e metros
    const km = Math.floor(meters / 1000);
    const m = Math.floor(meters % 1000);

    // Retorna a string formatada
    return `${km} km ${m} m Percorrido`;
}

let previousHodometro = null; // Inicialmente, o valor anterior é nulo

// Função para adicionar uma linha de parada à tabela
function appendStopRow(tbody, row, stopStart, stopEnd, ignition, totalTime) {
    const totalTimeSeconds = (stopEnd - stopStart) / 1000; // Diferença em segundos
    const totalTimeMinutes = totalTimeSeconds / 60; // Diferença em minutos

    if (totalTimeMinutes < 2) {
        return; // Não adiciona a linha se o tempo de parada for menor que 2 minutos
    }

    const tr = document.createElement("tr");

    // Verifica as condições e adiciona as classes apropriadas
    if (parseInt(row.speed) <= 5) {
        if (row.ignition === "true") {
            tr.classList.add("high-speed");
        } else {
            tr.classList.add("low-speed");
        }
    } else {
        tr.classList.add("speed-5");
    }

    // Calcula a diferença de hodômetro
    let hodometroDifference = previousHodometro !== null ? formatDistance((row.hodometro - previousHodometro).toFixed(2)) : "<--"; // Se previousHodometro for null, a diferença não é aplicável

    tr.innerHTML = 
        `<td><img src="imagens/LGS.png" alt="Ícone"  class="row-img" /></td>
        <td>${stopStart.toLocaleTimeString()}</td>
        <td>${stopEnd.toLocaleTimeString()}</td>
        <td>${row.endereco}</td>
        <td>${row.latitude}</td>
        <td>${row.longitude}</td>
        <td>${ `<i class="fas fa-power-off" style="color: ` + (ignition === "true" ? `green` : `red`) + `;"></i>` }</td>
        <td>${calculateTotalTime(stopStart, stopEnd)}</td>
        <td><a href="https://www.google.com/maps/place/${row.latitude},${row.longitude}" target="_blank"><ion-icon name="map-outline"></ion-icon></a></td>
        <td>${formatDistance(row.hodometro)}</td> <!-- Formatando a distância do hodômetro -->
        <td>${hodometroDifference}</td> <!-- Exibe a diferença de hodômetro -->`;

    tbody.appendChild(tr);

    // Atualizando o hodômetro anterior para o próximo loop
    previousHodometro = row.hodometro;
}

// Função para calcular o tempo total
function calculateTotalTime(start, end) {
    const totalTimeSeconds = (end - start) / 1000; // Diferença em segundos

    if (totalTimeSeconds < 3600) {
        const minutes = Math.floor(totalTimeSeconds / 60);
        const seconds = (totalTimeSeconds % 60).toFixed(1).replace(/\.0$/, ""); // Remove .0 se não há decimais
        return `${minutes}min ${seconds}s`;
    } else {
        const hours = Math.floor(totalTimeSeconds / 3600);
        const minutes = Math.floor((totalTimeSeconds % 3600) / 60);
        const seconds = (totalTimeSeconds % 60).toFixed(1).replace(/\.0$/, ""); // Remove .0 se não há decimais
        return `${hours}h ${minutes}min ${seconds}s`;
    }
}

// Adiciona os listeners de clique ao carregar a página
document.addEventListener("DOMContentLoaded", addRowClickListeners);

// Adiciona o listener de clique ao botão flutuante
document.getElementById("mapButton").addEventListener("click", openMapPopup);

// Adiciona o listener de clique ao botão de fechar o popup
document.getElementById("closeMapButton").addEventListener("click", closeMapPopup);
});