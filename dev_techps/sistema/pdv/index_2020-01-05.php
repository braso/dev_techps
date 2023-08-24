<?php
include "../conecta.php";


?><!DOCTYPE html>
<html lang="en">
<head>
  <title>CONTAINER Sistemas</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>    

  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
  <script src="/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>
  <link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
  <link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />


  <style>
    /* Set height of the grid so .sidenav can be 100% (adjust if needed) */
    /*.row.content {height: 100%;}*/
    header{      
      padding: 0.5% 0 0 0;
      width: 100%;
      float: left;
      background-color: #f1f1f1;
      border: 2px solid #e5e6e7;      
    }
    header p{
      width: 50%;
      float: left;
    }        
    header h1{
      margin: 0;
      padding: 0.76% 0;      
    }
    header p.text-right{      
      padding: 0.73% 0;      
    }
    /* Set gray background color and 100% height */
    .sidenav {
      width: 30%;
      background-color: #f1f1f1;
      height: 87%;
    }
    .box_right{
      width: 70%;
      padding: 0.5%;
      height: 87%;      
    }
    .sidenav button{
      margin: 0 4%;
    }
    /* Set black background color, white text and some padding */
    footer {
      position: fixed;
      width: 100%;
      background-color: #555;
      color: white;
      bottom: 0;
      padding: 0.5% 0 0 0;
    }
    .botoes{
      width: 100%;
      float: left;
      margin: 0 0 0 0;
      padding: 5% 0;      
    }
    .botoes button{
      margin: 1% 1% 1% 1%;
      text-align: left;
      line-height: 30px;
    }
    button.col-md-5{
      width: 48%;
    }
    .well{
      width: 92%;
      float: left;
      margin: 2% 0 0 4%;
      /*font-size: 8pt;*/
      text-align: center;
    }

    .box_itens{    
      position: relative;
      padding: 0;
      height: 77.5%;
      overflow-y: auto;
    }

    .panel.panel-default{
      margin: 0.45% 0 0 0;
      padding: 1% 0 0 0;
    }
    .panel.panel-default h1{
      margin: 0;
    }
    /* On small screens, set height to 'auto' for sidenav and grid */
    @media screen and (max-width: 767px) {
      .sidenav {
        height: auto;
        padding: 15px;
      }
      .row.content {height: auto;} 
    }
  </style>
</head>
<body>
<header class="container-fluid">
  <p class="text-left col-md-4">
      <img src="img/logo.png" alt="img/logo.png"/>    
  </p>
  <h1 class="col-md-4 text-center"><b>Caixa 001</b></h1>  
  <p class="text-right col-md-4">
    <span><b>Operador:</b> </span>Colaborador<br>
    <?=date("d/m/Y H:i");?>  
  </p>
