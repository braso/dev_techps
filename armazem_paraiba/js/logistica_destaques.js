// Destaque de linhas do grid por Ação Esperada (regra de igualdade: POI + Ação no grid)
(function () {
  var STORAGE_KEY = "logistica_destaques_config";
  var destaques = [];

  function normalizar(v) {
    return String(v == null ? "" : v).trim().toLowerCase();
  }

  function carregar() {
    try {
      var s = localStorage.getItem(STORAGE_KEY);
      destaques = s ? JSON.parse(s) : [];
      if (!Array.isArray(destaques)) destaques = [];
    } catch (e) {
      destaques = [];
    }
  }

  function salvar() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(destaques)); } catch (e) {}
  }

  // Coluna 5 do grid = "Ação Esperada" (texto completo da célula)
  function acaoDaCelula(tr) {
    var cells = tr.getElementsByTagName("td");
    if (!cells || cells.length < 6) return "";
    return String(cells[5].textContent || "").trim();
  }

  // Só destaca linhas cuja Ação Esperada seja EXATAMENTE igual à configurada
  function aplicarDestaques() {
    var rows = document.querySelectorAll("#results table tbody tr");

    for (var i = 0; i < rows.length; i++) {
      rows[i].classList.remove("destaque-linha");
      rows[i].style.removeProperty("--destaque-cor");
    }

    if (!destaques.length) return;

    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var acao = normalizar(acaoDaCelula(tr));
      if (!acao) continue;

      for (var j = 0; j < destaques.length; j++) {
        var cfg = destaques[j];
        if (!cfg || !cfg.acao || !cfg.cor) continue;
        if (normalizar(cfg.acao) !== acao) continue;

        tr.classList.add("destaque-linha");
        tr.style.setProperty("--destaque-cor", cfg.cor);
        break;
      }
    }
  }

  // Ações esperadas cadastradas no módulo de POI (mesma origem da coluna
  // "Ação Esperada"). Fallback: valores distintos encontrados no próprio grid.
  function acoesDisponiveis() {
    var set = {};
    var pois = window.pois || [];
    for (var i = 0; i < pois.length; i++) {
      var p = pois[i];
      if (!p) continue;
      var t = String(p.poi_tx_acoes_esperadas || "").trim();
      if (t) set[t] = true;
    }

    if (!Object.keys(set).length) {
      var rows = document.querySelectorAll("#results table tbody tr");
      for (var j = 0; j < rows.length; j++) {
        var cells = rows[j].getElementsByTagName("td");
        if (cells.length < 6) continue;
        var t = String(cells[5].textContent || "").trim();
        if (t) set[t] = true;
      }
    }

    return Object.keys(set);
  }

  function popularAcoes() {
    var sel = document.getElementById("destaqueAcao");
    if (!sel) return;
    var atual = sel.value;
    sel.innerHTML = '<option value="">Selecione a ação esperada</option>';
    acoesDisponiveis().forEach(function (a) {
      var op = document.createElement("option");
      op.value = a;
      op.textContent = a;
      sel.appendChild(op);
    });
    if (atual) {
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === atual) {
          sel.selectedIndex = i;
          break;
        }
      }
    }
  }

  function renderLista() {
    var ul = document.getElementById("destaquesList");
    if (!ul) return;
    ul.innerHTML = "";

    if (!destaques.length) {
      var vazio = document.createElement("li");
      vazio.className = "list-group-item text-muted";
      vazio.textContent = "Nenhuma configuração. Adicione uma Ação Esperada e uma cor.";
      ul.appendChild(vazio);
      return;
    }

    destaques.forEach(function (cfg, idx) {
      var li = document.createElement("li");
      li.className = "list-group-item d-flex justify-content-between align-items-center";

      var label = document.createElement("span");
      var swatch = document.createElement("span");
      swatch.style.cssText = "display:inline-block;width:16px;height:16px;border-radius:3px;background:" + cfg.cor + ";vertical-align:middle;margin-right:8px;";
      label.appendChild(swatch);
      label.appendChild(document.createTextNode(cfg.acao));

      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-danger btn-sm";
      btn.textContent = "Remover";
      btn.addEventListener("click", function () {
        destaques.splice(idx, 1);
        salvar();
        renderLista();
        aplicarDestaques();
      });

      li.appendChild(label);
      li.appendChild(btn);
      ul.appendChild(li);
    });
  }

  function adicionar() {
    var acaoSel = document.getElementById("destaqueAcao");
    var corEl = document.getElementById("destaqueCor");
    if (!acaoSel || !corEl) return;

    var acao = (acaoSel.value || "").trim();
    if (!acao) { alert("Selecione a Ação Esperada."); acaoSel.focus(); return; }

    var cor = corEl.value || "#ff0000";

    for (var i = 0; i < destaques.length; i++) {
      if (normalizar(destaques[i].acao) === normalizar(acao)) {
        destaques[i].cor = cor;
        salvar();
        renderLista();
        aplicarDestaques();
        return;
      }
    }

    destaques.push({ acao: acao, cor: cor });
    salvar();
    renderLista();
    aplicarDestaques();
  }

  function abrirModal() {
    popularAcoes();
    renderLista();
    var modal = document.getElementById("configDestaqueModal");
    if (modal) modal.style.display = "flex";
  }

  function fecharModal() {
    var modal = document.getElementById("configDestaqueModal");
    if (modal) modal.style.display = "none";
  }

  function init() {
    carregar();

    var btn = document.getElementById("configDestaqueBtn");
    if (btn) btn.addEventListener("click", abrirModal);

    var addBtn = document.getElementById("addDestaqueBtn");
    if (addBtn) addBtn.addEventListener("click", adicionar);

    var applyBtn = document.getElementById("aplicarDestaquesBtn");
    if (applyBtn) applyBtn.addEventListener("click", function () { aplicarDestaques(); });

    // Reaplica os destaques sempre que o grid for re-renderizado
    var resultsDiv = document.getElementById("results");
    if (resultsDiv && window.MutationObserver) {
      new MutationObserver(aplicarDestaques).observe(resultsDiv, { childList: true, subtree: true });
    }

    aplicarDestaques();
  }

  window.fecharModalDestaque = fecharModal;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();