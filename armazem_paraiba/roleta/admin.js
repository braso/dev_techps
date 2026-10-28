const API = window.ROLETA_API || 'api.php';
const $ = (s)=>document.querySelector(s);
const $$ = (s)=>document.querySelectorAll(s);
let premiosBonusCache = null;
let leadsCache = [];
let docCheckSeq = 0;
let docExisteLead = false;
let docUltimaConsulta = '';
$$('.tab').forEach(t=>{ t.addEventListener('click',()=>{ $$('.tab').forEach(x=>x.classList.remove('active')); $$('.tabpane').forEach(x=>x.classList.remove('active')); t.classList.add('active'); document.getElementById('tab-'+t.dataset.tab).classList.add('active'); if(t.dataset.tab==='dashboard')carregarStats(); if(t.dataset.tab==='premios')carregarPremios(); if(t.dataset.tab==='leads')carregarLeads(); if(t.dataset.tab==='codigos')carregarCodigos(); if(t.dataset.tab==='historico')carregarHistorico(); if(t.dataset.tab==='config')carregarConfig(); }); });
function escapeHtml(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function somenteDigitos(v){ return String(v??'').replace(/\D+/g,''); }
function formatarDocumento(v){
  const d=somenteDigitos(v).slice(0,14);
  if(!d) return '';
  if(d.length<=11){
    if(d.length<=3) return d;
    if(d.length<=6) return d.slice(0,3)+'.'+d.slice(3);
    if(d.length<=9) return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
    return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
  }
  if(d.length<=12) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
  return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
}
function formatarTelefone(v){
  const d=somenteDigitos(v).slice(0,11);
  if(!d) return '';
  if(d.length<2) return '('+d;
  if(d.length===2) return '('+d+') ';
  if(d.length<=6) return '('+d.slice(0,2)+') '+d.slice(2);
  if(d.length<=10) return '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);
  return '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);
}
function validarCpf(cpf){
  const d=somenteDigitos(cpf);
  if(d.length!==11 || /^(\d)\1{10}$/.test(d)) return false;
  for(let t=9;t<11;t++){
    let soma=0;
    for(let i=0;i<t;i++) soma+=parseInt(d[i],10)*(t+1-i);
    let dig=(soma*10)%11;
    if(dig===10) dig=0;
    if(parseInt(d[t],10)!==dig) return false;
  }
  return true;
}
function validarCnpj(cnpj){
  const d=somenteDigitos(cnpj);
  if(d.length!==14 || /^(\d)\1{13}$/.test(d)) return false;
  const calc=(base,pesos)=>{ let soma=0; for(let i=0;i<base.length;i++) soma+=parseInt(base[i],10)*pesos[i]; const resto=soma%11; return resto<2?0:11-resto; };
  const d1=calc(d.slice(0,12),[5,4,3,2,9,8,7,6,5,4,3,2]);
  if(parseInt(d[12],10)!==d1) return false;
  const d2=calc(d.slice(0,13),[6,5,4,3,2,9,8,7,6,5,4,3,2]);
  return parseInt(d[13],10)===d2;
}
function validarDocumento(v){
  const d=somenteDigitos(v);
  if(d.length===11) return validarCpf(d);
  if(d.length===14) return validarCnpj(d);
  return false;
}
function emailValido(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||'').trim()); }
const mensagensErroLead={nome_obrigatorio:'Informe o nome / razão social.',email_invalido:'E-mail inválido.',telefone_invalido:'Telefone inválido. Informe DDD + número.',documento_invalido:'CPF/CNPJ inválido.',documento_duplicado:'Este CPF/CNPJ já está cadastrado.'};
async function api(action, opts={}){ const url = API+'?action='+action; const r = await fetch(url, opts); return {r, d: await r.json().catch(()=>({}))}; }

