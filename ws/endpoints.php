<?php 
require_once "../load_env.php";
require_once "lib.php";


function make_login(){
    $key = $_ENV["APP_KEY"];
    $msg = '';
    
    if(isset($_POST["username"]))
    {

        if(empty($_POST["username"])){
            $error = 'Please Enter login Details';
        } else if(empty($_POST["password"])){
            $error = 'Please Enter Password Details';
        } else {
            $query = "SELECT * FROM user WHERE user_tx_login = ?";

            $data = get_data($query,[$_POST["username"]])[0];    
            if($data){
                if($data['user_tx_senha'] ===  md5($_POST['password'])){
                    $key = $_ENV["APP_KEY"];
                    $token = makeToken((object)$data,$key);
                    
                    //setcookie("token", $token, time() + 3600, "/", "", true, true);
                    echo $token;
                    exit;

                } else {
                    $msg = 'Wrong Password';
                }
            } else {
                $msg = 'Wrong Username Address';
            }
        }
    }
    header('HTTP/1.0 400 Bad Request');
    echo $msg;
    exit;
}

function get_user($userid=null){
    $key = $_ENV["APP_KEY"];
    $decoded = validadte_token($key);
    if(!$userid){
        $userid = $decoded->data->user_id;
    }
    
    $query = "SELECT  distinct user_nb_id as id,
    coalesce(user_tx_matricula,enti_tx_matricula) as registration,
    coalesce(e.empr_tx_nome) as company,
    coalesce(u.user_tx_login,user_tx_cpf,enti.enti_tx_cpf) as cpf,
    coalesce(enti.enti_tx_carteira,user_tx_rg) as rg,
    enti.enti_tx_cnhRegistro as cnh,
    user_tx_email as email,
    user_tx_nivel as role,
    user_tx_foto as avatar,
    user_tx_nascimento  as birthdate 
    From user u
    left join entidade enti on u.user_nb_entidade  = enti.enti_nb_id  and u.user_nb_entidade  is not null
    left join empresa e on enti_nb_empresa = e.empr_nb_id and u.user_nb_entidade  is not null
    where u.user_nb_id =?";

    $data = get_data($query,[$userid]);    
    if($data){
        $data[0]['journeys'] = get_user_jorneys($userid);
        echo json_encode($data[0]);
    }
    else{
        header('HTTP/1.0 400 Bad Request');
        echo "User do not exists";
    }
    exit;
}

function get_jorneys($userid=null){
    $key = $_ENV["APP_KEY"];
    $decoded = validadte_token($key);
    if(!$userid){
        $userid = $decoded->data->user_id;
    }

    $data = get_user_jorneys($userid);
    if($data){
        echo json_encode($data);
    }
    else{
        header('HTTP/1.0 400 Bad Request');
        echo "Could not find jorneys";
    }
    exit;
}

function refresh(){
    $key = $_ENV["APP_KEY"];
    $decoded = validadte_token($key);
    $token = makeToken($decoded->data,$key);
    echo $token;
    exit;
}

