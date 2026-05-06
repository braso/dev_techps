# Alterações no Upload de Funcionários via CSV

## Problema Relatado
A planilha estava cadastrando **somente os dados obrigatórios**. Vários campos opcionais preenchidos na planilha não eram gravados no banco de dados, sem gerar erro de validação.

## Solução Implementada
Expandido o sistema de upload de CSV para mapear e processar **TODOS os campos disponíveis** da tabela `entidade`, não apenas os obrigatórios.

---

## Campos Agora Suportados

### Campos Obrigatórios (Mantidos)
- Email
- Telefone 1
- Matrícula
- Nome
- Nascido em
- CPF
- RG
- CEP
- Bairro
- Endereço
- Salário
- Ocupação
- Data Admissão
- Parâmetro da Jornada

### Campos Opcionais (NOVOS)

#### Básicos
- `Telefone 2` → `enti_tx_fone2`
- `Login` → `user_tx_login`
- `Status` → `enti_tx_status` (ativo/inativo)
- `Desligamento` → `enti_tx_desligamento` (date)
- `Saldo de Horas` → `enti_tx_banco`
- `Subcontratado` → `enti_tx_subcontratado` (sim/não)

#### Estado Civil e Pessoais
- `Estado Civil` → `enti_tx_civil`
- `Sexo` → `enti_tx_sexo`
- `Raça/Cor` → `enti_tx_racaCor`
- `Tipo Sanguíneo` → `enti_tx_tipoSanguineo`
- `Emissor RG` → `enti_tx_rgOrgao`
- `Data Emissão RG` → `enti_tx_rgDataEmissao` (date)
- `UF RG` → `enti_tx_rgUf`

#### Filiação
- `Pai` → `enti_tx_pai`
- `Mãe` → `enti_tx_mae`
- `Cônjuge` → `enti_tx_conjugue`
- `Número` → `enti_tx_numero`
- `Complemento` → `enti_tx_complemento`
- `Referência` → `enti_tx_referencia`

#### Localização (Além dos Obrigatórios)
- `Cod IBGE` → `cida_nb_id` (resolve via IBGE)
- `UF` → usado em conjunto com Cidade/UF
- `Empresa` → `enti_nb_empresa`
- `Setor` → `enti_setor_id`
- `Sub Setor` → `enti_subSetor_id`
- `Cargo` → `enti_tx_tipoOperacao`

#### Jornada (Além dos Obrigatórios)
- `Jornada Semanal` → `enti_tx_jornadaSemanal`
- `Jornada Sábado` → `enti_tx_jornadaSabado`
- `HE % Semanal` → `enti_tx_percHESemanal`
- `HE % Extra` → `enti_tx_percHEEx`
- `Parametros da Jornada Escala` → alternativa de parâmetro

#### Documentos
- `PIS` → `enti_tx_pis`
- `CTPS Número` → `enti_tx_ctpsNumero`
- `CTPS Série` → `enti_tx_ctpsSerie`
- `CTPS UF` → `enti_tx_ctpsUf`
- `Título Número` → `enti_tx_tituloNumero`
- `Título Zona` → `enti_tx_tituloZona`
- `Título Seção` → `enti_tx_tituloSecao`
- `Reservista` → `enti_tx_reservista`
- `Registro Funcional` → `enti_tx_registroFuncional`
- `Órgão Regime Funcional` → `enti_tx_OrgaoRegimeFuncional`
- `Vencimento Registro` → `enti_tx_vencimentoRegistro` (date)

#### Carteira Nacional de Habilitação (CNH)
- `CNH Número` → `enti_tx_cnhRegistro`
- `CNH Categoria` → `enti_tx_cnhCategoria`
- `CNH Cidade` → `enti_nb_cnhCidade`
- `CNH Emissão` → `enti_tx_cnhEmissao` (date)
- `CNH Validade` → `enti_tx_cnhValidade` (date)
- `CNH Primeira Habilitação` → `enti_tx_cnhPrimeiraHabilitacao` (date)
- `CNH Permissão` → `enti_tx_cnhPermissao`
- `CNH Pontuação` → `enti_tx_cnhPontuacao`
- `CNH Atividade Remunerada` → `enti_tx_cnhAtividadeRemunerada` (sim/não)
- `CNH Observações` → `enti_tx_cnhObs`

#### Observações
- `Observações` → `enti_tx_obs`

---

## Como Usar

### 1. Download do Modelo CSV
Acesse **Cadastro de Funcionário** → Clique em **"Download Modelo CSV"** para obter a planilha com todos os cabeçalhos suportados.

