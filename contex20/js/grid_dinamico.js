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
limit = 10;
orderCol = '';

const camposBd = Object.values(fields);

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
        }catch(error){}
        
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

    limit = parseInt($('input[name=\"limit\"')[0].value);
    if(limit < 1){
        limit = 1;
    }

    keys = Object.values(searchFields);
    inputs = $('form[name=\"contex_form\"] :input');


    for(f = 0; f < keys.length; f++){
        if(data[keys[f]] != ''){
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
                header = [...Object.keys(fields)];
                header.forEach(function(value, key){
                    if(camposBd[key] != null && camposBd[key].indexOf('status') >= 0){
                        statusCol = key;
                    }
                    camposBd[key] = camposBd[key].indexOf(' AS ') >= 0? 
                        camposBd[key].substring(camposBd[key].indexOf(' AS ')+4):
                        camposBd[key]
                    ;
                    
                    header[key] =
                        '<th colspan=\"1\" rowspan=\"1\" class=\"table-col-head\" value=\"'+key+'\">'
                            +(orderCol.indexOf(camposBd[key]) >= 0? (orderCol.indexOf(' ASC') >= 0? '<spam class=\"glyphicon glyphicon-menu-down\"></spam>&emsp;': '<spam class=\"glyphicon glyphicon-menu-up\"></spam>&emsp;'): '')
                            +value
                        +'</th>'
                });
            //}

            //Formatando informações das linhas{
                response.rows.forEach(function(dataArray, rowKey){
                    // row = '<td>'+dataArray.join('</td><td>')+'</td>';
                    row = '';
                    Object.keys(dataArray).forEach(function(key){
                        if(camposBd.indexOf(key) >= 0){
                            row += '<td>'+(dataArray[key] != null? dataArray[key]: '')+'</td>';
                        }
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
                if(pageNumber > 2 && qtdPaginas > 3){
                    footer += '<div value=\"1\"><<</div>'
                }
                for(f = ((pageNumber-1)<=1? 1: (pageNumber-1)-(pageNumber==qtdPaginas)); f < qtdPaginas+1 && f < pageNumber+2+(pageNumber == 1); f++){
                    if(f == pageNumber){
                        footer += '<div class=\"page-selected\" value=\"'+f+'\">'+f+'</div>'
                    }else{
                        footer += '<div value=\"'+f+'\">'+f+'</div>'
                    }
                }
                if(qtdPaginas > 3 && pageNumber < qtdPaginas-1){
                    footer += '<div value=\"'+(qtdPaginas)+'\">>></div>'
                }
            //}

            $('#result thead')[0].innerHTML = header.join('');
            $('#result tbody')[0].innerHTML = response.rows;
            $('.grid-footer .tab-pagination')[0].innerHTML = footer;
            $('.table-loading-icon')[0].innerHTML = '';


            definirFuncoesInternas();
        },
        error: function(errMsg) {
            console.error('Erro na consulta:', errMsg);
        }
    });
};

$(document).ready(function(){
    $('form[name=\"contex_form\"]').on('change', consultarRegistros);
    $('#limit').on('change', function(){
        consultarRegistros();
    });
    consultarRegistros();
});

$('.grid-footer .tab-pagination').click(function(event) {
    if($(event.target)[0].className != 'tab-pagination'){
        pageNumber = parseInt($(event.target)[0].attributes.value.value);
        consultarRegistros();
    }
});