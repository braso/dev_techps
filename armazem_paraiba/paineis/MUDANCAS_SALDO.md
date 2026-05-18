# Alterações realizadas na página de Saldo

## Resumo das mudanças
Refinamento da visualização do painel de saldo para uma estrutura hierárquica de empresas e funcionários.

## Principais modificações

### 1. Alteração na lógica de visualização (PHP)
**Arquivo**: `saldo.php`

- **Mudança**: Quando há filtros de ocupação, cargo, setor ou subsetor, mas SEM empresa selecionada, o sistema agora mostra AS EMPRESAS (em vez de funcionários individuais)
- **Implementação**: Adicionada variável `$mostrarEmpresasComFiltros` que detecta quando há filtros de detalhe sem empresa selecionada
- **Resultado**: Interface mais clara com visualização hierárquica

### 2. Novo comportamento de clique
- **Sem filtros**: Clicar na empresa mantém o comportamento anterior (abre a visualização de funcionários)
- **Com filtros**: Clicar na empresa abre a visualização de funcionários DA EMPRESA, mantendo os filtros aplicados

### 3. Função JavaScript `abrirModalFuncionarios()`
- Permite abrir a visualização de funcionários mantendo os filtros atuais
- Passa os filtros de ocupação, cargo, setor e subsetor junto com a empresa selecionada

### 4. Novo arquivo de suporte
**Arquivo**: `modal-funcionarios.php`
- Endpoint para carregar dados de funcionários via AJAX
- Aceita filtros de ocupação, cargo, setor, subsetor e busca por nome
- Retorna dados em formato JSON

## Fluxo de uso refinado

### Visualização Geral (sem filtros)
```
Painel de Empresas (visão geral)
    ↓ (clique em empresa)
Painel de Funcionários (da empresa selecionada)
```

### Visualização com Filtros
```
Painel de Empresas (apenas aquelas com funcionários correspondentes)
    ↓ (clique em empresa)
Painel de Funcionários (da empresa, com filtros aplicados)
                        → Filtro de Ocupação
                        → Filtro de Cargo
                        → Filtro de Setor
                        → Filtro de Subsetor
                        → Busca por nome (quando clicar novamente)
```

## Benefícios
✓ Visualização mais clara e intuitiva
✓ Acesso aos dados hierárquico: Empresa → Funcionários
✓ Filtros aplicados consistentemente
✓ Nenhuma alteração nas funcionalidades existentes
✓ Compatível com o sistema de impressão e exportação

## Notas técnicas
- A lógica de detecção de filtros verifica: `busca_ocupacao`, `operacao`, `busca_setor`, `busca_subsetor`
- Os filtros são mantidos ao navegar para a visualização de funcionários
- O arquivo `modal-funcionarios.php` pode ser explorado para futuras implementações de modal pop-up
