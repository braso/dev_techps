document.addEventListener("DOMContentLoaded", () => {
    const filterForm = document.getElementById("filterForm");
    const resultsDiv = document.getElementById("results");
    const messageDiv = document.getElementById("messageDiv");
  
    filterForm.addEventListener("submit", (event) => {
      event.preventDefault();
  
      const plate = document.getElementById("plate").value;
      const dateStart = document.getElementById("date_start").value;
      const dateEnd = document.getElementById("date_end").value;
      const motoristaNome = document.getElementById("id").options[document.getElementById("id").selectedIndex].text;
      const speed = 99;
  
      if (!dateStart || !dateEnd) {
        messageDiv.innerHTML = "Por favor, selecione as datas de início e fim.";
        return;
      }
  
      const formattedDateStart = formatDate(dateStart);
      const formattedDateEnd = formatDate(dateEnd);
  
      axios
        .post("https://logistica.logsyncwebservice.techps.com.br/data1", {
          plate,
          date_start: formattedDateStart.includes(" ") ? formattedDateStart : formattedDateStart + " 00:00:00",
          date_end: formattedDateEnd.includes(" ") ? formattedDateEnd : formattedDateEnd + " 23:59:59",
          speed,
        })
        .then((response) => {
          const resultData = response.data;
          if (resultData.length === 0) {
            resultsDiv.textContent = "Nenhum resultado encontrado para o período selecionado.";
          } else {
            displayResults(resultData, plate, dateStart, dateEnd, speed, motoristaNome);
            messageDiv.innerHTML = "";
          }
        })
        .catch((error) => {
          console.error("Erro ao buscar dados:", error);
          messageDiv.innerHTML = "Erro ao buscar os dados. Verifique o console.";
        });
    });
  
    const formatDate = (dateTimeString) =>
      dateTimeString.includes("T")
        ? dateTimeString.replace("T", " ")
        : dateTimeString.split("-").join("-");
  
    const formatDateBR = (dateString) => {
      const [year, month, day] = dateString.split("-");
      return `${day.padStart(2, "0")}/${month.padStart(2, "0")}/${year}`;
    };
  
    const getCurrentDateTime = () => {
      const now = new Date();
      return `${now.toLocaleDateString()}-${now.toLocaleTimeString()}`;
    };
  
    const formatTime = (seconds) => {
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const sec = Math.floor(seconds % 60);
      return `${hours}h ${minutes}min ${sec}s`;
    };
  
    const displayResults = (data, plate, dateStart, dateEnd, speed, motoristaNome) => {
      resultsDiv.innerHTML = "";
  
      const extrairCoordenadasParaArray = (data) => {
        const coordenadas = [];
        data.forEach((item) => {
          const { latitude, longitude, moduleTime, ignition, speed } = item;
  
          if (!isNaN(parseFloat(latitude)) && !isNaN(parseFloat(longitude))) {
            const date = new Date(moduleTime).toLocaleDateString();
  
            coordenadas.push({
              latitude: parseFloat(latitude),
              longitude: parseFloat(longitude),
              moduleTime,
              date,
              ignition,
              speed,
            });
          } else {
            console.warn("Coordenadas inválidas encontradas:", item);
          }
        });
        return coordenadas;
      };
  
      const coordenadasArray = extrairCoordenadasParaArray(data);
  
      let totalTrueTime = 0;
      let totalFalseTime = 0;
      let totalStopsIgnitionOn = 0;
      let totalStopsIgnitionOff = 0;
      const tableDates = [];
  
      const dataGroupedByDate = data.reduce((acc, row) => {
        const date = new Date(row.moduleTime).toLocaleDateString();
        acc[date] = acc[date] || [];
        acc[date].push(row);
        return acc;
      }, {});
  
      const marcarEnderecosRepetidos = (() => {
        const enderecosMap = {};
        return (endereco) => {
          if (enderecosMap[endereco]) {
            return `📍 ${endereco}`;
          } else {
            enderecosMap[endereco] = true;
            return endereco;
          }
        };
      })();
  
      Object.keys(dataGroupedByDate).forEach((date) => {
        const table = document.createElement("table");
        table.classList.add("table", "table-bordered");
        table.id = `resultsTable-${date.replace(/\//g, "-")}`;
        table.innerHTML = `<thead class="thead-dark">
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
  
        dataGroupedByDate[date].forEach((row, index) => {
          if (parseInt(row.speed) <= 5) {
            if (!isStopped || currentIgnition !== row.ignition) {
              if (isStopped) {
                const totalTime = (new Date(row.moduleTime) - stopStart) / 1000;
  
                if (totalTime >= 5 * 60) {
                  appendStopRow(
                    tbody,
                    dataGroupedByDate[date][index - 1],
                    stopStart,
                    new Date(row.moduleTime),
                    currentIgnition,
                    totalTime
                  );
  
                  if (currentIgnition === "true") {
                    totalStopsIgnitionOn++;
                    totalTrueTime += totalTime;
                  } else {
                    totalStopsIgnitionOff++;
                    totalFalseTime += totalTime;
                  }
                }
              }
  
              stopStart = new Date(row.moduleTime);
              isStopped = true;
              currentIgnition = row.ignition;
            }
  
            stopEnd = new Date(row.moduleTime);
          } else {
            if (isStopped) {
              const totalTime = (stopEnd - stopStart) / 1000;
  
              if (totalTime >= 5 * 60) {
                appendStopRow(
                  tbody,
                  dataGroupedByDate[date][index - 1],
                  stopStart,
                  stopEnd,
                  currentIgnition,
                  totalTime
                );
  
                if (currentIgnition === "true") {
                  totalStopsIgnitionOn++;
                  totalTrueTime += totalTime;
                } else {
                  totalStopsIgnitionOff++;
                  totalFalseTime += totalTime;
                }
              }
  
              isStopped = false;
            }
          }
        });
  
        tableDates.push(date);
  
        if (isStopped) {
          const totalTime = (stopEnd - stopStart) / 1000;
  
          if (totalTime >= 5 * 60) {
            appendStopRow(
              tbody,
              dataGroupedByDate[date][dataGroupedByDate[date].length - 1],
              stopStart,
              stopEnd,
              currentIgnition,
              totalTime
            );
  
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
        summaryDiv.innerHTML = `<h2 class="title-section">Resumo da pesquisa</h2>
                  <div class="summary">
                      <div class="summary-column">
                          <h6><i class="fas fa-user"></i> <b>Nome Funcionário:  </b> ${motoristaNome}</h6>
                          <h6><i class="fas fa-search"></i> <b>Data de Consulta:  </b> ${getCurrentDateTime()}</h6> 
                          
                          <h6><i class="fas fa-id-card"></i> <b>Placa:  </b> ${plate}</h6>
                          <h6><i class="fas fa-calendar"></i> <b>Período de Consulta:  </b> 24H </h6>
                      </div>
                      <div class="summary-column">
                          <h6><i class="fas fa-power-off" style="color: green;"></i> <b>${formatTime(totalTrueTime)} </b>   com ignição ligada. Totalizando    <b>${totalStopsIgnitionOn} </b>    paradas.</h6>
                          <h6><i class="fas fa-power-off" style="color: red;"></i> <b>${formatTime(totalFalseTime)} </b>  com ignição desligada. Totalizando    <b>${totalStopsIgnitionOff} </b>    paradas.</h6>
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
  
        const dateHeader = document.createElement("h3");
        dateHeader.textContent = `Resultados para ${date}`;
        resultsDiv.appendChild(dateHeader);
  
        table.style.display = "none";
        resultsDiv.appendChild(table);
  
        const secondTable = document.createElement("table");
        secondTable.classList.add("table", "table-bordered");
        secondTable.innerHTML = `<thead class="thead-dark">
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
      });
  
      addRowClickListeners();
    };
  
    let startTime = null;
    let startRow = null;
    let logoState = false;
    let selectedRows = [];
    let coordinates = [];


    
  
    function addRowClickListeners() {
      const images = document.getElementsByClassName("row-img");
  
      for (let i = 0; i < images.length; i++) {
        images[i].addEventListener("click", function () {
          const row = this.parentNode.parentNode;
          const cells = row.getElementsByTagName("td");
  
          const date = cells[0].innerText.trim();
          const start = cells[1].innerText.trim();
          const end = cells[2].innerText.trim();
          const address = cells[3].innerText.trim();
          const latitude = cells[4].innerText.trim();
          const longitude = cells[5].innerText.trim();
          const ignition = cells[6].innerText.trim();
  
          const plate = document.getElementById("plate").value.trim();
          const comment = document.getElementById("coment").value.trim();
  
          const formatTime = (timeString) => {
            if (!timeString || timeString === "undefined" || timeString.split(":").length < 2) {
              return "";
            }
            const [hours, minutes] = timeString.split(":");
            if (hours >= 0 && hours < 24 && minutes >= 0 && minutes < 60) {
              return `${hours.padStart(2, "0")}:${minutes.padStart(2, "0")}`;
            }
            return "";
          };
  
          const timeToMinutes = (timeString) => {
            const [hours, minutes] = timeString.split(":");
            return parseInt(hours) * 60 + parseInt(minutes);
          };
  
          if (startTime === null) {
            startTime = start;
            startRow = row;
            logoState = true;
  
            const allRows = document.querySelectorAll("table tr");
            allRows.forEach((row) => {
              row.style.backgroundColor = "";
              row.style.color = "";
              const img = row.querySelector(".row-img");
              if (img) img.src = "imagens/LGS.png";
            });
  
            row.style.backgroundColor = "#dde8cb";
            row.style.color = "black";
            this.src = "imagens/LGC.png";
  
            selectedRows.push(row);
            coordinates.push({
              ignition,
              latitude: parseFloat(latitude),
              longitude: parseFloat(longitude),
              startTime: start,
              endTime: end,
              address,
            });
          } else if (logoState) {
            const endTime = end;
  
            if (timeToMinutes(endTime) <= timeToMinutes(startTime)) {
              alert("A hora de fim deve ser maior que a hora de início.");
              return;
            }
  
            const allRows = document.querySelectorAll("table tr");
            const startIndex = Math.min(Array.from(allRows).indexOf(startRow), Array.from(allRows).indexOf(row));
            const endIndex = Math.max(Array.from(allRows).indexOf(startRow), Array.from(allRows).indexOf(row));
  
            selectedRows = [];
            coordinates = [];
  
            for (let i = startIndex; i <= endIndex; i++) {
              const currentRow = allRows[i];
              currentRow.style.backgroundColor = "#fcc7c7";
              currentRow.style.color = "black";
              const img = currentRow.querySelector(".row-img");
              if (img) img.src = "imagens/LGC.png";
  
              selectedRows.push(currentRow);
              const currentCells = currentRow.getElementsByTagName("td");
              const currentLatitude = parseFloat(currentCells[4].innerText.trim());
              const currentLongitude = parseFloat(currentCells[5].innerText.trim());
              const currentStartTime = currentCells[1].innerText.trim();
              const currentEndTime = currentCells[2].innerText.trim();
              const currentAddress = currentCells[3].innerText.trim();
              const currentIgnition = currentCells[6].innerText.trim();
  
              coordinates.push({
                ignition: currentIgnition,
                latitude: currentLatitude,
                longitude: currentLongitude,
                startTime: currentStartTime,
                endTime: currentEndTime,
                address: currentAddress,
              });
            }
  
            const firstStartTime = selectedRows[0].getElementsByTagName("td")[1].innerText.trim();
            const lastEndTime = selectedRows[selectedRows.length - 1].getElementsByTagName("td")[2].innerText.trim();
  
            const totalParada = timeToMinutes(lastEndTime) - timeToMinutes(firstStartTime);
  
            row.style.backgroundColor = "#fcc7c7";
            row.style.color = "black";
  
            document.getElementById("hora").value = formatTime(startTime);
            document.getElementById("horaFim").value = formatTime(endTime);
  
            document.getElementById(
              "descricao"
            ).innerHTML = `Registro de parada identificada no histórico de posição do sistema de rastreamento instalado no veículo, local: ${address} | Placa: ${plate} | Latitude: ${latitude} | Longitude: ${longitude} | ${comment}`;
  
            const motoristaSelect = document.getElementById("motorista");
            for (let j = 0; j < motoristaSelect.options.length; j++) {
              if (motoristaSelect.options[j].value === plate) {
                motoristaSelect.selectedIndex = j;
                break;
              }
            }
  
            document.getElementById("latitude").value = latitude;
            document.getElementById("longitude").value = longitude;
  
            logoState = false;
  
            document.getElementById("mapButton").style.display = "block";
  
            openModal(totalParada);
          } else {
            startTime = null;
            startRow = null;
  
            const allRows = document.querySelectorAll("table tr");
            allRows.forEach((row) => {
              row.style.backgroundColor = "";
              row.style.color = "";
              const img = row.querySelector(".row-img");
              if (img) img.src = "imagens/LGS.png";
            });
  
            logoState = true;
  
            document.getElementById("mapButton").style.display = "none";
  
            selectedRows = [];
            coordinates = [];
  
            closeModal();
          }
        });
      }
    }
  
    document.addEventListener("DOMContentLoaded", addRowClickListeners);
  
    function calcularDistancia(lat1, lon1, lat2, lon2) {
        const R = 6371; // Raio da Terra em km
        const dLat = (lat2 - lat1) * (Math.PI / 180);
        const dLon = (lon2 - lon1) * (Math.PI / 180);
        const a = 
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * (Math.PI / 180)) * Math.cos(lat2 * (Math.PI / 180)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c; // Distância em km
    }
    
    function openModal(totalParada) {
        let modal = document.getElementById("paradaModal");
        if (!modal) {
            modal = document.createElement("div");
            modal.id = "paradaModal";
            modal.style.cssText = `
                position: fixed;
                top: 90%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: white;
                padding: 20px;
                border: 1px solid #ccc;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                display: none;
                width: 1000px;
                text-align: center;
            `;
            document.body.appendChild(modal);
        }
    
        const horas = Math.floor(totalParada / 60);
        const minutos = totalParada % 60;
        const totalParadaFormatado = `${horas}h ${minutos}m`;
    
        if (coordinates.length > 1) {
            const primeiraCoordenada = coordinates[0];
            const ultimaCoordenada = coordinates[coordinates.length - 1];
            const distanciaPercorrida = calcularDistancia(
                primeiraCoordenada.latitude,
                primeiraCoordenada.longitude,
                ultimaCoordenada.latitude,
                ultimaCoordenada.longitude
            ).toFixed(2);
    
            modal.innerHTML = `<p> 🛑 Tempo Total da Parada: <b>${totalParadaFormatado}</b> <br>
                                    ⚠️ Válido para seleção de paradas no mesmo lugar ⚠️</p>
                                 ⬆️ Distância Percorrida: <b>${distanciaPercorrida} km</b> <br>
                                 ⚠️ Distância estimada de deslocamento entre primeira e ultima posição , use está informação para verificar se houve deslocamento entre posições ⚠️</p>`;
        } else {
            modal.innerHTML = `<p> 🛑 Total da Parada: <b>${totalParadaFormatado}</b> <br>
                                ⚠️ Válido para seleção de paradas no mesmo lugar ⚠️</p>`;
        }
    
        modal.style.display = "block";
    }
  
    function closeModal() {
      const modal = document.getElementById("paradaModal");
      if (modal) {
        modal.style.display = "none";
      }
    }
  



    let map;

    function openMapPopup() {
      document.getElementById("mapPopup").style.display = "block";
    
      if (map) {
        map.remove();
        map = null;
      }
    
      const initialCenter = [-23.5505, -46.6333];
      const initialZoom = 12;
    
      map = L.map("map", {
        center: initialCenter,
        zoom: initialZoom,
      });
    
      const defaultLayer = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      });
    
      const satelliteLayer = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        {
          attribution: "© Esri",
          maxZoom: 19,
        }
      );
    
      const hybridLayer = L.tileLayer("https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}", {
        maxZoom: 20,
        subdomains: ["mt0", "mt1", "mt2", "mt3"],
        attribution: "© Google",
      }).addTo(map);
    
      const terrainLayer = L.tileLayer("https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png", {
        maxZoom: 17,
        attribution: "© OpenTopoMap",
      });
    
      const baseMaps = {
        Híbrido: hybridLayer,
        OpenStreetMap: defaultLayer,
        Satélite: satelliteLayer,
        Terreno: terrainLayer,
      };
    
      L.control.layers(baseMaps).addTo(map);
    
      let googleMapsLink = "https://www.google.com/maps/dir/?api=1&travelmode=driving";
    
      if (coordinates.length > 0) {
        googleMapsLink += `&origin=${coordinates[0].latitude},${coordinates[0].longitude}`;
    
        if (coordinates.length > 2) {
          const waypoints = coordinates
            .slice(1, -1)
            .map((coord) => `${coord.latitude},${coord.longitude}`)
            .join("|");
          googleMapsLink += `&waypoints=${waypoints}`;
        }
    
        if (coordinates.length > 1) {
          const lastCoord = coordinates[coordinates.length - 1];
          googleMapsLink += `&destination=${lastCoord.latitude},${lastCoord.longitude}`;
        } else {
          googleMapsLink = `https://www.google.com/maps/place/${coordinates[0].latitude},${coordinates[0].longitude}`;
        }
      } else {
        googleMapsLink = "#";
      }
    
      coordinates.forEach(function (coord, index) {
        if (!isNaN(coord.latitude) && !isNaN(coord.longitude)) {
          let popupContent = `<div style="font-size: 16px;">
                   <div style="font-size: 16px;">
                    <strong>Parada #${index + 1}</strong><br>
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-clock" style="color: #28a745; margin-right: 5px;"></i>
                        <strong>Hora de Início:</strong> ${coord.startTime}
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-clock" style="color: #dc3545; margin-right: 5px;"></i>
                        <strong>Hora de Fim:</strong>  ${coord.endTime}
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-map-marker-alt" style="color: #6c757d; margin-right: 5px;"></i>
                        <strong>Endereço:</strong> ${coord.address}
                    </div>
    
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-power-off" style="color: ${
                          coord.ignition === "Ligada" ? "green" : "red"
                        }; margin-right: 5px;"></i>
                        <strong>Ignição:</strong> ${coord.ignition}
                    </div>
                    <p><a href="${googleMapsLink}" target="_blank">Ver Rota no Google Maps</a></p>
                  </div>`;
    
          let icon;
          if (index === 0) {
            // Primeiro marcador: verde
            icon = L.icon({
              iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
              shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
              iconSize: [25, 41],
              iconAnchor: [12, 41],
              popupAnchor: [1, -34],
              shadowSize: [41, 41]
            });
          } else if (index === coordinates.length - 1) {
            // Último marcador: emoji de bandeira de chegada
            icon = L.divIcon({
              className: 'custom-emoji-icon',
              html: '<div style="font-size: 24px;">🏁</div>', // Use um emoji como marcador
              iconSize: [25, 41],
              iconAnchor: [12, 41],
              popupAnchor: [1, -34]
            });
          } else {
            // Marcadores intermediários: amarelo
            icon = L.icon({
              iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',
              shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
              iconSize: [25, 41],
              iconAnchor: [12, 41],
              popupAnchor: [1, -34],
              shadowSize: [41, 41]
            });
          }
    
          const marker = L.marker([coord.latitude, coord.longitude], { icon: icon })
            .addTo(map)
            .bindPopup(popupContent);
    
          // Não abre o popup automaticamente
        }
      });
    
      var group = new L.featureGroup(
        coordinates.map(function (coord) {
          return L.marker([coord.latitude, coord.longitude]);
        })
      );
      map.fitBounds(group.getBounds());
    }
    
    function resetSelection() {
      var allRows = document.querySelectorAll("table tr");
      allRows.forEach(function (row) {
        row.style.backgroundColor = "";
        row.style.color = "";
        var img = row.querySelector(".row-img");
        if (img) img.src = "imagens/LGS.png";
      });
    
      selectedRows = [];
      coordinates = [];
      startTime = null;
      startRow = null;
      logoState = false;
    }
    
    document.getElementById("mapButton").addEventListener("click", openMapPopup);
    
    function closeMapPopup() {
      document.getElementById("mapPopup").style.display = "none";
    }
    
    document.getElementById("closeMapButton").addEventListener("click", closeMapPopup);
    
    function formatKilometers(kilometers) {
      return kilometers.toLocaleString("pt-BR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    }
    
    function appendRow(tbody, row) {
      const tr = document.createElement("tr");
    
      const moduleDateTime = new Date(row.moduleTime);
      const moduleDate = moduleDateTime.toLocaleDateString();
      const moduleTime = moduleDateTime.toLocaleTimeString();
    
      let currentHodometro = row.hodometro;
      let hodometroDifference = currentHodometro - previousHodometro;
    
      let formattedCurrentHodometro = formatKilometers(currentHodometro);
      let formattedHodometroDifference = formatKilometers(hodometroDifference);
    
      tr.innerHTML = `<td><img src="imagens/LGS.png" alt="Ícone"  class="row-img" /></td>
              <td>${moduleDate}</td>
              <td>${moduleTime}</td>
              <td>${row.endereco}</td>
              <td>${row.latitude}</td>
              <td>${row.longitude}</td>
              <td>${row.ignition}</td>
              <td></td>
              <td><a href="https://www.google.com/maps/place/${row.latitude},${row.longitude}" target="_blank"><ion-icon name="map-outline"></ion-icon></a></td>
                 <td>${formattedCurrentHodometro}</td>
                <td>${formattedHodometroDifference}</td>
              `;
    
      tbody.appendChild(tr);
    
      previousHodometro = currentHodometro;
    }
    
    function formatDistance(distance) {
      const meters = parseFloat(distance);
    
      const km = Math.floor(meters / 1000);
      const m = Math.floor(meters % 1000);
    
      return `${km} km ${m} m Percorrido`;
    }
    
    let previousHodometro = null;
    
    function appendStopRow(tbody, row, stopStart, stopEnd, ignition, totalTime) {
      const totalTimeSeconds = (stopEnd - stopStart) / 1000;
      const totalTimeMinutes = totalTimeSeconds / 60;
    
      if (totalTimeMinutes < 2) {
        return;
      }
    
      const tr = document.createElement("tr");
    
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
    
    function calculateTotalTime(start, end) {
      const totalTimeSeconds = (end - start) / 1000;
    
      if (totalTimeSeconds < 3600) {
        const minutes = Math.floor(totalTimeSeconds / 60);
        const seconds = (totalTimeSeconds % 60).toFixed(1).replace(/\.0$/, "");
        return `${minutes}min ${seconds}s`;
      } else {
        const hours = Math.floor(totalTimeSeconds / 3600);
        const minutes = Math.floor((totalTimeSeconds % 3600) / 60);
        const seconds = (totalTimeSeconds % 60).toFixed(1).replace(/\.0$/, "");
        return `${hours}h ${minutes}min ${seconds}s`;
      }
    }
    
    document.addEventListener("DOMContentLoaded", addRowClickListeners);
    
    document.getElementById("mapButton").addEventListener("click", openMapPopup);
    
    document.getElementById("closeMapButton").addEventListener("click", closeMapPopup);
    });