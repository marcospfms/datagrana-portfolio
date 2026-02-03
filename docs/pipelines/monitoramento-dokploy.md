# Guia de Monitoramento: Queue Workers e Commands no Dokploy

Este guia ensina como verificar e monitorar **queue workers**, **scheduled commands** (cron) e processos em geral no Dokploy.

---

## 📋 Índice

1. [Verificar Queue Worker](#1-verificar-queue-worker)
2. [Verificar Scheduled Commands (Cron)](#2-verificar-scheduled-commands-cron)
3. [Monitorar Jobs na Fila](#3-monitorar-jobs-na-fila)
4. [Verificar Logs em Tempo Real](#4-verificar-logs-em-tempo-real)
5. [Debug de Problemas Comuns](#5-debug-de-problemas-comuns)
6. [Comandos Úteis via Terminal](#6-comandos-úteis-via-terminal)

---

## 1. Verificar Queue Worker

O **queue worker** processa jobs assíncronos (como notificações push). Ele é gerenciado pelo **Supervisor** dentro do container.

### 1.1. Via Dokploy UI (Recomendado)

**Passo a passo:**

1. Acesse o **Dokploy** no navegador
2. Vá em **Projects** → Selecione seu projeto (ex: `datagrana-sandbox` ou `datagrana-prod`)
3. Clique na **Application** (ex: `datagrana-portfolio-sandbox`)
4. Vá na aba **Logs**

**O que procurar nos logs:**

```log
[INFO] spawned: 'queue-worker' with pid 123
[INFO] success: queue-worker entered RUNNING state, process has stayed up for > than 1 seconds (startsecs)
```

**Se aparecer isso = Queue worker está rodando! ✅**

**Se NÃO aparecer:**
- Queue worker não está configurado ou não está inicializando
- Veja seção de troubleshooting abaixo

### 1.2. Via Terminal do Dokploy

1. Na Application, vá na aba **Terminal**
2. Execute:

```bash
# Verificar status do supervisor
supervisorctl status

# Deve aparecer algo como:
# queue-worker                     RUNNING   pid 123, uptime 1 day, 5:30:00
```

**Interpretação dos status:**

| Status | Significado |
|--------|-------------|
| `RUNNING` | ✅ Worker está ativo e processando |
| `STOPPED` | ❌ Worker foi parado manualmente |
| `FATAL` | ❌ Worker crashou (veja logs para detalhes) |
| `STARTING` | ⏳ Worker está inicializando |
| `BACKOFF` | ⚠️ Worker está reiniciando após falha |

### 1.3. Verificar Processos Rodando

Ainda no **Terminal do Dokploy**:

```bash
# Listar processos relacionados ao queue
ps aux | grep "queue:work"

# Saída esperada:
# www-data   123  0.5  1.2  php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

**Se aparecer o processo = Worker está rodando! ✅**

---

## 2. Verificar Scheduled Commands (Cron)

O Laravel executa comandos agendados via **Task Scheduler** (`schedule:run`). No Dokploy, isso pode ser configurado de duas formas:

### Opção A: Via Supervisor (Recomendado)

Se você configurou o scheduler como um programa no `supervisord.conf`:

```ini
[program:scheduler]
command=php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
...
```

**Verificar status:**

```bash
# No Terminal do Dokploy
supervisorctl status scheduler

# Deve aparecer:
# scheduler                        RUNNING   pid 456, uptime 2 days, 10:15:00
```

### Opção B: Via Cron do Container

Se você usa cron dentro do container (menos comum no Docker):

```bash
# No Terminal do Dokploy
crontab -l

# Deve aparecer algo como:
# * * * * * php /var/www/html/artisan schedule:run >> /dev/null 2>&1
```

**Verificar se cron está rodando:**

```bash
ps aux | grep cron

# Deve aparecer:
# root       789  0.0  0.1  cron
```

### 2.3. Testar Comandos Agendados Manualmente

Para testar se um comando específico funciona:

```bash
# No Terminal do Dokploy
php artisan schedule:list

# Lista todos os comandos agendados e seus horários

# Executar scheduler manualmente (processa comandos que devem rodar agora)
php artisan schedule:run

# Executar comando específico diretamente
php artisan app:cleanup-pdfs
```

---

## 3. Monitorar Jobs na Fila

Para ver se jobs estão sendo processados corretamente:

### 3.1. Verificar Tabela `jobs` (Jobs Pendentes)

```bash
# No Terminal do Dokploy
php artisan tinker

# Dentro do tinker
DB::table('jobs')->count();
// Se retornar 0 = Fila está vazia ✅
// Se retornar > 0 = Há jobs aguardando processamento

# Ver detalhes dos jobs pendentes
DB::table('jobs')->get();
```

### 3.2. Verificar Tabela `failed_jobs` (Jobs Falhados)

```bash
# No Terminal do Dokploy
php artisan queue:failed

# Lista jobs que falharam com detalhes do erro

# Ver contagem
php artisan tinker
DB::table('failed_jobs')->count();
// Se retornar 0 = Sem falhas ✅
// Se retornar > 0 = Há jobs que falharam
```

**Retentar jobs falhados:**

```bash
# Retentar um job específico
php artisan queue:retry <job_id>

# Retentar TODOS os jobs falhados
php artisan queue:retry all
```

### 3.3. Monitorar Queue em Tempo Real

```bash
# No Terminal do Dokploy
php artisan queue:monitor

# Exibe estatísticas sobre:
# - Jobs processados
# - Jobs falhados
# - Tempo de processamento
```

---

## 4. Verificar Logs em Tempo Real

### 4.1. Via Dokploy UI (Mais Fácil)

1. Vá na aba **Logs** da Application
2. Logs aparecem em tempo real
3. Use `Ctrl+F` para buscar por:
   - `processing_push_notification` (jobs de push sendo processados)
   - `push_notification_sent` (push enviado com sucesso)
   - `ERROR` ou `FATAL` (erros)

### 4.2. Via Terminal (Mais Controle)

```bash
# No Terminal do Dokploy

# Seguir logs do Laravel (stderr)
tail -f storage/logs/laravel.log

# Seguir logs do supervisor
tail -f /var/log/supervisor/supervisord.log

# Filtrar logs por palavra-chave
tail -f storage/logs/laravel.log | grep "queue"
tail -f storage/logs/laravel.log | grep "push_notification"
```

### 4.3. Logs Estruturados (Se configurado)

Se você configurou `LOG_CHANNEL=stderr`, os logs vão para stdout/stderr do container:

```bash
# Via SSH no servidor (se tiver acesso)
docker logs -f <container_name> 2>&1 | grep "queue"

# Dentro do Dokploy, os logs já aparecem na aba Logs automaticamente
```

---

## 5. Debug de Problemas Comuns

### Problema 1: Queue Worker Não Está Rodando

**Sintomas:**
- `supervisorctl status` mostra `STOPPED` ou `FATAL`
- Jobs ficam travados na tabela `jobs`

**Diagnóstico:**

```bash
# Ver logs do supervisor
cat /var/log/supervisor/supervisord.log

# Tentar iniciar manualmente
supervisorctl start queue-worker

# Se falhar, ver erro detalhado
supervisorctl tail queue-worker stderr
```

**Soluções Comuns:**

1. **Erro de permissão:**
   ```bash
   # Verificar permissões do storage
   ls -la storage/

   # Se necessário, corrigir (dentro do Dockerfile ou manualmente)
   chown -R www-data:www-data storage/
   chmod -R 775 storage/
   ```

2. **Erro de conexão com banco:**
   ```bash
   # Verificar conexão
   php artisan db:show

   # Se falhar, verificar variáveis de ambiente
   env | grep DB_
   ```

3. **Worker crashando:**
   ```bash
   # Reiniciar worker
   supervisorctl restart queue-worker

   # Se persistir, verificar logs do Laravel
   tail -f storage/logs/laravel.log
   ```

### Problema 2: Jobs Ficam Travados (Stuck)

**Sintomas:**
- `DB::table('jobs')->count()` sempre retorna o mesmo número
- Jobs nunca são processados

**Diagnóstico:**

```bash
# Ver jobs travados
php artisan tinker
DB::table('jobs')->get();

# Verificar se worker está realmente processando
tail -f storage/logs/laravel.log | grep "Processing"
```

**Soluções:**

1. **Reiniciar worker gracefully:**
   ```bash
   php artisan queue:restart

   # Aguardar alguns segundos e verificar
   supervisorctl status queue-worker
   ```

2. **Limpar jobs corrompidos (cuidado!):**
   ```bash
   php artisan tinker
   DB::table('jobs')->truncate(); // Remove TODOS os jobs pendentes
   ```

3. **Aumentar timeout se jobs demoram muito:**
   ```bash
   # Editar supervisord.conf
   # Mudar --timeout=60 para --timeout=120
   # Rebuild e redeploy
   ```

### Problema 3: Notificações Não Chegam

**Checklist de diagnóstico:**

1. **Job foi processado?**
   ```bash
   tail -f storage/logs/laravel.log | grep "push_notification_sent"

   # Se aparecer = Job processou ✅
   # Se NÃO aparecer = Job não chegou no worker ou falhou
   ```

2. **Job falhou?**
   ```bash
   php artisan queue:failed

   # Se aparecer job relacionado a push = Há erro no código
   # Ver detalhes do erro
   ```

3. **Token do dispositivo é válido?**
   ```bash
   php artisan tinker

   $user = User::find(1);
   $user->pushTokens;

   // Verificar se token existe e está correto
   ```

4. **Idempotência está bloqueando?**
   ```bash
   php artisan tinker

   // Verificar cache
   Cache::get('push:user.payment.confirmed:123:1');
   // Se retornar `true` = Notificação já foi enviada (bloqueada)
   ```

### Problema 4: Scheduled Commands Não Rodam

**Sintomas:**
- Comando agendado nunca executa
- Cleanup de PDFs não acontece

**Diagnóstico:**

```bash
# Listar comandos agendados
php artisan schedule:list

# Ver quando foi a última execução (se configurou logs)
tail storage/logs/scheduler.log

# Executar manualmente para testar
php artisan schedule:run
```

**Soluções:**

1. **Scheduler não está rodando:**
   ```bash
   # Verificar se supervisor está gerenciando
   supervisorctl status scheduler

   # Se STOPPED, iniciar
   supervisorctl start scheduler
   ```

2. **Comando não está registrado:**
   ```bash
   # Verificar routes/console.php ou app/Console/Kernel.php
   # Certificar que schedule() está configurado
   ```

3. **Timezone incorreto:**
   ```bash
   # Verificar timezone do servidor
   php artisan tinker
   config('app.timezone');

   # Se diferente do esperado, ajustar em .env
   TZ=America/Manaus
   ```

---

## 6. Comandos Úteis via Terminal

### Gerenciamento de Queue

```bash
# Ver status de todos os workers
supervisorctl status

# Reiniciar worker gracefully (finaliza job atual antes de reiniciar)
php artisan queue:restart

# Parar worker (via supervisor)
supervisorctl stop queue-worker

# Iniciar worker
supervisorctl start queue-worker

# Ver jobs falhados
php artisan queue:failed

# Retentar todos os jobs falhados
php artisan queue:retry all

# Limpar jobs falhados antigos (>48h)
php artisan queue:prune-failed --hours=48

# Limpar TODOS os jobs falhados
php artisan queue:flush
```

### Monitoramento de Filas

```bash
# Monitorar queue em tempo real
php artisan queue:monitor

# Ver quantos jobs estão na fila
php artisan tinker
DB::table('jobs')->count();

# Ver detalhes de jobs na fila
php artisan tinker
DB::table('jobs')->get();
```

### Gerenciamento de Scheduler

```bash
# Listar comandos agendados
php artisan schedule:list

# Executar scheduler manualmente (útil para testar)
php artisan schedule:run

# Testar comando específico
php artisan app:cleanup-pdfs

# Ver quando comando vai rodar novamente
php artisan schedule:list | grep cleanup
```

### Debug Geral

```bash
# Verificar conexão com banco
php artisan db:show

# Ver configurações do Laravel
php artisan config:show queue
php artisan config:show cache

# Limpar caches
php artisan optimize:clear

# Ver versão do Laravel e PHP
php artisan --version
php -v

# Verificar permissões
ls -la storage/
ls -la bootstrap/cache/
```

---

## 7. Checklist de Health Check (Use Periodicamente)

Execute esses comandos para garantir que tudo está funcionando:

```bash
# 1. Queue worker rodando?
supervisorctl status queue-worker
# Esperado: RUNNING ✅

# 2. Scheduler rodando? (se configurado)
supervisorctl status scheduler
# Esperado: RUNNING ✅

# 3. Jobs pendentes na fila?
php artisan tinker -q "DB::table('jobs')->count()"
# Esperado: 0 ou número baixo ✅

# 4. Jobs falhados?
php artisan queue:failed | wc -l
# Esperado: 0 ou número baixo ✅

# 5. Conexão com banco OK?
php artisan db:show
# Esperado: Detalhes do banco aparecem ✅

# 6. Logs sem erros críticos?
tail -n 50 storage/logs/laravel.log | grep -i "error\|fatal"
# Esperado: Nenhuma linha ou poucos erros ✅
```

---

## 8. Configuração de Alertas (Opcional - Futuro)

Para ambientes de produção, considere configurar:

### 8.1. Monitoramento de Uptime

- **UptimeRobot** (grátis) ou **Pingdom**
- Criar monitor para `https://datagrana.app/health`
- Alerta via email/SMS se site ficar offline

### 8.2. Monitoramento de Queue

Criar endpoint customizado:

```php
// routes/api.php
Route::get('/health/queue', function () {
    $jobsCount = DB::table('jobs')->count();
    $failedCount = DB::table('failed_jobs')->count();

    return response()->json([
        'queue' => [
            'pending' => $jobsCount,
            'failed' => $failedCount,
            'status' => $jobsCount < 100 && $failedCount < 10 ? 'healthy' : 'degraded'
        ]
    ]);
});
```

Monitorar esse endpoint externamente.

### 8.3. Logs Centralizados

- **Papertrail** (grátis até 50MB/mês)
- **Loggly** ou **Sentry**
- Integração via Laravel logging

---

## 9. Resumo Rápido

| O que verificar | Onde verificar | Comando rápido |
|-----------------|----------------|----------------|
| Queue worker rodando? | Terminal Dokploy | `supervisorctl status queue-worker` |
| Jobs na fila? | Terminal Dokploy | `php artisan tinker -q "DB::table('jobs')->count()"` |
| Jobs falhados? | Terminal Dokploy | `php artisan queue:failed` |
| Logs em tempo real | Aba Logs | (via UI) |
| Scheduler rodando? | Terminal Dokploy | `supervisorctl status scheduler` |
| Comandos agendados | Terminal Dokploy | `php artisan schedule:list` |

---

## 10. Referências

- **Documentação Laravel Queue**: https://laravel.com/docs/11.x/queues
- **Documentação Laravel Task Scheduling**: https://laravel.com/docs/11.x/scheduling
- **Supervisor Docs**: http://supervisord.org/
- **Estrutura Pipeline**: `datagrana-portfolio/docs/pipelines/estrutura-fluxo-pipeline.md`
- **Guia Implementação Dokploy**: `datagrana-portfolio/docs/pipelines/guia-implementacao-dokploy.md`

---

**Dica Final**: Adicione este arquivo aos favoritos e consulte sempre que precisar verificar o status do sistema em produção!
