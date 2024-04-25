<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<style type="text/css">
	.modal-header{
		background-color: #373737;
	}
	.modal-header h4{
		font-weight: bold;
		color: red;
	}
	.modal-body p{
		width: 100%;
		text-align: center;
	}
	.modal-footer button{
		background-color: #373737;
		color: #FFF;
	}
	.modal-footer button:hover{
		background-color: #A1A6AB;
		color: #FFF;
	}
</style>

<script>
	$(document).ready(function(){
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
				<h4 class="modal-title"><?=($title?? '')?></h4>
			</div>
			<div class="modal-body">
				<?=($msg?? '')?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
			</div>
		</div>	  
	</div>
</div>