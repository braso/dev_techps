<?php
    /* Modo debug
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    //*/
    
    require_once "../load_env.php";
    require_once "lib.php";

    function make_login(){
        $msg = "";

        //Check mandatory fields{
            if(empty($_POST["username"])){
                $msg = 'Please Enter Login Details';
            }elseif(empty($_POST["password"])){
                $msg = 'Please Enter Password Details';
            }
            if(!empty($msg)){
                // header('HTTP/1.0 400 Bad Request');
                echo $msg;
                exit;
            }
        //}

        //Check if user exists{
            $data = get_data(
                "SELECT user_tx_senha, user_nb_id FROM user 
                    WHERE user_tx_status = 'ativo'
                        AND user_tx_login = '".$_POST["username"]."';"
            );

            if(empty($data)){
                $msg = 'Wrong Username Address';
            }elseif($data[0]['user_tx_senha'] !== md5($_POST['password'])){
                $msg = 'Wrong Password';
            }
            if(!empty($msg)){
                header("HTTP/1.1 204 No Content");
                echo $msg;
                die();
            }
        //}

        $data = $data[0];

        $token = makeToken((object)$data,$_ENV["APP_KEY"]);
        
        echo "{ \"id\": ".$data['user_nb_id'].", \"token\": \"".$token."\"}";
        exit;
    }

    function get_user($userid = null){

        try{
            $decoded = validate_token($_ENV["APP_KEY"]);
        }catch(Exception $e){
            die($e->getMessage());
        }

        if(empty($userid)){
            $userid = $decoded->data->user_id;
        }
        
        $query = 
            "SELECT 
                DISTINCT user_nb_id						AS id, 
                COALESCE(user_tx_nome,enti_tx_nome)		AS name,
                enti_tx_matricula						AS registration,
                COALESCE(e.empr_tx_nome)				AS company,
                COALESCE(user_tx_cpf,enti_tx_cpf)		AS cpf,
                COALESCE(user_tx_rg)					AS rg,
                enti_tx_cnhRegistro						AS cnh,
                user_tx_email							AS email,
                user_tx_nivel							AS role,
                user_tx_foto							AS avatar,
                user_tx_nascimento						AS birthdate,
                cida_tx_nome							AS city
            FROM user u
                LEFT JOIN entidade enti ON u.user_nb_entidade = enti.enti_nb_id
                    AND u.user_nb_entidade IS NOT NULL
                LEFT JOIN empresa e ON enti_nb_empresa = e.empr_nb_id
                    AND u.user_nb_entidade IS NOT NULL
                LEFT JOIN cidade ON user_nb_cidade = cida_nb_id
                WHERE u.user_tx_status = 'ativo'
                	AND u.user_nb_id = {$userid}"
        ;

        $data = get_data($query);
        if(empty($data)){
            // header('HTTP/1.0 400 Bad Request');
            echo "User does not exists";
            exit;
        }

        echo json_encode($data);
        exit;
    }

    function get_journeys($userid = null){
        try{
            $decoded = validate_token($_ENV["APP_KEY"]);
        }catch(Exception $e){
            die($e->getMessage());
        }
        
        if(empty($userid)){
            $userid = $decoded->data->user_id;
        }

        $data = get_user_journeys($userid);
        if(empty($data)){
            // header('HTTP/1.0 400 Bad Request');
            echo json_encode([]);
            exit;
        }

        echo json_encode($data);
        exit;
    }

    function refresh(){
        try{
            $decoded = validate_token($_ENV["APP_KEY"]);
        }catch(Exception $e){
            die($e->getMessage());
        }

        $token = makeToken($decoded->data, $_ENV["APP_KEY"]);
        echo $token;
        
        exit;
    }

    function begin_journey(){
        try{
            $decoded = validate_token($_ENV["APP_KEY"]);
        }catch(Exception $e){
            die($e->getMessage());
        }
		
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
                // header('HTTP/1.0 400 Bad Request');
                echo "Bad Request: missing values (userID, startDateTime)";
                exit;
            }
            if(!in_array($_POST["type"], ["journey", "break"])){
                // header('HTTP/1.0 400 Bad Request');
                echo "Bad Request: type value";
                exit;
            }
            if(empty($_POST['breakType'])){
                $_POST['breakType'] = 'jornada';
            }
        //}

        $_POST["startDateTime"] = substr($_POST["startDateTime"], 0, 16);

        //Check if user has entity{
            $entity = 
                "SELECT * from entidade e
                    join user u on u.user_nb_entidade = e.enti_nb_id
                    where e.enti_tx_status = 'ativo' AND u.user_tx_status = 'ativo'
                    AND u.user_nb_id = {$_POST["userID"]}"
            ;
            $entity = get_data($entity);
            if(empty($entity)){
                // header('HTTP/1.0 400 Bad Request');
                echo "User Id does not have entity";
                exit;
            }
            $entity = $entity[0];
        //}
		

        //Check if break type (macro) exists{
            $macro = 
                "SELECT * from macroponto
                    WHERE macr_tx_status = 'ativo'
                        AND lower(macr_tx_nome) LIKE '"."in%cio%".strtolower($_POST["breakType"])."'";
            ;
            $macro = get_data($macro);
            if(empty($macro)){
                // header('HTTP/1.0 400 Bad Request');
                echo "Break type not found";
                exit;
            }
            $macro = $macro[0];
        //}
		

        //Check if startDateTime is correctly formatted{
            if(!preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $_POST['startDateTime'])){
                // header('HTTP/1.0 400 Bad Request');
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
                // header('HTTP/1.0 400 Bad Request');
                echo "Bad Request missing values (journeyID, breakType)";
                exit;
            }

            //Check if there is an open journey with this id{
                $query = 
                    "SELECT * FROM ponto
                        JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                        WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
                            AND pont_tx_matricula = ".$entity['enti_tx_matricula']."
                            AND pont_nb_id >= ".$_POST['journeyID']."
                            AND pont_tx_data <= STR_TO_DATE('".$_POST['startDateTime'].":59', '%Y-%m-%d %H:%i:%s')
                            AND lower(macr_tx_nome) LIKE '%jornada%'
                        ORDER BY pont_tx_data DESC
                        LIMIT 1;"
                ;
                $openJourney = get_data($query);
                $openJourney = !empty($openJourney)? $openJourney[0]: $openJourney;
                if(empty($openJourney) || is_numeric(strpos(strtolower($openJourney['macr_tx_nome']), 'fim'))){
                    // header('HTTP/1.0 400 Bad Request');
                    echo "Open journey not found";
                    exit;
                }
            //}

            //Check if there is an open break before trying to insert another opening{
                $lastBreakOpening = 
                    "SELECT *, (macr_tx_nome like '%inicio%') as open_break FROM ponto
                        JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                        WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
                            AND pont_tx_matricula = '{$entity["enti_tx_matricula"]}'
                            AND pont_tx_data <= STR_TO_DATE('{$_POST["startDateTime"]}:59', '%Y-%m-%d %H:%i:%s')
                            AND lower(macr_tx_nome) != 'inicio de jornada'
                        ORDER BY pont_tx_data DESC
                        LIMIT 1;"
                ;

                $lastBreakOpening = get_data($lastBreakOpening)[0];
                if(!empty($lastBreakOpening) && $lastBreakOpening['open_break']){
                    // header('HTTP/1.0 400 Bad Request');
                    echo "Breakpoint open without closing previous one.";
                    exit;
                }
            //}
        }elseif($_POST["type"] == "journey"){

            $lastJourney = get_data(
                "SELECT *, (lower(macr_tx_nome) like '%inicio%') as open_journey FROM ponto
                    JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                    WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
                        AND pont_tx_matricula = '".$entity["enti_tx_matricula"]."'
                        AND macr_tx_nome LIKE '%jornada%'
                        AND pont_tx_data <= STR_TO_DATE('".$_POST["startDateTime"].":59', '%Y-%m-%d %H:%i:%s')
                    ORDER BY pont_tx_data DESC
                    LIMIT 1;"
            );

            if($lastJourney[0]['open_journey']){
                // header('HTTP/1.0 400 Bad Request');
                if(substr($lastJourney[0]['pont_tx_data'], 0, strlen($lastJourney[0]['pont_tx_data'])-3) == $_POST["startDateTime"]){
                    echo intval($lastJourney[0]["pont_nb_id"]);
                }else{
                    echo "Journey open without closing previous one.";
                }
                exit;
            }
        }else{
            // header('HTTP/1.0 400 Bad Request');
            echo "Type not found";
            exit;
        }

        //Confere se já tem um ponto no mesmo minuto, e adiciona aos segundos como índice de ordenação{
            
            $pontoMesmoMinuto = get_data(
                "SELECT * FROM ponto 
                    WHERE pont_tx_status = 'ativo'
                        AND pont_tx_matricula = ".$entity['enti_tx_matricula']."
                        AND pont_tx_data LIKE '%".$_POST["startDateTime"]."%'
                    ORDER BY pont_tx_data DESC
                    LIMIT 1;"
            );
            $pontoMesmoMinuto = !empty($pontoMesmoMinuto)? $pontoMesmoMinuto[0]: $pontoMesmoMinuto;

            
            if(!empty($pontoMesmoMinuto["pont_tx_data"])){
                if($pontoMesmoMinuto["pont_tx_tipo"] == $macroid){
                    echo "Same register already sent.";
                    exit;
                }
                $indiceSeg = intval(substr($pontoMesmoMinuto["pont_tx_data"], -2))+1;
                $_POST["startDateTime"] = $_POST["startDateTime"].":".sprintf('%02d', $indiceSeg);
            }
        //}

        $ponto = [
            "pont_nb_userCadastro" => $decoded->data->user_id,
            "pont_tx_dataCadastro" => date("Y-m-d H:i:s"),
            "pont_tx_matricula" => $entity["enti_tx_matricula"],
            "pont_tx_data" => $_POST["startDateTime"],
            "pont_tx_tipo" => $macroid,
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
        try{
            $decoded = validate_token($_ENV["APP_KEY"]);
        }catch(Exception $e){
            die($e->getMessage());
        }
        
        $putfp = fopen('php://input', 'r');
        $putdata = "";
        
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
                // header('HTTP/1.0 400 Bad Request');
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

        $requestdata->endDateTime = substr($requestdata->endDateTime, 0, 16);

        $userEntityRegistry = get_data(
            "SELECT entidade.enti_tx_matricula FROM entidade 
                JOIN user ON enti_nb_id = user_nb_entidade
                WHERE user_tx_status = 'ativo'
                    AND user_nb_id = ".$requestdata->userID.""
        )[0];
        
        //Check if there's an open journey{
            $query = 
                "SELECT * from ponto
                    where pont_tx_status = 'ativo'
                        AND pont_tx_matricula = ".$userEntityRegistry["enti_tx_matricula"]."
                        AND pont_tx_data <= STR_TO_DATE('".$requestdata->endDateTime.":59', '%Y-%m-%d %H:%i:%s')
                        AND pont_nb_id = ".($requestdata->journeyID?? -1)
            ;
            $jornadaAberta = get_data($query);
            
            if(empty($jornadaAberta)){
                // header('HTTP/1.0 400 Bad Request');
                echo "Open journey not found";
                exit;
            }
        //}

        //Check if endDateTime is correctly formatted{
            if(!preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/", $requestdata->endDateTime)){
                // header('HTTP/1.0 400 Bad Request');
                echo "Bad date formatting.";
                exit;
            }
        //}

        $macroAbertura = get_data(
            "SELECT * FROM macroponto 
                WHERE macr_tx_status = 'ativo'
                    AND lower(macr_tx_nome) LIKE '%inicio%".$requestdata->breakType."'"
        )[0];
        $macroFechamento = get_data(
            "SELECT * FROM macroponto 
                WHERE macr_tx_status = 'ativo'
                    AND lower(macr_tx_nome) LIKE '%fim%".$requestdata->breakType."'"
        )[0];

        //Check if there's an open interval before trying to close{
            $lastRegister = 
                "SELECT *, (macr_tx_nome like '%inicio%') as open_break FROM ponto
                    JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
                    WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
                        AND pont_tx_matricula = ".$userEntityRegistry["enti_tx_matricula"]."
                        AND pont_tx_data <= STR_TO_DATE('".$requestdata->endDateTime.":59', '%Y-%m-%d %H:%i:%s')
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
                        WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
                            AND pont_tx_matricula = ".$userEntityRegistry["enti_tx_matricula"]."
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
                // header('HTTP/1.0 400 Bad Request');
                echo $msg;
                exit;
            }
        //}

        //Confere se já tem um ponto no mesmo minuto, e adiciona aos segundos como índice de ordenação{
            $pontoMesmoMinuto = get_data(
                "SELECT * FROM ponto 
                    WHERE pont_tx_status = 'ativo'
                        AND pont_tx_matricula = ".$userEntityRegistry["enti_tx_matricula"]."
                        AND pont_tx_data LIKE '%".$requestdata->endDateTime."%'
                    ORDER BY pont_tx_data DESC
                    LIMIT 1;"
            );
            $pontoMesmoMinuto = !empty($pontoMesmoMinuto)? $pontoMesmoMinuto[0]: $pontoMesmoMinuto;
            

            if(!empty($pontoMesmoMinuto["pont_tx_data"])){
                if($pontoMesmoMinuto["pont_tx_tipo"] == $macroFechamento["macr_tx_codigoInterno"]){
                    echo "Same register already sent.";
                    exit;
                }
                $indiceSeg = intval(substr($pontoMesmoMinuto["pont_tx_data"], -2))+1;
                $requestdata->endDateTime = $requestdata->endDateTime.":".sprintf('%02d', $indiceSeg);
            }
        //}

        $ponto = [
            "pont_nb_userCadastro"  => $decoded->data->user_id,
            "pont_tx_dataCadastro"  => date("Y-m-d H:i:s"),
            "pont_tx_matricula"     => $userEntityRegistry["enti_tx_matricula"],
            "pont_tx_data"          => $requestdata->endDateTime,
            "pont_tx_tipo"          => $macroFechamento["macr_tx_codigoInterno"],
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
        
        $result = insert_data($query, $ponto);
        if($requestdata->type == "break"){
			$result = "Break finish registered successfully.";
		}
        echo $result;
        exit;
    }

    function delLastRegister(int $userId){
        $userEntityRegistry = get_data(
            "SELECT entidade.enti_tx_matricula FROM entidade 
                JOIN user ON enti_nb_id = user_nb_entidade
                WHERE user_tx_status = 'ativo'
                    AND user_nb_id = ".$userId.""
        )[0];
        return delete_last($userEntityRegistry["enti_tx_matricula"]);
    }