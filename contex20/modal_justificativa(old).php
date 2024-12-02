<!-- <script>
	$(document).ready(function(){
		$("#myModal").modal();
	});
</script>
<div class='modal fade' id='myModal' tabindex='-1' role='dialog' aria-labelledby='myModalLabel'>
        <div class='modal-dialog' role='document'>
            <div class='modal-content'>
                <div class='modal-header'>
                <button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                <h4 class='modal-title' id='myModalLabel'>Justifica Exclusão de Registro</h4>
                </div>
                <div class='modal-body'>
                    <div class='form-group'>
                        <b><label for='justificar' class='control-label' style='font-size: 15px;'>Justificar:</label></b>
                        <textarea class='form-control' id='justificar'></textarea>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
                    <button type='button' class='btn btn-primary' data-dismiss='modal' 
					onclick='javascript:contex_icone( <?=( $id ?? "")?>,<?=( $acao ?? "")?>,<?=( $campos ?? "" )?>,<?=( $valores ?? "" )?>,<?=( $target ?? "" )?>,<?=( $msg ?? "" )?>,<?=( $action ?? "" )?>,<?=($data_de ?? "" )?>, <?=( $data_ate ?? "")?> ,document.getElementById("justificar").value);'>Gravar</button>
                </div>
            </div>
        </div>
    </div> -->