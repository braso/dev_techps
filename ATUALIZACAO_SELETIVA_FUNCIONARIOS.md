# Implementação - Atualização Seletiva de Funcionários (FASE 2)

## O Que Mudou?

Anteriormente, quando você tentava importar um funcionário cujo número de matrícula já existia no sistema, a importação era **rejeitada** completamente:

```
❌ ERRO: Linha 5: matrícula já cadastrada.
```

Agora, o sistema é **inteligente**:

```
✅ OK: Funcionário 'João Silva' atualizado. 
   Campos preenchidos: rgOrgao, pai, cnhRegistro.
```

---

## Como Funciona

### ANTES
```
Funcionário no BD              Planilha                 Ação
┌─────────────────────┐     ┌──────────────────┐
│ RG Órgão: VAZIO     │ +   │ RG Órgão: SSP    │  →  ❌ REJEITA TUDO
│ Pai: VAZIO          │     │ Pai: José Silva  │
│ CNH: VAZIO          │     │ CNH: 12345678900 │
└─────────────────────┘     └──────────────────┘
```

### AGORA
```
Funcionário no BD              Planilha                 Ação
┌─────────────────────┐     ┌──────────────────┐
│ RG Órgão: VAZIO     │ +   │ RG Órgão: SSP    │  →  ✅ PREENCHE
│ Pai: VAZIO          │     │ Pai: José Silva  │  →  ✅ PREENCHE
│ CNH: VAZIO          │     │ CNH: 12345678900 │  →  ✅ PREENCHE
└─────────────────────┘     └──────────────────┘
```

---

## Regra de Ouro: "Só Preenche o Vazio"

A atualização seletiva **NUNCA** sobrescreve dados já existentes no banco. Ela apenas **preenche campos vazios**.

### Exemplos

#### Exemplo 1 - Campos Vazios Preenchidos
```
Antes:                        Depois:
enti_tx_sexo: NULL       →     enti_tx_sexo: "Masculino"  ✅ Preenchido
enti_tx_pai: NULL        →     enti_tx_pai: "José Silva"  ✅ Preenchido
enti_tx_mae: "Maria"     →     enti_tx_mae: "Maria"       ❌ Mantém
enti_tx_obs: NULL        →     enti_tx_obs: NULL          ❌ Deixa vazio (não está na planilha)
```

#### Exemplo 2 - Campos Já Preenchidos
```
Funcionário João Silva no BD:        Planilha com dados diferentes:
enti_tx_sexo: "Masculino"            Sexo: "Feminino"
enti_tx_pai: "José Silva"            Pai: "Pedro Silva"

Resultado:
✅ MANTÉM "Masculino"  (não sobrescreve com "Feminino")
✅ MANTÉM "José Silva" (não sobrescreve com "Pedro Silva")
```

---

## Quais Campos Podem Ser Atualizados?

**TODOS os campos opcionais:**

### Pessoais
- Sexo
- Estado Civil
- Raça/Cor
- Tipo Sanguíneo

### RG Completo
- RG Órgão (emissor)
- Data Emissão RG
- UF RG

### Filiação
- Pai
- Mãe
- Cônjuge

### Endereço
- Número
- Complemento
- Referência
- Telefone 2

### Documentos
- PIS
- CTPS (número, série, UF)
- Título de Eleitor (número, zona, seção)
- Registro Funcional
- Órgão Regime Funcional
- Vencimento Registro
- Reservista

### CNH (Carteira Nacional de Habilitação)
- Número
- Categoria
- Cidade Emissão
- Data Emissão
- Data Validade
- Data Primeira Habilitação
- Permissão
- Pontuação
- Atividade Remunerada
- Observações

### Outros
- Desligamento (data)
- Saldo de Horas (banco)
- Subcontratado (sim/não)
- Observações

---

## Exemplo Prático

### Cenário
Você tem o funcionário **João Silva** já cadastrado com alguns dados:

```sql
INSERT INTO entidade (enti_tx_matricula, enti_tx_nome, enti_tx_sexo, enti_tx_pai, enti_tx_cnhRegistro)
VALUES ('001', 'João Silva', NULL, NULL, NULL);
```

### Você Importa do CSV
```csv
Matrícula;Nome;Sexo;Pai;Mãe;CNH Número;CNH Categoria
001;João Silva;Masculino;José Silva;Maria;123456789;AB
```

### O Sistema Detecta
```
Matrícula 001 já existe!
├─ Sexo no BD:              NULL  → CSV tem "Masculino"   ✅ ATUALIZA
├─ Pai no BD:               NULL  → CSV tem "José Silva"  ✅ ATUALIZA
├─ Mãe no BD:               NULL  → CSV tem "Maria"       ✅ ATUALIZA
├─ CNH Número no BD:        NULL  → CSV tem "123456789"   ✅ ATUALIZA
└─ CNH Categoria no BD:     NULL  → CSV tem "AB"          ✅ ATUALIZA
```

### Resultado no Relatório
```
✅ OK: Importação concluída. Total linhas: 1. Gravados: 1.

Atualizações Realizadas (1):
- Linha 2: Funcionário 'João Silva' atualizado. 
  Campos preenchidos: sexo, pai, mae, cnhRegistro, cnhCategoria.
```

