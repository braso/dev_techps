/**
 * Editor de carimbos e rubricas posicionáveis.
 *
 * Renderiza o documento com pdf.js, página por página, e põe uma camada de
 * marcas arrastáveis por cima. Cada marca guarda a posição e o tamanho em
 * FRAÇÃO da página (0 a 1), com origem no canto superior esquerdo — a mesma
 * convenção que o marcas_helper.php usa para desenhar no servidor.
 *
 * Por que fração e não pixel: a página aparece na tela num tamanho que depende
 * do zoom, da largura da janela e do aparelho. Guardar pixels amarraria a
 * marca à tela de quem posicionou.
 *
 * Por que o visualizador nativo do navegador não serve: num <iframe> não há
 * como saber a geometria da página renderizada, o scroll nem o zoom, logo não
 * há como traduzir "onde o usuário soltou" em coordenada do PDF.
 */
(function () {
	"use strict";

	var ST = {
		pdf: null,
		paginas: [],          // { numero, wrap, camada, page, vpBase }
		paginasOriginais: 0,  // sem as páginas de comprovante
		marcas: [],           // marcas desta assinatura
		seq: 0,
		rubricaDataUrl: "",
		selecionada: null,
		pronto: false,

		/* "signatario": quem está assinando posiciona as marcas dele.
		   "remetente": quem envia reserva o lugar de cada signatário, e cada
		   marca guarda a ordem do signatário no formulário. */
		modo: "signatario",
		dono: 1,

		largBase: 0,          // largura de ajuste à coluna, em pixels
		zoom: 1,              // 1 = largura da coluna
		obrigatorias: 0       // lugares reservados pelo remetente
	};

	/* Cores por signatário no modo remetente: sem isso, com três signatários
	   na mesma página ninguém sabe qual marca é de quem. */
	var CORES_DONO = ["#2563eb", "#059669", "#d97706", "#9333ea", "#dc2626", "#0891b2"];

	function corDoDono(ordem) {
		return CORES_DONO[(Math.max(1, ordem) - 1) % CORES_DONO.length];
	}

	/* Tamanho inicial de cada tipo, em fração da página. Igual à tabela de
	   assinaturaMarcas_tipos() no PHP — se mudar lá, muda aqui. */
	var TIPOS = {
		rubrica:    { rotulo: "Rubrica",    icone: "fa-pen-nib",      larg: 0.16, alt: 0.045, imagem: true },
		assinatura: { rotulo: "Assinatura", icone: "fa-signature",    larg: 0.28, alt: 0.075, imagem: true },
		carimbo:    { rotulo: "Carimbo",    icone: "fa-stamp",        larg: 0.40, alt: 0.085, bloco: true },
		nome:       { rotulo: "Nome",       icone: "fa-user",         larg: 0.30, alt: 0.022 },
		cpf:        { rotulo: "CPF",        icone: "fa-id-card",      larg: 0.22, alt: 0.022 },
		rg:         { rotulo: "RG",         icone: "fa-address-card", larg: 0.22, alt: 0.022 },
		data:       { rotulo: "Data",       icone: "fa-clock",        larg: 0.24, alt: 0.022 },
		texto:      { rotulo: "Texto",      icone: "fa-font",         larg: 0.30, alt: 0.022 }
	};

	var MIN_LARG = 0.03;
	var MIN_ALT = 0.012;

	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (html !== undefined) { e.innerHTML = html; }
		return e;
	}

	function prender(v, min, max) {
		return v < min ? min : (v > max ? max : v);
	}

	// ─── Páginas do documento original ────────────────────────────────────
	//
	// As páginas de comprovante são removidas e redesenhadas a cada
	// assinatura, então o número delas muda ao longo da coleta. Marca presa a
	// uma delas mudaria de lugar sozinha. O número de páginas de comprovante
	// está gravado no próprio PDF, no Subject/Title, pelo script.js.
	function descobrirPaginasOriginais(pdf, total) {
		return pdf.getMetadata().then(function (meta) {
			var prefixo = "TECHPS_AUDIT_V1:";
			var bruto = "";
			var info = (meta && meta.info) || {};
			[info.Subject, info.Title].forEach(function (v) {
				var s = String(v || "");
				if (!bruto && s.indexOf(prefixo) === 0) { bruto = s; }
			});
			if (!bruto) { return total; }
			try {
				var json = decodeURIComponent(escape(atob(bruto.slice(prefixo.length))));
				var est = JSON.parse(json);
				var comprovante = Number(est && est.audit_pages);
				if (isFinite(comprovante) && comprovante > 0 && comprovante < total) {
					return total - comprovante;
				}
			} catch (e) { /* PDF sem trilha legível: trata tudo como original */ }
			return total;
		}).catch(function () { return total; });
	}

	// ─── Desenho de uma marca na tela ─────────────────────────────────────
	function pintar(marca) {
		var node = marca.node;
		var pag = ST.paginas[marca.pagina - 1];
		if (!node || !pag) { return; }

		node.style.left   = (marca.pos_x * 100) + "%";
		node.style.top    = (marca.pos_y * 100) + "%";
		node.style.width  = (marca.larg * 100) + "%";
		node.style.height = (marca.alt * 100) + "%";

		if (ST.modo === "remetente") {
			node.style.borderColor = corDoDono(marca.dono);
			var etiq = node.querySelector(".marca-etiqueta");
			if (etiq) { etiq.style.color = corDoDono(marca.dono); }
		}

		var corpo = node.querySelector(".marca-corpo");
		if (!corpo) { return; }

		var t = TIPOS[marca.tipo] || {};

		if (ST.modo === "remetente") {
			/* Quem envia não tem a rubrica nem o CPF do signatário: a marca
			   mostra o que vai ser estampado ali, não o conteúdo. */
			corpo.textContent = (t.rotulo || marca.tipo)
				+ " · signatário " + marca.dono;
			return;
		}

		if (t.imagem) {
			if (ST.rubricaDataUrl) {
				corpo.innerHTML = "";
				var img = el("img");
				img.src = ST.rubricaDataUrl;
				img.style.cssText = "width:100%;height:100%;object-fit:contain;pointer-events:none;";
				corpo.appendChild(img);
			} else {
				corpo.innerHTML = "<span class='marca-aviso'>Desenhe sua rubrica</span>";
			}
			return;
		}

		corpo.textContent = textoDaMarca(marca);
	}

	function textoDaMarca(marca) {
		function campo(id) {
			var e = document.getElementById(id);
			return e ? String(e.value || "").trim() : "";
		}
		var nome = campo("nome") || (window.ASSINATURA_NOME || "");
		switch (marca.tipo) {
			case "nome":    return nome || "Nome";
			case "cpf":     return "CPF: " + (campo("cpf") || "—");
			case "rg":      return "RG: "  + (campo("rg")  || "—");
			case "data":    return new Date().toLocaleString("pt-BR");
			case "texto":   return marca.conteudo || "Texto";
			case "carimbo":
				return "Assinado eletronicamente\n" + (nome || "—") + "\n"
					+ "CPF " + (campo("cpf") || "—") + "  ·  RG " + (campo("rg") || "—") + "\n"
					+ new Date().toLocaleString("pt-BR");
			default: return marca.tipo;
		}
	}

	// ─── Arrastar e redimensionar ─────────────────────────────────────────
	//
	// Pointer events cobrem mouse, dedo e caneta com o mesmo código — o que
	// importa aqui, porque boa parte dos signatários assina pelo celular.
	function ligarGestos(marca) {
		var node = marca.node;
		var pag = ST.paginas[marca.pagina - 1];
		var arrastando = false, redimensionando = false;
		var inicio = null;

		function medidasPagina() {
			var r = pag.camada.getBoundingClientRect();
			return { w: r.width || 1, h: r.height || 1 };
		}

		function comecar(e, modo) {
			e.preventDefault();
			e.stopPropagation();
			selecionar(marca);
			var m = medidasPagina();
			inicio = {
				px: e.clientX, py: e.clientY,
				x: marca.pos_x, y: marca.pos_y,
				w: marca.larg, h: marca.alt,
				pw: m.w, ph: m.h
			};
			arrastando = (modo === "mover");
			redimensionando = (modo === "tamanho");
			try { node.setPointerCapture(e.pointerId); } catch (err) {}
		}

		function mover(e) {
			if (!inicio) { return; }
			e.preventDefault();
			var dx = (e.clientX - inicio.px) / inicio.pw;
			var dy = (e.clientY - inicio.py) / inicio.ph;

			if (arrastando) {
				marca.pos_x = prender(inicio.x + dx, 0, 1 - marca.larg);
				marca.pos_y = prender(inicio.y + dy, 0, 1 - marca.alt);
			} else if (redimensionando) {
				marca.larg = prender(inicio.w + dx, MIN_LARG, 1 - marca.pos_x);
				marca.alt  = prender(inicio.h + dy, MIN_ALT,  1 - marca.pos_y);
			}
			pintar(marca);
		}

		function terminar(e) {
			if (!inicio) { return; }
			inicio = null;
			arrastando = redimensionando = false;
			try { node.releasePointerCapture(e.pointerId); } catch (err) {}
		}

		node.addEventListener("pointerdown", function (e) {
			if (e.target.closest(".marca-alca")) { return comecar(e, "tamanho"); }
			if (e.target.closest(".marca-excluir")) { return; }
			comecar(e, "mover");
		});
		node.addEventListener("pointermove", mover);
		node.addEventListener("pointerup", terminar);
		node.addEventListener("pointercancel", terminar);
	}

	function selecionar(marca) {
		ST.marcas.forEach(function (m) {
			if (m.node) { m.node.classList.toggle("marca-sel", m === marca); }
		});
		ST.selecionada = marca || null;
	}

	// ─── Criação e remoção ────────────────────────────────────────────────
	function criarMarca(tipo, pagina, posX, posY) {
		var t = TIPOS[tipo];
		if (!t) { return null; }
		var pag = ST.paginas[pagina - 1];
		if (!pag) { return null; }

		var marca = {
			id: ++ST.seq,
			tipo: tipo,
			pagina: pagina,
			larg: t.larg,
			alt: t.alt,
			rotacao: 0,
			conteudo: "",
			dono: ST.dono
		};
		marca.pos_x = prender(posX !== undefined ? posX : 0.08, 0, 1 - marca.larg);
		marca.pos_y = prender(posY !== undefined ? posY : 0.80, 0, 1 - marca.alt);

		if (tipo === "texto") {
			var txt = window.prompt("Texto do carimbo:", "");
			if (txt === null || String(txt).trim() === "") { return null; }
			marca.conteudo = String(txt).trim().slice(0, 200);
		}

		var node = el("div", "marca" + (t.bloco ? " marca-bloco" : ""));
		node.setAttribute("data-tipo", tipo);
		node.appendChild(el("div", "marca-corpo"));

		var alca = el("div", "marca-alca", "<i class='fas fa-up-right-and-down-left-from-center'></i>");
		alca.title = "Arraste para redimensionar";
		node.appendChild(alca);

		var fechar = el("button", "marca-excluir", "<i class='fas fa-times'></i>");
		fechar.type = "button";
		fechar.title = "Remover esta marca";
		fechar.addEventListener("click", function (e) {
			e.preventDefault();
			e.stopPropagation();
			removerMarca(marca);
		});
		node.appendChild(fechar);

		node.appendChild(el("div", "marca-etiqueta",
			"<i class='fas " + t.icone + "'></i> " + t.rotulo
			+ (ST.modo === "remetente" ? " (" + marca.dono + ")" : "")));

		pag.camada.appendChild(node);
		marca.node = node;
		ST.marcas.push(marca);

		ligarGestos(marca);
		pintar(marca);
		selecionar(marca);
		atualizarContador();
		return marca;
	}

	function removerMarca(marca) {
		var i = ST.marcas.indexOf(marca);
		if (i >= 0) { ST.marcas.splice(i, 1); }
		if (marca.node && marca.node.parentNode) {
			marca.node.parentNode.removeChild(marca.node);
		}
		if (ST.selecionada === marca) { ST.selecionada = null; }
		atualizarContador();
	}

	function paginaVisivel() {
		/* A página "atual" é a que ocupa o meio da área de rolagem — é onde o
		   usuário está olhando quando clica em adicionar. */
		var caixa = document.getElementById("marcasScroll");
		if (!caixa) { return 1; }
		var meio = caixa.getBoundingClientRect().top + caixa.clientHeight / 2;
		var melhor = 1, menorDist = Infinity;
		ST.paginas.forEach(function (p) {
			if (p.numero > ST.paginasOriginais) { return; }
			var r = p.wrap.getBoundingClientRect();
			var centro = r.top + r.height / 2;
			var d = Math.abs(centro - meio);
			if (d < menorDist) { menorDist = d; melhor = p.numero; }
		});
		return melhor;
	}

	function atualizarContador() {
		var alvo = document.getElementById("marcasResumo");
		if (!alvo) { return; }
		var n = ST.marcas.length;
		var txt = n === 0
			? "Nenhuma marca posicionada."
			: (n === 1 ? "1 marca posicionada." : n + " marcas posicionadas.");
		if (ST.obrigatorias > 0) {
			txt += " " + ST.obrigatorias
				+ (ST.obrigatorias === 1 ? " lugar reservado" : " lugares reservados")
				+ " por quem enviou o documento.";
		}
		alvo.textContent = txt;
	}

	// ─── Rubricar todas as páginas ────────────────────────────────────────
	function rubricarTodas() {
		var base = null;
		for (var i = ST.marcas.length - 1; i >= 0; i--) {
			if (ST.marcas[i].tipo === "rubrica") { base = ST.marcas[i]; break; }
		}
		var x = base ? base.pos_x : 0.74;
		var y = base ? base.pos_y : 0.92;
		var larg = base ? base.larg : TIPOS.rubrica.larg;
		var alt = base ? base.alt : TIPOS.rubrica.alt;

		var criadas = 0;
		for (var p = 1; p <= ST.paginasOriginais; p++) {
			var jaTem = ST.marcas.some(function (m) { return m.tipo === "rubrica" && m.pagina === p; });
			if (jaTem) { continue; }
			var nova = criarMarca("rubrica", p, x, y);
			if (nova) {
				nova.larg = larg;
				nova.alt = alt;
				nova.pos_x = prender(x, 0, 1 - larg);
				nova.pos_y = prender(y, 0, 1 - alt);
				pintar(nova);
				criadas++;
			}
		}
		selecionar(null);
		return criadas;
	}

	// ─── Marcas já registradas por outros signatários ─────────────────────
	function mostrarMarcasAnteriores(lista) {
		(lista || []).forEach(function (m) {
			var pagina = parseInt(m.pagina, 10) || 0;
			var pag = ST.paginas[pagina - 1];
			if (!pag) { return; }
			var node = el("div", "marca-anterior");
			node.style.left   = (parseFloat(m.pos_x) * 100) + "%";
			node.style.top    = (parseFloat(m.pos_y) * 100) + "%";
			node.style.width  = (parseFloat(m.larg) * 100) + "%";
			node.style.height = (parseFloat(m.alt) * 100) + "%";
			node.title = "Marca de " + (m.assinante_nome || "outro signatário");
			node.appendChild(el("span", "", (m.assinante_nome || "assinado")));
			pag.camada.appendChild(node);
		});
	}

	// ─── Lugares reservados pelo remetente ────────────────────────────────
	//
	// Aparecem travados para o signatário: ele vê onde vai ser estampado, mas
	// não arrasta nem remove — a posição é decisão de quem enviou. O conteúdo
	// entra no servidor, no ato da assinatura.
	function mostrarObrigatorias(lista) {
		(lista || []).forEach(function (m) {
			var pagina = parseInt(m.pagina, 10) || 0;
			var pag = ST.paginas[pagina - 1];
			if (!pag) { return; }
			var t = TIPOS[String(m.tipo || "")] || {};
			var node = el("div", "marca-obrigatoria");
			node.style.left   = (parseFloat(m.pos_x) * 100) + "%";
			node.style.top    = (parseFloat(m.pos_y) * 100) + "%";
			node.style.width  = (parseFloat(m.larg) * 100) + "%";
			node.style.height = (parseFloat(m.alt) * 100) + "%";
			node.title = "Lugar reservado por quem enviou o documento: "
				+ (t.rotulo || m.tipo) + ". Será preenchido com a sua assinatura.";
			node.appendChild(el("span", "", "<i class='fas fa-thumbtack mr-1'></i>"
				+ (t.rotulo || m.tipo)));
			pag.camada.appendChild(node);
			ST.obrigatorias++;
		});
		atualizarContador();
	}

	// ─── Zoom ─────────────────────────────────────────────────────────────
	//
	// Numa tela de celular a página inteira cabe, mas cada marca fica do
	// tamanho de uma unha. O zoom redesenha o canvas numa escala maior; as
	// marcas acompanham sozinhas, porque a posição delas é percentual e o CSS
	// trabalha em %. Esse é o ganho de guardar fração em vez de pixel.
	function aplicarZoom(fator) {
		var novo = Math.max(0.5, Math.min(4, Number(fator) || 1));
		if (Math.abs(novo - ST.zoom) < 0.001) { return ST.zoom; }
		ST.zoom = novo;
		ST.paginas.forEach(function (pag) { desenharPagina(pag); });
		var rotulo = document.getElementById("marcasZoomRotulo");
		if (rotulo) { rotulo.textContent = Math.round(ST.zoom * 100) + "%"; }
		return ST.zoom;
	}

	/* Recalcula a largura de ajuste e redesenha tudo.
	   Chamado ao entrar/sair da tela cheia, ao girar o telefone e ao
	   redimensionar a janela: sem isso o canvas continuaria no tamanho da
	   área antiga e o documento ficaria pequeno no espaço grande. */
	function ajustarLargura() {
		if (!ST.caixa) { return 0; }
		var nova = Math.max(260, ST.caixa.clientWidth - 24);
		if (Math.abs(nova - ST.largBase) < 2) { return ST.largBase; }
		ST.largBase = nova;
		ST.paginas.forEach(function (pag) { desenharPagina(pag); });
		return ST.largBase;
	}

	/* Janela redimensionada: espera parar de mexer antes de redesenhar, porque
	   redesenhar a cada pixel de arrasto travaria a tela. */
	var temporizadorLargura = null;
	function ligarRedimensionamento() {
		window.addEventListener("resize", function () {
			if (temporizadorLargura) { clearTimeout(temporizadorLargura); }
			temporizadorLargura = setTimeout(ajustarLargura, 180);
		});
	}

	/* Redesenha o canvas de uma página na escala atual. */
	function desenharPagina(pag) {
		if (!pag.page || !pag.vpBase || !ST.largBase) { return Promise.resolve(); }
		var escala = (ST.largBase * ST.zoom) / pag.vpBase.width;
		var vp = pag.page.getViewport({ scale: escala });
		var dpr = window.devicePixelRatio || 1;

		pag.wrap.style.width = vp.width + "px";
		pag.wrap.style.height = vp.height + "px";

		var canvas = pag.wrap.querySelector("canvas");
		canvas.width = Math.floor(vp.width * dpr);
		canvas.height = Math.floor(vp.height * dpr);
		canvas.style.width = vp.width + "px";
		canvas.style.height = vp.height + "px";

		var ctx = canvas.getContext("2d");
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		if (pag.tarefa && pag.tarefa.cancel) {
			/* Zoom clicado duas vezes seguidas: o pdf.js reclama se dois
			   render rodarem no mesmo canvas. */
			try { pag.tarefa.cancel(); } catch (e) {}
		}
		pag.tarefa = pag.page.render({ canvasContext: ctx, viewport: vp });
		return pag.tarefa.promise.catch(function () { /* render cancelado */ });
	}

	// ─── Ajuste fino pelas setas ──────────────────────────────────────────
	//
	// No desktop, acertar o milímetro arrastando é ruim; com a marca
	// selecionada, as setas movem de pouquinho. Shift move mais rápido.
	function ligarSetas() {
		document.addEventListener("keydown", function (e) {
			var m = ST.selecionada;
			if (!m || !m.node) { return; }
			var alvo = e.target;
			if (alvo && (alvo.tagName === "INPUT" || alvo.tagName === "TEXTAREA" || alvo.tagName === "SELECT")) {
				return;
			}
			var passo = e.shiftKey ? 0.01 : 0.002;
			var mexeu = false;
			if (e.key === "ArrowLeft")  { m.pos_x = prender(m.pos_x - passo, 0, 1 - m.larg); mexeu = true; }
			if (e.key === "ArrowRight") { m.pos_x = prender(m.pos_x + passo, 0, 1 - m.larg); mexeu = true; }
			if (e.key === "ArrowUp")    { m.pos_y = prender(m.pos_y - passo, 0, 1 - m.alt);  mexeu = true; }
			if (e.key === "ArrowDown")  { m.pos_y = prender(m.pos_y + passo, 0, 1 - m.alt);  mexeu = true; }
			if (e.key === "Delete" || e.key === "Backspace") { removerMarca(m); mexeu = true; }
			if (mexeu) {
				e.preventDefault();
				if (m.node && m.node.parentNode) { pintar(m); }
			}
		});
	}

	// ─── Montagem ─────────────────────────────────────────────────────────
	function render(fonte, opcoes) {
		var caixa = document.getElementById((opcoes && opcoes.caixa) || "marcasScroll");
		var aviso = document.getElementById((opcoes && opcoes.aviso) || "marcasCarregando");
		if (!caixa || !window.pdfjsLib) { return Promise.resolve(false); }

		if (opcoes && opcoes.modo === "remetente") { ST.modo = "remetente"; }
		if (opcoes && opcoes.dono) { ST.dono = Math.max(1, parseInt(opcoes.dono, 10) || 1); }
		if (opcoes && opcoes.workerSrc) {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = opcoes.workerSrc;
		} else {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = "vendor_js/pdf.worker.min.js";
		}

		/* Aceita URL (tela de assinatura) ou os bytes do arquivo que o
		   remetente acabou de escolher, que ainda não foi enviado. */
		var pedido = (fonte && fonte.byteLength !== undefined)
			? { data: fonte }
			: { url: String(fonte) };

		ST.caixa = caixa;

		return window.pdfjsLib.getDocument(pedido).promise.then(function (pdf) {
			ST.pdf = pdf;
			return descobrirPaginasOriginais(pdf, pdf.numPages).then(function (orig) {
				ST.paginasOriginais = orig;

				ST.largBase = Math.max(260, caixa.clientWidth - 24);
				var fila = Promise.resolve();

				for (var n = 1; n <= pdf.numPages; n++) {
					(function (numero) {
						fila = fila.then(function () {
							return pdf.getPage(numero).then(function (page) {
								var wrap = el("div", "pdf-pagina");
								wrap.appendChild(el("canvas"));

								var camada = el("div", "pdf-camada");
								wrap.appendChild(camada);

								var rotulo = el("div", "pdf-numero",
									numero > orig
										? "Comprovante · não aceita carimbo"
										: "Página " + numero + " de " + orig);
								if (numero > orig) { wrap.classList.add("pdf-pagina-bloqueada"); }
								wrap.appendChild(rotulo);

								caixa.appendChild(wrap);

								var pag = {
									numero: numero,
									wrap: wrap,
									camada: camada,
									page: page,
									vpBase: page.getViewport({ scale: 1 }),
									tarefa: null
								};
								ST.paginas.push(pag);
								return desenharPagina(pag);
							});
						});
					})(n);
				}

				return fila.then(function () {
					if (aviso) { aviso.remove(); }
					ST.pronto = true;
					ligarSetas();
					ligarRedimensionamento();

					var rotuloZoom = document.getElementById("marcasZoomRotulo");
					if (rotuloZoom) { rotuloZoom.textContent = Math.round(ST.zoom * 100) + "%"; }

					/* Clicar numa página adiciona a marca escolhida na barra
					   exatamente onde o usuário clicou. */
					ST.paginas.forEach(function (p) {
						if (p.numero > ST.paginasOriginais) { return; }
						p.camada.addEventListener("pointerdown", function (e) {
							if (e.target !== p.camada) { return; }
							var sel = document.getElementById("marcaTipoAtivo");
							var tipo = sel ? sel.value : "";
							if (!tipo) { selecionar(null); return; }
							var r = p.camada.getBoundingClientRect();
							var t = TIPOS[tipo] || {};
							criarMarca(
								tipo, p.numero,
								(e.clientX - r.left) / (r.width || 1) - (t.larg || 0.1) / 2,
								(e.clientY - r.top) / (r.height || 1) - (t.alt || 0.03) / 2
							);
						});
					});
					return true;
				});
			});
		}).catch(function (err) {
			if (aviso) {
				aviso.innerHTML = "<i class='fas fa-triangle-exclamation text-amber-500 mr-1'></i>"
					+ "Não foi possível abrir o documento para posicionar carimbos. "
					+ "Use o link \"Abrir Documento em nova aba\" para conferir o conteúdo.";
			}
			console.error("Editor de marcas:", err);
			return false;
		});
	}

	// ─── API usada pela tela e pelo envio ─────────────────────────────────
	window.MarcasEditor = {
		iniciar: function (fonte, opcoes) { return render(fonte, opcoes); },

		atualizarRubrica: function (dataUrl) {
			ST.rubricaDataUrl = String(dataUrl || "");
			ST.marcas.forEach(function (m) {
				if ((TIPOS[m.tipo] || {}).imagem) { pintar(m); }
			});
		},

		atualizarTextos: function () {
			ST.marcas.forEach(function (m) {
				if (!(TIPOS[m.tipo] || {}).imagem) { pintar(m); }
			});
		},

		adicionar: function (tipo) {
			if (!ST.pronto) { return null; }
			return criarMarca(tipo, paginaVisivel());
		},

		rubricarTodas: function () { return ST.pronto ? rubricarTodas() : 0; },

		limpar: function () {
			ST.marcas.slice().forEach(removerMarca);
		},

		/** Zera tudo: usado quando o remetente troca o PDF depois de já ter
		 *  posicionado. Sem isso, as marcas do documento antigo seriam
		 *  enviadas junto com o documento novo. */
		reiniciar: function () {
			ST.marcas.slice().forEach(removerMarca);
			ST.paginas.forEach(function (pag) {
				if (pag.tarefa && pag.tarefa.cancel) {
					try { pag.tarefa.cancel(); } catch (e) {}
				}
				if (pag.wrap && pag.wrap.parentNode) {
					pag.wrap.parentNode.removeChild(pag.wrap);
				}
			});
			if (ST.pdf && ST.pdf.destroy) {
				try { ST.pdf.destroy(); } catch (e) {}
			}
			ST.pdf = null;
			ST.paginas = [];
			ST.paginasOriginais = 0;
			ST.obrigatorias = 0;
			ST.selecionada = null;
			ST.zoom = 1;
			ST.pronto = false;
			atualizarContador();
		},

		mostrarAnteriores: mostrarMarcasAnteriores,

		total: function () { return ST.marcas.length; },

		pronto: function () { return ST.pronto; },

		/** Lista para o formulário. Só posição e tipo: o conteúdo de cada
		 *  carimbo é montado no servidor, a partir do que foi declarado. */
		coletar: function () {
			return ST.marcas.map(function (m) {
				var saida = {
					tipo: m.tipo,
					pagina: m.pagina,
					pos_x: Number(m.pos_x.toFixed(6)),
					pos_y: Number(m.pos_y.toFixed(6)),
					larg: Number(m.larg.toFixed(6)),
					alt: Number(m.alt.toFixed(6)),
					rotacao: m.rotacao || 0,
					conteudo: m.tipo === "texto" ? m.conteudo : ""
				};
				if (ST.modo === "remetente") {
					saida.ordem = m.dono || 1;
				}
				return saida;
			});
		},

		/** Qual signatário recebe as próximas marcas (modo remetente). */
		definirDono: function (ordem) {
			ST.dono = Math.max(1, parseInt(ordem, 10) || 1);
			return ST.dono;
		},

		corDoDono: corDoDono,

		/** Lugares que o remetente reservou, mostrados travados ao signatário. */
		mostrarObrigatorias: mostrarObrigatorias,

		/** Zoom: 1 é a largura da coluna. As marcas acompanham sozinhas,
		 *  porque a posição delas é percentual e o CSS usa %. */
		zoom: function (fator) { return aplicarZoom(fator); },
		zoomAtual: function () { return ST.zoom; },

		/** Reaproveita o espaço disponível depois de a área mudar de tamanho
		 *  (tela cheia, janela redimensionada, telefone girado). */
		ajustarLargura: function () { return ajustarLargura(); }
	};
})();
