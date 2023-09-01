			




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



									<!-- <li class="dropdown-submenu "> -->

										<!-- <a href="javascript:;" class="nav-link nav-toggle ">Geral<span class="arrow"></span></a> -->

										<!-- <ul class="dropdown-menu"> -->

											<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_cliente" class="nav-link ">Cliente</a></li> -->

											<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_conjunto" class="nav-link ">Conjunto</a></li> -->

											<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_finalidade" class="nav-link ">Finalidade</a></li> -->

											<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_tipo_imovel" class="nav-link ">Tipo Imóvel</a></li>

											<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_imovel" class="nav-link ">Imóvel</a></li> -->

											<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_construtor" class="nav-link ">Construtor</a></li>

											<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_corretor" class="nav-link ">Corretor</a></li>

											<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_banco" class="nav-link ">Banco</a></li> -->

										<!-- </ul> -->

									<!-- </li> -->


									<? if($_SESSION["user_tx_nivel"] == 'Administrador' || $_SESSION["user_tx_nivel"] == 'Master' ){ ?>
										<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_empresa" class="nav-link ">Empresa/Filial</a></li>
									<? } ?>		
									<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_motorista" class="nav-link ">Motorista</a></li>

									<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_parametro" class="nav-link ">Parâmetro</a></li>

									<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_motivo" class="nav-link ">Motivo</a></li>

									<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_feriado" class="nav-link ">Feriado</a></li>
									
									<? if($_SESSION["user_tx_nivel"] == 'Administrador' || $_SESSION["user_tx_nivel"] == 'Super Administrador' ){ ?>
										<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_usuario" class="nav-link ">Usuário</a></li>
										
										<li class="dropdown-submenu ">
	
											<a href="javascript:;" class="nav-link nav-toggle ">Positron<span class="arrow"></span></a>
	
											<ul class="dropdown-menu">
	
												<li class=" "><a href="<?=$CONTEX["path"]?>/cadastro_macro" class="nav-link ">Macro</a></li>
	
											</ul>
	
										</li>
									<? } ?>


									<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/ponto" class="nav-link ">Ponto</a></li> -->

								</ul>

							</li>





							<li class="menu-dropdown classic-menu-dropdown ">

								<a href="javascript:;"> Ponto<span class="arrow"></span></a>

								<ul class="dropdown-menu pull-left">

									<li class=" "><a href="<?=$CONTEX["path"]?>/carregar_ponto" class="nav-link ">Carregar Ponto</a></li>
									<li class=" "><a href="<?=$CONTEX["path"]?>/espelho_ponto" class="nav-link ">Espelho de Ponto</a></li>
									<li class=" "><a href="<?=$CONTEX["path"]?>/nao_conformidade" class="nav-link ">Não Conformidade</a></li>
									<li class=" "><a href="<?=$CONTEX["path"]?>/endosso" class="nav-link ">Endosso</a></li>
									<li class=" "><a href="<?=$CONTEX["path"]?>/nao_cadastrados" class="nav-link ">Matriculas Não Cadastradas</a></li>

								</ul>

							</li>							



							<li class="menu-dropdown classic-menu-dropdown ">

								<a href="javascript:;"> Documentos<span class="arrow"></span></a>

								<ul class="dropdown-menu pull-left">

									<li class=" "><a href="<?=$CONTEX["path"]?>/documentos/procuracao" target="_blank" class="nav-link ">Documento 1</a></li>

								</ul>

							</li>



							<li class="menu-dropdown classic-menu-dropdown ">

								<a href="javascript:;"> Relatórios<span class="arrow"></span></a>

								<ul class="dropdown-menu pull-left">

									<li class=" "><a href="<?=$CONTEX["path"]?>/relatorios/rel_clientes" target="_blank" class="nav-link ">Clientes</a></li>

									<li class=" "><a href="<?=$CONTEX["path"]?>/relatorios/rel_visitas" class="nav-link ">Visitas</a></li>

									<!-- <li class=" "><a href="<?=$CONTEX["path"]?>/relatorios/rel_imoveis" target="_blank" class="nav-link ">Imóveis</a></li> -->

								</ul>

							</li>



						</ul>

					</div>

					<!-- FIM MEGA MENU -->

				</div>

			</div>

			<!-- FIM HEADER MENU -->