async function carregarStats(){ const {d}=await api('stats'); $('#st-premios').textContent=d.total_premios; $('#st-estoque').textContent=d.estoque_total; $('#st-hoje').textContent=d.giros_hoje; $('#st-total').textContent=d.giros_total; $('#st-codigos').textContent=d.codigos_ativos; $('#st-leads').textContent=d.leads_total; }

async function carregarPremios(){
  premiosBonusCache=null;
  const {d}=await api('list_premios_all');
  const itens=Array.isArray(d)?d:[];
  const tbody=$('#tbodyPremios'); tbody.innerHTML='';
  const pt=itens.reduce((s,i)=>s+(i.peso||1),0)||1;
  itens.forEach(p=>{
    const pct=((p.peso/pt)*100).toFixed(1);
    const estoqueHtml=p.girar_novamente
      ? '<strong title="Estoque ilimitado">∞</strong>'
      : `<span>${p.quantidade}</span> <button class="btn btn-secondary btn-small" onclick="ajustarEstoque(${p.id},1)">+1</button> <button class="btn btn-secondary btn-small" onclick="ajustarEstoque(${p.id},-1)">-1</button>`;
    const tipoHtml=p.girar_novamente?' <span class="badge spin">Gire Novamente</span>':'';
    const tr=document.createElement('tr');
    tr.innerHTML=`<td>${p.foto?`<img src="${p.foto}" alt="">`:'—'}</td><td><code>${p.codigo}</code></td><td>${escapeHtml(p.nome)}${tipoHtml}</td><td><span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:${p.cor};border:1px solid #444"></span></td><td>${p.peso}</td><td>${pct}%</td><td>${estoqueHtml}</td><td><span class="badge ${p.ativo?'on':'off'}">${p.ativo?'Sim':'Não'}</span></td><td><button class="btn btn-secondary btn-small" onclick="editarPremio(${p.id})">Editar</button> <button class="btn btn-danger btn-small" onclick="removerPremio(${p.id})">Excluir</button></td>`;
    tbody.appendChild(tr);
  });
}

function atualizarQtdPremio(){ $('#pQtd').disabled = $('#pGirarNovamente').checked; }
function abrirFormPremio(){ $('#formPremio').classList.remove('hidden'); $('#formPremioTitulo').textContent='Novo Prêmio'; $('#premioId').value=''; $('#pNome').value=''; $('#pCor').value='#30a0b8'; $('#pPeso').value=1; $('#pQtd').value=0; $('#pQtd').disabled=false; $('#pAtivo').value='1'; $('#pGirarNovamente').checked=false; $('#pFoto').value=''; }
function fecharFormPremio(){ $('#formPremio').classList.add('hidden'); }
async function editarPremio(id){ const {d}=await api('list_premios_all'); const p=d.find(x=>x.id===id); if(!p)return; abrirFormPremio(); $('#formPremioTitulo').textContent='Editar Prêmio'; $('#premioId').value=p.id; $('#pNome').value=p.nome; $('#pCor').value=p.cor; $('#pPeso').value=p.peso; $('#pQtd').value=p.quantidade; $('#pAtivo').value=p.ativo?'1':'0'; $('#pGirarNovamente').checked=!!p.girar_novamente; atualizarQtdPremio(); }
$('#pGirarNovamente').addEventListener('change', atualizarQtdPremio);
$('#formPremio').addEventListener('submit', async (e)=>{ e.preventDefault(); const id=$('#premioId').value; const fd=new FormData(); fd.append('nome',$('#pNome').value.trim()); fd.append('cor',$('#pCor').value); fd.append('peso',$('#pPeso').value); fd.append('quantidade',$('#pGirarNovamente').checked?'0':$('#pQtd').value); fd.append('ativo',$('#pAtivo').value); fd.append('girar_novamente',$('#pGirarNovamente').checked?'1':'0'); const foto=$('#pFoto').files[0]; if(foto) fd.append('foto',foto); fd.append('id',id); const {r,d}=await api(id?'edit_premio':'create_premio',{method:'POST',body:fd}); if(r.ok){ fecharFormPremio(); carregarPremios(); carregarStats(); } else alert(d.error||'Erro'); });
async function removerPremio(id){ if(!confirm('Excluir?'))return; const {r}=await api('delete_premio',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); if(r.ok){carregarPremios();carregarStats();} }
async function ajustarEstoque(id,delta){ const {r}=await api('ajustar_estoque',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,delta})}); if(r.ok) carregarPremios(); }

