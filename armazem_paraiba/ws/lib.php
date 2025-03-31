<?php

    require_once 'vendor/autoload.php';
    require_once __DIR__."/../load_env.php";
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;


    function makeToken($data,$key){
        return JWT::encode(
            array(
                'iat'		=>	time(),
                'nbf'		=>	time(),
                'exp'		=>	time() + 3600,
                'username'  => $data->user_tx_nome,
                'data'	    => [
                    'user_id'	=>	$data->user_nb_id,
                    'user_name'	=>	$data->user_tx_nome
                ]
            ),
            $key,
            'HS256'
        );
    }

    function validate_token($key){
        if (! preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            header('HTTP/1.0 400 Bad Request');
            echo 'Token not found in request';
            exit;
        }
        $jwt = $matches[1];
        if (! $jwt) {
            // No token was able to be extracted from the authorization header
            header('HTTP/1.0 400 Bad Request');
            exit;
        }

        $token = null;
        try{
            $token = JWT::decode($jwt, new Key($key, 'HS256'));
            if(!isset($token->exp) || time() >= $token->exp){
                throw new Exception("Token Expired.");
            }
        }catch (Exception $e){
            header('HTTP/1.0 401 Not Authorized');
            die($e->getMessage());
        }

        return $token;

        // try {
        //     $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        //     $expireDate = date("Y-m-d H:i:s", $decoded->exp);
        //     if(date("Y-m-d H:i:s") > $expireDate){
        //         throw new Exception("Token Expired.");
        //     }
        //     return $decoded;
        // } catch (Exception $e) {
        //     header('HTTP/1.0 401 Not Authorized');
        //     die($e->getMessage());
        // }
    }

    function get_data($query,$querydata=[]){
        $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"].";charset=utf8mb4", $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);
        
        $statement = $connect->prepare($query);

        $statement->execute($querydata);
        $data = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor(); 
        return $data;
    }

    function insert_data($query,$querdata){
        $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"].";charset=utf8mb4", $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);
        
        $connect->prepare($query)->execute($querdata);
        
        $prodId = $connect->lastInsertId();
        
        return $prodId;
    }

    function get_user_journeys($userid){
        $typos = get_data("SELECT * from macroponto WHERE macr_tx_status = 'ativo';");

        $macroNomes = [];
        foreach($typos as $typo){
            $macroNomes[$typo["macr_nb_id"]] = $typo["macr_tx_nome"];
        }

        $limitDate = new DateTime();
        date_sub($limitDate, date_interval_create_from_date_string("31 days"));

        $query = 
			"SELECT p.*, m.*
        		FROM ponto p
				JOIN macroponto m ON m.macr_tx_codigoInterno = p.pont_tx_tipo
        		JOIN entidade e ON p.pont_tx_matricula=e.enti_tx_matricula
        		JOIN user u ON u.user_nb_entidade = e.enti_nb_id
        		WHERE p.pont_tx_status = 'ativo' AND m.macr_tx_status = 'ativo'
					AND u.user_nb_id = ?
                    AND p.pont_tx_data > STR_TO_DATE(?, '%Y-%m-%d %H:%i')
        		ORDER BY pont_tx_data ASC";
        
        $data = get_data($query,[$userid, date_format($limitDate, "Y-m-d")]);

        $currentJourney = (object)[];
        $currentBreak = [];
        $journeysArray = [];

		foreach($data as $ponto){
			if(is_int(strpos(strtolower($ponto["macr_tx_nome"]), "inicio"))){
				if(strtolower($ponto["macr_tx_nome"]) == "inicio de jornada"){
					$currentBreak = [];
					$currentJourney->breaks = [];
					$currentJourney = create_journey_ob($ponto, "journey", $ponto["pont_nb_userCadastro"]);
					$currentJourney->finalDateTime = null;
				}else{
					$currentBreak[$ponto["pont_tx_tipo"]] = null;
					$currentBreak[$ponto["pont_tx_tipo"]] = create_journey_ob(
						$ponto,
						"break",
						$ponto["pont_nb_userCadastro"],
						trim(str_replace("inicio de ", "", strtolower($macroNomes[$ponto["pont_tx_tipo"]])))
					);
					$currentBreak[$ponto["pont_tx_tipo"]]->finalDateTime = null;
				}
			}else{
				if(strtolower($ponto["macr_tx_nome"]) == "fim de jornada"){
					if(empty($currentJourney) || $currentJourney == (object)[]){
						$currentJourney= create_journey_ob($ponto, "journey" ,$ponto["pont_nb_userCadastro"]);
						$currentJourney->startDateTime = null;
					}

					$currentJourney->breaks = array_merge(($currentJourney->breaks?? []),array_values($currentBreak));
					$currentJourney->finalDateTime = $ponto["pont_tx_data"];
					$currentJourney->finalPoint = [
						"id" => $ponto["pont_nb_id"],
						"finalDateTime" => $ponto["pont_tx_data"],
						"type" => "journey"
					];

					$journeysArray[] = $currentJourney;
					$currentBreak = [];
					$currentJourney = (object)[];
				}else{
					$tipo = $ponto['macr_nb_id'];
					if(!empty($currentBreak[$tipo])){
						$currentBreak[$tipo]->finalDateTime = $ponto["pont_tx_data"];
						$currentJourney->breaks[] = $currentBreak[$tipo];
						$currentBreak[$tipo] = null;
					}

					$currentBreak[$tipo] = create_journey_ob(
						$ponto,
						"break",
						$userid,
						trim(str_replace("fim de ", "", strtolower($macroNomes[$tipo])))
					);
					$currentBreak[$tipo]->startDateTime = null;
					$currentJourney->breaks = array_merge(($currentJourney->breaks?? []),array_values($currentBreak));
					$currentBreak = [];
				}
			}
		}
		
        if(!empty($currentJourney) && $currentJourney != (object)[]){
			if(!empty($currentBreak)){
				$currentJourney->breaks = array_merge(($currentJourney->breaks?? []),array_values($currentBreak));	
			}
            $journeysArray[] = $currentJourney;
        }
        return $journeysArray;
    }

    function delete_last(int $driverRegistration){
        $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"].";charset=utf8mb4", $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);

        $lastId = $connect->query(
            "SELECT pont_nb_id FROM ponto 
                WHERE pont_tx_matricula = '".$driverRegistration."' ORDER BY pont_nb_id DESC LIMIT 1;"
        )->fetch(MYSQLI_ASSOC)->pont_nb_id;

        return ($connect->exec("DELETE FROM ponto WHERE pont_nb_id = ".$lastId)? "deleted": "failed");
    }

    //Journey Functions
    function create_journey_ob($ponto,$type,$userid,$btype=''){
        $current = (object)[
            "id"            => $ponto["pont_nb_id"],
            "userID"        => $userid,
            "startDateTime" => $ponto["pont_tx_data"],
            "finalDateTime" => $ponto["pont_tx_data"],
            "type"          => $type,
            "breakType"     => $btype,
            "breaks"        => null
        ];
        
        if($btype==""){
            $current->breaks = [];
        }
        return $current;
    }
