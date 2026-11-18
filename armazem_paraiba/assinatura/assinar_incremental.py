#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
assinar_incremental.py - Assinador Incremental PAdES via pyHanko
Preserva assinaturas digitais pré-existentes (ex: Gov.br, ICP-Brasil)
através de Incremental Update (Append Mode) conforme ISO 32000-1 / PAdES.
"""

import sys
import os
import argparse
import json
from datetime import datetime
from fractions import Fraction

# Garante carregamento de bibliotecas instaladas com --user (cPanel / ~/.local)
try:
    import site
    usp = site.getusersitepackages()
    if usp and os.path.exists(usp) and usp not in sys.path:
        sys.path.insert(0, usp)
    home_dir = os.path.expanduser("~")
    local_p = os.path.join(home_dir, ".local", "lib", f"python{sys.version_info.major}.{sys.version_info.minor}", "site-packages")
    if os.path.exists(local_p) and local_p not in sys.path:
        sys.path.insert(0, local_p)
except Exception:
    pass

try:
    from pyhanko.pdf_utils import layout
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
    from pyhanko.sign import fields, signers
    from pyhanko.pdf_utils.images import PdfImage
    from pyhanko.stamp import QRStampStyle
    from pyhanko.pdf_utils.text import TextBoxStyle
    from pyhanko.pdf_utils.font.basic import SimpleFontEngineFactory

    # Compatibilidade com Python 3.14 (onde Fraction(float, float) requer Rational)
    _orig_recalculate = layout.BoxConstraints._recalculate

    def _safe_recalculate(self):
        if self._width is not None and self._height is not None:
            w = round(self._width) if isinstance(self._width, float) else self._width
            h = round(self._height) if isinstance(self._height, float) else self._height
            self._ar = Fraction(w, h)
            self._fully_specified = True
        elif self._ar is not None:
            if self._height is not None:
                self._width = int(self._height * self._ar)
                self._fully_specified = True
            elif self._width is not None:
                self._height = int(self._width / self._ar)
                self._fully_specified = True

    layout.BoxConstraints._recalculate = _safe_recalculate

except ImportError as e:
    print(json.dumps({"ok": False, "error": f"Biblioteca pyHanko não disponível: {e}"}))
    sys.exit(1)


def get_page_dimensions(w, page_idx: int) -> tuple:
    page_w = 595
    page_h = 842
    try:
        page_ref, _ = w.find_page_for_modification(page_idx)
        page_obj = page_ref.get_object()
        mediabox = page_obj.get('/MediaBox', None)
        if mediabox is None and '/Parent' in page_obj:
            mediabox = page_obj['/Parent'].get_object().get('/MediaBox')
        if mediabox:
            page_w = int(float(mediabox[2]) - float(mediabox[0]))
            page_h = int(float(mediabox[3]) - float(mediabox[1]))
    except Exception:
        pass
    return page_w, page_h


def assinar_pdf_incremental(
    input_path: str,
    output_path: str,
    pfx_path: str,
    pfx_password: str,
    reason: str = "Garantia de Autenticidade e Integridade ICP-Brasil",
    location: str = "Sistema TechPS",
    url_validador: str = "https://validar.iti.gov.br/",
    visible_stamp: bool = True,
    signer_name: str = "",
    signer_cpf: str = "",
    signer_rg: str = "",
    rubrica_path: str = "",
    marcas_json: str = ""
) -> dict:
    if not os.path.isfile(input_path):
        return {"ok": False, "error": f"Arquivo de entrada não encontrado: {input_path}"}
    if not os.path.isfile(pfx_path):
        return {"ok": False, "error": f"Certificado PFX não encontrado: {pfx_path}"}

    try:
        signer = signers.SimpleSigner.load_pkcs12(
            pfx_path,
            passphrase=pfx_password.encode("utf-8")
        )
    except Exception as e:
        return {"ok": False, "error": f"Erro ao carregar certificado PFX: {e}"}

    try:
        cert = signer.signing_cert
        cert_signer_name = "TechPS"
        if cert and hasattr(cert, "subject"):
            cn_attribs = cert.subject.native.get("common_name", None)
            if cn_attribs:
                cert_signer_name = str(cn_attribs)
            else:
                cert_signer_name = str(cert.subject.human_friendly)

        with open(input_path, "rb") as inf:
            # strict=False é mandatório para permitir PDFs com assinaturas existentes (como Gov.br)
            w = IncrementalPdfFileWriter(inf, strict=False)

            # Obter total de páginas
            try:
                page_count = int(w.root['/Pages']['/Count'])
            except Exception:
                try:
                    page_count = len(w.root['/Pages']['/Kids'])
                except Exception:
                    page_count = 1

            target_idx = None
            sig_box = None

            # 1. Posições definidas pelas marcas (se existirem)
            if marcas_json:
                try:
                    marcas_list = json.loads(marcas_json)
                    if isinstance(marcas_list, list) and len(marcas_list) > 0:
                        for m in marcas_list:
                            if not isinstance(m, dict):
                                continue
                            target_p = max(0, int(m.get('pagina', 1)) - 1)
                            pw, ph = get_page_dimensions(w, target_p)
                            x1 = float(m.get('pos_x', 0)) * pw
                            larg = float(m.get('larg', 0.25)) * pw
                            x2 = x1 + larg
                            alt = float(m.get('alt', 0.08)) * ph
                            y2 = ph - (float(m.get('pos_y', 0)) * ph)
                            y1 = y2 - alt
                            sig_box = (min(x1, x2), min(y1, y2), max(x1, x2), max(y1, y2))
                            target_idx = target_p
                            if not rubrica_path and m.get('caminho_imagem'):
                                rubrica_path = str(m.get('caminho_imagem'))
                            break
                except Exception:
                    pass

            # 2. Se não houver marcas explícitas, calcula o posicionamento inteligente
            if target_idx is None or sig_box is None:
                # Detecta se há assinatura prévia (Gov.br) na Página 0
                p0_has_sig = False
                sig1_rect = None
                try:
                    p0_ref, _ = w.find_page_for_modification(0)
                    p0_obj = p0_ref.get_object()
                    annots = p0_obj.get('/Annots', [])
                    for a in annots:
                        a_obj = a.get_object() if hasattr(a, 'get_object') else a
                        if isinstance(a_obj, dict) and a_obj.get('/FT') == '/Sig':
                            p0_has_sig = True
                            rect = a_obj.get('/Rect')
                            if rect and len(rect) >= 4:
                                sig1_rect = [float(x) for x in rect]
                            break
                except Exception:
                    pass

                # Detecta se o nome do signatário aparece na Página 0 (ex: aluno no certificado)
                signer_on_p0 = False
                if signer_name:
                    import unicodedata, re
                    def _norm_s(s):
                        return ''.join(c for c in unicodedata.normalize('NFKD', str(s).upper()) if not unicodedata.combining(c) and c.isalnum())
                    c_target = _norm_s(signer_name)
                    parts = signer_name.split()
                    first_two = _norm_s(parts[0] + parts[1]) if len(parts) >= 2 else c_target
                    try:
                        p0_ref, _ = w.find_page_for_modification(0)
                        p0_obj = p0_ref.get_object()
                        contents = p0_obj.get('/Contents')
                        if contents:
                            data = contents.get_object().data.decode('latin1', errors='ignore')
                            words = re.findall(r'[(<](.*?)[)>]', data)
                            stream_text = _norm_s(''.join(words))
                            if first_two in stream_text or c_target in stream_text:
                                signer_on_p0 = True
                    except Exception:
                        pass

                pw0, ph0 = get_page_dimensions(w, 0)
                # Se for certificado landscape ou tiver assinatura prévia/nome na P0
                if p0_has_sig or signer_on_p0 or (pw0 > ph0 and page_count <= 2):
                    target_idx = 0
                    if sig1_rect:
                        # Alinha perfeitamente com a altura e proporção da assinatura pré-existente (Gov.br do instrutor), no lado do aluno
                        y1 = sig1_rect[1]
                        y2 = sig1_rect[3]
                        sig_w = max(165, sig1_rect[2] - sig1_rect[0])
                        x1 = 404
                        x2 = x1 + sig_w
                        sig_box = (x1, y1, x2, y2)
                    elif pw0 > ph0:
                        x1 = max(pw0 * 0.5, 404)
                        x2 = min(pw0 - 15, x1 + 165)
                        y1 = 102
                        y2 = y1 + 45
                        sig_box = (x1, y1, x2, y2)
                    else:
                        stamp_w = 200
                        stamp_h = 50
                        x1 = max(10, pw0 - stamp_w - 15)
                        y1 = 15
                        x2 = min(pw0 - 15, x1 + stamp_w)
                        y2 = y1 + stamp_h
                        sig_box = (x1, y1, x2, y2)
                else:
                    # Fallback padrão: canto inferior direito da última página
                    target_idx = max(0, page_count - 1)
                    pw, ph = get_page_dimensions(w, target_idx)
                    stamp_w = 200
                    stamp_h = 50
                    x1 = max(10, pw - stamp_w - 15)
                    y1 = 15
                    x2 = min(pw - 15, x1 + stamp_w)
                    y2 = y1 + stamp_h
                    sig_box = (x1, y1, x2, y2)

            sig_field_name = f"SigTechPS_{int(datetime.now().timestamp())}"
            meta = signers.PdfSignatureMetadata(
                field_name=sig_field_name,
                reason=reason,
                location=location
            )

            new_field_spec = fields.SigFieldSpec(
                sig_field_name=sig_field_name,
                box=sig_box if visible_stamp else (0, 0, 0, 0),
                on_page=target_idx
            )
            # Em modo incremental sobre PDF existente, registra o campo antes da assinatura
            fields.append_signature_field(w, new_field_spec)

            stamp = None
            appearance_params = None

            if visible_stamp:
                font_engine = SimpleFontEngineFactory.default_factory()
                tb_style = TextBoxStyle(font=font_engine, font_size=5.0)

                lines = ["ASSINADO DIGITALMENTE"]
                display_name = signer_name.strip() if signer_name else cert_signer_name
                if display_name:
                    lines.append(display_name[:35])
                if signer_cpf:
                    lines.append(f"CPF: {signer_cpf}")
                lines.append("Data: %(ts)s")
                lines.append("Valide: %(url)s")

                stamp_text = "\n".join(lines)

                bg_img = None
                if rubrica_path and os.path.isfile(rubrica_path):
                    try:
                        bg_img = PdfImage(rubrica_path, opacity=0.30)
                    except Exception:
                        pass

                stamp = QRStampStyle(
                    stamp_text=stamp_text,
                    text_box_style=tb_style,
                    background=bg_img,
                    border_width=0,
                    innsep=2
                )
                appearance_params = {"url": url_validador}

            pdf_signer = signers.PdfSigner(
                signature_meta=meta,
                signer=signer,
                stamp_style=stamp
            )

            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            with open(output_path, "wb") as outf:
                pdf_signer.sign_pdf(
                    pdf_out=w,
                    output=outf,
                    existing_fields_only=True,
                    appearance_text_params=appearance_params
                )

        if not os.path.isfile(output_path) or os.path.getsize(output_path) == 0:
            return {"ok": False, "error": "Arquivo de saída não foi gerado ou está vazio."}

        return {
            "ok": True,
            "output_path": output_path,
            "signer_name": signer_name if signer_name else cert_signer_name,
            "sig_field": sig_field_name,
            "target_page": target_idx + 1
        }

    except Exception as e:
        if os.path.isfile(output_path):
            try:
                os.unlink(output_path)
            except OSError:
                pass
        return {"ok": False, "error": f"Erro durante a assinatura incremental: {e}"}


def main():
    parser = argparse.ArgumentParser(description="Assinador Incremental PAdES via pyHanko")
    parser.add_argument("--input", required=True, help="Caminho do PDF de entrada")
    parser.add_argument("--output", required=True, help="Caminho do PDF assinado de saída")
    parser.add_argument("--pfx", required=True, help="Caminho do certificado .pfx")
    parser.add_argument("--password", required=True, help="Senha do certificado .pfx")
    parser.add_argument("--reason", default="Garantia de Autenticidade e Integridade ICP-Brasil", help="Motivo da assinatura")
    parser.add_argument("--location", default="Sistema TechPS", help="Local da assinatura")
    parser.add_argument("--url", default="https://validar.iti.gov.br/", help="URL para validação do QR Code")
    parser.add_argument("--no-stamp", action="store_true", help="Não desenhar carimbo visual")
    parser.add_argument("--signer-name", default="", help="Nome do signatário")
    parser.add_argument("--signer-cpf", default="", help="CPF do signatário")
    parser.add_argument("--signer-rg", default="", help="RG do signatário")
    parser.add_argument("--rubrica", default="", help="Caminho da imagem de rubrica")
    parser.add_argument("--marcas-json", default="", help="JSON de marcas e coordenadas")

    args = parser.parse_args()

    res = assinar_pdf_incremental(
        input_path=args.input,
        output_path=args.output,
        pfx_path=args.pfx,
        pfx_password=args.password,
        reason=args.reason,
        location=args.location,
        url_validador=args.url,
        visible_stamp=not args.no_stamp,
        signer_name=args.signer_name,
        signer_cpf=args.signer_cpf,
        signer_rg=args.signer_rg,
        rubrica_path=args.rubrica,
        marcas_json=args.marcas_json
    )

    print(json.dumps(res, ensure_ascii=False))
    if not res.get("ok"):
        sys.exit(1)


if __name__ == "__main__":
    main()