async function carregarPremiosBonusCache(force=false){ if(!premiosBonusCache || force){ const {d}=await api('list_premios_all'); premiosBonusCache = Array.isArray(d) ? d : []; } return premiosBonusCache; }
function renderBonusPremios(selecionados=[]){
  const box=$('#bonusPremiosList');
  if(!premiosBonusCache || premiosBonusCache.length===0){ box.innerHTML='<p class="muted small">Nenhum brinde cadastrado. Cadastre um brinde na aba Prêmios.</p>'; return; }
  const mapa={}; (selecionados||[]).forEach(b=>{ mapa[Number(b.premio_id)]=Number(b.bonus)||0; });
  const totalPeso=premiosBonusCache.reduce((s,p)=>s+Math.max(1,Number(p.peso)||1),0)||1;
  box.innerHTML=premiosBonusCache.map(p=>{
    const peso=Math.max(1,Number(p.peso)||1);
    const pct=((peso/totalPeso)*100).toFixed(1);
    const status=!Number(p.ativo)?' · inativo':(Number(p.quantidade)<=0?' · sem estoque':'');
    const atual=mapa[Number(p.id)]||0;
    return `<div class="bonus-row${Number(p.ativo)?'':' is-off'}">
      <div class="bonus-info">
        <span class="bonus-dot" style="background:${p.cor||'#888'}"></span>
        <span class="bonus-nome">${escapeHtml(p.nome)}</span>
        <span class="muted small">peso ${peso} · ${pct}%${status}</span>
      </div>
      <select class="bonus-select" data-premio-id="${p.id}">
        <option value="0"${atual===0?' selected':''}>Sem bônus</option>
        <option value="25"${atual===25?' selected':''}>+25% de chance</option>
        <option value="50"${atual===50?' selected':''}>+50% de chance</option>
        <option value="75"${atual===75?' selected':''}>+75% de chance</option>
      </select>
    </div>`;
  }).join('');
}
function coletarBonusPremios(){ return Array.from(document.querySelectorAll('#bonusPremiosList .bonus-select')).map(sel=>({premio_id:Number(sel.dataset.premioId),bonus:Number(sel.value)})).filter(b=>b.bonus>0); }

