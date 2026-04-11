## Integração: enviar documento para assinatura (de qualquer tela)

Este módulo cria uma solicitação na assinatura e envia o e-mail para o funcionário assinar.

### Onde fica

- Pasta: `armazem_paraiba/assinatura/integracao/`
- Funções: `assinatura_integracao.php`
- Endpoint (POST): `enviar.php`

---

## Opção A (Recomendado): chamar por função (PHP)

Em qualquer tela PHP do sistema (já com `$conn` do `conecta.php`), inclua e chame:

```php
require_once __DIR__ . "/../assinatura/integracao/assinatura_integracao.php";

$res = assinatura_integracao_enviarDocumentoParaAssinatura(
    $conn,
    $entiNbId,
    $caminhoDoPdf,
    [
        "nome_arquivo_original" => "Solicitação_123.pdf",
        "tipo_documento_id" => 0,
        "validar_icp" => "nao",
        "modo_envio" => "avulso",
        "funcao" => "Funcionário"
    ]
);

if (!empty($res["ok"])) {
    echo "Enviado. Solicitação: " . $res["id_solicitacao"] . " / Documento: " . $res["id_documento"];
} else {
    echo "Erro: " . ($res["error"] ?? "Falha");
}
```

### Parâmetros importantes

- `$entiNbId`: `entidade.enti_nb_id` do funcionário que vai assinar
- `$caminhoDoPdf`: caminho do PDF (absoluto ou relativo dentro do projeto). Exemplos:
  - `arquivos/Funcionarios/123/meu_doc.pdf`
  - `assinatura/uploads/tmp/abc.pdf`
  - `C:/.../armazem_paraiba/arquivos/Funcionarios/123/meu_doc.pdf`

### O que acontece internamente

- Copia o PDF para `assinatura/uploads/integracao/`
- Cria registro em `solicitacoes_assinatura` + `assinantes`
- Envia e-mail para o primeiro assinante (o funcionário)

---

## Opção B: usar endpoint POST (botão/form)

Você pode criar um botão que faz POST para a assinatura, sem escrever muita lógica na tela atual.

### POST para `assinatura/integracao/enviar.php`

Campos aceitos:

- `enti_nb_id` (obrigatório)
- `caminho` (obrigatório se não enviar arquivo)
- `arquivo` (upload PDF opcional, se não usar `caminho`)
- `nome_arquivo_original` (opcional)
- `tipo_documento_id` (opcional)
- `validar_icp` (opcional: `sim`/`nao`)
- `modo_envio` (opcional: `avulso`/`governanca`)
- `funcao` (opcional)
- `retorno` (opcional: URL para redirecionar após sucesso/erro)
- `format` (opcional: `json` para resposta JSON)

### Exemplo (com caminho do arquivo já gerado)

```html
<form method="post" action="/armazem_paraiba/assinatura/integracao/enviar.php">
  <input type="hidden" name="enti_nb_id" value="123">
  <input type="hidden" name="caminho" value="arquivos/Funcionarios/123/solicitacao_123.pdf">
  <input type="hidden" name="nome_arquivo_original" value="Solicitação_123.pdf">
  <input type="hidden" name="retorno" value="/armazem_paraiba/minha_tela.php?id=123">
  <button type="submit">Enviar para assinatura</button>
</form>
```

### Exemplo (retorno JSON)

```bash
curl -X POST "http://localhost/armazem_paraiba/assinatura/integracao/enviar.php" ^
  -H "Accept: application/json" ^
  -d "enti_nb_id=123" ^
  -d "caminho=arquivos/Funcionarios/123/solicitacao_123.pdf" ^
  -d "nome_arquivo_original=Solicitação_123.pdf" ^
  -d "format=json"
```

