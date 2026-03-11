<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

$tipos = mysqli_fetch_all(query(
	"SELECT tipo_nb_id, tipo_tx_nome
	FROM tipos_documentos
	WHERE tipo_tx_status = 'ativo'
	ORDER BY tipo_tx_nome ASC"
), MYSQLI_ASSOC);
?>

<div class="bg-gray-50 py-10 px-4 font-sans">
	<div class="max-w-4xl w-full mx-auto bg-white shadow-xl rounded-2xl overflow-hidden">
		<div class="bg-white px-8 py-6 border-b border-gray-100 flex justify-between items-center">
			<div>
				<h2 class="text-xl font-bold text-gray-800">Documentos em Lote</h2>
				<p class="text-gray-500 text-sm">Inserção em massa de PDFs para funcionários</p>
			</div>
			<a href="index.php" class="text-gray-500 hover:text-blue-600 text-sm font-medium transition-colors flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
				<i class="fas fa-arrow-left"></i> Voltar
			</a>
		</div>

		<div class="p-8">
			<?php if (isset($_GET['status'])): ?>
				<?php if ($_GET['status'] === 'success'): ?>
					<div class="mb-8 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200 flex items-center gap-3 shadow-sm">
						<i class="fas fa-check-circle text-xl"></i>
						<div>
							<p class="font-bold">Sucesso!</p>
							<p class="text-sm"><?php echo htmlspecialchars($_GET['message'] ?? 'Documentos inseridos.', ENT_QUOTES, 'UTF-8'); ?></p>
						</div>
					</div>
				<?php else: ?>
					<div class="mb-8 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200 flex items-center gap-3 shadow-sm">
						<i class="fas fa-exclamation-circle text-xl"></i>
						<div>
							<p class="font-bold">Erro</p>
							<p class="text-sm"><?php echo htmlspecialchars($_GET['message'] ?? 'Ocorreu um erro.', ENT_QUOTES, 'UTF-8'); ?></p>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form action="processar_documentos_em_lote.php" method="POST" enctype="multipart/form-data" id="formLote" class="space-y-8">
				<div class="grid md:grid-cols-2 gap-6">
					<div>
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Tipo de Documento</label>
						<select name="tipo_documento" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
							<option value="">Selecione</option>
							<?php foreach($tipos as $t): ?>
								<option value="<?php echo (int) $t["tipo_nb_id"]; ?>"><?php echo htmlspecialchars($t["tipo_tx_nome"] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div>
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Visibilidade</label>
						<select name="visivel" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
							<option value="nao">Não</option>
							<option value="sim">Sim</option>
						</select>
					</div>

					<div class="md:col-span-2">
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Nome do Documento</label>
						<input type="text" name="nome" required placeholder="Ex: Holerite 02/2026" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
					</div>

					<div class="md:col-span-2">
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Descrição (opcional)</label>
						<input type="text" name="descricao" placeholder="Ex: Holerite mensal" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
					</div>

					<div>
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Data de Vencimento (opcional)</label>
						<input type="date" name="data_vencimento" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
					</div>

					<div>
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Funcionários</label>
						<select name="status_funcionario" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
							<option value="ativo" selected>Ativos</option>
							<option value="inativo">Inativos</option>
							<option value="todos">Todos</option>
						</select>
					</div>

					<div class="md:col-span-2 border border-gray-200 rounded-2xl p-5 bg-gray-50">
						<div class="flex items-start justify-between gap-4 flex-wrap">
							<div class="min-w-0">
								<div class="text-sm font-bold text-gray-700 uppercase tracking-wide">Assinatura</div>
								<div class="text-sm text-gray-500 mt-1">Cria uma solicitação de assinatura para cada funcionário e envia o e-mail</div>
							</div>
							<label class="flex items-center gap-2 text-sm text-gray-700 whitespace-nowrap">
								<input type="checkbox" name="enviar_assinatura" value="sim" checked class="text-blue-600 mt-1">
								Enviar para assinatura
							</label>
						</div>

						<div class="mt-4">
							<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Função do Signatário</label>
							<input type="text" name="funcao_assinatura" value="Funcionário" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
						</div>
					</div>
				</div>

				<div class="border border-gray-200 rounded-2xl p-6">
					<div class="flex items-center justify-between gap-4 flex-wrap">
						<div>
							<h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Modo</h3>
							<p class="text-sm text-gray-500 mt-1">Escolha como os PDFs serão aplicados</p>
						</div>
						<div class="flex items-center gap-4">
							<label class="flex items-center gap-2 text-sm text-gray-700">
								<input type="radio" name="modo" value="unico" checked class="text-blue-600">
								Mesmo PDF para todos
							</label>
							<label class="flex items-center gap-2 text-sm text-gray-700">
								<input type="radio" name="modo" value="por_funcionario" class="text-blue-600">
								Um PDF por funcionário
							</label>
						</div>
					</div>

					<div id="boxUnico" class="mt-6">
						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">PDF Único</label>
						<input type="file" name="arquivo_unico" accept="application/pdf" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
					</div>

					<div id="boxMulti" class="mt-6 hidden">
						<div class="mb-3 text-sm text-gray-600 bg-yellow-50 border border-yellow-100 rounded-xl p-4">
							<div class="font-semibold text-gray-800 mb-1">Padrão do nome do arquivo</div>
							<div>Use o ID do funcionário ou o CPF no início do nome.</div>
							<div class="mt-2 font-mono text-xs text-gray-700">Ex: 123.pdf | 123_holerite_02_2026.pdf | 12345678901.pdf</div>
						</div>

						<label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">PDFs</label>
						<input type="file" name="arquivos[]" accept="application/pdf" multiple class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
					</div>
				</div>

				<button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-blue-200 transform hover:-translate-y-0.5 transition-all flex items-center justify-center gap-3 text-lg">
					<span>Inserir Documentos</span>
					<i class="fas fa-upload"></i>
				</button>
			</form>
		</div>
	</div>

	<script>
		const radios = document.querySelectorAll('input[name="modo"]');
		const boxUnico = document.getElementById('boxUnico');
		const boxMulti = document.getElementById('boxMulti');

		function updateModo() {
			const modo = document.querySelector('input[name="modo"]:checked')?.value;
			if (modo === 'por_funcionario') {
				boxUnico.classList.add('hidden');
				boxMulti.classList.remove('hidden');
			} else {
				boxMulti.classList.add('hidden');
				boxUnico.classList.remove('hidden');
			}
		}

		radios.forEach(r => r.addEventListener('change', updateModo));
		updateModo();
	</script>
</div>

<?php include_once "componentes/layout_footer.php"; ?>

