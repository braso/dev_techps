

// Oculta o botão de alternar formulário inicialmente
document.getElementById("toggleFormBtn").style.display = "none";

// Adiciona um evento ao botão "consultar"
document.getElementById("consultarBtn").addEventListener("click", function () {
    // Mostra a tela de carregamento
    document.getElementById("loading-screen").style.display = "block";

    // Simula um atraso de 5 segundos
    setTimeout(function () {
        // Esconde a tela de carregamento
        document.getElementById("loading-screen").style.display = "none";
        // Mostra o botão de alternar formulário após o carregamento
        document.getElementById("toggleFormBtn").style.display = "block";
    }, 1); // 5000 milissegundos = 5 segundos
});

// Adiciona um evento ao botão de alternar o formulário
document.getElementById("toggleFormBtn").addEventListener("click", function () {
    var formContainer = document.getElementById("formContainer");
    var motoristaSelect = document.getElementById("id"); // Campo de seleção do motorista
    var dataInput = document.getElementById("date_start"); // Campo de seleção de data

    // Alterna a visibilidade do formulário
    if (formContainer.classList.contains("show")) {
        formContainer.classList.remove("show");
    } else {
        // Preenche o campo do motorista no formulário de ajuste com o valor selecionado
        var motoristaValue = motoristaSelect.value; // Obtém o valor selecionado
        document.getElementById("motorista").value = motoristaValue; // Define o valor no formulário de ajuste

        // Preenche o campo de data no formulário de ajuste com a data selecionada
        var dataValue = dataInput.value; // Obtém o valor selecionado
        var formattedDate = dataValue.split('T')[0]; // Formata a data para "yyyy-MM-dd"
        document.getElementById("data").value = formattedDate; // Define o valor no formulário de ajuste

        formContainer.classList.add("show");
    }
});






// Mapeamento dos tipos de ajuste
var tipoAjusteMap = {
    "3": "Início e Fim de Refeição",
    "5": "Início e Fim de Espera",
    "7": "Início e Fim de Descanso",
    "9": "Início e Fim de Repouso",
};
// Função para atualizar o resumo acima da tabela
function atualizarResumo() {
    var table = document.getElementById("adjustmentTable").getElementsByTagName("tbody")[0];
    var rows = table.getElementsByTagName("tr");

    var resumoContainer = document.getElementById("resumoContainer");
    resumoContainer.innerHTML = ""; // Limpa o resumo anterior

    // Cria um resumo baseado nas linhas da tabela
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName("td");
        var horaInicio = cells[2].textContent.trim();
        var horaFim = ((i + 1 < rows.length)? rows[i + 1].getElementsByTagName("td")[2].textContent.trim(): "");
        var tipoAjusteCodigo = cells[5].textContent.trim();

        // Converte o código do tipo de ajuste para o nome usando o mapeamento
        var tipoAjusteNome = tipoAjusteMap[tipoAjusteCodigo] || "Tipo Desconhecido";

        // Cria o resumo como uma string e adiciona ao container
        var resumo = document.createElement("p");

        // Adiciona o ícone de check verde
        var checkIcon = document.createElement("i");
        checkIcon.className = "fas fa-check-circle"; // Classe Font Awesome para o ícone de check
        checkIcon.style.color = "green"; // Define a cor do ícone

        // Adiciona o ícone e o texto ao resumo
        resumo.appendChild(checkIcon);
        resumo.appendChild(document.createTextNode(` Hora Início: ${horaInicio}, Hora Fim: ${horaFim}, Tipo de Ajuste: ${tipoAjusteNome}  - `));

        // Adiciona o botão de excluir ao lado do resumo
        var deleteButton = document.createElement("button");
        deleteButton.className = "btn btn-danger btn-sm ml-2";

        // Adiciona o ícone da lixeira (Font Awesome)
        var trashIcon = document.createElement("i");
        trashIcon.className = "fas fa-trash-alt"; // Classe Font Awesome para o ícone de lixeira
        deleteButton.appendChild(trashIcon);

        // Adiciona o evento de clique para excluir a linha
        deleteButton.addEventListener("click", function () {
            excluirLinha(this);
        });

        // Adiciona o botão ao resumo
        resumo.appendChild(deleteButton);

        // Adiciona o resumo ao container
        resumoContainer.appendChild(resumo);

        // Pula a próxima linha se for uma linha de fim automático
        if (horaFim !== "") {
            i++; // Salta a próxima linha, já que foi usada como hora de fim
        }
    }
}


// Função para excluir uma linha da tabela
function excluirLinha(button) {
    // Remove o parágrafo (resumo) que contém o botão de excluir
    var resumo = button.parentElement;
    resumo.remove();

    // Aqui você pode também remover a linha correspondente na tabela, se necessário.
    // Exemplo: encontrar a linha correspondente na tabela e removê-la.
}








