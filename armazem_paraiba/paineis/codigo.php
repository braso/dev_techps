<?php
if(is_int(strpos($diaPonto["inicioJornada"], "fa-warning"))){
						$totalMotorista["inicioSemRegistro"] += 1;
					}
					if(is_int(strpos($diaPonto["inicioRefeicao"], "fa-warning"))){
						$totalMotorista["inicioRefeicaoSemRegistro"] += 1;
					}
					if(is_int(strpos($diaPonto["fimRefeicao"], "fa-warning"))){
						$totalMotorista["fimRefeicaoSemRegistro"] += 1;
					}
					if(is_int(strpos($diaPonto["fimJornada"], "fa-warning"))){
						$totalMotorista["fimSemRegistro"] += 1;
					}
					if(is_int(strpos($diaPonto["diffRefeicao"], "fa-warning"))){
						$totalMotorista["refeicao1h"] += 1;
					}
					if(is_int(strpos($diaPonto["diffRefeicao"], "fa-info-circle")) && is_int(strpos($diaPonto["diffRefeicao"], "color:orange;"))){
						$totalMotorista["refeicao2h"] += 1;
					}
					if(is_int(strpos($diaPonto["diffEspera"], "fa-info-circle")) && is_int(strpos($diaPonto["diffEspera"], "color:red;"))){
						$totalMotorista["esperaAberto"] += 1;
					}
					if(is_int(strpos($diaPonto["diffDescanso"], "fa-info-circle")) && is_int(strpos($diaPonto["diffDescanso"], "color:red;"))){
						$totalMotorista["descansoAberto"] += 1;
					}
					if(is_int(strpos($diaPonto["diffRepouso"], "fa-info-circle")) && is_int(strpos($diaPonto["diffRepouso"], "color:red;"))){
						$totalMotorista["repousoAberto"] += 1;
					}
					if(is_int(strpos($diaPonto["diffJornada"], "fa-info-circle")) && is_int(strpos($diaPonto["diffJornada"], "color:red;"))){
						$totalMotorista["jornadaAberto"] += 1;
					}
					if(is_int(strpos($diaPonto["diffJornadaEfetiva"], "fa-warning")) && is_int(strpos($diaPonto["diffJornadaEfetiva"], "color:orange;"))){
						$totalMotorista["jornadaExedida"] += 1;
					}
					if(is_int(strpos($diaPonto["maximoDirecaoContinua"], "fa-warning")) && is_int(strpos($diaPonto["maximoDirecaoContinua"], "color:orange;"))){
						$totalMotorista["mdcDescanso"] += 1;
					}
					if(is_int(strpos($diaPonto["intersticio"], "fa-warning")) && is_int(strpos($diaPonto["intersticio"], "color:red;"))){
						$totalMotorista["intersticio"] += 1;
					}
				}