function begin_jorney(){
    $key = $_ENV["APP_KEY"];
    $decoded = validadte_token($key);
    if(count($_POST)==0){
        $putfp = fopen('php://input', 'r');
        $putdata = '';
        
        while($data = fread($putfp, 1024))
            $putdata .= $data;
        fclose($putfp);
        $requstdata = json_decode($putdata);
        foreach($requstdata as $label=>$value){
            $_POST[$label]= $value;
        }

    }
    if(!$_POST["userID"]||!$_POST["startDateTime"]||!$_POST["type"]){
        header('HTTP/1.0 400 Bad Request');
        echo "Bad Request missing values";
        exit;
    }

    $query = "SELECT * from entidade e
    join user u on u.user_nb_entidade  = e.enti_nb_id
    where u.user_nb_id =?";

    $entiti = get_data($query,[$_POST["userID"]]);
    if(count($entiti)==0){
        header('HTTP/1.0 400 Bad Request');
        echo "User Id does not have entiti";
        exit;
    }
    $macroid = 1;
    $outerid= 10;
    if($_POST["type"]!="journey"){
        if(!$_POST["journeyID"]||!$_POST["breakType"]){
            header('HTTP/1.0 400 Bad Request');
            echo "Bad Request missing values";
            exit;
        }
        $query = "SELECT * from macroponto
        where lower(macr_tx_nome) LIKE ?";
        $macro = get_data($query,["in%cio%".strtolower($_POST["breakType"])]);
        if(count($macro)==0){
            header('HTTP/1.0 400 Bad Request');
            echo "Break type not found";
            exit;
        }
        $macroid = $macro[0]["macr_tx_codigoInterno"];
        $outerid = $macro[0]["macr_tx_codigoExterno"];

        $query = "SELECT * from ponto
            where pont_nb_id = ? and pont_tx_tipo=1";
        $pontoaberto = get_data($query,[$jorney_id]);
        if(count($pontoaberto)==0){
            header('HTTP/1.0 400 Bad Request');
            echo "Jorney not found";
            exit;
        }
        $sqltst = "SELECT * from ponto  where  pont_tx_data<:data_i and  
        pont_tx_tipo % 2 = 1  and pont_tx_tipo>2
        and pont_tx_matricula=:mat  and pont_tx_data>:data_o
        order by pont_tx_data";
        $abertos = get_data($sqltst,["data_i"=>$_POST["startDateTime"],
        "tipo_e"=>$macroid,"data_o"=>$pontoaberto["pont_tx_data"],
        "mat"=>$entiti[0]["enti_tx_matricula"]]);
        foreach($abertos as $test){
            $sqltst = "SELECT * from ponto  where  pont_tx_data<:data_i 
            and pont_tx_data>:data_o
            and  pont_tx_tipo = :tipo_e and  pont_tx_matricula=:mat";
            $test2 = get_data($sqltst,["data_i"=>$_POST["startDateTime"],
            "data_o"=>$test["pont_tx_data"],
            "tipo_e"=>$test["pont_tx_tipo"]+1,"mat"=>$entiti[0]["enti_tx_matricula"]]);
            if(count($test)>0 and count($test2)==0){
                header('HTTP/1.0 400 Bad Request');
                echo "Breakpoint open without closing";
                exit;
            }
        }
        
    }

    $sqltst = "SELECT * from ponto  where  pont_tx_data<:data_i and  pont_tx_tipo = :tipo_e and pont_tx_matricula=:mat 
    order by pont_tx_data DESC limit 1";
    $test = get_data($sqltst,["data_i"=>$_POST["startDateTime"],"tipo_e"=>$macroid,"mat"=>$entiti[0]["enti_tx_matricula"]]);
    $sqltst = "SELECT * from ponto  where  pont_tx_data<:data_i and pont_tx_data>:data_o
     and  pont_tx_tipo = :tipo_e and  pont_tx_matricula=:mat";
    $test2 = get_data($sqltst,["data_i"=>$_POST["startDateTime"],"data_o"=>$test[0]["pont_tx_data"],
    "tipo_e"=>$macroid+1,"mat"=>$entiti[0]["enti_tx_matricula"]]);
    if(count($test)>0 and count($test2)==0){
        header('HTTP/1.0 400 Bad Request');
        echo "Jorney open without closing";
        exit;
    }

    $ponto = [];
    $ponto["pont_nb_user"] = $decoded->data->user_id;
    $ponto["pont_tx_dataCadastro"] = date("Y-m-d H:i:s");
    $ponto["pont_tx_matricula"] = $entiti[0]["enti_tx_matricula"];
    $ponto["pont_tx_data"] = $_POST["startDateTime"];
    $ponto["pont_tx_tipo"] = $macroid;
    $ponto["pont_tx_tipoOriginal"] = $outerid;
    $ponto["pont_nb_motivo"] = null;
    $ponto["pont_tx_descricao"] = null;
    $ponto["pont_tx_status"] = 'ativo';
    $ponto["pont_tx_justificativa"] = null;
    $query = "INSERT INTO ponto (pont_nb_user, pont_tx_dataCadastro, pont_tx_matricula, pont_tx_data, pont_tx_tipo,pont_tx_tipoOriginal,pont_nb_motivo,pont_tx_descricao,pont_tx_status,pont_tx_justificativa) 
    VALUES (:pont_nb_user, :pont_tx_dataCadastro, :pont_tx_matricula, :pont_tx_data,:pont_tx_tipo,:pont_tx_tipoOriginal,:pont_nb_motivo,:pont_tx_descricao,:pont_tx_status,:pont_tx_justificativa )";
    $result = insert_data($query,$ponto);
    echo $result;
    exit;
}

