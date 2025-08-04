<?php
// Incluindo arquivos de configuração
include_once "load_env.php";
include_once "conecta.php";
mysqli_query($conn, "SET time_zone = '-3:00'");
ob_start();
echo'<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
echo'<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">';
// Função de cabeçalho HTML (retorna a string, não imprime)
cabecalho('Cadastro de Placas');



function carregaPontoUser(){

  global $conn;
  $sql = "SELECT * FROM ponto WHERE ponto_nb_user = '".$_SESSION["user"]["id"]."'";
  $result = mysqli_query($conn, $sql);
  $dados = mysqli_fetch_assoc($result);
  return $dados;


}

echo $dados = carregaPontoUser();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Mapa de Veículos - Traccar</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
    }

    h1 {
      padding: 10px;
      margin: 0;
      background: #28a745;
      color: white;
      text-align: center;
    }

    #map {
      height: 90vh;
      width: 100%;
    }

    button {
      position: absolute;
      top: 70px;
      left: 10px;
      z-index: 1000;
      padding: 10px;
      background: #007bff;
      color: white;
      border: none;
      cursor: pointer;
      border-radius: 4px;
    }

    button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>

<h1>Mapa de Veículos - Traccar</h1>
<button onclick="buscarPosicoes()">Atualizar Veículos</button>
<div id="map"></div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
  const usuario = 'opafrutas@techps.com.br';
  const senha = 'Opafrutas@2025';
  const auth = btoa(`${usuario}:${senha}`);

  let map = L.map('map').setView([-15.77972, -47.92972], 5); // Centro inicial no Brasil
  let markers = [];

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  async function buscarPosicoes() {
    const endpoint = 'https://rastreamento.braso.net.br/api/positions';

    try {
      const resposta = await fetch(endpoint, {
        method: 'GET',
        headers: {
          'Authorization': `Basic ${auth}`,
          'Accept': 'application/json'
        }
      });

      const status = resposta.status;
      const data = await resposta.json();

      if (status === 200) {
        // Remove marcadores antigos
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        data.forEach(pos => {
          const lat = pos.latitude;
          const lon = pos.longitude;

     const popupContent = `
  <b>Usuário ID:</b> ${userId}<br>
  <b>Device ID:</b> ${pos.deviceId}<br>
  <b>Data:</b> ${new Date(pos.deviceTime).toLocaleString()}<br>
  <b>Velocidade:</b> ${(pos.speed * 1.852).toFixed(2)} km/h<br>
  <b>Ignição:</b> ${pos.attributes.ignition ? 'Ligado' : 'Desligado'}
`;

          const marker = L.marker([lat, lon]).addTo(map)
            .bindPopup(popupContent);

          markers.push(marker);
        });

        if (data.length > 0) {
          map.setView([data[0].latitude, data[0].longitude], 12);
        }

      } else {
        alert(`Erro ${status}: ${JSON.stringify(data)}`);
      }
    } catch (err) {
      alert('Erro na requisição: ' + err.message);
    }
  }

  // Carrega ao abrir a página
  buscarPosicoes();
</script>

</body>
</html>


<?php
rodape();