function normalizarBusca(v){ return String(v??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); }
function renderLeads(){
  const tbody=$('#tbodyLeads'); tbody.innerHTML='';
  const termo=$('#filtroLeads').value.trim();
  const termoNorm=normalizarBusca(termo);
  const termoDigitos=somenteDigitos(termo);
  const lista=leadsCache.filter(l=>{
    if(!termo) return true;
    if(termoNorm && normalizarBusca(l.nome).includes(termoNorm)) return true;
    if(termoDigitos && somenteDigitos(l.documento).includes(termoDigitos)) return true;
    return false;
  });
  if(lista.length===0){
    const tr=document.createElement('tr');
    tr.innerHTML='<td colspan="14" class="muted">Nenhum cadastro encontrado.</td>';
    tbody.appendChild(tr);
    return;
  }
  lista.forEach(l=>{
    const bonusTxt=(l.bonus&&l.bonus.length)?l.bonus.map(b=>`${escapeHtml(b.nome)} <span class="bonus-tag">+${b.bonus}%</span>`).join('<br>'):'—';
    const temGiros=l.giros_permitidos!==null&&l.giros_permitidos!==undefined&&l.giros_permitidos!=='';
    const permitidos=Number(l.giros_permitidos)||0, usados=Number(l.giros_usados)||0;
    const girosTxt=temGiros?`<strong>${permitidos}</strong><br><small class="muted">${usados} usados · ${Math.max(0,permitidos-usados)} restantes</small>`:'—';
    const tr=document.createElement('tr');
    tr.innerHTML=`<td>${escapeHtml(l.nome)}</td><td>${escapeHtml(formatarDocumento(l.documento)||'—')}</td><td>${escapeHtml(l.email||'—')}</td><td>${escapeHtml(formatarTelefone(l.telefone)||'—')}</td><td>${escapeHtml(l.empresa||'—')}</td><td>${escapeHtml(l.segmento||'—')}</td><td>${escapeHtml(l.funcao||'—')}</td><td><span class="obs-text" title="${escapeHtml(l.observacoes||'')}">${escapeHtml(l.observacoes||'—')}</span></td><td>${l.codigo?`<code>${l.codigo}</code>`:'—'}</td><td>${bonusTxt}</td><td>${escapeHtml(l.premios_ganhos||'—')}</td><td>${new Date(l.created_at).toLocaleString('pt-BR')}</td><td>${girosTxt}</td><td><button class="btn btn-secondary btn-small" onclick="editarLead(${l.id})">Editar</button> <button class="btn btn-danger btn-small" onclick="removerLead(${l.id})">Excluir</button></td>`;
    tbody.appendChild(tr);
  });
}
async function carregarLeads(){
  const {d}=await api('list_leads');
  leadsCache=Array.isArray(d)?d:[];
  renderLeads();
}
function limparStatusDocumento(){
  docExisteLead=false;
  docUltimaConsulta='';
  const msg=$('#docMsg');
  if(msg){ msg.textContent=''; msg.className='field-msg'; }
}
async function consultarDocumento(){
  const input=$('#lDocumento');
  const msg=$('#docMsg');
  const digitos=somenteDigitos(input.value);
  const seq=++docCheckSeq;
  docExisteLead=false;
  if(!digitos){ limparStatusDocumento(); return; }
  if(!validarDocumento(digitos)){
    msg.textContent='CPF/CNPJ inválido.'; msg.className='field-msg error'; return;
  }
  msg.textContent='Consultando CPF/CNPJ...'; msg.className='field-msg';
  try {
    const leadId=parseInt($('#lId').value)||0;
    const {r,d}=await api('check_documento',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({documento:input.value,id:leadId})});
    if(seq!==docCheckSeq) return;
    if(!r.ok){
      msg.textContent=d.error==='documento_invalido'?'CPF/CNPJ inválido.':'Não foi possível consultar o CPF/CNPJ.';
      msg.className='field-msg error';
      return;
    }
    if(typeof d.existe==='undefined'){
      msg.textContent='Não foi possível consultar o CPF/CNPJ.';
      msg.className='field-msg error';
      return;
    }
    docUltimaConsulta=leadId+':'+digitos;
    if(d.existe){
      docExisteLead=true;
      msg.textContent='CPF/CNPJ já cadastrado'+(d.nome?` para ${d.nome}`:'')+'.';
      msg.className='field-msg error';
    }else{
      msg.textContent='CPF/CNPJ disponível.';
      msg.className='field-msg ok';
    }
  } catch(e) {
    if(seq!==docCheckSeq) return;
    msg.textContent='Erro ao consultar o CPF/CNPJ.';
    msg.className='field-msg error';
  }
}
async function abrirFormLead(){
  $('#formLead').classList.remove('hidden');
  $('#formLeadTitulo').textContent='Novo Cadastro (gera código)';
  $('#btnSalvarLead').textContent='Cadastrar e Gerar Código';
  $('#lId').value='';
  ['lNome','lDocumento','lEmail','lTelefone','lEmpresa','lSegmento','lFuncao','lObservacoes'].forEach(i=>$('#'+i).value='');
  docCheckSeq++; limparStatusDocumento();
  $('#lGiros').value=1; $('#lExpira').value='';
  $('#leadMsg').textContent=''; $('#leadMsg').className='msg';
  $('#bonusPremiosList').innerHTML='<p class="muted small">Carregando brindes...</p>';
  await carregarPremiosBonusCache(true);
  renderBonusPremios([]);
}
function fecharFormLead(){ $('#formLead').classList.add('hidden'); }
async function editarLead(id){
  const {d}=await api('list_leads');
  const l=Array.isArray(d)?d.find(x=>x.id===id):null;
  if(!l){ alert('Cadastro não encontrado'); return; }
  $('#formLead').classList.remove('hidden');
  $('#formLeadTitulo').textContent='Editar Cadastro';
  $('#btnSalvarLead').textContent='Salvar Alterações';
  $('#lId').value=l.id;
  $('#lNome').value=l.nome||'';
  $('#lDocumento').value=formatarDocumento(l.documento||'');
  docCheckSeq++; limparStatusDocumento();
  $('#lEmail').value=l.email||'';
  $('#lTelefone').value=formatarTelefone(l.telefone||'');
  $('#lEmpresa').value=l.empresa||'';
  $('#lSegmento').value=l.segmento||'';
  $('#lFuncao').value=l.funcao||'';
  $('#lObservacoes').value=l.observacoes||'';
  $('#lGiros').value=l.giros_permitidos||1;
  $('#lExpira').value=l.expira_em?(String(l.expira_em).replace(' ','T').slice(0,16)):'';
  $('#leadMsg').textContent=''; $('#leadMsg').className='msg';
  $('#bonusPremiosList').innerHTML='<p class="muted small">Carregando brindes...</p>';
  await carregarPremiosBonusCache(true);
  renderBonusPremios(l.bonus||[]);
}
$('#formLead').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const docDigitado=somenteDigitos($('#lDocumento').value);
  if(docDigitado && !validarDocumento(docDigitado)){
    $('#leadMsg').textContent='CPF/CNPJ inválido. Confira os números.'; $('#leadMsg').className='msg error'; $('#lDocumento').focus(); return;
  }
  if(docDigitado){
    const chaveDoc=((parseInt($('#lId').value)||0)+':'+docDigitado);
    if(docUltimaConsulta!==chaveDoc) await consultarDocumento();
  }
  if(docExisteLead){
    $('#leadMsg').textContent='Este CPF/CNPJ já está cadastrado.'; $('#leadMsg').className='msg error'; $('#lDocumento').focus(); return;
  }
  const telDigitado=somenteDigitos($('#lTelefone').value);
  if(telDigitado && telDigitado.length!==10 && telDigitado.length!==11){
    $('#leadMsg').textContent='Telefone inválido. Informe DDD + número.'; $('#leadMsg').className='msg error'; $('#lTelefone').focus(); return;
  }
  const emailDigitado=$('#lEmail').value.trim();
  if(emailDigitado && !emailValido(emailDigitado)){
    $('#leadMsg').textContent='E-mail inválido.'; $('#leadMsg').className='msg error'; $('#lEmail').focus(); return;
  }
  const id=$('#lId').value;
  const body={nome:$('#lNome').value.trim(),documento:$('#lDocumento').value.trim()||null,email:$('#lEmail').value.trim()||null,telefone:$('#lTelefone').value.trim()||null,empresa:$('#lEmpresa').value.trim()||null,segmento:$('#lSegmento').value.trim()||null,funcao:$('#lFuncao').value.trim()||null,observacoes:$('#lObservacoes').value.trim()||null,giros:parseInt($('#lGiros').value)||1,expira_em:$('#lExpira').value||null,bonus_premios:coletarBonusPremios()};
  const {r,d}=await api(id?'edit_lead':'create_lead',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  if(r.ok){ fecharFormLead(); carregarLeads(); carregarCodigos(); carregarStats(); if(!id) alert('Código: '+d.codigo); }
  else {
    $('#leadMsg').textContent=mensagensErroLead[d.error]||d.error||'Erro'; $('#leadMsg').className='msg error';
    if(d.error==='documento_duplicado'){
      docExisteLead=true;
      const msg=$('#docMsg'); msg.textContent='CPF/CNPJ já cadastrado.'; msg.className='field-msg error';
      $('#lDocumento').focus();
    }
  }
});
$('#lDocumento').addEventListener('input', e=>{
  e.target.value=formatarDocumento(e.target.value);
  docCheckSeq++;
  limparStatusDocumento();
});
$('#lDocumento').addEventListener('blur', consultarDocumento);
$('#lTelefone').addEventListener('input', e=>{ e.target.value=formatarTelefone(e.target.value); });
$('#lEmail').addEventListener('blur', e=>{ e.target.value=e.target.value.trim().toLowerCase(); });
$('#filtroLeads').addEventListener('input', renderLeads);
async function removerLead(id){ if(!confirm('Excluir?'))return; const {r}=await api('delete_lead',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); if(r.ok){carregarLeads();carregarCodigos();carregarStats();} }

