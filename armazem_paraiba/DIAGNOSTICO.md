# DIAGNÓSTICO DE LOGS EM PRODUÇÃO - VALUEHOST

## Situação Atual
- Logs funcionam em **localhost** ✅
- Logs **NÃO funcionam** em produção ❌
- Nenhum arquivo de log criado após interações
- Sem acesso a logs do Apache/PHP visíveis

## Ações Imediatas

### 1️⃣ EXECUTAR DIAGNÓSTICO HTTP (PRIORITÁRIO)

**Acesse via navegador:**
```
https://seu-servidor.com/armazem_paraiba/diagnose.php
```

Este arquivo vai:
- ✅ Mostrar versão do PHP (8.3 confirmado)
- ✅ Mostrar usuário do processo web
- ✅ Testar escrita em **5 caminhos diferentes**
- ✅ Indicar **exatamente qual falha e por quê**
- ✅ Criar arquivos de teste para validar permissões

**Esperado:** Pelo menos 1 arquivo deve ser criado com sucesso e mostrar conteúdo



### 2️⃣ COMPARTILHAR O RESULTADO

Tire screenshot ou copie o output completo de `diagnose.php` e envie

**O resultado vai mostrar algo como:**
```
[logs_dir]
  Path: repositories/dev_techps/armazem_paraiba/logs
  Is Dir: NÃO
  Tentando criar folder...
    ✗ FALHOU ao criar pasta  <--- AQUI ESTÁ O PROBLEMA

[debug_log.txt]
  Path: repositories/dev_techps/armazem_paraiba/debug_log.txt
  ✓ file_put_contents: OK (bytes: 150)
  ✓ Arquivo criado: SIM
```

---

## Se Diagnóstico Falhar em TUDO

Se nenhum arquivo foi criado, execute via SSH:

```bash
# Verificar permissões
ls -la armazem_paraiba/
cd armazem_paraiba && ls -la

# Tenta criar arquivo manualmente
touch armazem_paraiba/test.txt
ls -la test.txt

# Verifica usuário do Apache
ps aux | grep -E 'apache|www|php' | grep -v grep

# Tenta escrever como usuário atual
echo "teste" > armazem_paraiba/manual_test.txt
cat armazem_paraiba/manual_test.txt

# Verifica logs do PHP (se existirem)
tail -100 /var/log/apache2/error_log
tail -100 /var/log/php-fpm/error.log
```

---

## Informações que Coletaremos do Diagnóstico

1. **Qual caminho de log consegue escrever** (logs/, debug_log.txt, temp dir?)
2. **Qual usuário está executando o PHP**
3. **Se open_basedir está restringindo acesso**
4. **Se há problema de permissão específico**

---

## Próximas Correções Planejadas

Uma vez com o resultado do diagnóstico:

### Se conseguir escrever em `debug_log.txt`:
✅ Problema resolvido! Logger está funcionando

### Se conseguir escrever em `/tmp/`:
✅ Logs vão para `/tmp/dev_techps_logs/` 
🔧 Precisa sincronizar com aplicação (melhor que nada)

### Se NADA funcionar:
❌ Problema é permissão de processo web
🔧 **Solução:** ValueHost vai precisar conferir:
- Permissões RFC da pasta `armazem_paraiba/`
- Qual usuário executa o Apache/PHP
- Se há restrições de SELinux/AppArmor

---

## Arquivos Atualizados

| Arquivo | Mudança |
|---------|---------|
| `Logger.php` | Agora registra diagnostics em `logger_diagnostics.txt` |
| `diagnose.php` | **NOVO** - Teste interativo via HTTP |
| `saldo.php` | Bootstrap ampliado com mais contexto (CWD, PHP user, PID) |

---

## Como Interpretar os Resultados

```
✓ = Sucesso - arquivo foi criado e contém dados
✗ = Falha - operação não foi bem-sucedida

Path:     LOCAL onde script tentou escrever
Writable: Se pasta permite escrita (SIM/NÃO)
Created:  Se arquivo foi criado com sucesso
```

---

## AÇÃO IMEDIATA

1. **Acesse:** `https://seu-servidor.com/armazem_paraiba/diagnose.php`
2. **Copie o output completo**
3. **Cole aqui ou compartilhe resultado**

Daí consigo identificar exatamente o problema e ajustar o código.