</header>  
<div class="container-fluid">
  <div class="row content" id="div_principal">
    <div class="col-sm-3 sidenav">
      <br>
      <div class="form-group">
        <label for="email">Cliente:</label>
        <div class="input-group">
          <span>Cliente Padrão</span>          
        </div>        
        <br>
        <label for="email">Produto:</label>
        <div class="input-group">
          <select type="text" class="form-control" id="campo_produto"></select>
          <span class="input-group-btn">
            <button class="btn btn-default" type="button">
              <span class="glyphicon glyphicon-search"></span>
            </button>
          </span>
        </div>
        <br>

        <input type="hidden" id="id_venda" value="">
        <input type="hidden" id="operacoes" value="">
        

        <script type="text/javascript">
          $.fn.select2.defaults.set("theme", "bootstrap");
          $(window).bind("load", function() {
            $('#campo_produto').select2({
              language: 'pt-BR',
              placeholder: 'Selecione um item',
              allowClear: true,
              ajax: {
                url: '/contex20/select2.php?path=<?=$CONTEX[path]?>&tabela=produto',
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                  return {
                    results: data
                  };
                },
                cache: true
              }
            });
          });

		$('#campo_produto').on('select2:select', function (e) {
			var data = e.params.data;
			// alert(data.id);

			$.post("funcoes_pdv.php", {
					acao : "adiciona_produto",
					id_produto : data.id,
					id_venda: document.getElementById('id_venda').value
				}, function(msg){
					$("#operacoes").html(msg);
					// alert(document.getElementById('id_venda').value);
					alert(msg);
				})
			});
        </script>



        <label class="control-label col-sm-6" for="email">Quantidade:</label>
        <div class="col-sm-6">
          <input type="email" class="form-control" id="email" placeholder="0">
        </div>
        <br><br>
        <label class="control-label col-sm-6" for="pwd">Vlr. Unitário:</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" id="pwd" placeholder="0,00">
        </div>
        <br><br>
        <label class="control-label col-sm-6" for="pwd">Desconto (%):</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" id="pwd" placeholder="0,00">
        </div>
        <br><br>
        <label class="control-label col-sm-6" for="pwd">Desconto (R$):</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" id="pwd" placeholder="0,00">
        </div>
        <br><br>
        <label class="control-label col-sm-6" for="pwd">Total:</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" id="pwd" placeholder="0,00" readonly="readonly">
        </div>    
        <div class="botoes">    
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon004.png" alt="img/icon004.png"/> Pagamento (F2)</button>
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon002.png" alt="img/icon002.png"/> Cancelar (F4)</button>        
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon003.png" alt="img/icon003.png"/> Clientes (F5)</button>
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon006.png" alt="img/icon006.png"/> Vendas (F6)</button>        
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon005.png" alt="img/icon005.png"/> Recebimentos (F7)</button>
          <button type="button" class="btn btn-default col-md-5"><img src="img/icon001.png" alt="img/icon001.png"/> Consulta (F8)</button>          
        </div>
      </div>          
    </div>

    <div class="col-sm-9 box_right">
      <div class="panel panel-default col-md-12 box_itens">
        <div class="table-responsive">
          <table class="table table-striped table-bordered table-condensed">
            <thead>
              <tr>
                <th>&nbsp;</th>
                <th class="text-center col-md-1">ID</th>
                <th>PRODUTO</th>
                <th class="text-center col-md-1">QTDE</th>
                <th class="text-center col-md-1">UNITÁRIO</th>
                <th class="text-center col-md-1">DESCONTO</th>
                <th class="text-center col-md-1">TOTAL</th>
                <th class="col-md-1">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="text-center">1</td>
                <td class="text-center">999999</td>
                <td>Descrição do Produto 001</td>
                <td class="text-center">1,000</td>
                <td class="text-center">100,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">90,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">2</td>
                <td class="text-center">888888</td>
                <td>Descrição do Produto 002</td>
                <td class="text-center">2,000</td>
                <td class="text-center">90,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">162,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
              <tr>
                <td class="text-center">3</td>
                <td class="text-center">777777</td>
                <td>Descrição do Produto 003</td>
                <td class="text-center">3,000</td>
                <td class="text-center">80,00</td>
                <td class="text-center">10,00</td>
                <td class="text-center">216,00</td>
                <td class="text-center"><img src="img/icon002.png" alt="img/icon002.png"/></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>      
      <div class="panel panel-default col-md-12">
        <form action="/action_page.php">
          <div class="form-group col-md-4">
            <label for="email">Comandas:</label>
            <input type="email" class="form-control" id="email">
          </div>          
          <div class="form-group col-md-3">
            <label for="email">Itens:</label>
            <h1><b>6</b></h1>
          </div>          
          <div class="form-group col-md-5">
            <label for="email">Valor Total:</label>
            <h1><b>R$468,00</b></h1>
          </div>          
        </form>
      </div>      
    </div>
  </div>
</div>

<footer class="container-fluid">
  <p class="text-left" style="width: 50%;float: left;"><b>Licenciado a:</b> Pet Shop Reino Animal</p>
  <p class="text-right" style="width: 50%;float: left;"><b>Desenvolvido por:</b> <a href="http://www.containerti.com.br">ContainerTI</a></p>
</footer>
<script type="text/javascript">     
  if (window.screenTop && window.screenY) {
    window.document.getElementById("div_principal").style.height=screen.height+"px";
  }else{
    window.document.getElementById("div_principal").style.height=window.innerHeight+"px";
  }
</script>
</body>
</html>