document.getElementById("addAdjustmentBtn").addEventListener("click", function () {
    // Obtém os valores dos campos do formulário
    var motorista 	= document.getElementById("motorista").value;
    var data 		= document.getElementById("data").value;
    var hora 		= document.getElementById("hora").value;
    var horaFim 	= document.getElementById("horaFim").value;
    var idMacro 	= document.getElementById("idMacro").value;
    var motivo 		= document.getElementById("motivo").value;
    var descricao 	= document.getElementById("descricao").value;
    var plate 		= document.getElementById("plate").value;
    var comentario 	= document.getElementById("coment").value;
    var latitude 	= document.getElementById("latitude").value;
    var longitude 	= document.getElementById("longitude").value;

    // Verifica se todos os campos obrigatórios estão preenchidos
    if (motorista && data && hora && idMacro && motivo && descricao && plate && latitude && longitude){
        var table = document.getElementById("adjustmentTable").getElementsByTagName("tbody")[0];

        // Cria a descrição concatenada com o comentário
        var descricaoCompleta = descricao + (comentario ? ' - ' + comentario : '');

        // Se o campo de hora de fim for preenchido, adiciona a linha de fim automaticamente
        if (horaFim) {
            var newRow = table.insertRow();
            newRow.insertCell(0).textContent = motorista;
            newRow.insertCell(1).textContent = data;
            newRow.insertCell(2).textContent = hora;
            newRow.insertCell(3).textContent = latitude;  // Adiciona Latitude
            newRow.insertCell(4).textContent = longitude; // Adiciona Longitude
            newRow.insertCell(5).textContent = idMacro;
            newRow.insertCell(6).textContent = motivo;
            newRow.insertCell(7).textContent = descricaoCompleta;
            newRow.insertCell(8).textContent = plate;

            // Cria o botão de exclusão
            var deleteCell = newRow.insertCell(9);
            var deleteBtn = document.createElement("button");
            deleteBtn.textContent = "Excluir";
            deleteBtn.className = "btn btn-danger";
            deleteBtn.addEventListener("click", function () {
                table.deleteRow(newRow.rowIndex - 1);
            });
            deleteCell.appendChild(deleteBtn);

            // Adiciona uma linha de fim automaticamente
            var newRowFim = table.insertRow();
            newRowFim.insertCell(0).textContent = motorista;
            newRowFim.insertCell(1).textContent = data;
            newRowFim.insertCell(2).textContent = horaFim;
            newRowFim.insertCell(3).textContent = latitude; // Adiciona Latitude
            newRowFim.insertCell(4).textContent = longitude; // Adiciona Longitude
            newRowFim.insertCell(5).textContent = (idMacro == "3" ? "4" : (idMacro == "5" ? "6" : (idMacro == "7" ? "8" : (idMacro == "9" ? "10" : ""))));
            newRowFim.insertCell(6).textContent = motivo;
            newRowFim.insertCell(7).textContent = descricaoCompleta;
            newRowFim.insertCell(8).textContent = plate;

            // Cria o botão de exclusão para a linha de fim
            var deleteCellFim = newRowFim.insertCell(9);
            var deleteBtnFim = document.createElement("button");
            deleteBtnFim.textContent = "Excluir";
            deleteBtnFim.className = "btn btn-danger";
            deleteBtnFim.addEventListener("click", function () {
                table.deleteRow(newRowFim.rowIndex - 1);
            });
            deleteCellFim.appendChild(deleteBtnFim);

        } else {
            // Adiciona uma linha normal se não precisar de final automático
            var newRow = table.insertRow();
            newRow.insertCell(0).textContent = motorista;
            newRow.insertCell(1).textContent = data;
            newRow.insertCell(2).textContent = hora;
            newRow.insertCell(3).textContent = latitude;  // Adiciona Latitude
            newRow.insertCell(4).textContent = longitude; // Adiciona Longitude
            newRow.insertCell(5).textContent = idMacro;
            newRow.insertCell(6).textContent = motivo;
            newRow.insertCell(7).textContent = descricaoCompleta;
            newRow.insertCell(8).textContent = plate;

            // Cria o botão de exclusão
            var deleteCell = newRow.insertCell(9);
            var deleteBtn = document.createElement("button");
            deleteBtn.textContent = "Excluir";
            deleteBtn.className = "btn btn-danger";
            deleteBtn.addEventListener("click", function () {
                table.deleteRow(newRow.rowIndex - 1);
            });
            deleteCell.appendChild(deleteBtn);
        }

        // Limpa os campos do formulário, exceto motorista e data
        document.getElementById("hora").value = "";
        document.getElementById("horaFim").value = "";
        document.getElementById("idMacro").value = "";
        document.getElementById("motivo").value = "";
        document.getElementById("coment").value = ""; // Limpa o campo de comentário
        document.getElementById("latitude").value = ""; // Limpa o campo de latitude
        document.getElementById("longitude").value = ""; // Limpa o campo de longitude

        atualizarResumo(); // Atualiza o resumo após adição
    } else {
        alert("Por favor, preencha todos os campos.");
    }
});





document.getElementById("submitAdjustmentsBtn").addEventListener("click", function () {
    var table = document.getElementById("adjustmentTable").getElementsByTagName("tbody")[0];
    var rows = table.getElementsByTagName("tr");
    var adjustments = [];

    // Itera sobre cada linha da tabela para coletar os dados
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName("td");
        adjustments.push({
            motorista: 	cells[0].textContent,
            data: 		cells[1].textContent,
            hora: 		cells[2].textContent,
            latitude: 	cells[3].textContent,	// Adiciona Latitude
            longitude: 	cells[4].textContent,	// Adiciona Longitude
            idMacro: 	cells[5].textContent,
            motivo: 	cells[6].textContent,
            descricao: 	cells[7].textContent,
            plate: 		cells[8].textContent
        });
    }

    // Se houver ajustes para enviar
    if (adjustments.length > 0) {
        var form = document.createElement("form");
        form.method = "POST";
        form.action = ""; // Submissão para o mesmo arquivo

        // Cria um campo oculto para armazenar os ajustes
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "ajustes";
        input.value = JSON.stringify(adjustments);
        form.appendChild(input);

        // Adiciona o formulário ao corpo do documento
        document.body.appendChild(form);
        form.submit();
    } else {
        alert("Nenhum ajuste para enviar.");
    }
});

// Oculta as mensagens de popup após 5 segundos
setTimeout(function () {
    console.log('5 segundos se passaram, ocultando mensagens e redirecionando..');

    var messages = document.querySelectorAll(".alert");
    messages.forEach(function (message) {
        message.style.display = "none";
    });
}, 5000); // 5000 milissegundos = 5 segundos