### 2. Preenchimento da Planilha
- Preencha os **campos obrigatórios** (marcados com `*` no cabeçalho)
- Preencha apenas os **campos opcionais que desejar** (vazio = não é processado)
- Use datas no formato `DD/MM/YYYY` ou `YYYY-MM-DD`
- Use valores monetários com vírgula como separador decimal

### 3. Upload
1. Acesse **Cadastro de Funcionário**
2. Localize o botão **"Upload CSV"** no final da página de busca
3. Selecione o arquivo `.csv` preenchido
4. Clique em **"Upload CSV"**

### 4. Resultado
O sistema importará todos os funcionários, incluindo **todos os campos opcionais preenchidos**, sem ignorá-los.

---

## Alterações Técnicas

### Arquivo Modificado
- `armazem_paraiba/cadastro_funcionario.php`

### Funções Atualizadas

#### 1. `obterCabecalhoModeloCsvFuncionario()`
- **Antes:** 40+ campos desorganizados e duplicados
- **Depois:** 70+ campos organizados por categoria

#### 2. `uploadCsvFuncionarios()`
- **Mapeamento de Colunas:** Expandido de ~30 para ~70 campos
- **Detecção de Variações:** Mantém a lógica fuzzy matching para nomes ligeiramente diferentes
- **Extração de Dados:** Todos os campos novos são extraídos do CSV
- **Validação:** Retém validações de campos obrigatórios
- **Inserção:** Array `$novoMotorista` agora inclui todos os campos opcionais

### Compatibilidade
- ✅ Backward compatible: CSVs antigos continuam funcionando
- ✅ Flexível: Detecta variações de nomes (ex: "Salário" vs "Salario")
- ✅ Seguro: Campos vazios são ignorados (NULL no BD)

---

## Testes Recomendados

1. **Teste 1 - Somente Obrigatórios**
   - Crie um CSV com apenas os campos obrigatórios
   - Verifique se importa normalmente

2. **Teste 2 - Todos os Campos**
   - Preencha a planilha com TODOS os campos
   - Verifique se todos são gravados

3. **Teste 3 - Campos Seletivos**
   - Preencha apenas alguns campos opcionais (ex: RG, CNH, Documentos)
   - Verifique se apenas os preenchidos são gravados

4. **Teste 4 - Validações de Data**
   - Teste formatos: `DD/MM/YYYY` e `YYYY-MM-DD`
   - Verifique se dates com valores inválidos geram erro apropriado

---

## Exemplo de Uso

### Planilha com Alguns Campos Opcionais

```csv
Email;Telefone 1;Matrícula;Nome;Nascido em;CPF;RG;CEP;Bairro;Endereço;Salário;Ocupação;Dt Admissão;Parametro_Jornada;RG UF;Sexo;PIS;CNH Número
joao@empresa.com;(85)9999-9999;001;João Silva;15/03/1990;12345678901;1234567;60000-000;Centro;Rua A;3000.00;Motorista;01/01/2024;Padrão;CE;Masculino;17012345678;1234567890
```

**Resultado:** Funcionário importado com:
- Dados obrigatórios ✅
- RG UF preenchido ✅
- Sexo preenchido ✅
- PIS preenchido ✅
- CNH Número preenchido ✅
- Outros campos opcionais = NULL (não preenchidos)

---

## Notas Importantes

1. **Campos de Data:** Suportam automaticamente `DD/MM/YYYY` e `YYYY-MM-DD`
2. **CPF/RG/PIS:** São automaticamente limpos (removidos caracteres especiais)
3. **Valores Monetários:** Use vírgula como separador decimal (ex: `3000,50`)
4. **Status:** Se não preenchido, assume "ativo"; usa "inativo" se explícito
5. **Login:** Se não preenchido, usa a matrícula como login
6. **Empresa:** Se não preenchido, usa a empresa padrão da sessão/filtro

---

## Logs de Erro

Se algum funcionário não for importado, o sistema exibe:
- **Número da linha** do CSV
- **Campo problemático**
- **Descrição do erro**

Exemplo:
```
ERRO: Importação concluída. Total linhas: 10. Gravados: 8.
Ocorreram 2 erro(s):
- Linha 3: campos obrigatórios não preenchidos (email, telefone_1).
- Linha 7: CPF inválido.
```

---

## Suporte

Se encontrar problemas:
1. Verifique se os campos obrigatórios estão preenchidos
2. Verifique o formato das datas (DD/MM/YYYY)
3. Verifique se os valores de `Parametro_Jornada` existem no cadastro
4. Verifique se as empresas/setores/cargos existem (se preenchidos)
5. Consulte os logs de erro gerados pelo sistema
