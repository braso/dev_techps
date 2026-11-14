# -*- coding: utf-8 -*-
"""Converte o manual DOCX do Espelho de Ponto em Markdown estruturado.

Uso:
    python tools/converter_manual.py "caminho/do/manual.docx" "knowledge/manual_espelho_ponto.md"
"""
import sys, re, os
import docx

HEADINGS = [
    "OBJETIVO", "LOGIN DE ACESSO", "ESQUESEU SUA SENHA", "TELA SISTEMA",
    "ROTINA DE EMPRESA/FILIAL", "ROTINA DE ENDOSSO", "ROTINA DE FERIADO",
    "ROTINA DE FÉRIAS", "ROTINA DE FUNCIONÁRIO", "ROTINA DE MACRO",
    "ROTINA DE MOTIVOS", "RONTINA DE CARGO", "ROTINA DE PARÂMETRO",
    "ROTINA DE USUÁRIO", "ROTINA DE PLACAS", "ROTINA DE CELULARES",
    "ROTINA SETOR", "ROTINA TIPO DE DOCUMENTO", "ROTINA HABILIDADES TÉCNICAS",
    "ROTINA HABILIDADES COMPORTAMENTAIS", "ROTINA PERFIL DE ACESSO",
    "ROTINA PERMISSÃO DE USUÁRIOS", "CONSULTAR ENDOSSOS", "ESPELHO DE PONTO",
    "INTEGRAÇÃO DE PONTOS", "NÃO CONFORMIDADES", "NÃO CADASTRADO",
    "ROTINAS DE PAINÉIS",
]
HEADINGS_NORM = {re.sub(r"\s+", " ", h).strip().upper(): h for h in HEADINGS}

def norm(s):
    return re.sub(r"\s+", " ", s.replace("\xa0", " ")).strip()

def main(src, dst):
    d = docx.Document(src)
    paras = [norm(p.text) for p in d.paragraphs]
    # Começa no título real "OBJETIVO" (o do sumário contém pontilhado).
    start = 0
    for i, p in enumerate(paras):
        if p.upper() == "OBJETIVO":
            start = i
            break
    out = []
    out.append("# Manual do Sistema — Espelho de Ponto")
    out.append("")
    out.append("> Fonte: `Detalhamento_Manual_Espelho de Ponto - V08_30_03_2026.docx`.")
    out.append("> Texto extraído automaticamente do manual oficial. As imagens/figuras não são incluídas; os textos descritivos foram preservados.")
    out.append("")
    seen = set()
    for p in paras[start:]:
        if not p:
            continue
        up = p.upper()
        if up in ("SUMÁRIO", "LISTA DE FIGURAS"):
            continue
        if re.match(r"^Figura\s*:?\s*\d+", p, re.I):
            continue
        if re.match(r"^\d{1,3}$", p):
            continue
        if re.match(r"^_{5,}$", p):
            continue
        if up in HEADINGS_NORM:
            title = HEADINGS_NORM[up]
            if title.upper() in seen:
                continue
            seen.add(title.upper())
            out.append("")
            out.append(f"## {title}")
            out.append("")
            continue
        if re.match(r"^\d+(\.\d+)?\s*[–-]\s+.+", p) and len(p) < 140:
            out.append("")
            out.append(f"### {p}")
            out.append("")
            continue
        out.append(p)
        out.append("")
    text = "\n".join(out).strip() + "\n"
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    with open(dst, "w", encoding="utf-8") as f:
        f.write(text)
    print(f"OK: {dst} ({len(text)} chars, {len(text.split())} words)")

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(1)
    main(sys.argv[1], sys.argv[2])
