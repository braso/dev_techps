<?php
    // ============================================================
    // Suporte Widget — botão flutuante + modal de abertura de chamado
    // Incluído pelo rodape.php (contex20) — vale para todas as páginas
    // de todas as empresas. Os dados são enviados à API externa
    // (server.js) que grava no banco central de suporte.
    // ============================================================

    $__supKey = $_ENV["SUPORTE_API_KEY"] ?? "";
    $__supUid = $_SESSION["user_nb_id"] ?? "";
    $__supApiUrl = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    if (empty($__supKey) || empty($__supUid) || empty($_ENV["URL_BASE"]) || $__supApiUrl === "") {
        return; // Sem chave/URL configurada ou sem sessão: widget não renderiza.
    }

    $__supEmpresa = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    $__supEmpNome = trim(strval($_SESSION["empr_tx_nome"] ?? "")) !== "" ? trim(strval($_SESSION["empr_tx_nome"])) : $__supEmpresa;
    $__supNome    = trim(strval($_SESSION["user_tx_nome"] ?? ""));
    $__supLogin   = trim(strval($_SESSION["user_tx_login"] ?? ""));
    $__supEmail   = trim(strval($_SESSION["user_tx_email"] ?? ""));
    if ($__supEmpresa === "" || $__supNome === "" || $__supLogin === "") {
        return;
    }
    // Valida formato simples do e-mail (usado para notificações do chamado).
    if (!filter_var($__supEmail, FILTER_VALIDATE_EMAIL)) {
        $__supEmail = "";
    }

    // Token de curta duração (5 min) — assinado com chave derivada por empresa.
    $__supExp    = time() + 300;
    $__supJson   = json_encode([
        "empresa"      => $__supEmpresa,
        "empresa_nome" => $__supEmpNome,
        "uid"          => $__supUid,
        "ulogin"       => $__supLogin,
        "unome"        => $__supNome,
        "user_email"   => $__supEmail,
        "exp"          => $__supExp,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $__supPayload = rtrim(strtr(base64_encode($__supJson), "+/", "-_"), "=");
    $__supKeyD    = hash_hmac("sha256", "techps_suporte|" . $__supEmpresa, $__supKey, true);
    $__supSig     = rtrim(strtr(base64_encode(hash_hmac("sha256", $__supPayload, $__supKeyD, true)), "+/", "-_"), "=");
    $__supToken   = $__supPayload . "." . $__supSig;

    $__supEndpoint = $__supApiUrl . "/suporte/tickets";
?>
<!-- ══ WIDGET SUPORTE ══════════════════════════════════════════════════ -->
<button type="button" id="suporte-widget-btn" title="Abrir chamado de suporte"
    style="position:fixed;left:24px;bottom:24px;width:56px;height:56px;border-radius:50%;border:none;background:#337ab7;color:#fff;font-size:24px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:9997;display:flex;align-items:center;justify-content:center;transition:transform .15s;">
    <i class="fa fa-life-ring" aria-hidden="true"></i>
</button>

<div id="suporte-widget-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:9998;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;max-width:480px;width:94%;max-height:92vh;overflow-y:auto;position:relative;padding:22px;box-shadow:0 10px 40px rgba(0,0,0,.3);">
        <button type="button" id="suporte-widget-fechar" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:#888;" title="Fechar">&times;</button>

        <h4 style="margin:0 0 4px;color:#333;"><i class="fa fa-life-ring" style="color:#337ab7;"></i> Suporte Técnico</h4>
        <p style="margin:0 0 16px;font-size:12px;color:#888;">Descreva o problema para a equipe TechPS. Empresa e usuário são preenchidos automaticamente.</p>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Empresa</label>
            <input type="text" id="suporte-campo-empresa" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:13px;color:#333;box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Usuário</label>
            <input type="text" id="suporte-campo-usuario" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:13px;color:#333;box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Página onde ocorreu o problema</label>
            <input type="text" id="suporte-campo-pagina" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:12px;color:#333;box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Descrição do problema <span style="color:#e74c3c;">*</span></label>
            <textarea id="suporte-campo-descricao" rows="4" maxlength="2000" placeholder="Ex.: ao tentar lançar a batida de ponto, a tela fica em branco..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            <div style="font-size:11px;color:#8a6d3b;background:#fcf8e3;border:1px solid #faebcc;border-radius:4px;padding:6px 8px;margin-top:4px;"><i class="fa fa-info-circle"></i> Atenção: se houver vídeo do problema, hospede no Google Drive ou envie um link compartilhado e cole na descrição.</div>
            <div style="text-align:right;font-size:11px;color:#aaa;" id="suporte-descricao-contador">0/2000</div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Imagens (opcional, até 5)</label>
            <input type="file" id="suporte-campo-imagens" accept="image/*" multiple style="display:none;" />
            <button type="button" id="suporte-botao-anexar" style="border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;width:100%;cursor:pointer;font-size:13px;">
                <i class="fa fa-camera"></i> Anexar imagens (JPG, PNG, WEBP, GIF — máx. 5MB cada)
            </button>
            <div id="suporte-lista-imagens" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;"></div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="suporte-botao-cancelar" style="border:1px solid #ccc;background:#fff;color:#555;border-radius:4px;padding:8px 16px;cursor:pointer;font-size:13px;">Cancelar</button>
            <button type="button" id="suporte-botao-enviar" style="border:none;background:#337ab7;color:#fff;border-radius:4px;padding:8px 18px;cursor:pointer;font-size:13px;font-weight:700;">
                <i class="fa fa-paper-plane"></i> Enviar chamado
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    var cfg = {
        endpoint: <?= json_encode($__supEndpoint) ?>,
        token:    <?= json_encode($__supToken) ?>,
        empresa:  <?= json_encode($__supEmpNome) ?>,
        usuario:  <?= json_encode($__supNome . " (" . $__supLogin . ")") ?>
    };

    var MAX_IMAGENS  = 5;
    var MAX_BYTES    = 5 * 1024 * 1024;
    var arquivos     = [];
    var urlPagina    = "";
    var enviando     = false;

    var btnAbrir   = document.getElementById('suporte-widget-btn');
    var modal      = document.getElementById('suporte-widget-modal');
    var btnFechar  = document.getElementById('suporte-widget-fechar');
    var btnCancelar= document.getElementById('suporte-botao-cancelar');
    var btnEnviar  = document.getElementById('suporte-botao-enviar');
    var btnAnexar  = document.getElementById('suporte-botao-anexar');
    var campoImg   = document.getElementById('suporte-campo-imagens');
    var txtDesc    = document.getElementById('suporte-campo-descricao');
    var contador   = document.getElementById('suporte-descricao-contador');
    var listaImg   = document.getElementById('suporte-lista-imagens');

    if (!btnAbrir || !modal) return;

    // ── Abrir: captura a URL exata no momento do clique ──
    btnAbrir.addEventListener('click', function(){
        urlPagina = window.location.href;
        document.getElementById('suporte-campo-empresa').value  = cfg.empresa;
        document.getElementById('suporte-campo-usuario').value = cfg.usuario;
        document.getElementById('suporte-campo-pagina').value  = urlPagina;
        modal.style.display = 'flex';
        txtDesc.focus();
    });

    function fechar(){
        modal.style.display = 'none';
    }
    btnFechar.addEventListener('click', fechar);
    btnCancelar.addEventListener('click', fechar);
    modal.addEventListener('click', function(e){ if (e.target === modal) fechar(); });

    // ── Descrição / contador ──
    txtDesc.addEventListener('input', function(){
        contador.textContent = txtDesc.value.length + '/2000';
    });

    // ── Anexos ──
    btnAnexar.addEventListener('click', function(){ campoImg.click(); });

    campoImg.addEventListener('change', function(){
        Array.prototype.forEach.call(campoImg.files, function(arquivo){
            if (arquivos.length >= MAX_IMAGENS) return;
            if (arquivo.type.indexOf('image/') !== 0) { alert('"' + arquivo.name + '" não é uma imagem.'); return; }
            if (arquivo.size > MAX_BYTES) { alert('"' + arquivo.name + '" excede 5MB.'); return; }
            arquivos.push(arquivo);
        });
        campoImg.value = '';
        renderizarImagens();
    });

    function renderizarImagens(){
        listaImg.innerHTML = '';
        arquivos.forEach(function(arquivo, indice){
            var item = document.createElement('div');
            item.style.cssText = 'position:relative;width:72px;height:72px;border-radius:6px;overflow:hidden;border:1px solid #ddd;';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(arquivo);
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
            var remover = document.createElement('button');
            remover.type = 'button';
            remover.innerHTML = '&times;';
            remover.title = 'Remover imagem';
            remover.style.cssText = 'position:absolute;top:2px;right:2px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;font-size:12px;line-height:1;cursor:pointer;';
            remover.addEventListener('click', function(){
                URL.revokeObjectURL(img.src);
                arquivos.splice(indice, 1);
                renderizarImagens();
            });
            item.appendChild(img);
            item.appendChild(remover);
            listaImg.appendChild(item);
        });
    }

    // ── Envio ──
    btnEnviar.addEventListener('click', function(){
        if (enviando) return;
        var descricao = txtDesc.value.trim();
        if (descricao.length < 5) { alert('Descreva o problema (mínimo 5 caracteres).'); txtDesc.focus(); return; }

        enviando = true;
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';

        var formData = new FormData();
        formData.append('descricao', descricao);
        formData.append('pagina_url', urlPagina);
        arquivos.forEach(function(arquivo){ formData.append('imagens', arquivo); });

        fetch(cfg.endpoint, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + cfg.token },
            body: formData
        }).then(function(resposta){
            return resposta.json().then(function(json){
                return { status: resposta.status, json: json };
            });
        }).then(function(res){
            if (res.json && res.json.ok) {
                var imgs = listaImg.querySelectorAll('img');
                imgs.forEach(function(i){ URL.revokeObjectURL(i.src); });
                arquivos = [];
                renderizarImagens();
                txtDesc.value = '';
                contador.textContent = '0/2000';
                fechar();
                var msg = 'Chamado nº ' + res.json.ticket_id + ' aberto com sucesso. A equipe TechPS irá analisar.';
                if (window.Swal) {
                    Swal.fire({ icon: 'success', title: 'Chamado enviado', text: msg, confirmButtonColor: '#337ab7' });
                } else {
                    alert(msg);
                }
            } else {
                var erro = (res.json && res.json.msg) ? res.json.msg : 'Falha ao enviar o chamado. Tente novamente.';
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: erro, confirmButtonColor: '#337ab7' });
                } else {
                    alert(erro);
                }
            }
        }).catch(function(){
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Sem comunicação com o servidor de suporte. Tente novamente.', confirmButtonColor: '#337ab7' });
            } else {
                alert('Sem comunicação com o servidor de suporte. Tente novamente.');
            }
        }).finally(function(){
            enviando = false;
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i class="fa fa-paper-plane"></i> Enviar chamado';
        });
    });
})();
</script>
<!-- ══ FIM WIDGET SUPORTE ══════════════════════════════════════════════ -->
