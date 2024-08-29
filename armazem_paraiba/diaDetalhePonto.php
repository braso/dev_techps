<?php

    function diaDetalhePonto($matricula, $data): array{
        global $totalResumo, $contagemEspera;
        setlocale(LC_ALL, "pt_BR.utf8");

        $aRetorno = [
            "data" => data($data),
            "diaSemana" => strtoupper(substr(pegarDiaDaSemana($data), 0, 3)),
            "inicioJornada" => [],
            "inicioRefeicao" => [],
            "fimRefeicao" => [],
            "fimJornada" => [],
            "diffRefeicao" => "00:00",
            "diffEspera" => "00:00",
            "diffDescanso" => "00:00",
            "diffRepouso" => "00:00",
            "diffJornada" => "00:00",
            "jornadaPrevista" => "00:00",
            "diffJornadaEfetiva" => "00:00",
            "maximoDirecaoContinua" => "00:00",
            "intersticio" => "00:00",
            "he50" => "00:00",
            "he100" => "00:00",
            "adicionalNoturno" => "00:00",
            "esperaIndenizada" => "00:00",
            "diffSaldo" => "00:00"
        ];

        $tiposRegistrados = [];

        $tipos = [
            "inicioJornada" => "Inicio de Jornada",
            "fimJornada" => "Fim de Jornada",
            "inicioRefeicao" => "Inicio de Refeição",
            "fimRefeicao" => "Fim de Refeição",
            "inicioEspera" => "Inicio de Espera",
            "fimEspera" => "Fim de Espera",
            "inicioDescanso" => "Inicio de Descanso",
            "fimDescanso" => "Fim de Descanso",
            "inicioRepouso" => "Inicio de Repouso",
            "fimRepouso" => "Fim de Repouso",
            "inicioRepousoEmb" => "Inicio de Repouso Embarcado",
            "fimRepousoEmb" => "Fim de Repouso Embarcado"
        ];
        $registros = [];
        $extraFeriado = "";
        $stringFeriado = "";


        $aMotorista = mysqli_fetch_assoc(query(
            "SELECT * FROM entidade"
            ." LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id"
            ." LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id"
            ." WHERE enti_tx_status = 'ativo'"
                ." AND enti_tx_matricula = '".$matricula."'"
            ." LIMIT 1;"
        ));

        if(empty($aMotorista["enti_nb_parametro"])){
            $aMotorista["enti_nb_parametro"] = $aMotorista["empr_nb_parametro"];
        }

        if(!empty($aMotorista["enti_nb_empresa"]) && !empty($aMotorista["empr_nb_cidade"]) && !empty($aMotorista["cida_nb_id"]) && !empty($aMotorista["cida_tx_uf"])){
            $extraFeriado = 
                " AND ("
                    ."("
                        ."feri_nb_cidade = '".$aMotorista["cida_nb_id"]."'"
                        ." OR feri_tx_uf = '".$aMotorista["cida_tx_uf"]."'"
                    .")"
                    ." OR ("
                        ."("
                            ."feri_nb_cidade = ''"
                            ." OR feri_nb_cidade IS NULL"
                        .")"
                    ." AND ("
                        ."feri_tx_uf = ''"
                        ." OR feri_tx_uf IS NULL"
                        .")"
                    .")"
                .")";
        }

        $aParametro = carregar("parametro", $aMotorista["enti_nb_parametro"]);
        $alertaJorEfetiva = ((isset($aParametro["para_tx_acordo"]) && $aParametro["para_tx_acordo"] == "sim")? "12:00": "10:00");

        $queryFeriado = query(
            "SELECT feri_tx_nome FROM feriado 
                WHERE feri_tx_data LIKE '".$data."%' 
                    AND feri_tx_status = 'ativo' ".$extraFeriado
        );
        
        while ($row = carrega_array($queryFeriado)){
            $stringFeriado .= $row[0]."\n";
        }

        foreach(array_keys($tipos) as $tipo){
            $registros[$tipo] = [];
        }

        $tiposBd = mysqli_fetch_all(query(
            "SELECT DISTINCT macr_tx_codigoInterno, macr_tx_nome FROM macroponto"
        ), MYSQLI_ASSOC);

        $dictTipos = [];
        for($f = 0; $f < count($tiposBd); $f++){
            if(is_int(array_search($tiposBd[$f]["macr_tx_nome"], array_values($tipos), true))){
                $dictTipos[$tiposBd[$f]["macr_tx_codigoInterno"]] = array_keys($tipos)[array_search($tiposBd[$f]["macr_tx_nome"], array_values($tipos), true)];
            }
        }

        $tipos = $dictTipos;

        $pontosDia = [];
        $sql = query(
            "SELECT * FROM ponto 
                WHERE pont_tx_status = 'ativo' 
                    AND pont_tx_matricula = '".$matricula."' 
                    AND pont_tx_data LIKE '".$data."%' 
                ORDER BY pont_tx_data ASC"
        );
        while($ponto = carrega_array($sql)){
            $pontosDia[] = $ponto;
        }

        if(count($pontosDia) > 0){
            if($pontosDia[count($pontosDia)-1]["pont_tx_tipo"] != "2"){ //Se o último registro do dia != fim de jornada => há uma jornada aberta que seguiu para os dias seguintes

                $dataProxFim = mysqli_fetch_assoc(query(
                    "SELECT pont_tx_data FROM ponto
                        JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
                        WHERE pont_tx_status = 'ativo'
                            AND pont_tx_matricula = '".$matricula."'
                            AND pont_tx_data > '".$data." 23:59:59'
                            AND pont_tx_tipo = '2'
                        ORDER BY pont_tx_data ASC
                        LIMIT 1;"
                ));

                $diaSeguinte = (new DateTime($data))->add(DateInterval::createFromDateString("1 day"));
                $diaSeguinte = $diaSeguinte->format("Y-m-d");
                
                $pontosDiaSeguinte = [];
                if(!empty($dataProxFim["pont_tx_data"])){
                    $pontosDiaSeguinte = mysqli_fetch_all(
                        query(
                            "SELECT macroponto.macr_tx_nome, ponto.* FROM ponto 
                            JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
                            WHERE pont_tx_status = 'ativo' 
                                AND pont_tx_matricula = '".$matricula."'
                                AND pont_tx_data > '".$data." 23:59:59'
                                AND pont_tx_data <= '".$dataProxFim["pont_tx_data"]."'
                            ORDER BY pont_tx_data ASC;"
                        ),
                        MYSQLI_ASSOC
                    );
                }

                for($f = 0; $f < count($pontosDiaSeguinte); $f++){

                    //Se encontrar um fim de jornada, ignora os pontos que estiverem depois dele, pois corresponderão a uma próxima jornada.
                    if($pontosDiaSeguinte[$f]["pont_tx_tipo"] == "2"){
                        $pontosDiaSeguinte = array_slice($pontosDiaSeguinte, 0, $f+1);
                        break;
                    }

                    //Não pega os pontos do dia seguinte caso tenha um início de jornada sem ter fechado o anterior. Isso impede dos mesmos pontos ficarem repetidos em dois dias distintos.
                    if($pontosDiaSeguinte[$f]["pont_tx_tipo"] == "1"){
                        $pontosDiaSeguinte = [];
                        break;
                    }
                }
                $pontosDia = array_merge($pontosDia, $pontosDiaSeguinte);
            }
            
            while(count($pontosDia) > 0 && $pontosDia[0]["pont_tx_tipo"] != "1"){ //Se o 1° registro != início de jornada => É uma jornada que veio do dia anterior
                array_shift($pontosDia);
            }
        }

        foreach($pontosDia as $ponto){
            if(!isset($registros[$tipos[$ponto["pont_tx_tipo"]]])){
                $registros[$tipos[$ponto["pont_tx_tipo"]]] = [];
            }
            $registros[$tipos[$ponto["pont_tx_tipo"]]][] = $ponto["pont_tx_data"];
        }


        $registros["jornadaCompleto"]  = ordenar_horarios($registros["inicioJornada"], $registros["fimJornada"]);		/* $jornadaOrdenado */
        $registros["jornadaCompleto"]["totalIntervalo"] = is_string($registros["jornadaCompleto"]["totalIntervalo"])? 
            new DateTime($data." ".$registros["jornadaCompleto"]["totalIntervalo"]): 
            $registros["jornadaCompleto"]["totalIntervalo"]
        ;
        if(!empty($registros["inicioJornada"][0])){

            $diffJornada = date_diff(
                new DateTime(substr($registros["inicioJornada"][0], 0, strpos($registros["inicioJornada"][0], " "))." 00:00:00"), 
                $registros["jornadaCompleto"]["totalIntervalo"]
            );
            $diffJornada = formatToTime($diffJornada->days*24+$diffJornada->h, $diffJornada->i);

        }else{
            $diffJornada = "00:00";
        }

        if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], "refeicao"))){
            $registros["refeicaoCompleto"] = ordenar_horarios($registros["inicioRefeicao"], $registros["fimRefeicao"]);		/* $refeicaoOrdenada */
        }else{
            $registros["refeicaoCompleto"] = ordenar_horarios([], []);
        }

        if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], "espera"))){
            $registros["esperaCompleto"] = ordenar_horarios($registros["inicioEspera"],   $registros["fimEspera"], True);	/* $esperaOrdenada */
        }else{
            $registros["esperaCompleto"] = ordenar_horarios([], []);
        }

        if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], "descanso"))){
            $registros["descansoCompleto"] = ordenar_horarios($registros["inicioDescanso"], $registros["fimDescanso"]);		/* $descansoOrdenado */
        }else{
            $registros["descansoCompleto"] = ordenar_horarios([], []);
        }

        if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], "repouso"))){
            $registros["repousoCompleto"]  = ordenar_horarios($registros["inicioRepouso"], $registros["fimRepouso"]);		/* $repousoOrdenado */
        }else{
            $registros["repousoCompleto"] = ordenar_horarios([], []);
        }

        
        //REPOUSO POR ESPERA{
            if(isset($registros["esperaCompleto"]["paresParaRepouso"]) && !empty($registros["esperaCompleto"]["paresParaRepouso"])){
                $paresParaRepouso = $registros["esperaCompleto"]["paresParaRepouso"];
                // unset($registros["esperaCompleto"]["paresParaRepouso"]);
                for ($i = 0; $i < count($paresParaRepouso); $i++){
                    $registros["repousoPorEspera"]["inicioRepouso"][] 	= $data." ".$paresParaRepouso[$i]["inicio"].":00";	/*$aDataHorainicioRepouso*/
                    $registros["repousoPorEspera"]["fimRepouso"][] 		= $data." ".$paresParaRepouso[$i]["fim"].":00";		/*$aDataHorafimRepouso*/
                }
                $registros["repousoPorEspera"]["repousoCompleto"] = ordenar_horarios($registros["repousoPorEspera"]["inicioRepouso"], $registros["repousoPorEspera"]["fimRepouso"],false,true);
            }else{
                $registros["repousoPorEspera"]["repousoCompleto"] = ordenar_horarios([], []);
            }
        //}

        foreach(["refeicao", "descanso", "repouso", "espera"] as $campo){
            $totalIntervalo = (is_string($registros[$campo."Completo"]["totalIntervalo"]))?
                new DateTime($data." ".$registros[$campo."Completo"]["totalIntervalo"]):
                $registros[$campo."Completo"]["totalIntervalo"]
            ;

            
            $totalIntervalo = date_diff(new DateTime($data." 00:00"), $totalIntervalo);
            if($totalIntervalo->s > 0){
                $totalIntervalo->i++;
                $totalIntervalo->s = 0;
                if($totalIntervalo->i >=60){
                    $totalIntervalo->h++;
                    $totalIntervalo->i -= 60;
                }
            }

            $totalIntervalo = formatToTime($totalIntervalo->days*24+$totalIntervalo->h, $totalIntervalo->i);

            $registros[$campo."Completo"]["totalIntervalo"] = $totalIntervalo;
        }
        $totalIntervalo = (is_string($registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]))?
            new DateTime($data." ".$registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]):
            $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]
        ;
        
        $totalIntervalo = date_diff(new DateTime($data." 00:00"), $totalIntervalo);
        $totalIntervalo = formatToTime($totalIntervalo->days*24+$totalIntervalo->h, $totalIntervalo->i);

        $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"] = $totalIntervalo;


        $registros["repousoCompleto"]["totalIntervalo"] = operarHorarios([$registros["repousoCompleto"]["totalIntervalo"], $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]], "+");
        $registros["repousoCompleto"]["icone"] .= $registros["repousoPorEspera"]["repousoCompleto"]["icone"];
        
        $aRetorno["diffRefeicao"] = $registros["refeicaoCompleto"]["icone"].$registros["refeicaoCompleto"]["totalIntervalo"];
        $aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
        $aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
        $aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];

        $contagemEspera += count($registros["esperaCompleto"]["pares"]);

        $aAbono = carrega_array(
            query(
                "SELECT * FROM abono, motivo, user 
                    WHERE abon_tx_status = 'ativo' 
                        AND abon_nb_userCadastro = user_nb_id 
                        AND abon_tx_matricula = '".$matricula."' 
                        AND abon_tx_data = '".$data."' 
                        AND abon_nb_motivo = moti_nb_id
                    ORDER BY abon_nb_id DESC 
                    LIMIT 1"
            )
        );
        
        $aRetorno["diffJornada"] = $registros["jornadaCompleto"]["icone"].$diffJornada;

        //JORNADA PREVISTA{
            $jornadas = [
                "sabado" => $aMotorista["enti_tx_jornadaSabado"],
                "semanal"=> $aMotorista["enti_tx_jornadaSemanal"],
                "feriado"=> ($stringFeriado != ""? True: null)
            ];

            [$jornadaPrevistaOriginal, $jornadaPrevista] = calcJorPre($data, $jornadas, ($aAbono["abon_tx_abono"]?? null));

            $aRetorno["jornadaPrevista"] = $jornadaPrevista;
            if($jornadas["feriado"] == True){
                $iconeFeriado =  " <a><i style='color:green;' title='".$stringFeriado."' class='fa fa-info-circle'></i></a>";
                $aRetorno["diaSemana"] .= $iconeFeriado;
            }
        //}

        //JORNADA EFETIVA{

            if(is_string($registros["jornadaCompleto"]["totalIntervalo"])){
                $jornadaIntervalo = new DateTime($data." 00:00");
            }else{
                $jornadaIntervalo = $registros["jornadaCompleto"]["totalIntervalo"];
            }

            $totalNaoJornada = [
                $registros["refeicaoCompleto"]["totalIntervalo"]
            ];

            //Ignorar intervalos que tenham sido marcados para ignorar no parâmetro{
                if(!empty($aMotorista["enti_nb_parametro"]) && !empty($aParametro["para_tx_ignorarCampos"])){
                    $campos = ["espera", "descanso", "repouso"/*, "repousoEmbarcado"*/];
                    foreach($campos as $campo){
                        if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], $campo))){
                            $totalNaoJornada[] = $registros[$campo."Completo"]["totalIntervalo"];
                        }
                    }
                }else{
                    $totalNaoJornada = [
                        $registros["refeicaoCompleto"]["totalIntervalo"],
                        $registros["esperaCompleto"]["totalIntervalo"],
                        $registros["descansoCompleto"]["totalIntervalo"],
                        $registros["repousoCompleto"]["totalIntervalo"]
                    ];
                }
            //}
            
            //SOMATORIO DE TODAS AS ESPERAS

            if(!empty($registros["inicioJornada"][0])){
                $value = new DateTime($data." 00:00");
                for($f = 0; $f < count($totalNaoJornada); $f++){
                    $times = explode(":", $totalNaoJornada[$f]);
                    $totalNaoJornada[$f] = new DateInterval("P".floor($times[0]/24)."DT".($times[0]%24)."H".$times[1]."M");
                    $value->add($totalNaoJornada[$f]);
                }
                $totalNaoJornada = $value;
            }else{
                $totalNaoJornada = new DateTime($data." 00:00");
            }

            $jornadaEfetiva = $totalNaoJornada->diff($jornadaIntervalo);
            $diffJornadaEfetiva = formatToTime($jornadaEfetiva->days*24+$jornadaEfetiva->h, $jornadaEfetiva->i);
            if($jornadaEfetiva->days > 0){
                $jornadaEfetiva = (new DateTime($data." 00:00"))->add($jornadaEfetiva);
            }else{
                $jornadaEfetiva = DateTime::createFromFormat("Y-m-d H:i", $data." ".$jornadaEfetiva->format("%H:%I"));
            }

            $aRetorno["diffJornadaEfetiva"] = verificaLimiteTempo($diffJornadaEfetiva, $alertaJorEfetiva);
        //}

        //CÁLCULO DE INSTERTÍCIO{
            if(isset($registros["inicioJornada"]) && count($registros["inicioJornada"]) > 0){

                $ultimoFimJornada = carrega_array(query(
                    "SELECT pont_tx_data FROM ponto
                        WHERE pont_tx_status = 'ativo'
                            AND pont_tx_tipo = 2
                            AND pont_tx_matricula = '".$matricula."'
                            AND pont_tx_data < '".$registros["inicioJornada"][0]."'
                        ORDER BY pont_tx_data DESC
                        LIMIT 1"
                ));
                if(!empty($ultimoFimJornada)){
                    $ultimoFimJornada = DateTime::createFromFormat("Y-m-d H:i:s", $ultimoFimJornada[0]);
                    
                    $intersticioDiario = (new DateTime($registros["inicioJornada"][0]))->diff($ultimoFimJornada);
                    
                    // Obter a diferença total em minutos
                    $minInterDiario = (
                        $intersticioDiario->days*60*24+
                        $intersticioDiario->h*60+
                        $intersticioDiario->i
                    );

                    // Calcular as horas e minutos

                    $intersticio = sprintf("%02d:%02d", floor($minInterDiario / 60), $minInterDiario % 60); // Formatar a string no formato H:I

                    $totalIntersticio = somarHorarios(
                        [$intersticio, $totalNaoJornada->format("H:i")]
                    );

                    $icone = "";
                    if($totalIntersticio < sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00")){ // < 11 horas
                        $restante = operarHorarios([sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00"), $totalIntersticio], "-");
                        $icone .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado, faltaram ".$restante."' class='fa fa-warning'></i></a>";
                    }
                    if($minInterDiario < (8*60)){ // < 8 horas
                        $icone .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
                    }

                    $aRetorno["intersticio"] = $icone.$totalIntersticio;
                }else{
                    $aRetorno["intersticio"] = "00:00";
                }
            }
        //}

        //CALCULO SALDO{
            $saldoDiario = date_diff(
                DateTime::createFromFormat("Y-m-d H:i", $data." ".$jornadaPrevista),
                $jornadaEfetiva
            );
            
            $saldoDiario = ($saldoDiario->invert? "-": "").sprintf("%02d:%02d", abs($saldoDiario->days*24+$saldoDiario->h), abs($saldoDiario->i));
            $aRetorno["diffSaldo"] = $saldoDiario;
        //}

        //CALCULO ESPERA INDENIZADA{
            $intervaloEsp = somarHorarios([$registros["esperaCompleto"]["totalIntervalo"], $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]]);
            $indenizarEspera = ($intervaloEsp >= "02:00");

            if($saldoDiario[0] == "-"){
                if($intervaloEsp > substr($saldoDiario, 1)){
                    $transferir = substr($saldoDiario, 1);
                }else{
                    $transferir = $intervaloEsp;
                }	
                $saldoDiario = operarHorarios([$saldoDiario, $transferir], "+");
                $aRetorno["diffSaldo"] = $saldoDiario;
                $intervaloEsp = operarHorarios([$intervaloEsp, $transferir], "-");
            }

            if($indenizarEspera){
                $esperaIndenizada = $intervaloEsp;
            }else{
                $esperaIndenizada = "00:00";
            }

            $aRetorno["esperaIndenizada"] = $esperaIndenizada;
        //}

        //INICIO ADICIONAL NOTURNO
            // $jornadaNoturno = $registros["jornadaCompleto"]["totalIntervaloAdicionalNot"];
            // $refeicaoNoturno = $registros["refeicaoCompleto"]["totalIntervaloAdicionalNot"];
            // $esperaNoturno = $registros["esperaCompleto"]["totalIntervaloAdicionalNot"];
            // $descansoNoturno = $registros["descansoCompleto"]["totalIntervaloAdicionalNot"];
            // $repousoNoturno = $registros["repousoCompleto"]["totalIntervaloAdicionalNot"];

            $intervalosNoturnos = somarHorarios([
                $registros["refeicaoCompleto"]["totalIntervaloAdicionalNot"], 
                $registros["esperaCompleto"]["totalIntervaloAdicionalNot"], 
                $registros["descansoCompleto"]["totalIntervaloAdicionalNot"], 
                $registros["repousoCompleto"]["totalIntervaloAdicionalNot"]
            ]);

            $aRetorno["adicionalNoturno"] = operarHorarios([$registros["jornadaCompleto"]["totalIntervaloAdicionalNot"], $intervalosNoturnos], "-");
        //FIM ADICIONAL NOTURNO
        
        //TOLERÂNCIA{
            $tolerancia = carrega_array(query(
                "SELECT parametro.para_tx_tolerancia FROM entidade 
                    JOIN parametro ON enti_nb_parametro = para_nb_id 
                    WHERE enti_nb_parametro = ".$aMotorista["enti_nb_parametro"]."
                    LIMIT 1;"
            ))[0];
            $tolerancia = intval($tolerancia);
        

            $saldo = explode(":", $aRetorno["diffSaldo"]);
            $saldo = intval($saldo[0])*60 + ($saldo[0][0] == "-"? -1: 1)*intval($saldo[1]);
            
            if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
                $aRetorno["diffSaldo"] = "00:00";
                $saldo = 0;
            }
        //}

        //HORAS EXTRAS{
            if($aRetorno["diffSaldo"][0] != "-"){ 	//Se o saldo for positivo

                if($jornadas["feriado"] == True || (new DateTime($data." 00:00:00"))->format("D") == "Sun"){
                    $aRetorno["he100"] = $aRetorno["diffSaldo"];
                    $aRetorno["he50"] = "00:00";
                }else{
                    if(	(isset($aParametro["para_tx_HorasEXExcedente"]) && !empty($aParametro["para_tx_HorasEXExcedente"])) &&
                        $aParametro["para_tx_HorasEXExcedente"] != "00:00" && 
                        $aRetorno["diffSaldo"] >= $aParametro["para_tx_HorasEXExcedente"]
                    ){// saldo diário >= limite de horas extras 100%
                        $aRetorno["he100"] = operarHorarios([$aRetorno["diffSaldo"], $aParametro["para_tx_HorasEXExcedente"]], "-");
                    }else{
                        $aRetorno["he100"] = "00:00";
                    }
                    $aRetorno["he50"] = operarHorarios([$aRetorno["diffSaldo"], $aRetorno["he100"]], "-");
                }
            }
        //}

        

        //MÁXIMA DIREÇÃO CONTÍNUA{
            // $aRetorno["maximoDirecaoContinua"] = verificarAlertaMDC(
            // 	maxDirecaoContinua($tiposRegistrados),
            // 	$registros["descansoCompleto"]["totalIntervalo"]
            // );

            $intervalos = [];
            $interAtivo = null;
            foreach($pontosDia as $ponto){
                if(empty($interAtivo)){
                    $interAtivo = new DateTime($ponto["pont_tx_data"]);
                    continue;
                }
                
                $intervalos[] = [
                    !($tipos[$ponto["pont_tx_tipo"]] == "inicioJornada" || (is_int(strpos($tipos[$ponto["pont_tx_tipo"]], "fim")) && $tipos[$ponto["pont_tx_tipo"]] != "fimJornada")), 
                    date_diff($interAtivo, new DateTime($ponto["pont_tx_data"]))
                ];
                $interAtivo = new DateTime($ponto["pont_tx_data"]);
            }
            
            $aRetorno["maximoDirecaoContinua"] = verificarAlertaMDC($intervalos);
        //}

        //JORNADA MÍNIMA
            $dtJornada = new DateTime($data." ".$jornadaEfetiva->format("H:i"));
            $dtJornadaMinima = new DateTime($data." 06:00");

            $fezJorMinima = ($dtJornada >= $dtJornadaMinima);
        //FIM JORNADA MÍNIMA

        //ALERTAS{
            if((!isset($registros["inicioJornada"][0]) || $registros["inicioJornada"][0] == "") && $aRetorno["jornadaPrevista"] != "00:00"){
                $aRetorno["inicioJornada"][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
            }
            if($fezJorMinima || count($registros["inicioJornada"]) > 0){
                if(!isset($registros["fimJornada"][0]) || $registros["fimJornada"][0] == ""){
                    $aRetorno["fimJornada"][] 	  = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
                }

                //01:00 DE REFEICAO{
                    $maiorRefeicao = "00:00";
                    if(count($registros["refeicaoCompleto"]["pares"]) > 0){
                        for ($i = 0; $i < count($registros["refeicaoCompleto"]["pares"]); $i++){
                            if(!empty($registros["refeicaoCompleto"]["pares"][$i]["intervalo"]) && $maiorRefeicao < $registros["refeicaoCompleto"]["pares"][$i]["intervalo"]){
                                $maiorRefeicao = $registros["refeicaoCompleto"]["pares"][$i]["intervalo"];
                            }
                        }
                    }

                    $avisoRefeicao = "";
                    if($maiorRefeicao > "02:00"){
                        $avisoRefeicao = "<a><i style='color:orange;' title='Refeição com tempo máximo de 02:00h não respeitado.' class='fa fa-info-circle'></i></a>";
                    }elseif($dtJornada > $dtJornadaMinima && $maiorRefeicao < '01:00'){
                        $avisoRefeicao = "<a><i style='color:red;' title='Refeição ininterrupta maior do que 01:00h não respeitado.' class='fa fa-warning'></i></a>";
                    }
                //}

                if((!isset($registros["inicioRefeicao"][0]) || empty($aRetorno["inicioRefeicao"][0])) && $jornadaEfetiva->format("H:i") > "06:00"){
                    $aRetorno["inicioRefeicao"][] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>";
                }else{
                    $aRetorno["inicioRefeicao"][] = $avisoRefeicao;
                }

                if((!isset($registros["fimRefeicao"][0]) || empty($aRetorno["fimRefeicao"][0])) && ($jornadaEfetiva->format("H:i") > "06:00")){
                    $aRetorno["fimRefeicao"][] 	  = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>";
                }else{
                    $aRetorno["fimRefeicao"][] = $avisoRefeicao;
                }
                if(!empty($avisoRefeicao)){
                    $aRetorno["diffRefeicao"] = $avisoRefeicao." ".$aRetorno["diffRefeicao"];
                }
            }
            if(is_array($aAbono) && count($aAbono) > 0){
                $warning = 
                    "<a><i "
                        ."style='color:green;' "
                        ."title='"
                            ."Jornada Original: ".str_pad($jornadaPrevistaOriginal, 2, "0", STR_PAD_LEFT).":00\n"
                            ."Abono: ".$aAbono["abon_tx_abono"]."\n"
                            ."Motivo: ".$aAbono["moti_tx_nome"]."\n"
                            ."Justificativa: ".$aAbono["abon_tx_descricao"]."\n\n"
                            ."Registro efetuado por ".$aAbono["user_tx_login"]." em ".data($aAbono["abon_tx_dataCadastro"], 1)."' "
                        ."class='fa fa-info-circle'></i>"
                    ."</a>&nbsp;"
                ;
                $aRetorno["jornadaPrevista"] = $warning.$aRetorno["jornadaPrevista"];
            }
        //}

        foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $campo){
            if(count($registros[$campo]) > 0 && !empty($registros[$campo][0])){
                $aRetorno[$campo] = $registros[$campo];
            }
        }

        if(count($registros["inicioEspera"]) > 0 && count($registros["fimEspera"]) > 0){
            $aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
        }
        if(count($registros["inicioDescanso"]) > 0 && count($registros["fimDescanso"]) > 0){
            $aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
        }
        if(count($registros["inicioRepouso"]) > 0 && count($registros["fimRepouso"]) > 0){
            $aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];
        }
        
        //LEGENDAS{
            if(!empty($registros["inicioJornada"])){
                $datas = 
                    "('".implode("', '", $registros["inicioJornada"])."'"
                    .(!empty($registros["inicioRefeicao"])? ", '".implode("', '", $registros["inicioRefeicao"])."'": "")
                    .(!empty($registros["fimRefeicao"])? ", '".implode("', '", $registros["fimRefeicao"])."'": "")
                    .(!empty($registros["fimJornada"])? ", '".implode("', '", $registros["fimJornada"])."')": ")")
                ;

                $legendas = mysqli_fetch_all(
                    query(
                        "SELECT * FROM ponto
                            JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
                            JOIN user ON ponto.pont_nb_user = user.user_nb_id
                            LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
                            WHERE ponto.pont_nb_motivo IS NOT NULL 
                                AND pont_tx_status = 'ativo'
                                AND pont_tx_data IN ".$datas." 
                                AND pont_tx_matricula = '".$matricula."'"
                    ),
                    MYSQLI_ASSOC
                );
        
                $tipos = [
                    "I" => 0, 
                    "P" => 0, 
                    "T" => 0, 
                    "DSR" => 0
                ];
                $contagens = [
                    "inicioJornada" => $tipos,
                    "fimJornada" => $tipos,
                    "inicioRefeicao" => $tipos,
                    "fimRefeicao" => $tipos,
                ];
                
                foreach ($legendas as $value){
                    $legenda = $value["moti_tx_legenda"];
                
                    switch ($value["macr_tx_nome"]){
                        case "Inicio de Jornada":
                            $acao = "inicioJornada";
                            break;
                        case "Fim de Jornada":
                            $acao = "fimJornada";
                            break;
                        case "Inicio de Refeição":
                            $acao = "inicioRefeicao";
                            break;
                        case "Fim de Refeição":
                            $acao = "fimRefeicao";
                            break;
                        default:
                            $acao = "";
                    }
                    if($acao != "" && !empty($legenda) && array_key_exists($legenda, $contagens[$acao])){
                        $contagens[$acao][$legenda]++;
                    }
                }
                
                foreach ($contagens as $acao => $tipos){
                    foreach ($tipos as $tipo => $quantidade){
                        if($quantidade > 0){
                            $aRetorno[$acao][] = "<strong>$tipo</strong>";
                        }
                    }
                }
            }
        //}

        //Aviso de registro inativado{
            $ajuste = mysqli_fetch_all(
                query(
                    "SELECT pont_tx_data, macr_tx_nome, pont_tx_status FROM ponto
                        JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
                        JOIN user ON ponto.pont_nb_user = user.user_nb_id
                        LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
                        WHERE pont_tx_data LIKE '%".$data."%' 
                            AND pont_tx_matricula = '".$matricula."'"
                ),
                MYSQLI_ASSOC
            );

            $possuiAjustes = [
                "jornada"  => ["inicio" => False, "fim" => False], 	//$quantidade_inicioJ e $quantidade_fimJ
                "refeicao" => ["inicio" => False, "fim" => False],	//$quantidade_inicioR e $quantidade_fimR
            ];

            foreach ($ajuste as $valor){
                if($data == substr($valor["pont_tx_data"], 0, 10)){
                    if($valor["pont_tx_status"] == "inativo"){
                        $possuiAjustes["jornada"]["inicio"]  = $possuiAjustes["jornada"]["inicio"] 	|| $valor["macr_tx_nome"] == "Inicio de Jornada";
                        $possuiAjustes["jornada"]["fim"] 	 = $possuiAjustes["jornada"]["fim"] 	|| $valor["macr_tx_nome"] == "Fim de Jornada";
                        $possuiAjustes["refeicao"]["inicio"] = $possuiAjustes["refeicao"]["inicio"] || $valor["macr_tx_nome"] == "Inicio de Refeição";
                        $possuiAjustes["refeicao"]["fim"] 	 = $possuiAjustes["refeicao"]["fim"]	|| $valor["macr_tx_nome"] == "Fim de Refeição";
                    }
                }
            }
            if($possuiAjustes["jornada"]["inicio"]){
                $aRetorno["inicioJornada"][] = "*";
            }
            if($possuiAjustes["jornada"]["fim"]){
                $aRetorno["fimJornada"][] = "*";
            }
            if($possuiAjustes["refeicao"]["inicio"]){
                $aRetorno["inicioRefeicao"][] = "*";
            }
            if($possuiAjustes["refeicao"]["fim"]){
                $aRetorno["fimRefeicao"][] = "*";
            }
        //}

        //SOMANDO TOTAIS{
            $campos = [
                "diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", 
                "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", 
                "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo"
            ];
            foreach($campos as $campo){
                if(empty($totalResumo[$campo])){
                    $totalResumo[$campo] = "00:00";
                }
                $totalResumo[$campo] = operarHorarios(
                    [$totalResumo[$campo], strip_tags(str_replace(["&nbsp;", " "], "", $aRetorno[$campo]))], 
                    "+"
                );
            }
            unset($campos);
        //}

        if($saldo > 0){
            $aRetorno["diffSaldo"] = "<b>".$aRetorno["diffSaldo"]."</b>";
        }

        $ultimoFimJornada = [];
        if(count($aRetorno["fimJornada"]) > 0){
            for($f = count($aRetorno["fimJornada"])-1; $f >= 0; $f--){
                if(preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/", $aRetorno["fimJornada"][$f])){
                    $ultimoFimJornada = [
                        "key" => $f, 
                        "value" => $aRetorno["fimJornada"][$f]
                    ];
                    break;
                }
            }
        }else{
            $ultimoFimJornada = null;
        }

        if(!empty($ultimoFimJornada)){
            $dataDia = DateTime::createFromFormat("d/m/Y H:i:s", $aRetorno["data"]." 00:00:00");
            $dataFim = DateTime::createFromFormat("Y-m-d H:i:s", $ultimoFimJornada["value"]);
            $qttDias = date_diff($dataDia, $dataFim);
            if(!is_bool($qttDias)){
                $qttDias = intval($qttDias->format("%d"));
                if($qttDias > 0){
                    array_splice($aRetorno["fimJornada"], $ultimoFimJornada["key"]+1, 0, "D+".$qttDias);
                }
            }
        }

        //Converter array em string{
            $legendas = mysqli_fetch_all(query(
                "SELECT DISTINCT moti_tx_legenda FROM motivo 
                    WHERE moti_tx_legenda IS NOT NULL;"
                ), 
                MYSQLI_ASSOC
            );

            foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
                if(count($aRetorno[$tipo]) > 0){
                    for($f = 0; $f < count($aRetorno[$tipo]); $f++){
                        //Formatar datas para hora e minutos
                        if(strlen($aRetorno[$tipo][$f]) > 3 && strpos($aRetorno[$tipo][$f], ":", strlen($aRetorno[$tipo][$f])-3) !== false){
                            $aRetorno[$tipo][$f] = date("H:i", strtotime($aRetorno[$tipo][$f]));
                        }
                    }
                    $aRetorno[$tipo] = implode("<br>", $aRetorno[$tipo]);
                    foreach($legendas as $legenda){
                        $aRetorno[$tipo] = str_replace("<br><strong>".$legenda["moti_tx_legenda"]."</strong>", " <strong>".$legenda["moti_tx_legenda"]."</strong>", $aRetorno[$tipo]);
                    }
                    $aRetorno[$tipo] = str_replace("<br>D+", " D+", $aRetorno[$tipo]);
                    $aRetorno[$tipo] = str_replace("<br>*", " *", $aRetorno[$tipo]);
                }else{
                    $aRetorno[$tipo] = "";
                }
            }
        //}
        
        return $aRetorno;
    }