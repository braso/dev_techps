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

try:
    from pyhanko.pdf_utils import layout
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
    from pyhanko.sign import fields, signers
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


def assinar_pdf_incremental(
    input_path: str,
    output_path: str,
    pfx_path: str,
    pfx_password: str,
    reason: str = "Garantia de Autenticidade e Integridade ICP-Brasil",
    location: str = "Sistema TechPS",
    url_validador: str = "https://validar.iti.gov.br/",
    visible_stamp: bool = True
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
        signer_name = "TechPS"
        if cert and hasattr(cert, "subject"):
            cn_attribs = cert.subject.native.get("common_name", None)
            if cn_attribs:
                signer_name = str(cn_attribs)
            else:
                signer_name = str(cert.subject.human_friendly)

        with open(input_path, "rb") as inf:
            # strict=False é mandatório para permitir PDFs com assinaturas existentes (como Gov.br)
            w = IncrementalPdfFileWriter(inf, strict=False)

            # Obter número de páginas e dimensões da última página
            try:
                page_count = int(w.root['/Pages']['/Count'])
            except Exception:
                try:
                    page_count = len(w.root['/Pages']['/Kids'])
                except Exception:
                    page_count = 1

            target_idx = max(0, page_count - 1)
            page_w = 595
            page_h = 842

            try:
                page_ref, _ = w.find_page_for_modification(target_idx)
                page_obj = page_ref.get_object()
                mediabox = page_obj.get('/MediaBox', None)
                if mediabox is None and '/Parent' in page_obj:
                    mediabox = page_obj['/Parent'].get_object().get('/MediaBox')
                if mediabox:
                    page_w = int(float(mediabox[2]) - float(mediabox[0]))
                    page_h = int(float(mediabox[3]) - float(mediabox[1]))
            except Exception:
                pass

            sig_field_name = f"SigTechPS_{int(datetime.now().timestamp())}"
            meta = signers.PdfSignatureMetadata(
                field_name=sig_field_name,
                reason=reason,
                location=location
            )

            font_engine = SimpleFontEngineFactory.default_factory()
            tb_style = TextBoxStyle(font=font_engine, font_size=7)

            stamp = None
            new_field_spec = None
            appearance_params = None

            if visible_stamp:
                stamp = QRStampStyle(
                    stamp_text=(
                        "ASSINADO DIGITALMENTE VIA TECHPS\n"
                        f"{signer_name[:40]}\n"
                        "Data: %(ts)s\n"
                        "Valide em: %(url)s"
                    ),
                    text_box_style=tb_style,
                    border_width=1,
                )

                # Posiciona no canto inferior direito respeitando as dimensões da página
                stamp_w = 230
                stamp_h = 58
                margin_x = 15
                margin_y = 15

                x1 = max(10, page_w - stamp_w - margin_x)
                y1 = margin_y
                x2 = min(page_w - margin_x, x1 + stamp_w)
                y2 = y1 + stamp_h

                new_field_spec = fields.SigFieldSpec(
                    sig_field_name=sig_field_name,
                    box=(x1, y1, x2, y2),
                    on_page=target_idx
                )
                appearance_params = {"url": url_validador}

            pdf_signer = signers.PdfSigner(
                signature_meta=meta,
                signer=signer,
                stamp_style=stamp,
                new_field_spec=new_field_spec
            )

            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            with open(output_path, "wb") as outf:
                pdf_signer.sign_pdf(
                    pdf_out=w,
                    output=outf,
                    appearance_text_params=appearance_params
                )

        if not os.path.isfile(output_path) or os.path.getsize(output_path) == 0:
            return {"ok": False, "error": "Arquivo de saída não foi gerado ou está vazio."}

        return {
            "ok": True,
            "output_path": output_path,
            "signer_name": signer_name,
            "sig_field": sig_field_name
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

    args = parser.parse_args()

    res = assinar_pdf_incremental(
        input_path=args.input,
        output_path=args.output,
        pfx_path=args.pfx,
        pfx_password=args.password,
        reason=args.reason,
        location=args.location,
        url_validador=args.url,
        visible_stamp=not args.no_stamp
    )

    print(json.dumps(res, ensure_ascii=False))
    if not res.get("ok"):
        sys.exit(1)


if __name__ == "__main__":
    main()
