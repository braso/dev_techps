const API = window.ROLETA_API || 'api.php';
const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');
const spinBtn = document.getElementById('spinBtn');
const codeInput = document.getElementById('codigoInput');
const btnValidar = document.getElementById('btnValidar');
const codeMsg = document.getElementById('codeMsg');
const codeBox = document.getElementById('codeBox');
const girosRestantesEl = document.getElementById('girosRestantes');
const resultado = document.getElementById('resultado');
const resTitulo = document.getElementById('resTitulo');
const resFoto = document.getElementById('resFoto');
const resNome = document.getElementById('resNome');
const btnFecharResultado = document.getElementById('btnFecharResultado');
const btnFecharResultadoX = document.getElementById('btnFecharResultadoX');
const btnTrocarCodigo = document.getElementById('btnTrocarCodigo');

let itens = [];
let codigoAtual = '';
let modoLivre = window.ROLETA_MODO_LIVRE || false;
let girando = false;
let angulo = 0;
let resultadoGirarNovamente = false;

function itemDisponivel(it) {
  if (!it) return false;
  if (typeof it.disponivel !== 'undefined') return !!it.disponivel;
  return Number(it.quantidade) > 0;
}

// Quebra o nome em linhas que caibam na largura da fatia
function wrapCanvasText(text, maxWidth, maxLines) {
  const words = String(text || '').trim().split(/\s+/).filter(Boolean);
  const lines = [];
  let current = '';
  for (const word of words) {
    const test = current ? current + ' ' + word : word;
    if (ctx.measureText(test).width <= maxWidth) {
      current = test;
      continue;
    }
    if (current) {
      lines.push(current);
      current = '';
    }
    // palavra maior que a largura: divide por caracteres
    let rest = word;
    while (ctx.measureText(rest).width > maxWidth && rest.length > 1) {
      let cut = rest.length - 1;
      while (cut > 1 && ctx.measureText(rest.slice(0, cut)).width > maxWidth) cut--;
      lines.push(rest.slice(0, cut));
      rest = rest.slice(cut);
    }
    current = rest;
  }
  if (current) lines.push(current);
  if (lines.length === 0) return [''];
  if (lines.length > maxLines) {
    const kept = lines.slice(0, maxLines);
    let last = kept[kept.length - 1];
    while (last.length > 1 && ctx.measureText(last + '…').width > maxWidth) last = last.slice(0, -1);
    kept[kept.length - 1] = last.replace(/…$/, '') + '…';
    return kept;
  }
  return lines;
}

function drawWheel() {
  const cx = canvas.width/2, cy = canvas.height/2, r = Math.min(cx,cy)-10;
  ctx.clearRect(0,0,canvas.width,canvas.height);
  if (itens.length === 0) {
    ctx.fillStyle='#e0e0e0'; ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='#999'; ctx.font='18px sans-serif'; ctx.textAlign='center'; ctx.fillText('Sem prêmios',cx,cy);
    return;
  }
  const n = itens.length, slice = (Math.PI*2)/n;
  const fontSize = n <= 6 ? 17 : n <= 9 ? 15 : n <= 12 ? 13 : n <= 16 ? 12 : 11;
  const lineHeight = fontSize + 3;
  const maxLines = n <= 10 ? 3 : 2;
  const maxTextWidth = Math.max(60, r - 64);
  for (let i=0;i<n;i++){
    const item = itens[i];
    const disponivel = itemDisponivel(item);
    const start = angulo + i*slice, end = start+slice;
    ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,start,end); ctx.closePath();
    ctx.fillStyle = item.cor || '#888'; ctx.fill();
    // brindes esgotados ficam acinzentados, mas continuam visíveis
    if (!disponivel) { ctx.fillStyle='rgba(105,105,105,0.7)'; ctx.fill(); }
    ctx.strokeStyle='rgba(255,255,255,0.6)'; ctx.lineWidth=2; ctx.stroke();
    ctx.save(); ctx.translate(cx,cy); ctx.rotate(start+slice/2);
    ctx.textAlign='right'; ctx.textBaseline='middle';
    ctx.fillStyle = disponivel ? '#fff' : '#f0f0f0';
    ctx.font='bold '+fontSize+'px sans-serif'; ctx.shadowColor='rgba(0,0,0,0.5)'; ctx.shadowBlur=4;
    const lines = wrapCanvasText(item.nome, maxTextWidth, maxLines);
    const blockHeight = (lines.length - 1) * lineHeight;
    lines.forEach((line, idx) => {
      ctx.fillText(line, r-12, idx*lineHeight - blockHeight/2);
    });
    ctx.restore();
  }
  ctx.beginPath(); ctx.arc(cx,cy,46,0,Math.PI*2); ctx.fillStyle='#ffffff'; ctx.fill();
  ctx.strokeStyle='#32c5d2'; ctx.lineWidth=4; ctx.stroke();
  ctx.fillStyle='#32c5d2'; ctx.font='bold 14px sans-serif'; ctx.textAlign='center'; ctx.fillText('GIRAR', cx, cy+5);
}

function msg(text,type=''){ codeMsg.textContent=text; codeMsg.className='msg '+(type==='error'?'error':type==='ok'?'ok':''); }