function finish_jorney($jorney_id){
    $key = $_ENV["APP_KEY"];
    $decoded = validadte_token($key);
    $putfp = fopen('php://input', 'r');
    $putdata = '';
    
    while($data = fread($putfp, 1024))
        $putdata .= $data;
    fclose($putfp);

    $requstdata = json_decode($putdata);
    if(!$requstdata->userID||!$requstdata->endDateTime){
        header('HTTP/1.0 400 Bad Request');
        echo "Bad Request missing values";
        exit;
    }
    $query = "SELECT * from ponto
    where pont_nb_id = ?";
    $pontoaberto = get_data($query,[$jorney_id]);
    
    if(count($pontoaberto)==0){
        header('HTTP/1.0 400 Bad Request');
        echo "Jorney not found";
        exit;
    }
    else{
        $pontoaberto = $pontoaberto[0];
    }
    $typetipo = intval($pontoaberto["pont_tx_tipo"])+1;

    $sqltst = "SELECT * from ponto 
    where pont_tx_data>:data_i and pont_tx_tipo = :tipo_e and pont_tx_matricula=:mat
    and pont_tx_data< coalesce((SELECT min(pont_tx_data) from ponto 
        where pont_tx_tipo=:tipo_b and pont_tx_data>:data_i 
            and pont_tx_matricula=:mat),now())";
    $test = get_data($sqltst,["data_i"=>$pontoaberto["pont_tx_data"],
    "tipo_e"=>$typetipo,"tipo_b"=>$pontoaberto["pont_tx_tipo"],"mat"=>$pontoaberto["pont_tx_matricula"]]);
    if(count($test)>0){
        header('HTTP/1.0 400 Bad Request');
        echo "Jorney already closed";
        exit;
    }

    exit;
    $ponto = [];
    $ponto["pont_nb_user"] = $decoded->data->user_id;
    $ponto["pont_tx_dataCadastro"] = date("Y-m-d H:i:s");
    $ponto["pont_tx_matricula"] = $pontoaberto["pont_tx_matricula"];
    $ponto["pont_tx_data"] = $requstdata->endDateTime;
    $ponto["pont_tx_tipo"] = $typetipo;
    $ponto["pont_tx_tipoOriginal"] = intval($pontoaberto["pont_tx_tipoOriginal"])+1;
    $ponto["pont_nb_motivo"] = null;
    $ponto["pont_tx_descricao"] = null;
    $ponto["pont_tx_status"] = 'ativo';
    $ponto["pont_tx_justificativa"] = null;
    $query = "INSERT INTO ponto (pont_nb_user, pont_tx_dataCadastro, pont_tx_matricula, pont_tx_data, pont_tx_tipo,pont_tx_tipoOriginal,pont_nb_motivo,pont_tx_descricao,pont_tx_status,pont_tx_justificativa) 
    VALUES (:pont_nb_user, :pont_tx_dataCadastro, :pont_tx_matricula, :pont_tx_data,:pont_tx_tipo,:pont_tx_tipoOriginal,:pont_nb_motivo,:pont_tx_descricao,:pont_tx_status,:pont_tx_justificativa )";
    $result = insert_data($query,$ponto);
    echo "{}";
    exit;
}