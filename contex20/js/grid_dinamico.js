/*Variáveis que devem estar definidas:
    dict[string] searchFields = {'nomeInput': 'campoBd'};
    dict[string] fields = {'Título': 'campoBd'};
    string queryBase;
*/
searchFields;
fields;
queryBase;

pageNumber = 1;
total = -1;
limit = 999999;
if(typeof orderCol === 'undefined'){
    orderCol = '';
}

const camposBd = Object.values(fields);
// FUNÇÃO PARA EXPORTAR O ESTADO ATUAL DA TABELA
function exportAllToCSV() {
    const btn = $('#btnExportCSV');
    const loading = $('#csvLoading');
    
    // Desabilita o botão e mostra loading
    btn.prop('disabled', true);
    loading.show();
    
    // Faz uma nova requisição para buscar TODOS os dados
    $.ajax({
        url: urlTableInfo,
        method: 'POST',
        data: {
            'query': [
                window.tableConfig.queryBase, 
                btoa(encodeURI(window.tableConfig.conditions)), 
                btoa(1000000), // 'all' para buscar todos os registros
                btoa(0) // offset 0
            ]
        },
        dataType: 'json',
        success: function(response) {
            // Prepara os dados para CSV
            let csvContent = "";
            
            // Cabeçalhos
            const headers = Object.keys(window.tableConfig.fields);
            csvContent += headers.map(header => 
                `"${header.replace(/"/g, '""')}"`
            ).join(';') + '\n';
            
            // Dados
            response.rows.forEach(row => {
                const rowData = [];
                
                // Itera sobre os headers para garantir a ordem correta das colunas
                headers.forEach(headerLabel => {
                    const dbField = window.tableConfig.fields[headerLabel];
                    let value = row[dbField] != null ? row[dbField].toString() : '';
                    
                    // Remove todas as tags HTML
                    value = value.replace(/<[^>]*>/g, '');

                    const campo = dbField.toLowerCase();
                    const isDoc = (
                        campo.includes("cpf") ||
                        campo.includes("cnpj") ||
                        /^[0-9]{11}$/.test(value.replace(/\D/g, '')) ||      // CPF sem máscara
                        /^[0-9]{14}$/.test(value.replace(/\D/g, ''))         // CNPJ sem máscara
                    );

                    if (isDoc) {
                        // Excel: mantém como texto
                        rowData.push(`="${value}"`);
                    } else {
                        value = value.replace(/"/g, '""');
                        rowData.push(`"${value}"`);
                    }
                });

                csvContent += rowData.join(';') + '\n';
            });
            
            // Cria e faz download do arquivo
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            
            const timestamp = new Date().toLocaleDateString('pt-BR').replace(/\//g, '-');
            link.setAttribute("href", url);
            link.setAttribute("download", `dados_completos_${timestamp}.csv`);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            console.log('CSV exportado com todos os dados:', response.rows.length, 'registros');
        },
        error: function(errMsg) {
            console.error('Erro ao exportar CSV:', errMsg);
            alert('Erro ao exportar dados. Tente novamente.');
        },
        complete: function() {
            // Reabilita o botão e esconde loading
            btn.prop('disabled', false);
            loading.hide();
        }
    });
}


let definirFuncoesInternas = function(){
    //Ordenações{
        $('.table-col-head').click(function(event){
            campo = camposBd[parseInt($(event.target)[0].attributes.value.value)];
            if(campo.indexOf(' AS ') >= 0){
                campo = campo.split(' AS ')[1];
            }
            if(orderCol.indexOf(campo+' ASC') >= 0){
                orderCol = campo+' DESC';
            }else{
                orderCol = campo+' ASC';
            }
            consultarRegistros();
        });

        try{
            funcoesInternas();
        }catch(error){
            console.log(error);
        }
        
    //}
}

function esconderInativar(classeInativar, statusColIndex){
    for(f = 0; f < $('[class=\"'+classeInativar+'\"]').length; f++){
        let rowElements = $('[class=\"'+classeInativar+'\"]')[f].parentElement.parentElement.children;
        let status = rowElements[statusColIndex].innerHTML;
        if(status == 'inativo'){
            $('[class=\"'+classeInativar+'\"]')[f].parentElement.remove();
            f--;
        }
    }
}

const consultarRegistros = function(){
    conditions = '';
    data = {};

    $('.table-loading-icon')[0].innerHTML = '<div class=\"loading-icon\" style=\"margin:10px;background-color: #ffffff;\">';

    $('form[name=\"contex_form\"] :input').each(function(key, tag){
        if(searchFields[tag.name] != undefined){
            data[searchFields[tag.name]] = this.value;
        }
    });

    let limitVal = $('input[name="limit"]').first().val();
    limit = parseInt(limitVal, 10);
    if(isNaN(limit) || limit <= 0){
        limit = 999999;
    }

    keys = Object.values(searchFields);
    inputs = $('form[name=\"contex_form\"] :input');


    
    for(f = 0; f < keys.length; f++){
        if(data[keys[f]] != '' && data[keys[f]] != undefined){
            if(inputs[f].name.indexOf('_like') > 0){
                conditions += ' AND '+keys[f]+' LIKE \"%'+data[keys[f]]+'%\"';
            }else if((inputs[f].name.indexOf('_g') > 0)){
                conditions += ' AND '+keys[f]+' > \"'+data[keys[f]]+'\"';
            }else if((inputs[f].name.indexOf('_ge') > 0)){
                conditions += ' AND '+keys[f]+' >= \"'+data[keys[f]]+'\"';
            }else if((inputs[f].name.indexOf('_l') > 0)){
                conditions += ' AND '+keys[f]+' < \"'+data[keys[f]]+'\"';
            }else if((inputs[f].name.indexOf('_le') > 0)){
                conditions += ' AND '+keys[f]+' <= \"'+data[keys[f]]+'\"';
            }else if((inputs[f].name.indexOf('_cpf') > 0)){
                conditions += ' AND '+keys[f]+' = \"'+data[keys[f]].replace(/[^0-9]/g, "")+'\"';
            }else{
                conditions += ' AND '+keys[f]+' = \"'+data[keys[f]]+'\"';
            }
        }
    }

    if(orderCol != ''){
        conditions += ' ORDER BY '+orderCol;
    }
    if(pageNumber < 1){
        pageNumber = 1;
    }

    try{
        console.log('Grid request', { url: urlTableInfo, queryBase: queryBase, conditions: conditions, limit: limit, page: pageNumber, offset: (pageNumber-1)*limit });
        console.log('Grid SQL', atob(queryBase) + conditions + ' LIMIT ' + limit + ' OFFSET ' + ((pageNumber-1)*limit));
    }catch(e){}
    $.ajax({
        url: urlTableInfo,
        method: 'POST',
        data: {
            'query': [queryBase, btoa(encodeURI(conditions)), btoa(limit), btoa((pageNumber-1)*limit)]
        },
        dataType: 'json',
        success: function(response) {
            total = response.total;
            qtdPaginas = Math.ceil((total/limit))
            statusCol = -1;

            if(fields['actions'] != null){
                actions = JSON.parse(fields['actions']);
                delete fields['actions'];
            }

            //Formatando informações do header{
                const colKeys = Object.keys(fields);
                header = [...colKeys];
                header.forEach(function(value, key){
                    if(camposBd[key] != null && camposBd[key].indexOf('status') >= 0){
                        statusCol = key;
                    }
                    camposBd[key] = camposBd[key].indexOf(' AS ') >= 0? 
                        camposBd[key].substring(camposBd[key].indexOf(' AS ')+4):
                        camposBd[key]
                    ;
                    
                    header[key] =
                        '<th colspan="1" rowspan="1" class="table-col-head" value="'+key+'">'
                            +(orderCol.indexOf(camposBd[key]) >= 0? (orderCol.indexOf(' ASC') >= 0? '<spam class="glyphicon glyphicon-menu-down"></spam>&emsp;': '<spam class="glyphicon glyphicon-menu-up"></spam>&emsp;'): '')
                            +value
                        +'</th>'
                });
            //}

            //Formatando informações das linhas{
                response.rows.forEach(function(dataArray, rowKey){
                    // row = '<td>'+dataArray.join('</td><td>')+'</td>';
                    row = '';
                    colKeys.forEach(function(colLabel){
                        let dbKey = fields[colLabel];
                        if(dbKey.indexOf(' AS ') >= 0){
                            dbKey = dbKey.substring(dbKey.indexOf(' AS ')+4);
                        }
                        row += '<td>'+(dataArray[dbKey] != null? dataArray[dbKey]: '')+'</td>';
                    });
                    
                    try {
                        actions.forEach(function(actionTag, key){
                            row += '<td class=\"tab-action\">'+actionTag+'</td>';
                        });
                    }catch(error){
                        console.log('actions not defined');
                    }
                    response.rows[rowKey] = '<tr>'+row+'</tr>';
                });
                response.rows = response.rows.join('');
            //}

            //Footer{
                if(pageNumber > qtdPaginas){
                    pageNumber = qtdPaginas;
                }
                footer = '';

                // Botão Primeira Página (<<)
                if(pageNumber > 1){
                    footer += '<div value="1" title="Primeira Página"><<</div>';
                }

                // Botão Página Anterior (<)
                if(pageNumber > 1){
                    footer += '<div value="'+(pageNumber-1)+'" title="Página Anterior"><</div>';
                }

                // Botões Numéricos
                for(f = ((pageNumber-1)<=1? 1: (pageNumber-1)-(pageNumber==qtdPaginas)); f < qtdPaginas+1 && f < pageNumber+2+(pageNumber == 1); f++){
                    if(f == pageNumber){
                        footer += '<div class=\"page-selected\" value=\"'+f+'\">'+f+'</div>'
                    }else{
                        footer += '<div value=\"'+f+'\">'+f+'</div>'
                    }
                }

                // Botão Próxima Página (>)
                if(pageNumber < qtdPaginas){
                    footer += '<div value="'+(pageNumber+1)+'" title="Próxima Página">></div>';
                }

                // Botão Última Página (>>)
                if(pageNumber < qtdPaginas){
                    footer += '<div value=\"'+(qtdPaginas)+'\" title="Última Página">>></div>'
                }


                //}
                
                btn = '<div class=\"export-csv-container\">' +
                        '<button id=\"btnExportCSV\" class=\"btn btn-success btn-sm\" title=\"Exportar TODOS os dados para CSV\">' +
                            '<i class=\"glyphicon glyphicon-download-alt\"></i> CSV (' + total + ' registros)' +
                        '</button>' +
                        '<div id=\"csvLoading\" class=\"csv-loading\" style=\"display: none;\">Gerando CSV...</div>' +
                      '</div>';
            $('#result thead')[0].innerHTML = header.join('');
            $('#result tbody')[0].innerHTML = response.rows;
            $('.total-registros').html('<div>Total: '+total+' &nbsp;|&nbsp; Página '+pageNumber+' de '+qtdPaginas+'</div>');
            $('.tab-pagination').html(footer);
            $('.table-loading-icon')[0].innerHTML = '';
            $('.botao-csv')[0].innerHTML = btn;

            try{ console.log('Grid response summary', { total: total, rows: (Array.isArray(response.rows)? response.rows.length: 0), fields: Object.keys(fields) }); }catch(e){}


            window.tableConfig = {
                queryBase: queryBase,
                conditions: conditions,
                fields: fields,
                camposBd: camposBd,
                totalRecords: total
            };

            $('#btnExportCSV').off('click').on('click', function() {
                exportAllToCSV();
            });

            definirFuncoesInternas();

            // Sincronização do scroll superior
            setTimeout(function() {
                const tableDiv = $('.table-div');
                const topScrollContainer = $('.top-scroll-container');
                const topScrollContent = $('.top-scroll-content');
                const tableElement = $('#result');

                // Ajusta a largura do conteúdo do scroll superior para igualar a tabela
                if (tableElement.width() > tableDiv.width()) {
                    topScrollContent.width(tableElement.width());
                    topScrollContainer.show();
                    
                    // Remove eventos anteriores para evitar duplicação
                    topScrollContainer.off('scroll');
                    tableDiv.off('scroll');

                    // Sincroniza os scrolls
                    topScrollContainer.on('scroll', function(){
                        if(tableDiv.scrollLeft() !== $(this).scrollLeft()){
                            tableDiv.scrollLeft($(this).scrollLeft());
                        }
                    });

                    tableDiv.on('scroll', function(){
                        if(topScrollContainer.scrollLeft() !== $(this).scrollLeft()){
                            topScrollContainer.scrollLeft($(this).scrollLeft());
                        }
                    });
                } else {
                    topScrollContainer.hide();
                }
            }, 100);

        },
        error: function(errMsg) {
            try{ console.error('Grid error', errMsg); }catch(e){}
        }
    });
};

$(document).ready(function(){
    try{ console.log('Grid init', { searchFields: searchFields, fields: fields }); }catch(e){}
    $('form[name="contex_form"]').on('change', consultarRegistros);
    $('input[name="limit"]').on('change', function(){
        $('input[name="limit"]').val($(this).val());
        consultarRegistros();
    });
    consultarRegistros();
});

$('.tab-pagination').click(function(event) {
    if($(event.target)[0].className != 'tab-pagination'){
        pageNumber = parseInt($(event.target)[0].attributes.value.value);
        consultarRegistros();
    }
});

function imprimirTabelaCompleta() {
    // Salva valores atuais
    const limitOriginal = parseInt($('input[name="limit"]').first().val());
    const paginaOriginal = pageNumber;

    // Altera o limit para um número bem alto
    $('input[name="limit"]').val(999999);
    pageNumber = 1;

    // Monta condições de filtro com base no formulário
    let data = {};
    $('form[name="contex_form"] :input').each(function(_, tag) {
        if (searchFields[tag.name] !== undefined) {
            data[searchFields[tag.name]] = tag.value;
        }
    });

    let condicoesImpressao = '';
    const keys = Object.values(searchFields);
    const inputs = $('form[name="contex_form"] :input');

    for (let f = 0; f < keys.length; f++) {
        if (data[keys[f]] !== '') {
            if (inputs[f].name.indexOf('_like') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' LIKE "%' + data[keys[f]] + '%"';
            } else if (inputs[f].name.indexOf('_g') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' > "' + data[keys[f]] + '"';
            } else if (inputs[f].name.indexOf('_ge') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' >= "' + data[keys[f]] + '"';
            } else if (inputs[f].name.indexOf('_l') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' < "' + data[keys[f]] + '"';
            } else if (inputs[f].name.indexOf('_le') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' <= "' + data[keys[f]] + '"';
            } else if (inputs[f].name.indexOf('_cpf') > 0) {
                condicoesImpressao += ' AND ' + keys[f] + ' = "' + data[keys[f]].replace(/[^0-9]/g, '') + '"';
            } else {
                condicoesImpressao += ' AND ' + keys[f] + ' = "' + data[keys[f]] + '"';
            }
        }
    }

    if (orderCol !== '') {
        condicoesImpressao += ' ORDER BY ' + orderCol;
    }

    // Faz a requisição AJAX para buscar todos os dados
    $.ajax({
        url: urlTableInfo,
        method: 'POST',
        data: {
            'query': [
                queryBase,
                btoa(encodeURI(condicoesImpressao)),
                btoa(999999), // limit
                btoa(0)       // offset
            ]
        },
        dataType: 'json',
        success: function(response) {
            // Gera o HTML da tabela
            const headerHtml = Object.keys(fields).map((value) => {
                return `<th class="header-cell">${value}</th>`;
            }).join('');
            
            const tableRowsHtml = response.rows.map((dataArray) => {
                let row = '';
                Object.keys(dataArray).forEach((key) => {
                    if (camposBd.indexOf(key) >= 0) {
                        row += `<td class="data-cell">${dataArray[key] !== null ? dataArray[key] : ''}</td>`;
                    }
                });
                return `<tr>${row}</tr>`;
            }).join('');

            const tabelaCompleta = `
                <style>
                    /* Estilos para o TCPDF */
                    body { font-family: Arial, sans-serif; font-size: 8pt; }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    th, td {
                        border: 1px solid #000;
                        padding: 3px; /* Reduz o padding para 3px */
                        text-align: left;
                    }
                    .header-cell {
                        font-weight: bold;
                        background-color: #f2f2f2;
                        font-size: 8pt; /* Reduz a fonte para 8pt */
                        white-space: nowrap; /* Evita quebra de linha no cabeçalho */
                    }
                    .data-cell {
                        font-size: 7pt; /* Reduz a fonte da célula para 7pt */
                    }
                </style>
                <table>
                    <thead>
                        <tr>${headerHtml}</tr>
                    </thead>
                    <tbody>
                        ${tableRowsHtml}
                    </tbody>
                </table>
            `;

            // Cria e submete o formulário para a página que gera o PDF
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = './impressao/grid.php'; 
            form.target = '_blank';

            var inputTabela = document.createElement('input');
            inputTabela.type = 'hidden';
            inputTabela.name = 'tabela_html';
            inputTabela.value = tabelaCompleta;
            form.appendChild(inputTabela);
            
            const selectIdEmpresa = document.getElementById('busca_empresa');
            const id = selectIdEmpresa.value;

            var IdEmpresa = document.createElement('input');
            IdEmpresa.type = 'hidden';
            IdEmpresa.name = 'IdEmpresa';
            IdEmpresa.value = id;
            form.appendChild(IdEmpresa);

            const divElemento = document.querySelector('.page-title');
            const titulo = divElemento.innerText.trim();
            const palavras = titulo.split(' ');
            const pagina = palavras[2];


            var paginaTitulo = document.createElement('input');
            paginaTitulo.type = 'hidden';
            paginaTitulo.name = 'paginaTitulo';
            paginaTitulo.value = pagina;
            form.appendChild(paginaTitulo);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Restaura os valores originais da paginação após a requisição
            $('input[name="limit"]').val(limitOriginal);
            pageNumber = paginaOriginal;
            consultarRegistros();
        },
        error: function(err) {
            console.error('Erro ao carregar todos os dados:', err);

            
        }
    });
}

/* Configuração de Colunas */
function openColumnConfig(){
    const container = document.getElementById('listaColunas');
    container.innerHTML = '';
    
    let displayOrder = [];
    let hiddenFields = [];
    
    // Get visible fields from current 'fields' (which respects order)
    const currentKeys = Object.keys(fields);
    
    currentKeys.forEach(key => {
        displayOrder.push({key: key, label: key, visible: true});
    });
    
    // Get hidden fields from 'allFields'
    if(typeof allFields !== 'undefined'){
        Object.keys(allFields).forEach(key => {
            if(!fields[key]){
                hiddenFields.push({key: key, label: key, visible: false});
            }
        });
    }
    
    const fullList = displayOrder.concat(hiddenFields);
    
    fullList.forEach((item, index) => {
        const div = document.createElement('div');
        // div.className = 'checkbox'; // Removido para evitar conflito com Bootstrap
        div.style.padding = '10px';
        div.style.borderBottom = '1px solid #eee';
        div.style.display = 'flex';
        div.style.justifyContent = 'space-between';
        div.style.alignItems = 'center';
        div.style.width = '100%';
        
        const label = document.createElement('label');
        label.style.cursor = 'pointer';
        label.style.flexGrow = '1';
        label.style.margin = '0';
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.fontWeight = 'normal';
        
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.value = item.key;
        input.checked = item.visible;
        input.className = 'column-config-check';
        input.style.margin = '0 10px 0 0';
        input.style.minWidth = '18px';
        input.style.height = '18px';
        input.style.position = 'relative'; // Garante visibilidade
        
        const spanText = document.createElement('span');
        spanText.innerText = item.label;
        spanText.style.wordBreak = 'break-word'; // Quebra texto longo
        spanText.style.lineHeight = '1.2';

        label.appendChild(input);
        label.appendChild(spanText);
        
        div.appendChild(label);
        
        // Add Up/Down buttons
        const btnGroup = document.createElement('div');
        
        const btnUp = document.createElement('button');
        btnUp.className = 'btn btn-xs btn-default';
        btnUp.innerHTML = '<span class="glyphicon glyphicon-chevron-up"></span>';
        btnUp.style.marginRight = '5px';
        btnUp.onclick = function(e){ e.preventDefault(); moveItem(div, -1); };
        
        const btnDown = document.createElement('button');
        btnDown.className = 'btn btn-xs btn-default';
        btnDown.innerHTML = '<span class="glyphicon glyphicon-chevron-down"></span>';
        btnDown.onclick = function(e){ e.preventDefault(); moveItem(div, 1); };
        
        btnGroup.appendChild(btnUp);
        btnGroup.appendChild(btnDown);
        
        div.appendChild(btnGroup);
        
        container.appendChild(div);
    });
    
    $('#modalConfigGrid').modal('show');
}

function moveItem(element, direction){
    const parent = element.parentNode;
    if(direction === -1 && element.previousElementSibling){
        parent.insertBefore(element, element.previousElementSibling);
    } else if(direction === 1 && element.nextElementSibling){
        parent.insertBefore(element.nextElementSibling, element);
    }
}

function saveColumnConfig(){
    const checks = document.getElementsByClassName('column-config-check');
    const config = [];
    
    for(let i=0; i<checks.length; i++){
        config.push({
            key: checks[i].value,
            visible: checks[i].checked
        });
    }
    
    if(typeof gridName === 'undefined'){
        alert('Erro: gridName não definido.');
        return;
    }

    $.ajax({
        url: '../contex20/grid_config_controller.php',
        method: 'POST',
        data: {
            grid_name: gridName,
            columns: JSON.stringify(config)
        },
        dataType: 'json',
        success: function(response){
            if(response.success){
                location.reload();
            } else {
                alert('Erro ao salvar configuração: ' + (response.error || 'Erro desconhecido'));
            }
        },
        error: function(xhr, status, error){
            console.error(xhr.responseText);
            alert('Erro ao conectar com o servidor.');
        }
    });
}
