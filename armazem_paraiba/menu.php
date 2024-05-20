<style>
    .menu-dropdown.active > a {
        background-color: #8c98a6 !important;
    }
</style>

<?php
function verificarAtividade($paginasAtivas) {
    foreach ($paginasAtivas as $pagina) {
        if (strpos($_SERVER["REQUEST_URI"], $pagina) !== false) {
            return 'active';
        }
    }
    return '';
}

$paginasPonto = ['/carregar_ponto.php', '/espelho_ponto.php', '/endosso.php', '/nao_conformidade.php', '/nao_cadastrados.php'];

$paginasCadastro = ['/cadastro_empresa.php', '/cadastro_motorista.php', '/cadastro_parametro.php', '/cadastro_motivo.php', '/cadastro_feriado.php', '/cadastro_usuario.php', '/cadastro_macro.php'];

$paginaPainel = ['/painel'];
if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador')) || is_int(strpos($_SESSION['user_tx_nivel'], 'Super Administrador'))) { ?>

	<!-- INICIO HEADER MENU -->
	<div class="page-header-menu">
		<div class="container-fluid">
			<!-- INICIO MEGA MENU -->
			<!-- DOC: Apply "hor-menu-light" class after the "hor-menu" class below to have a horizontal menu with white background -->
			<!-- DOC: Remove data-hover="dropdown" and data-close-others="true" attributes below to disable the dropdown opening on mouse hover -->
			<div class="hor-menu  ">
				<ul class="nav navbar-nav">
					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginasCadastro)?>">
						<a href="javascript:;">Cadastros<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">

							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_empresa.php" class="nav-link ">Empresa/Filial</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_endosso.php" class="nav-link ">Endosso</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_feriado.php" class="nav-link ">Feriado</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_macro.php" class="nav-link ">Macro (Positron)</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_motivo.php" class="nav-link ">Motivo</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_motorista.php" class="nav-link ">Motorista</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_parametro.php" class="nav-link ">Parâmetro</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_usuario.php" class="nav-link ">Usuário</a></li>
						</ul>
					</li>


					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginasPonto)?>">
						<a href="javascript:;">Ponto<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							<li class=""><a href="<?= $CONTEX["path"] ?>/endosso.php" class="nav-link ">Endossos</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/espelho_ponto.php" class="nav-link ">Espelhos de Ponto</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/carregar_ponto.php" class="nav-link ">Integrações de Ponto</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/nao_conformidade.php" class="nav-link ">Não Conformidades</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/nao_cadastrados.php" class="nav-link">Não Cadastrados</a></li> 
						</ul>
					</li>
					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginaPainel) ?>">
						<a href="<?= $CONTEX["path"]?>/painel">Painel<span class="arrow"></span></a>
					</li>
					<?php if(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))){ ?>
					<li class="menu-dropdown classic-menu-dropdown ">
						<a href="javascript:;"> Suporte<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							<li class=""><a href="<?= $CONTEX["path"] ?>/#" class="nav-link ">Perguntas Frequentes</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/doc.php" class="nav-link ">Ver Documentação</a></li>
						</ul>
					</li>
					<?php
					}
					?>
				</ul>
			</div>
			<!-- FIM MEGA MENU -->
		</div>
	</div>
	<!-- FIM HEADER MENU -->

<?php } ?>

<?php if (is_int(strpos($_SESSION['user_tx_nivel'], 'Funcionário'))) { ?>

	<!-- INICIO HEADER MENU -->
	<div class="page-header-menu">
		<div class="container-fluid">
			<!-- INICIO MEGA MENU -->
			<!-- DOC: Apply "hor-menu-light" class after the "hor-menu" class below to have a horizontal menu with white background -->
			<!-- DOC: Remove data-hover="dropdown" and data-close-others="true" attributes below to disable the dropdown opening on mouse hover -->
			<div class="hor-menu  ">
				<ul class="nav navbar-nav">
					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginasCadastro)?>">
						<a href="javascript:;">Cadastrar<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">

							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_empresa.php" class="nav-link ">Empresas/Filiais</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_endosso.php" class="nav-link ">Endossos</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_feriado.php" class="nav-link ">Feriados</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_macro.php" class="nav-link ">Macros (Positron)</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_motivo.php" class="nav-link ">Motivos</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_motorista.php" class="nav-link ">Motoristas</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_parametro.php" class="nav-link ">Parâmetros</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_usuario.php" class="nav-link ">Usuários</a></li>
						</ul>
					</li>


					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginasPonto)?>">
						<a href="javascript:;">Visualizar<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							<li class=""><a href="<?= $CONTEX["path"] ?>/endosso.php" class="nav-link ">Endossos</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/espelho_ponto.php" class="nav-link ">Espelhos de Ponto</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/carregar_ponto.php" class="nav-link ">Integrações de Ponto</a></li>
							<li class=""><a href="<?= $CONTEX["path"] ?>/nao_conformidade.php" class="nav-link ">Não Conformidades</a></li>
						</ul>
					</li>
				</ul>
			</div>
			<!-- FIM MEGA MENU -->
		</div>
	</div>
	<!-- FIM HEADER MENU -->

<?php } ?>

<?php if(in_array($_SESSION['user_tx_nivel'], ['Motorista', 'Ajudante'])){?>

	<!-- INICIO HEADER MENU -->
	<div class="page-header-menu">
		<div class="container-fluid">
			<!-- INICIO MEGA MENU -->
			<!-- DOC: Apply "hor-menu-light" class after the "hor-menu" class below to have a horizontal menu with white background -->
			<!-- DOC: Remove data-hover="dropdown" and data-close-others="true" attributes below to disable the dropdown opening on mouse hover -->
			<div class="hor-menu  ">
				<ul class="nav navbar-nav">

					<li class=""><a href="<?= $CONTEX["path"] ?>/batida_ponto.php" class="nav-link ">Registrar Ponto</a></li>
					<li class=""><a href="<?= $CONTEX["path"] ?>/cadastro_usuario.php" class="nav-link ">Usuário</a></li>
					<li class=""><a href="<?= $CONTEX["path"] ?>/espelho_ponto.php" class="nav-link ">Espelhos de Ponto</a></li>

				</ul>
			</div>
			<!-- FIM MEGA MENU -->
		</div>
	</div>
	<!-- FIM HEADER MENU -->

<?php } ?>