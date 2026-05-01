# Fluxo Refatorado: Ajuste de Ponto

## Resumo das Mudanças

### ❌ Removido
- `ga_processarAprovacaoAgrupada()` - Função de aprovação em lote
- `ga_montarTabelaSolicitacoesAgrupadas()` - Tabela profissional para agrupamento
- Lógica de agrupamento em `processarEmLote()`

### ✅ Mantido e Aprimorado
- `aceitarSolicitacao()` - Aprovação individual
- `ga_enviarAssinaturaAjusteAprovado()` - Envio para assinatura após aprovação
- `ga_garantirInstanciaDocumentoAjuste()` - Criação de instância do documento
- `ga_gerarArquivoPdfInstanciaDocumento()` - Geração do PDF

---

## Novo Fluxo: Aprovação Individual

### 1. **Solicitar Ajuste** (via `ajuste_pontofuncionario.php`)
```
Usuário preenche e envia solicitação
    ↓ (sem gerar PDF)
Status = 'enviada'
    ↓
Aguarda aprovação do gestor
```

### 2. **Gestor Aprova Individualmente** (via `gerenciar_ajustes.php`)
```
Gestor seleciona 1 solicitação
    ↓
Clica em "Aprovar"
    ↓
Função: aceitarSolicitacao($id)
    ├─ Valida dados
    ├─ Insere ponto na base de dados
    ├─ Atualiza status = 'aceita'
    └─ Chama ga_enviarAssinaturaAjusteAprovado($id)
        ├─ Cria instância documento (inst_documento_modulo)
        ├─ Preenche campos do layout:
        │  ├─ "De" = nome do solicitante (user)
        │  ├─ "Para" = nome do gestor aprovador
        │  ├─ "Observações" = dados da solicitação
        │  └─ "Data" = data do ajuste
        │
        ├─ Gera PDF (ga_gerarArquivoPdfInstanciaDocumento)
        │  └─ Arquivo fica em: armazem_paraiba/documentos/<arquiv...>
        │
        └─ Envia para assinatura (assinatura_integracao_enviarDocumentoParaMultiplosAssinantes)
            ├─ Copia PDF para: assinatura/uploads/integracao/
            ├─ Cria registro em solicitacoes_assinatura
            ├─ Adiciona 2 signatários em assinantes:
            │  ├─ Solicitante (ordem 1)
            │  └─ Gestor Aprovador (ordem 1, sem hierarquia)
            │
            └─ Status na fila = 'pendente' (aguardando assinatura)
                ↓
            Gestor autentica e assina
                ↓
            Solicitante autentica e assina
                ↓
            Documento fica pronto (status = 'assinado')
```

### 3. **Aprovação em Lote** (novo - opcional)
```
Gestor seleciona múltiplas solicitações
    ↓
Clica em "Aprovar Selecionados"
    ↓
Função: processarEmLote()
    └─ Para cada ID:
        └─ Chama aceitarSolicitacao($id)
            (Mesmo fluxo da aprovação individual)
                ↓
            Resultado: Cada solicitação gera seu próprio PDF e fila de assinatura
```

### 4. **Fluxo de Assinatura**
- **Sem email**: Assinatura apenas via sistema (ICP-Brasil)
- **Validação**: ICP-Brasil obrigatória
- **Salvamento**: Documento salvo no cadastro do funcionário
- **Hierarquia**: Nenhuma (ambos assinam com mesma ordem)

---

## Arquivos Modificados

### `telas/gerenciar_ajustes.php`
- ✅ Removida `ga_processarAprovacaoAgrupada()` (450+ linhas)
- ✅ Simplificada `ga_montarTabelaSolicitacoesAgrupadas()` → stub vazio
- ✅ Simplificado `processarEmLote()` → apenas chama `aceitarSolicitacao()` individual
- ✅ Mantida aprovação individual em `aceitarSolicitacao()`
- ✅ Mantido envio para assinatura em `ga_enviarAssinaturaAjusteAprovado()`

### Arquivos NÃO alterados
- `ajuste_pontofuncionario.php` - Sem PDF no envio
- `assinatura/integracao/assinatura_integracao.php` - Funções de integração
- `conecta.php` - Migrações de schema

---

## Como Testar

### Teste 1: Aprovação Individual
1. Usuário submete solicitação via `ajuste_pontofuncionario.php`
2. Gestor acessa `gerenciar_ajustes.php`
3. Gestor clica em "Aprovar" em uma solicitação
4. Sistema:
   - Valida ponto
   - Insere em banco
   - Gera PDF individual
   - Envia para fila de assinatura

### Teste 2: Aprovação em Lote
1. Gestor seleciona 3 solicitações com checkbox
2. Clica em "Aprovar Selecionados"
3. Sistema aprova cada uma individualmente
4. Resultado: 3 PDFs diferentes, 3 filas de assinatura diferentes

### Testes de Validação
```sql
-- Verificar se solicitação foi aprovada
SELECT id, status, data_decisao, id_instancia_documento 
FROM solicitacoes_ajuste 
WHERE id = 1;

-- Verificar se instância foi criada
SELECT inst_nb_id, inst_tx_status 
FROM inst_documento_modulo 
WHERE inst_nb_id = (SELECT id_instancia_documento FROM solicitacoes_ajuste WHERE id = 1);

-- Verificar se foi enviado para assinatura
SELECT id, status, tipo_documento_id 
FROM solicitacoes_assinatura 
WHERE id_documento LIKE 'INST_%' 
ORDER BY id DESC LIMIT 1;

-- Verificar signatários
SELECT id_solicitacao, nome, funcao, email, status 
FROM assinantes 
WHERE id_solicitacao = (
  SELECT id FROM solicitacoes_assinatura 
  WHERE id_documento LIKE 'INST_%' 
  ORDER BY id DESC LIMIT 1
);
```

---

## Checklist Pré-Produção

- [ ] Emails preenchidos para gestor e solicitante em `entidade.enti_tx_email`
- [ ] Pasta `assinatura/uploads/integracao/` existe e tem permissão 777
- [ ] Pasta `documentos/` existe com permissão de escrita
- [ ] Tipo de documento com layout ativo em `tipos_documentos`
- [ ] Campos do layout criados em `camp_documento_modulo`
- [ ] Função de integração disponível em `assinatura_integracao.php`
- [ ] Função `conferirErroPonto()` funcionando corretamente em `funcoes_ponto.php`

---

## Possíveis Problemas

### ❌ "Funcao de assinatura multi indisponivel"
**Causa**: Arquivo de integração não encontrado
**Solução**: Verificar se `assinatura/integracao/assinatura_integracao.php` existe

### ❌ "Instancia de documento nao encontrada"
**Causa**: Tipo de documento não tem layout ou campos
**Solução**: Criar campos em `camp_documento_modulo` para o tipo

### ❌ "Falha ao gerar PDF para assinatura"
**Causa**: Pasta de documentos sem permissão ou template ruim
**Solução**: Verificar permissões de `documentos/` e função `ga_gerarArquivoPdfInstanciaDocumento()`

### ❌ PDF não aparece na fila de assinatura
**Causa**: Email inválido, arquivo PDF não gerado, ou erro na integração
**Solução**: Verificar logs em `error_log` do servidor

---

## Notas Importantes

1. **Sem agrupamento**: Cada aprovação = 1 PDF único
2. **Sem email**: Assinatura apenas via sistema
3. **Validação**: ICP-Brasil obrigatória
4. **Ordem**: Sem hierarquia (gestor e solicitante assinam com ordem 1)
5. **Salvamento**: Documento fica no cadastro do funcionário
