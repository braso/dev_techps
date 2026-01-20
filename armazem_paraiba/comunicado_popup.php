<?php
function mostrarComunicadoPopup(){
    // IMPORTANTE: Traz a conexão principal para dentro do escopo da função
    global $conn; 

    // --- NOVA LÓGICA (COMUNICADO INTERNO) ---
    
    // Self-healing: Ensure columns exist
    $checkCols = mysqli_query($conn, "SHOW COLUMNS FROM comunicado_interno LIKE 'coin_tx_dest_perfis'");
    if(!$checkCols || mysqli_num_rows($checkCols) == 0){
        echo "<script>console.log('Migrating table comunicado_interno...');</script>";
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_dest_perfis TEXT;");
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_dest_setores TEXT;");
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_dest_subsetores TEXT;");
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_dest_cargos TEXT;");
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_imagem VARCHAR(255);");
        mysqli_query($conn, "ALTER TABLE comunicado_interno ADD COLUMN coin_tx_tipo_conteudo VARCHAR(50) DEFAULT 'texto';");
    }

    $nivel = $_SESSION["user_tx_nivel"] ?? "";
    $idEntidade = $_SESSION["user_nb_entidade"] ?? 0;
    
    // Dados da Entidade (Setor, Subsetor, Cargo)
    $userSetor = 0;
    $userSubsetor = 0;
    $userCargo = 0;

    if ($idEntidade > 0) {
        $resUser = mysqli_query($conn, "SELECT enti_setor_id, enti_subSetor_id, enti_tx_tipoOperacao FROM entidade WHERE enti_nb_id = '$idEntidade' LIMIT 1");
        if ($resUser && mysqli_num_rows($resUser) > 0) {
            $rowUser = mysqli_fetch_assoc($resUser);
            $userSetor = (int)$rowUser["enti_setor_id"];
            $userSubsetor = (int)$rowUser["enti_subSetor_id"];
            $userCargo = (int)$rowUser["enti_tx_tipoOperacao"];
        }
    }

    $conditions = [];
    $conditions[] = "coin_tx_status = 'ativo'";
    
    // Filtros de Destino (Lógica: Se definido no comunicado, usuário deve ter)
    // Perfis
    if (!empty($nivel)) {
        $conditions[] = "(coin_tx_dest_perfis IS NULL OR coin_tx_dest_perfis = '' OR FIND_IN_SET('todos', coin_tx_dest_perfis) OR FIND_IN_SET('$nivel', coin_tx_dest_perfis))";
    }

    // Setores
    if ($userSetor > 0) {
        $conditions[] = "(coin_tx_dest_setores IS NULL OR coin_tx_dest_setores = '' OR FIND_IN_SET('todos', coin_tx_dest_setores) OR FIND_IN_SET('$userSetor', coin_tx_dest_setores))";
    } else {
        $conditions[] = "(coin_tx_dest_setores IS NULL OR coin_tx_dest_setores = '' OR FIND_IN_SET('todos', coin_tx_dest_setores))";
    }

    // Subsetores
    if ($userSubsetor > 0) {
        $conditions[] = "(coin_tx_dest_subsetores IS NULL OR coin_tx_dest_subsetores = '' OR FIND_IN_SET('todos', coin_tx_dest_subsetores) OR FIND_IN_SET('$userSubsetor', coin_tx_dest_subsetores))";
    } else {
        $conditions[] = "(coin_tx_dest_subsetores IS NULL OR coin_tx_dest_subsetores = '' OR FIND_IN_SET('todos', coin_tx_dest_subsetores))";
    }

    // Cargos
    if ($userCargo > 0) {
        $conditions[] = "(coin_tx_dest_cargos IS NULL OR coin_tx_dest_cargos = '' OR FIND_IN_SET('todos', coin_tx_dest_cargos) OR FIND_IN_SET('$userCargo', coin_tx_dest_cargos))";
    } else {
        $conditions[] = "(coin_tx_dest_cargos IS NULL OR coin_tx_dest_cargos = '' OR FIND_IN_SET('todos', coin_tx_dest_cargos))";
    }

    $sqlWhere = implode(" AND ", $conditions);
    
    // Pega o último comunicado
    $sql = "SELECT * FROM comunicado_interno WHERE $sqlWhere ORDER BY coin_nb_id DESC LIMIT 1";
    
    echo "<script>console.log('SQL Comunicado: " . addslashes($sql) . "');</script>";

    // Check table existence first to avoid errors if update script wasn't run
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'comunicado_interno'");
    if($checkTable && mysqli_num_rows($checkTable) > 0){
        $res = mysqli_query($conn, $sql);
        $rows = ($res) ? mysqli_num_rows($res) : 0;
        echo "<script>console.log('New Logic Rows: $rows');</script>";

        if ($rows > 0) {
            $row = mysqli_fetch_assoc($res);
            
            $id = $row["coin_nb_id"];
            $titulo = $row["coin_tx_titulo"];
            $tipo = $row["coin_tx_tipo_conteudo"];
            $conteudo = "";
    
            if ($tipo == 'imagem' && !empty($row["coin_tx_imagem"])) {
                $imgSrc = $row["coin_tx_imagem"];
                // Imagem adaptável - Reels Size (9:16)
                // ALTERE AQUI AS DIMENSÕES DA IMAGEM
                // max-width: controla a largura máxima
                // max-height: controla a altura máxima (ajustado para caber na tela)
                $conteudo = "<div style='text-align:center; display: flex; justify-content: center;'>
                    <img src='$imgSrc' style='max-width: 900px; max-height: 80vh; width: 100%; height: auto; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);' alt='Comunicado'>
                </div>";
            } else {
                $conteudo = $row["coin_tx_texto"];
                 if(strpos($conteudo, '<') === false){
                    $conteudo = nl2br(htmlspecialchars($conteudo));
                 }
            }
    
            $tituloSafe = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
            $jsId = json_encode((string)$id);
            $jsUserId = json_encode($_SESSION['user_nb_id'] ?? 'guest');
            
            // Modal
            // ALTERE AQUI AS DIMENSÕES GERAIS DO POPUP
            // max-width: largura máxima do modal
            // Resetamos top/transform para evitar conflitos com CSS de outras páginas (ex: batida_ponto.css)
            $modalStyle = ($tipo == 'imagem') 
                ? "max-width: 450px; width: 100%; margin: 10px auto; top: 0; transform: none;" // Estilo tipo Reels (Topo)
                : "max-width: 1200px; width: 100%; margin: 30px auto; top: 0; transform: none;"; // Estilo padrão para texto

            echo "
            <div class='modal fade' id='comunicadoModal' tabindex='-1' role='dialog' aria-hidden='true'>
                <div class='modal-dialog modal-lg' style='$modalStyle'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                            <h4 class='modal-title'>$tituloSafe</h4>
                        </div>
                        <div class='modal-body' style='overflow-x: auto; overflow-y: auto; max-height: 60vh; padding: 15px;'>
                            $conteudo
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-primary' data-dismiss='modal'>Ok</button>
                        </div>
                    </div>
                </div>
            </div>";
    
            echo "<script>
            (function(){
                var userId = $jsUserId;
                var comId = $jsId;
                var storageKey = 'comunicado_viewed_' + userId + '_' + comId;
                
                if(localStorage.getItem(storageKey)){
                    console.log('Comunicado já visto: ' + storageKey);
                    return;
                }
                
                var t=0, iv=setInterval(function(){ 
                    t++; 
                    if(window.jQuery && $.fn.modal){ 
                        clearInterval(iv); 
                        try{ 
                            $('#comunicadoModal').modal('show'); 
                            
                            var markAsSeen = function(){
                                localStorage.setItem(storageKey, '1');
                                console.log('Comunicado marcado como visto: ' + storageKey);
                            };

                            $('#comunicadoModal .btn.btn-primary').on('click', markAsSeen);
                            $('#comunicadoModal').on('hidden.bs.modal', markAsSeen);
                            
                        }catch(e){ console.error(e); } 
                    } else if(t>50){ 
                        clearInterval(iv); 
                    } 
                }, 100);
            })();
            </script>";
            
            return; // Encontrou e exibiu, encerra a função
        }
    }

    // Tenta pegar via $_ENV, se não conseguir, tenta via getenv()
    $h = !empty($_ENV["DB_HOST2"]) ? trim((string)$_ENV["DB_HOST2"]) : getenv("DB_HOST2");
    $u = !empty($_ENV["DB_USER2"]) ? trim((string)$_ENV["DB_USER2"]) : getenv("DB_USER2");
    $p = !empty($_ENV["DB_PASSWORD2"]) ? trim((string)$_ENV["DB_PASSWORD2"]) : getenv("DB_PASSWORD2");
    $n = !empty($_ENV["DB_NAME2"]) ? trim((string)$_ENV["DB_NAME2"]) : getenv("DB_NAME2");
    $port = !empty($_ENV["DB_PORT2"]) ? (int)trim((string)$_ENV["DB_PORT2"]) : (int)getenv("DB_PORT2");
    $host = $h;
    if(strpos($host, ':') !== false){
        $parts = explode(':', $host, 2);
        $host = $parts[0];
        if(empty($port) && is_numeric($parts[1])){ $port = (int)$parts[1]; }
    }
    echo "<script>console.log('DB2: env host=".$host." name=".$n." port=".$port."');</script>";

    $texto = "";
    $fonte = ""; // Para saber de onde veio o dado (log)
    $titulo = "";
    $identificador = "";
    $nivel = isset($_SESSION["user_tx_nivel"]) ? $_SESSION["user_tx_nivel"] : "";
    $destino = (is_int(strpos($nivel, "Administrador")) || is_int(strpos($nivel, "Super Administrador"))) ? "Administrador" : "Funcionário";

    // 1. TENTA CONECTAR NO DB 2
    if(!empty($h) && !empty($u) && !empty($n)){
        $link = mysqli_init();
        mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        
        echo "<script>console.log('DB2: tentando conectar em ".$host.( $port? (':'.$port): '' )." banco ".$n."');</script>";
        $connected = $port ? mysqli_real_connect($link, $host, $u, $p, $n, $port) : mysqli_real_connect($link, $host, $u, $p, $n);
        if($connected){
            echo "<script>console.log('DB2: conexão estabelecida');</script>";
            mysqli_set_charset($link, "utf8mb4");
            
            $has = mysqli_query($link, "SHOW TABLES LIKE 'comunicados'");
            if($has && mysqli_num_rows($has) > 0){
                echo "<script>console.log('DB2: tabela comunicados encontrada');</script>";
                $chkCol = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_dataCadastro'");
                $hasDate = $chkCol && mysqli_num_rows($chkCol) > 0;
                $chkDest = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_destino'");
                $hasDest = $chkDest && mysqli_num_rows($chkDest) > 0;
                $chkTitle = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_titulo'");
                $hasTitle = $chkTitle && mysqli_num_rows($chkTitle) > 0;
                $chkId = mysqli_query($link, "SHOW COLUMNS FROM comunicados LIKE 'comu_nb_id'");
                $hasId = $chkId && mysqli_num_rows($chkId) > 0;
                $fields = "comu_tx_texto".($hasTitle ? ", comu_tx_titulo" : "").($hasId ? ", comu_nb_id" : "").($hasDate ? ", comu_tx_dataCadastro" : "");
                $base = "SELECT ".$fields." FROM comunicados";
                if($hasDest){ $base .= " WHERE comu_tx_destino = '".mysqli_real_escape_string($link, $destino)."'"; }
                $order = $hasDate ? " ORDER BY comu_tx_dataCadastro DESC, comu_nb_id DESC" : " ORDER BY comu_nb_id DESC";
                $res = mysqli_query($link, $base.$order." LIMIT 1");
                if(!$res){
                    echo "<script>console.warn('DB2: erro ao consultar comunicados: "+mysqli_error($link)+"');</script>";
                }

                if($res){
                    $row = mysqli_fetch_assoc($res);
                    if(!empty($row["comu_tx_texto"])){
                        $texto = $row["comu_tx_texto"];
                        if(!empty($row["comu_tx_titulo"])) $titulo = $row["comu_tx_titulo"];
                        $identificador = !empty($row["comu_nb_id"]) ? (string)$row["comu_nb_id"] : (!empty($row["comu_tx_dataCadastro"]) ? (string)$row["comu_tx_dataCadastro"] : substr(sha1(($titulo??"")."|".$texto),0,16));
                        $fonte = "Banco Externo (DB2)";
                        echo "<script>console.log('DB2: comunicado obtido (".strlen($texto)." chars)');</script>";
                        if(!empty($titulo)) echo "<script>console.log('DB2: título: ".substr(json_encode($titulo),0,60)."...');</script>";
                    } else {
                        echo "<script>console.log('DB2: nenhum texto retornado');</script>";
                    }
                }
            } else {
                echo "<script>console.log('DB2: Tabela comunicados não encontrada');</script>";
            }
            mysqli_close($link);
        } else {
            echo "<script>console.warn('DB2: Não foi possível conectar: " . mysqli_connect_error() . "');</script>";
        }
    } else {
        echo "<script>console.warn('DB2: env incompleto, verifique DB_HOST2/DB_USER2/DB_NAME2');</script>";
    }

    // 2. FALLBACK: TENTA NO DB PRINCIPAL ($conn)
    if(empty($texto) && isset($conn)){
        echo "<script>console.log('Fallback: tentando banco principal');</script>";
        // Verifica se a tabela existe no banco principal
        $chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'comunicados'");
        if($chk2 && mysqli_num_rows($chk2) > 0){
            $chkCol2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_dataCadastro'");
            $hasDate2 = $chkCol2 && mysqli_num_rows($chkCol2) > 0;
            $chkDest2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_destino'");
            $hasDest2 = $chkDest2 && mysqli_num_rows($chkDest2) > 0;
            $chkTitle2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_tx_titulo'");
            $hasTitle2 = $chkTitle2 && mysqli_num_rows($chkTitle2) > 0;
            $chkId2 = mysqli_query($conn, "SHOW COLUMNS FROM comunicados LIKE 'comu_nb_id'");
            $hasId2 = $chkId2 && mysqli_num_rows($chkId2) > 0;
            $fields2 = "comu_tx_texto".($hasTitle2 ? ", comu_tx_titulo" : "").($hasId2 ? ", comu_nb_id" : "").($hasDate2 ? ", comu_tx_dataCadastro" : "");
            $base2 = "SELECT ".$fields2." FROM comunicados";
            if($hasDest2){ $base2 .= " WHERE comu_tx_destino = '".mysqli_real_escape_string($conn, $destino)."'"; }
            $order2 = $hasDate2 ? " ORDER BY comu_tx_dataCadastro DESC, comu_nb_id DESC" : " ORDER BY comu_nb_id DESC";
            $r2 = mysqli_query($conn, $base2.$order2." LIMIT 1");
            if($r2){
                $rw2 = mysqli_fetch_assoc($r2);
                if(!empty($rw2["comu_tx_texto"])){
                    $texto = $rw2["comu_tx_texto"];
                    if(!empty($rw2["comu_tx_titulo"])) $titulo = $rw2["comu_tx_titulo"];
                    $fonte = "Banco Principal (Local)";
                    echo "<script>console.log('Fallback: comunicado obtido (".strlen($texto)." chars)');</script>";
                    if(empty($identificador)){
                        $identificador = !empty($rw2["comu_nb_id"]) ? (string)$rw2["comu_nb_id"] : (!empty($rw2["comu_tx_dataCadastro"]) ? (string)$rw2["comu_tx_dataCadastro"] : substr(sha1(($titulo??"")."|".$texto),0,16));
                    }
                }
            }
        } else {
            echo "<script>console.log('DB Principal: Tabela comunicados não existe.');</script>";
        }
    }

    // 3. EXIBIÇÃO
    if(!empty($texto)){
        $allowed = '<a><b><strong><i><em><u><br><p><ul><ol><li><h1><h2><h3><h4>';
        $conteudo = strip_tags($texto, $allowed);
        $conteudo = preg_replace('/on\\w+\s*=\s*"[^"]*"/i', '', $conteudo);
        $conteudo = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $conteudo);
        if(strpos($conteudo, '<') === false){
            $conteudo = nl2br(htmlspecialchars($conteudo));
            $conteudo = preg_replace('~(https?://[\w\-\./?%&=+#]+)~i', '<a href="$1" target="_blank" rel="noopener">$1</a>', $conteudo);
        }
        $jsConteudo = json_encode($conteudo);
        
        // Título
        $titulo = trim((string)$titulo);
        if($titulo === "") $titulo = "Comunicado";
        $tituloSafe = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $jsTitulo = json_encode($tituloSafe);
        $jsId = json_encode($identificador);
        
        echo "<script>console.log('Comunicado encontrado via: " . $fonte . "');</script>";
        
        // Modal Bootstrap
        echo "
        <div class='modal fade' id='comunicadoModal' tabindex='-1' role='dialog' aria-hidden='true'>
            <div class='modal-dialog modal-lg' style='max-width: 1200px; width: 100%;'>
                <div class='modal-content'>
                    <div class='modal-header'>
                    <button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <h4 class='modal-title'>".$tituloSafe."</h4>
                    </div>
                    <div class='modal-body'>".$conteudo."</div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-primary' data-dismiss='modal'>Ok</button>
                    </div>
                </div>
            </div>
        </div>";

        echo "<script>
        (function(){
            var id = ".$jsId.";
            function getCookie(n){ var m=document.cookie.match(new RegExp('(?:^|; )'+n.replace(/([.$?*|{}()\\[\\]\\/\\+^])/g,'\\\\$1')+'=([^;]*)')); return m? decodeURIComponent(m[1]) : null; }
            function setCookie(n,v,d){ var dt=new Date(); dt.setDate(dt.getDate()+(d||365)); document.cookie=n+'='+encodeURIComponent(v)+'; path=/; expires='+dt.toUTCString(); }
            if(getCookie('comunicado_visto') === id){ return; }
            function markSeen(){ setCookie('comunicado_visto', id, 365); }
            (function(){ if(document.getElementById('comunicadoNative')) return; var ov=document.createElement('div'); ov.id='comunicadoNative'; ov.style.position='fixed'; ov.style.zIndex='2147483647'; ov.style.left='0'; ov.style.top='0'; ov.style.right='0'; ov.style.bottom='0'; ov.style.background='rgba(0,0,0,.5)'; ov.style.display='flex'; ov.style.alignItems='center'; ov.style.justifyContent='center'; var box=document.createElement('div'); box.style.background='#fff'; box.style.maxWidth='960px'; box.style.width='95%'; box.style.borderRadius='6px'; box.style.boxShadow='0 6px 20px rgba(0,0,0,.2)'; box.style.padding='16px'; var h=document.createElement('div'); h.style.fontSize='18px'; h.style.fontWeight='600'; h.style.marginBottom='8px'; h.textContent = ".$jsTitulo."; var b=document.createElement('div'); b.innerHTML = ".$jsConteudo."; var f=document.createElement('div'); f.style.textAlign='right'; f.style.marginTop='12px'; var bt=document.createElement('button'); bt.textContent='Ok'; bt.className='btn btn-primary'; bt.onclick=function(){ markSeen(); document.body.removeChild(ov) }; f.appendChild(bt); box.appendChild(h); box.appendChild(b); box.appendChild(f); ov.appendChild(box); document.body.appendChild(ov); })();
            var t=0, iv=setInterval(function(){ t++; if(window.jQuery && $.fn.modal){ clearInterval(iv); try{ $('#comunicadoModal').modal('show'); $('#comunicadoModal .btn.btn-primary').on('click', function(){ markSeen(); }); var ov=document.getElementById('comunicadoNative'); if(ov) document.body.removeChild(ov); }catch(e){} } else if(t>50){ clearInterval(iv); } },100);
        })();
        </script>";
    } else {
        echo "<script>console.warn('Nenhum comunicado encontrado em nenhum dos bancos.');</script>";
    }
}
?>