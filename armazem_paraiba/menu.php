<style>
    .menu-dropdown.active > a {
        background-color: #8c98a6 !important;
        /*color: #fff !important;*/
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

$paginasPonto = ['/carregar_ponto', '/espelho_ponto', '/endosso', '/nao_conformidade', '/nao_cadastrados'];

$paginasCadastro = ['/cadastro_empresa', '/cadastro_motorista', '/cadastro_parametro', '/cadastro_motivo', '/cadastro_feriado','/cadastro_usuario','/cadastro_macro'];




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
						<a href="javascript:;"> Cadastros<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">

							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_empresa" class="nav-link ">Empresa/Filial</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_motorista" class="nav-link ">Motorista</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_parametro" class="nav-link ">Parâmetro</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_motivo" class="nav-link ">Motivo</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_feriado" class="nav-link ">Feriado</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_usuario" class="nav-link ">Usuário</a></li>

							<li class="dropdown-submenu ">
								<a href="javascript:;" class="nav-link nav-toggle ">Positron<span class="arrow"></span></a>
								<ul class="dropdown-menu">
									<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_macro" class="nav-link ">Macro</a></li>
								</ul>
							</li>
						</ul>
					</li>


					<li class="menu-dropdown classic-menu-dropdown <?= verificarAtividade($paginasPonto)?>">
						<a href="javascript:;"> Ponto<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							<li class=" "><a href="<?= $CONTEX["path"] ?>/carregar_ponto" class="nav-link ">Carregar Ponto</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/espelho_ponto" class="nav-link ">Espelho de Ponto</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/endosso" class="nav-link ">Endosso</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/nao_conformidade" class="nav-link ">Não Conformidade</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/nao_cadastrados" class="nav-link">Não cadastrados</a></li> 
						</ul>
					</li>
					<?php if(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))){ ?>
					<li class="menu-dropdown classic-menu-dropdown ">
						<a href="javascript:;"> Suporte<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							
							<li class=" "><a href="<?= $CONTEX["path"] ?>/doc.php" class="nav-link ">Ver Documentação</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/#" class="nav-link ">Perguntas Frequentes</a></li>
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

<? } ?>

<? if (is_int(strpos($_SESSION['user_tx_nivel'], 'Funcionário'))) { ?>

	<!-- INICIO HEADER MENU -->
	<div class="page-header-menu">
		<div class="container-fluid">
			<!-- INICIO MEGA MENU -->
			<!-- DOC: Apply "hor-menu-light" class after the "hor-menu" class below to have a horizontal menu with white background -->
			<!-- DOC: Remove data-hover="dropdown" and data-close-others="true" attributes below to disable the dropdown opening on mouse hover -->
			<div class="hor-menu  ">
				<ul class="nav navbar-nav">
					<li class="menu-dropdown classic-menu-dropdown ">
						<a href="javascript:;"> Cadastros<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">

							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_empresa" class="nav-link ">Empresa/Filial</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_motorista" class="nav-link ">Motorista</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_parametro" class="nav-link ">Parâmetro</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_motivo" class="nav-link ">Motivo</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_feriado" class="nav-link ">Feriado</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_usuario" class="nav-link ">Usuário</a></li>

							<li class="dropdown-submenu ">
								<a href="javascript:;" class="nav-link nav-toggle ">Positron<span class="arrow"></span></a>
								<ul class="dropdown-menu">
									<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_macro" class="nav-link ">Macro</a></li>
								</ul>
							</li>
						</ul>
					</li>


					<li class="menu-dropdown classic-menu-dropdown ">
						<a href="javascript:;"> Ponto<span class="arrow"></span></a>
						<ul class="dropdown-menu pull-left">
							<li class=" "><a href="<?= $CONTEX["path"] ?>/carregar_ponto" class="nav-link ">Carregar Ponto</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/espelho_ponto" class="nav-link ">Espelho de Ponto</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/nao_conformidade" class="nav-link ">Não Conformidade</a></li>
							<li class=" "><a href="<?= $CONTEX["path"] ?>/endosso" class="nav-link ">Endosso</a></li>
						</ul>
					</li>

				</ul>
			</div>
			<!-- FIM MEGA MENU -->
		</div>
	</div>
	<!-- FIM HEADER MENU -->

<? } ?>


<? if(in_array($_SESSION['user_tx_nivel'], ['Motorista', 'Ajudante'])){?>

	<!-- INICIO HEADER MENU -->
	<div class="page-header-menu">
		<div class="container-fluid">
			<!-- INICIO MEGA MENU -->
			<!-- DOC: Apply "hor-menu-light" class after the "hor-menu" class below to have a horizontal menu with white background -->
			<!-- DOC: Remove data-hover="dropdown" and data-close-others="true" attributes below to disable the dropdown opening on mouse hover -->
			<div class="hor-menu  ">
				<ul class="nav navbar-nav">

					<li class=" "><a href="<?= $CONTEX["path"] ?>/batida_ponto" class="nav-link ">Registrar Ponto</a></li>
					<li class=" "><a href="<?= $CONTEX["path"] ?>/cadastro_usuario" class="nav-link ">Usuário</a></li>
					<li class=" "><a href="<?= $CONTEX["path"] ?>/espelho_ponto" class="nav-link ">Espelho de Ponto</a></li>

				</ul>
			</div>
			<!-- FIM MEGA MENU -->
		</div>
	</div>
	<!-- FIM HEADER MENU -->

<? } ?>