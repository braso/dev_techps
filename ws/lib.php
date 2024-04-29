<?php
    require_once 'vendor/autoload.php';
    require_once "../load_env.php";
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Firebase\JWT\ExpiredException;
    use Firebase\JWT\SignatureInvalidException;
    use Firebase\JWT\BeforeValidException;


    function makeToken($data,$key){
        return JWT::encode(
            array(
                'iat'		=>	time(),
                'nbf'		=>	time(),
                'exp'		=>	time() + 3600,
                'username' => $data->user_tx_nome,
                'data'	=> array(
                    'user_id'	=>	$data->user_nb_id,
                    'user_name'	=>	$data->user_tx_nome
                )
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

        try {
            return JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (ExpiredException $e) {
            throw new Exception('Token expired');
        } catch (SignatureInvalidException $e) {
            throw new Exception('Invalid token signature');
        } catch (BeforeValidException $e) {
            throw new Exception('Token not valid yet');
        } catch (Exception $e) {
            throw new Exception('Invalid token');
        }
    }

    function get_data($query,$querydata=[]){
        $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);
        
        $statement = $connect->prepare($query);

        $statement->execute($querydata);
        $data = $statement->fetchAll(PDO::FETCH_ASSOC);  
        $statement->closeCursor(); 
        return $data;
    }

    function insert_data($query,$querdata){
        $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);
        
        $connect->prepare($query)->execute($querdata);
        
        $prodId = $connect->lastInsertId();
        
        return $prodId;
    }

    function get_user_journeys($userid){
        $typos = get_data("SELECT * from macroponto");  

        $auxArray = [];
        foreach($typos as $typo){
            $auxArray[$typo["macr_nb_id"]] = $typo["macr_tx_nome"];
        }

        $query =  "SELECT p.*
        From ponto p
        join entidade e on p.pont_tx_matricula=e.enti_tx_matricula
        join user u on u.user_nb_entidade  = e.enti_nb_id
        where p.pont_tx_status='ativo' and u.user_nb_id =?
        order by pont_tx_data";
        
        $data = get_data($query,[$userid]);   

        $currentJourney = null;
        $currentBreak = [];
        $journeysArray = [];
        foreach($data as $ponto){
            if($ponto["pont_tx_tipo"]%2){//inicio
                if($ponto["pont_tx_tipo"]==1){//Inicio Jornada
                    if($currentJourney){
                        $currentJourney->breaks = array_merge($currentJourney->breaks,array_values($currentBreak));
                        $currentBreak = [];
                    }
                    $currentJourney= create_journey_ob($ponto,"journey",$userid);
                    $currentJourney->finalDateTime = Null; 
                }
                else{
                    if($currentBreak[$ponto["pont_tx_tipo"]]){
                        $currentJourney->breaks[] = $currentBreak[$ponto["pont_tx_tipo"]];
                        $currentBreak[$ponto["pont_tx_tipo"]] = null;
                    }
                    $currentBreak[$ponto["pont_tx_tipo"]] = create_journey_ob($ponto,"break",$userid,
                        trim(str_replace("inicio de ","",strtolower($auxArray[$ponto["pont_tx_tipo"]]))));
                    $currentBreak[$ponto["pont_tx_tipo"]]->finalDateTime = Null; 
                }
            }else{//Fim 
                if($ponto["pont_tx_tipo"]==2){//Fim Jornada
                    if(!$currentJourney){
                        $currentJourney= create_journey_ob($ponto,"journey",$userid);
                        $currentJourney->startDateTime = Null; 
                    }
                    $currentJourney->breaks = array_merge($currentJourney->breaks,array_values($currentBreak));
                    $currentBreak = [];
                    $currentJourney->finalDateTime = $ponto["pont_tx_data"];
                    $journeysArray[] = $currentJourney;
                    $currentJourney = null;
                }else{
                    $tipo = $ponto["pont_tx_tipo"]-1;
                    if($currentBreak[$tipo]){
                        $currentBreak[$tipo]->finalDateTime = $ponto["pont_tx_data"];
                        $currentJourney->breaks[] = $currentBreak[$tipo];
                        $currentBreak[$tipo] = null;
                    }
                    $currentBreak[$tipo] = create_journey_ob($ponto,"break",$userid,
                        trim(str_replace("inicio de ","",strtolower($auxArray[$tipo]))));
                    $currentBreak[$tipo]->startDateTime = Null; 
                }
            }
        }
        if(count($currentBreak)>0 && !$currentJourney){
            $currentJourney = create_journey_ob(["pont_nb_id"=>null,"pont_tx_data"=>null],"journey",$userid);
        }
        if($currentJourney){
            $currentJourney->breaks = array_merge($currentJourney->breaks,array_values($currentBreak));
            $journeysArray[] = $currentJourney;
        }
        return $journeysArray;
    }



    //Journey Functions
    function create_journey_ob($ponto,$type,$userid,$btype=''){
        $current = new stdClass();
        $current->id = $ponto["pont_nb_id"];
        $current->userID = $userid;
        $current->startDateTime = $ponto["pont_tx_data"];
        $current->finalDateTime = $ponto["pont_tx_data"];
        $current->type = $type;
        $current->breakType = $btype;
        if($btype==''){
            $current->breaks = [];
        }
        else{
            $current->breaks = null;
        }
        return $current;
    }
?>