### Dados Finais no BD
```sql
SELECT * FROM entidade WHERE enti_tx_matricula = '001';

enti_tx_matricula:      '001'
enti_tx_nome:           'João Silva'
enti_tx_sexo:           'Masculino'        ← Preenchido
enti_tx_pai:            'José Silva'       ← Preenchido
enti_tx_mae:            'Maria'            ← Preenchido
enti_tx_cnhRegistro:    '123456789'        ← Preenchido
enti_tx_cnhCategoria:   'AB'               ← Preenchido
```

---

## Relatório Final Detalhado

### ANTES (FASE 1)
Quando havia conflito de matrícula, o relatório era simples:

```
ERRO: Nenhum funcionário foi importado. Total linhas: 3. Erros: 1.
- Linha 2: matrícula já cadastrada.
```

### AGORA (FASE 2)
O relatório é bem mais informativo:

#### Cenário 1: Tudo Novo
```
OK: Importação concluída. Total linhas: 3. Gravados: 3.

(Nenhuma atualização, todos são novos)

Erros Encontrados (0):
```

#### Cenário 2: Mix de Novos e Atualizações
```
OK: Importação concluída. Total linhas: 5. Gravados: 5.

Atualizações Realizadas (2):
- Linha 2: Funcionário 'Carlos Mendes' atualizado. 
  Campos preenchidos: sexo, rgOrgao, rgUf, pai, mae.
- Linha 4: Funcionário 'Ana Paula' atualizado. 
  Campos preenchidos: cnhRegistro, cnhCategoria, cnhEmissao, cnhValidade.

Erros Encontrados (0):
```

#### Cenário 3: Com Erros e Avisos
```
OK: Importação concluída. Total linhas: 10. Gravados: 8.

Atualizações Realizadas (1):
- Linha 7: Funcionário 'Pedro Junior' atualizado. 
  Campos preenchidos: tipoSanguineo, pis, ctpsNumero.

Erros Encontrados (2):
- Linha 3: CPF inválido.
- Linha 9: Cidade/IBGE não encontrado(a).
```

#### Cenário 4: Funcionário Já Completo
```
OK: Importação concluída. Total linhas: 5. Gravados: 4.

Atualizações Realizadas (0):
- Linha 3: Funcionário 'Maria Silva' já existe. Nenhum campo novo para atualizar.

Erros Encontrados (1):
- Linha 5: Matrícula inválida.
```

---

## Vantagens

✅ **Reutiliza dados existentes** - Não perde informações já cadastradas  
✅ **Complementa registros** - Preenche buracos nos cadastros  
✅ **Seguro** - Nunca sobrescreve dados já preenchidos  
✅ **Transparente** - Mostra exatamente o que foi atualizado  
✅ **Transacional** - Uma atualização falha = nada é gravado  
✅ **Compatível** - Funcionários novos continuam sendo criados normalmente  

---

## Casos de Uso

### Caso 1: Importação Incremental
Você importa funcionários em múltiplas rodadas:
- **Rodada 1:** Cria João Silva com dados básicos (nome, email, telefone)
- **Rodada 2:** Importa dados adicionais de João Silva (RG, CPF, Pai, Mãe)
- **Rodada 3:** Importa dados de CNH de João Silva

Resultado: Na rodada 2 e 3, os campos vazios são preenchidos, não rejeita!

### Caso 2: Migração de Dados
Você está migrando de outro sistema:
- **Migração 1:** Importa dados obrigatórios apenas
- **Migração 2:** Importa dados de documentos pessoais
- **Migração 3:** Importa dados de CNH
- **Migração 4:** Importa dados de filiação

Cada rodada complementa os registros anteriores!

### Caso 3: Correção em Lote
Você descobriu que faltam dados de CNH. Prepara um CSV com:
- Matrícula (para identificar)
- CNH (para preencher campos vazios)

Importa e todos os registros que tinham CNH vazia agora estão preenchidos!

---

## Fluxograma da Decisão

```
Funcionário existe?
│
├─ NÃO → Criar novo funcionário ✅
│
└─ SIM → Há campos vazios no BD que estão preenchidos na planilha?
        │
        ├─ SIM → Atualizar apenas os campos vazios ✅
        │
        └─ NÃO → Avisar "Nenhum campo novo para atualizar" ⚠️
```

---

## Segurança

### Transações de Banco de Dados
Cada atualização está dentro de uma transação:

```php
START TRANSACTION;
├─ Executa UPDATE
├─ Se erro → ROLLBACK (desfaz tudo)
└─ Se OK → COMMIT (confirma tudo)
```

### Validações Mantidas
- ✅ CPF continua sendo validado
- ✅ RG continua sendo validado
- ✅ Datas continuam sendo validadas
- ✅ Campos obrigatórios continuam obrigatórios

### Dados Protegidos
- ✅ Nunca sobrescreve dados já preenchidos
- ✅ Campos NULL no CSV não causam UPDATE
- ✅ Valores vazios na planilha são ignorados

---

## Próximos Passos

1. **Teste com dados reais** - Use um CSV com matrícula duplicada
2. **Verifique o relatório** - Veja quais campos foram preenchidos
3. **Valide os dados** - Confirme no BD que está correto
4. **Monitore erros** - Repare em validações que falharem

Tudo pronto! 🎉
