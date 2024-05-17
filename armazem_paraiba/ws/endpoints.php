<?php 
    require_once "../load_env.php";
    require_once "lib.php";

    function make_login(){
        $msg = '';

        //Check mandatory fields{
            if(empty($_POST["username"])){
                $msg = 'Please Enter Login Details';
            }elseif(empty($_POST["password"])){
                $msg = 'Please Enter Password Details';
            }
            if(!empty($msg)){
                header('HTTP/1.0 400 Bad Request');
                echo $msg;
                exit;
            }
        //}

        //Check if user exists{
            $data = get_data("SELECT user_tx_senha, user_nb_id FROM user WHERE user_tx_login = '".$_POST["username"]."'");

            if(empty($data)){
                $msg = 'Wrong Username Address';
            }
            if($data[0]['user_tx_senha'] !== md5($_POST['password'])){
                $msg = 'Wrong Password';
            }
            if(!empty($msg)){
                header('HTTP/1.0 400 Bad Request');
                echo $msg;
                exit;
            }
        //}

        $data = $data[0];

        $token = makeToken((object)$data,$_ENV["APP_KEY"]);
        
        echo "{ \"id\": ".$data['user_nb_id'].", \"token\": \"".$token."\"}";
        exit;
    }

    function get_user($userid = null){

        $decoded = validate_token($_ENV["APP_KEY"]);
        if(empty($userid)){
            $userid = $decoded->data->user_id;
        }
        
        $query = 
            "SELECT 
                distinct user_nb_id                                     as id, 
                coalesce(user_tx_nome,enti_tx_nome)                     as name,
                coalesce(user_tx_matricula,enti_tx_matricula)           as registration,
                coalesce(e.empr_tx_nome)                                as company,
                coalesce(user_tx_cpf,enti.enti_tx_cpf)  				as cpf,
                coalesce(enti.enti_tx_carteira,user_tx_rg)              as rg,
                enti.enti_tx_cnhRegistro                                as cnh,
                user_tx_email                                           as email,
                user_tx_nivel                                           as role,
                user_tx_foto                                            as avatar,
                user_tx_nascimento                                      as birthdate 
            from user u
                left join entidade enti on u.user_nb_entidade = enti.enti_nb_id 
                    and u.user_nb_entidade is not null
                left join empresa e on enti_nb_empresa = e.empr_nb_id 
                    and u.user_nb_entidade is not null
                where u.user_nb_id = ".$userid
        ;

        $data = get_data($query);
        if(empty($data)){
            header('HTTP/1.0 400 Bad Request');
            echo "User does not exists";
            exit;
        }

        echo json_encode($data);
        exit;
    }

    function get_journeys($userid = null){
        $decoded = validate_token($_ENV["APP_KEY"]);
        
        if(empty($userid)){
            $userid = $decoded->data->user_id;
        }

        $data = get_user_journeys($userid);
        if(empty($data)){
            header('HTTP/1.0 400 Bad Request');
            echo "Could not find journeys";
        }

        echo json_encode($data);
        exit;
    }

    function refresh(){
        $decoded = validate_token($_ENV["APP_KEY"]);

        $token = makeToken($decoded->data, $_ENV["APP_KEY"]);
        echo $token;
        
        exit;
    }

    function begin_journey(){
        $decoded = validate_token($_ENV["APP_KEY"]);
		
        if(empty($_POST)){
            $putfp = fopen('php://input', 'r');
            $putdata = '';
            
            while($data = fread($putfp, 1024)){
                $putdata .= $data;
            }

            fclose($putfp);
            $requestdata = json_decode($putdata);
            
            $_POST = get_object_vars($requestdata);
        }

        if(!empty($_POST["id"])){
            $_POST["userID"] = $_POST["id"];
        }

        if(empty($_POST["type"])){
            $_POST["type"] = "journey";
        }
        
        //Check mandatory fields{
            if(empty($_POST["userID"]) || empty($_POST["startDateTime"])){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad Request: missing values (userID, startDateTime)";
                exit;
            }
            if(!in_array($_POST["type"], ["journey", "break"])){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad Request: type value";
                exit;
            }
            if(empty($_POST['breakType'])){
                $_POST['breakType'] = 'jornada';
            }
        //}

        //Check if user has entity{
            $entity = 
                "SELECT * from entidade e
                    join user u on u.user_nb_entidade = e.enti_nb_id
                    where u.user_nb_id = ".$_POST["userID"]
            ;
            $entity = get_data($entity);
            if(empty($entity)){
                header('HTTP/1.0 400 Bad Request');
                echo "User Id does not have entity";
                exit;
            }
            $entity = $entity[0];
        //}
		

        //Check if break type (macro) exists{
            $macro = 
                "SELECT * from macroponto
                    where lower(macr_tx_nome) LIKE '"."in%cio%".strtolower($_POST["breakType"])."'";
            ;
            $macro = get_data($macro);
            if(empty($macro)){
                header('HTTP/1.0 400 Bad Request');
                echo "Break type not found";
                exit;
            }
            $macro = $macro[0];
        //}
		

        //Check if startDateTime is correctly formatted{
            if(!preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $_POST['startDateTime'])){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad date formatting.";
                exit;
            }
        //}

        
        $macroid = $macro["macr_tx_codigoInterno"];
        $outerid = $macro["macr_tx_codigoExterno"];
        $macroName = explode(" ", $macro["macr_tx_nome"]);
        unset($macroName[0]);
        unset($macroName[1]);
        $macroName = implode(" ", $macroName);

        if($_POST["type"] == "break"){
            //Check mandatory fields
            if(empty($_POST["journeyID"]) || empty($_POST["breakType"])){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad Request missing values (journeyID, breakType)";
                exit;
            }

            //Check if there is an open journey with this id{
                $query = 
                    "SELECT * FROM ponto
                        JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                        WHERE pont_tx_status = 'ativo'
                            AND pont_nb_user = ".$_POST['userID']."
                            AND pont_nb_id >= ".$_POST['journeyID']."
                            AND pont_tx_data < '".$_POST['startDateTime']."'
                            AND lower(macr_tx_nome) LIKE '%jornada%'
                        ORDER BY pont_tx_data DESC
                        LIMIT 1;"
                ;
                $openJourney = get_data($query);
                $openJourney = !empty($openJourney)? $openJourney[0]: $openJourney;
                if(empty($openJourney) || is_numeric(strpos(strtolower($openJourney['macr_tx_nome']), 'fim'))){
                    header('HTTP/1.0 400 Bad Request');
                    echo "Open journey not found";
                    exit;
                }
            //}

            //Check if there is an open break before trying to insert another opening{
                $lastBreakOpening = 
                    "SELECT *, (macr_tx_nome like '%inicio%') as open_break FROM ponto
                        JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                        WHERE pont_tx_status = 'ativo'
                            AND pont_tx_data < '".$_POST['startDateTime']."'
                            AND lower(macr_tx_nome) != 'inicio de jornada'
                        ORDER BY pont_tx_data DESC
                        LIMIT 1;"
                ;

                $lastBreakOpening = get_data($lastBreakOpening)[0];
                if(empty($lastBreakOpening) || $lastBreakOpening['open_break']){
                    header('HTTP/1.0 400 Bad Request');
                    echo "Breakpoint open without closing previous one.";
                    exit;
                }
            //}
        }elseif($_POST["type"] == "journey"){

            $lastJourney = get_data(
                "SELECT *, (lower(macr_tx_nome) like '%inicio%') as open_journey FROM ponto
                    JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                    WHERE pont_tx_status = 'ativo'
                        AND pont_tx_matricula = '".$entity["enti_tx_matricula"]."'
                        AND macr_tx_nome LIKE '%jornada%'
                        AND pont_tx_data < '".$_POST["startDateTime"]."'
                    ORDER BY pont_tx_data DESC
                    LIMIT 1;"
            );

            if($lastJourney[0]['open_journey']){
                header('HTTP/1.0 400 Bad Request');
                echo "Journey open without closing previous one.";
                exit;
            }
        }else{
            header('HTTP/1.0 400 Bad Request');
            echo "Type not found";
            exit;
        }

        $ponto = [
            "pont_nb_user" => $decoded->data->user_id,
            "pont_tx_dataCadastro" => date("Y-m-d H:i:s"),
            "pont_tx_matricula" => $entity["enti_tx_matricula"],
            "pont_tx_data" => $_POST["startDateTime"],
            "pont_tx_tipo" => $macroid,
            "pont_tx_tipoOriginal" => $outerid,
            "pont_nb_motivo" => null,
            "pont_tx_descricao" => null,
            "pont_tx_latitude" => (!empty($_POST["latitude"])? $_POST["latitude"]: null),
            "pont_tx_longitude" => (!empty($_POST["longitude"])? $_POST["longitude"]: null),
            "pont_tx_status" => 'ativo',
            "pont_tx_justificativa" => null
        ];

        $query =
            "INSERT INTO ponto (
                ".implode(", ", array_keys($ponto))."
            ) VALUES (
                :".implode(", :", array_keys($ponto))."
            )"
        ;
        $result = insert_data($query,$ponto);
		
		if($_POST["type"] == "break"){
			$result = "Break begin registered successfully.";
		}
		
        echo $result;
        exit;
    }

    function finish_journey(){

        $decoded = validate_token($_ENV["APP_KEY"]);
        $putfp = fopen('php://input', 'r');
        $putdata = '';
        
        while($data = fread($putfp, 1024)){
            $putdata .= $data;
        }
        fclose($putfp);

        $requestdata = json_decode($putdata);
		
		if(empty($requestdata)){
			parse_str($putdata, $putdata);
			$requestdata = (object)$putdata;
		}

        //Check mandatory fields{
            if(empty($requestdata->userID) || empty($requestdata->endDateTime) || empty($requestdata->journeyID)){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad Request missing values (userID, endDateTime, journeyID)";
                exit;
            }
            if(empty($requestdata->type)){
                $requestdata->type = 'journey';
            }
            if(empty($requestdata->breakType)){
                $requestdata->breakType = 'jornada';
            }
        //}
        
        //Check if there's an open journey{
            $query = 
                "SELECT * from ponto
                    where pont_tx_status = 'ativo'
                        AND pont_tx_data < '".$requestdata->endDateTime."'
                        AND pont_nb_user = ".$requestdata->userID."
                        AND pont_nb_id = ".$requestdata->journeyID
            ;
            $jornadaAberta = get_data($query);
            
            if(empty($jornadaAberta)){
                header('HTTP/1.0 400 Bad Request');
                echo "Open journey not found";
                exit;
            }
        //}

        //Check if endDateTime is correctly formatted{
            if(!preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $requestdata->endDateTime)){
                header('HTTP/1.0 400 Bad Request');
                echo "Bad date formatting.";
                exit;
            }
        //}

        $macroAbertura = get_data(
            "SELECT * FROM macroponto 
                WHERE lower(macr_tx_nome) LIKE '%inicio%".$requestdata->breakType."'"
        )[0];
        $macroFechamento = get_data(
            "SELECT * FROM macroponto 
                WHERE lower(macr_tx_nome) LIKE '%fim%".$requestdata->breakType."'"
        )[0];

        //Check if there's an open interval before trying to close{
            $lastRegister = 
                "SELECT *, (macr_tx_nome like '%inicio%') as open_break FROM ponto
                    JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                    WHERE pont_tx_status = 'ativo'
                        AND pont_tx_data < '".$requestdata->endDateTime."'
                        ".(
                            ($requestdata->breakType != "jornada")?
                                "AND lower(macr_tx_nome) != 'inicio de jornada'": 
                                ""
                        )."
                    ORDER BY pont_tx_data DESC
                    LIMIT 1;"
            ;

            $lastRegister = get_data($lastRegister);
            $lastRegister = $lastRegister[0];

            $msg = '';
            if($requestdata->type != "journey"){
                if(empty($lastRegister['open_break']) || $lastRegister['open_break'] == 0){
                    $msg = "No breakpoint open to close.";
                }elseif($lastRegister['pont_tx_tipo'] != $macroAbertura['macr_tx_codigoInterno']){
                    $msg = "Breakpoint type different from the open one.";
                }
            }else{
                $lastPoint = get_data(
                    "SELECT * FROM ponto 
                        JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                        WHERE pont_tx_status = 'ativo'
                            AND pont_nb_user = ".$requestdata->userID."
                        ORDER BY pont_tx_data DESC
                        LIMIT 1"
                );
                $lastPoint = !empty($lastPoint)? $lastPoint[0]: null;
                if(!empty($lastPoint) && is_numeric(strpos(strtolower($lastPoint['macr_tx_nome']), 'inicio')) && strtolower($lastPoint['pont_tx_tipo']) != $macroAbertura['macr_tx_codigoInterno']){
                    $msg = "Trying to close journey with an open breakpoint.";
                }elseif(strtolower($lastPoint['macr_tx_nome']) == 'fim de jornada'){
                    $msg = "Trying to close an already closed journey.";
                }
            }

            if(!empty($msg)){
                header('HTTP/1.0 400 Bad Request');
                echo $msg;
                exit;
            }
        //}

        $userEntityRegistry = get_data(
            "SELECT entidade.enti_tx_matricula FROM entidade 
                JOIN user ON user_tx_matricula = enti_tx_matricula
                WHERE user_tx_status = 'ativo'
                    AND user_nb_id = ".$requestdata->userID.""
        )[0];

        $ponto = [
            "pont_nb_user"          => $decoded->data->user_id,
            "pont_tx_dataCadastro"  => date("Y-m-d H:i:s"),
            "pont_tx_matricula"     => $userEntityRegistry["enti_tx_matricula"],
            "pont_tx_data"          => $requestdata->endDateTime,
            "pont_tx_tipo"          => $macroFechamento["macr_tx_codigoInterno"],
            "pont_tx_tipoOriginal"  => $macroFechamento["macr_tx_codigoExterno"],
            "pont_nb_motivo"        => null,
            "pont_tx_descricao"     => null,
            "pont_tx_latitude"      => (!empty($requestdata->latitude)? $requestdata->latitude: null),
            "pont_tx_longitude"     => (!empty($requestdata->longitude)? $requestdata->longitude: null),
            "pont_tx_status"        => 'ativo',
            "pont_tx_justificativa" => null
        ];

        $query =
            "INSERT INTO ponto (
                ".implode(", ", array_keys($ponto))."
            ) VALUES (
                :".implode(", :", array_keys($ponto))."
            )"
        ;
        $result = insert_data($query,$ponto);
        if($requestdata->type == "break"){
			$result = "Break finish registered successfully.";
		}
        echo $result;
        exit;
    }
?>