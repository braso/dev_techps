<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<style type="text/css">
	.modal-header{
		background-color: #444d58;
		color: #FFF;
	}
	.modal-header h4{
		font-weight: bold;
	}
	.modal-body p{
		width: 100%;
		text-align: center;
	}
	.modal-footer button{
		background-color: #444d58;
		color: #FFF;
		transition: background-color .2s;
	}
	.modal-footer button:hover{
		background-color: #A1A6AB;
		color: #FFF;
	}

	button.close {
		color: white	;
		cursor: pointer;
		padding-bottom: 1px;
		padding-left: 6px;
		padding-right: 6px;
		padding-top: 1px;
		background-color: #ff0000;
		opacity: 1;
		transition: background-color .2s;
		border-radius: 4px;
	}

	.close:focus, .close:hover{
		color: white;
	}
</style>

<script>
	$(document).ready(function(){
		$('#confirmButton').click(function(){
			form.submit();
		});


		form = document.createElement('form');
		form.style.display = 'none';
		form.name = 'modalForm';
		form.method = 'post';
		<?=$formValuesJS?>

		confirmado = document.createElement('input');
		confirmado.name = 'confirmado';
		confirmado.innerHTML = 'true';
		form.appendChild(confirmado);

		document.body.appendChild(form);
		console.log(form);
		$("#myModal").modal();
	});
</script>

<!-- Modal -->
<div class="modal fade" id="myModal" role="dialog">
	<div class="modal-dialog">
	<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><?=$title?></h4>
			</div>
			<div class="modal-body">
				<?=$msg?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" id="confirmButton">Confirmar</button>
			</div>
		</div>	  
	</div>
</div>