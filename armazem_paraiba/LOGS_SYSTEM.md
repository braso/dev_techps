# Sistema de Logs - Saldo.php

## Objetivo
Rastrear eventos, erros e performance do painel `armazem_paraiba/paineis/saldo.php` de forma estruturada e fácil de analisar.

---

## 📍 Localização dos Logs

**Diretório:** `armazem_paraiba/logs/`

**Arquivos:** `saldo_YYYY-MM-DD.log` (um arquivo por dia)

**Exemplo:** `saldo_2026-05-19.log`

---

## 📋 Formato dos Logs

Cada linha é um JSON com a seguinte estrutura:

```json
{
  "timestamp": "2026-05-19 14:35:42.567",
  "level": "INFO|WARNING|ERROR|DEBUG",
  "message": "Descrição do evento",
  "pid": 12345,
  "memory": "45.32MB",
  "context": {
    "chave1": "valor1",
    "chave2": "valor2"
  }
}
```

---

## 📊 Níveis de Log

| Nível | Cor | Uso |
|-------|-----|-----|
| **INFO** | 🔵 Azul | Eventos esperados e marcos principais |
| **WARNING** | 🟡 Amarelo | Situações inesperadas (ex: fallback ativado) |
| **ERROR** | 🔴 Vermelho | Erros críticos que afetam funcionamento |
| **DEBUG** | ⚪ Cinza | Informações detalhadas para diagnóstico |

---

## 🔍 Pontos de Log Implementados

### 1. **Inicialização**
- Log: `saldo.php - Painel iniciado`
- Captura: URL, método HTTP, IP do usuário
- Nível: INFO

### 2. **Processamento de Busca**
- Log: `Processando busca do painel saldo`
- Captura: Data do mês, empresa, modo detalhe
- Nível: INFO

### 3. **Geração de Relatório**
- Log: `empresas.json ausente - tentando gerar`
- Captura: Caminho, mês solicitado
- Nível: WARNING

- Log: `Invocando criar_relatorio_saldo()`
- Captura: Data processada
- Nível: INFO

- Log: `criar_relatorio_saldo() finalizado com sucesso`
- Captura: Duração em milissegundos
- Nível: INFO

- Log: `Erro ao executar criar_relatorio_saldo()` ⚠️
- Captura: Duração, mensagem de erro, memória usada
- Nível: ERROR

### 4. **Carregamento de Dados**
- Log: `Arquivo empresas.json encontrado`
- Captura: Caminho do arquivo
- Nível: INFO

- Log: `Fallback com zero totals ativado`
- Captura: Quantidade de empresas em fallback
- Nível: INFO

### 5. **Renderização**
- Log: `Dados encontrados e renderizando painel`
- Captura: Modo detalhe, quantidade de arquivos, período
- Nível: INFO

- Log: `Nenhum dado encontrado para o mês` ❌
- Captura: Mês, caminho, modo, empresa
- Nível: ERROR

---

## 🛠️ Como Analisar Logs

### Via Linha de Comando (Linux/Mac)

**Últimas 50 linhas de hoje:**
```bash
tail -50 armazem_paraiba/logs/saldo_$(date +%Y-%m-%d).log
```

**Filtrar apenas ERROs:**
```bash
grep '"level":"ERROR"' armazem_paraiba/logs/saldo_*.log
```

**Filtrar por data/hora específica:**
```bash
grep '2026-05-19 14:35' armazem_paraiba/logs/saldo_2026-05-19.log
```

**Contar quantidade de ERROs por dia:**
```bash
for f in armazem_paraiba/logs/saldo_*.log; do
  echo "$(basename $f): $(grep -c '"level":"ERROR"' "$f")"
done
```

### Via Browser (Desenvolvimento)

**Criar página de visualização (opcional):**

```php
<?php
$logFile = __DIR__ . '/../armazem_paraiba/logs/saldo_' . date('Y-m-d') . '.log';
if(!file_exists($logFile)) {
    die('Log não encontrado');
}

$lines = array_reverse(file($logFile));
echo '<pre style="background:#222; color:#0f0; font-family:monospace; padding:10px; overflow:auto; max-height:500px;">';
foreach(array_slice($lines, 0, 100) as $line) {
    $json = json_decode($line, true);
    $bgColor = '';
    if(isset($json['level'])) {
        $bgColor = match($json['level']) {
            'ERROR' => 'background:darkred;',
            'WARNING' => 'background:darkorange;',
            'INFO' => 'background:darkblue;',
            default => ''
        };
    }
    echo "<span style='$bgColor'>" . htmlspecialchars($line) . "</span>\n";
}
echo '</pre>';
?>
```

---

## 💾 Rotação de Logs

- **Tamanho máximo:** 5MB por arquivo
- **Backup:** Quando atinge 5MB, o arquivo é renomeado para `saldo_YYYY-MM-DD.log.HHMMSS`
- **Retenção:** Apenas 10 últimos backups são mantidos
- **Limpeza automática:** Backups antigos são deletados automaticamente

---

## 📈 Casos de Uso Comuns

### Diagnóstico de "Grid não aparece"
```bash
# Verificar se há ERROs ao gerar relatório
grep '"message":"Erro ao executar criar_relatorio_saldo' armazem_paraiba/logs/saldo_*.log

# Ver tempo de execução
grep '"duration_ms"' armazem_paraiba/logs/saldo_*.log
```

### Falhas de Permissão
```bash
# Verificar status de permissões
grep '"dir_gravável":false' armazem_paraiba/logs/saldo_*.log
```

### Análise de Performance
```bash
# Filtrar logs de duração de criar_relatorio_saldo
grep 'criar_relatorio_saldo() finalizado' armazem_paraiba/logs/saldo_*.log
```

---

## 🔐 Segurança

- Logs contêm apenas informações de negócio (sem senhas/tokens)
- Logs são armazenados no servidor
- Acesso restrito ao php-fpm/webserver user
- Considere fazer backup periódico

---

## 📝 Próximos Passos

1. **Commit e deploy** do código atualizado
2. **Teste em produção** com mês atual
3. **Monitore logs** para identificar o problema real
4. **Cole erros** encontrados aqui para ajuda rápida

---

## Classe Logger

**Arquivo:** `armazem_paraiba/utils/Logger.php`

**Uso:**
```php
// INFO
Logger::info('Descrição do evento', ['chave' => 'valor']);

// WARNING
Logger::warning('Aviso de situação inesperada', ['contexto' => 'dados']);

// ERROR
Logger::error('Erro crítico', ['erro' => 'mensagem']);

// DEBUG (detalhado)
Logger::debug('Informação detalhada', ['debug_info' => 'valor']);
```

---

**Última atualização:** 2026-05-19  
**Versão do Sistema:** 1.0
