<head>
	<script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
</head>
<h1>
  Draw over image
</h1>
<div class="wrapper">
  <canvas id="signature-pad" style="border: solid;" class="signature-pad" width=400 height=200></canvas>
</div>
<div>
  <button id="save">Salvar</button>
  <button id="clear">Limpar</button>
</div>

<form name="form_img" method="post">
	<input type="hidden" name="image">
</form>

<?
if($_POST[image]!=''){
	print_r($_POST);
	$data_uri = $_POST[image];
	$encoded_image = explode(",", $data_uri)[1];
	$decoded_image = base64_decode($encoded_image);
	file_put_contents("imagens/assinatura/signature.png", $decoded_image);
}
?>

<script type="text/javascript">
var signaturePad = new SignaturePad(document.getElementById('signature-pad'), {
  backgroundColor: 'rgba(255, 255, 255, 0)',
  penColor: 'rgb(0, 0, 0)'
});
var saveButton = document.getElementById('save');
var cancelButton = document.getElementById('clear');

saveButton.addEventListener('click', function (event) {
  var imageData = signaturePad.toDataURL();
  document.getElementsByName("image")[0].setAttribute("value", imageData);
  document.form_img.submit();
// Send data to server instead...
  // window.open(data);
});

cancelButton.addEventListener('click', function (event) {
  signaturePad.clear();
});

</script>