async function carregarCodigos(){ const {d}=await api('list_codigos'); const tbody=$('#tbodyCodigos'); tbody.innerHTML=''; d.forEach(c=>{ const rest=c.giros_permitidos-c.giros_usados; const dono=c.lead_nome?`${escapeHtml(c.lead_nome)}${c.lead_email?' <small class="muted">('+escapeHtml(c.lead_email)+')</small>':''}`:'—'; const tr=document.createElement('tr'); tr.innerHTML=`<td><code>${c.codigo}</code></td><td>${dono}</td><td>${c.giros_permitidos}</td><td>${c.giros_usados}</td><td>${rest}</td><td>${c.expira_em?new Date(c.expira_em).toLocaleString('pt-BR'):'—'}</td><td><span class="badge ${c.ativo?'on':'off'}">${c.ativo?'Sim':'Não'}</span></td><td><button class="btn btn-secondary btn-small" onclick="toggleCodigo(${c.id},${!c.ativo})">${c.ativo?'Desativar':'Ativar'}</button> <button class="btn btn-danger btn-small" onclick="removerCodigo(${c.id})">Excluir</button></td>`; tbody.appendChild(tr); }); }
function abrirFormCodigo(){ $('#formCodigo').classList.remove('hidden'); } function fecharFormCodigo(){ $('#formCodigo').classList.add('hidden'); }
$('#formCodigo').addEventListener('submit', async (e)=>{ e.preventDefault(); const body={giros:parseInt($('#cGiros').value),expira_em:$('#cExpira').value||null}; const {r,d}=await api('create_codigo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}); if(r.ok){ fecharFormCodigo(); carregarCodigos(); alert('Código: '+d.codigo); } else alert(d.error); });
async function toggleCodigo(id,ativo){ await api('edit_codigo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,ativo})}); carregarCodigos(); }
async function removerCodigo(id){ if(!confirm('Excluir?'))return; await api('delete_codigo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); carregarCodigos(); }

async function carregarHistorico(){ const {d}=await api('historico'); const tbody=$('#tbodyHistorico'); tbody.innerHTML=''; d.forEach((g,i)=>{ const tr=document.createElement('tr'); tr.innerHTML=`<td>${d.length-i}</td><td>${new Date(g.created_at).toLocaleString('pt-BR')}</td><td>${g.codigo_liberacao||'—'}</td><td>${escapeHtml(g.lead_nome||'—')}</td><td>${g.premio_foto?`<img src="${g.premio_foto}" style="width:28px;height:28px"> `:''}${escapeHtml(g.premio_nome)}</td>`; tbody.appendChild(tr); }); }

async function carregarConfig(){ const {d}=await api('get_config'); $('#cfgModoLivre').checked=d.modo_livre; }
async function salvarConfig(){ const body={modo_livre:$('#cfgModoLivre').checked}; const {r}=await api('set_config',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}); const el=$('#cfgMsg'); if(r.ok){el.textContent='Salvo!';el.className='msg ok';}else{el.textContent='Erro';el.className='msg error';} }

carregarStats();