function setSpinEnabled(enabled){ spinBtn.disabled = !enabled; }

async function validarCodigo() {
  const codigo = codeInput.value.trim().toUpperCase();
  const body = codigo ? {codigo} : {};
  let r, d;
  try {
    r = await fetch(API+'?action=validar', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    d = await r.json();
  } catch(e){ msg('Erro de rede','error'); return; }
  if (!r.ok){ msg(d.error||'Erro','error'); setSpinEnabled(modoLivre); return; }
  codigoAtual = codigo;
  modoLivre = !!d.modo_livre;
  if (d.giros_restantes !== null && d.giros_restantes !== undefined) girosRestantesEl.textContent = d.giros_restantes;
  else girosRestantesEl.textContent = '∞';
  const welcomeBox = document.getElementById('welcomeBox');
  if (d.primeiro_nome){ welcomeBox.textContent = `Olá, ${d.primeiro_nome}! Boa sorte na roleta Techps 🎡`; welcomeBox.classList.remove('hidden'); }
  else welcomeBox.classList.add('hidden');
  await carregarItens();
  msg('Código validado! Pode girar.','ok');
  setSpinEnabled(true);
}

btnValidar.addEventListener('click', validarCodigo);
codeInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter') validarCodigo(); });
btnTrocarCodigo.addEventListener('click', ()=>{ codigoAtual=''; codeInput.value=''; girosRestantesEl.textContent='-'; document.getElementById('welcomeBox').classList.add('hidden'); msg('',''); setSpinEnabled(modoLivre); });

async function carregarItens() {
  try {
    const r = await fetch(API+'?action=listar_premios');
    itens = await r.json();
  } catch(e){ itens = []; }
  drawWheel();
}

function easeOutCubic(t){ return 1 - Math.pow(1-t,3); }

async function girar() {
  if (girando) return;
  if (itens.length===0){ alert('Sem prêmios cadastrados'); return; }
  if (!itens.some(itemDisponivel)){ alert('Todos os brindes estão esgotados no momento.'); return; }
  if (!modoLivre && !codigoAtual){ msg('Digite e valide seu código abaixo.','error'); codeInput.focus(); return; }
  girando=true; spinBtn.disabled=true;
  let resultadoData;
  try {
    const r = await fetch(API+'?action=girar', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({codigo:codigoAtual})});
    const d = await r.json();
    if (!r.ok){ alert(d.error||'Erro ao girar'); girando=false; spinBtn.disabled=false; return; }
    resultadoData = d;
  } catch(e){ alert('Erro de rede'); girando=false; spinBtn.disabled=false; return; }
  const winIdx = itens.findIndex(it=>it.id===resultadoData.premio.id);
  const n = itens.length, slice=(Math.PI*2)/n;
  const base = -Math.PI/2 - (winIdx*slice + slice/2);
  const startAngulo = angulo;
  // Garante giro completo para frente: pelo menos 5 e até 10 voltas além do ângulo atual
  const voltas = 5 + Math.floor(Math.random()*6);
  let finalAngulo = base;
  while (finalAngulo < startAngulo + 5 * Math.PI * 2) {
    finalAngulo += Math.PI * 2;
  }
  finalAngulo += (voltas - 5) * Math.PI * 2;
  const delta = finalAngulo - startAngulo;
  const duracao = 5500; const t0 = performance.now();
  function frame(now){
    const t = Math.min(1,(now-t0)/duracao); const e = easeOutCubic(t);
    angulo = startAngulo + delta*e; drawWheel();
    if (t<1) requestAnimationFrame(frame);
    else {
      angulo = finalAngulo; drawWheel(); girando=false; spinBtn.disabled=false;
      if (resultadoData.giros_restantes !== null && resultadoData.giros_restantes !== undefined) girosRestantesEl.textContent = resultadoData.giros_restantes;
      else girosRestantesEl.textContent = '∞';
      mostrarResultado(resultadoData); carregarItens();
    }
  }
  requestAnimationFrame(frame);
}

function mostrarResultado(data){
  const premio = data.premio;
  resultadoGirarNovamente = !!data.girar_novamente;
  resTitulo.textContent = resultadoGirarNovamente ? '🎡 Gire Novamente!' : '🎉 Você ganhou!';
  resNome.textContent = premio.nome;
  btnFecharResultado.textContent = resultadoGirarNovamente ? 'Girar Novamente' : 'Continuar';
  if (premio.foto){ resFoto.src = premio.foto; resFoto.style.display=''; } else resFoto.style.display='none';
  resultado.classList.remove('hidden');
  document.body.animate([{filter:'brightness(1)'},{filter:'brightness(1.15)'},{filter:'brightness(1)'}],{duration:600});
}
btnFecharResultado.addEventListener('click', ()=>{
  resultado.classList.add('hidden');
  if (resultadoGirarNovamente){ resultadoGirarNovamente=false; girar(); }
});
btnFecharResultadoX.addEventListener('click', ()=>{
  resultado.classList.add('hidden');
  resultadoGirarNovamente=false;
});
spinBtn.addEventListener('click', girar);

// init
if (modoLivre){ setSpinEnabled(true); girosRestantesEl.textContent='∞'; }
carregarItens();
drawWheel();