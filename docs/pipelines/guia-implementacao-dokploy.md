# Guia de Implementação: Pipeline CI/CD com Dokploy

Este guia descreve o passo a passo para configurar o pipeline de CI/CD para os ambientes de **Sandbox** e **Produção** utilizando **Dokploy** e **GitHub Actions**.

## 0. Conceitos importantes (antes de começar)

### 0.1. Como o Dokploy organiza as coisas

- **Project**: é um agrupador lógico de serviços (ex: aplicações, bancos).
- **Services** (dentro do Project): por exemplo, **Application** (Laravel) e **Database** (MySQL/MariaDB).
- **Domínios e TLS**: normalmente são roteados por Traefik.
- **Rede**: em muitos cenários (especialmente com Traefik), os serviços acabam compartilhando a rede overlay `dokploy-network` para roteamento e comunicação.

### 0.2. Por que aparece "Production environment" quando você cria um projeto?

No UI do Dokploy, é comum um projeto já nascer com um "ambiente"/rótulo padrão exibido como **Production environment**. Isso é apenas um label/ambiente padrão no painel (não significa que você configurou produção por acidente).

### 0.3. Decisão recomendada para Sandbox vs Produção (isolamento)

Para o seu objetivo (separar **Sandbox** para não "enxergar"/misturar com **Produção**):

1. **Recomendado (isolamento operacional + menos risco):** criar **2 projetos separados**:
   - `datagrana-sandbox` (branch `develop`)
   - `datagrana-prod` (branch `main`)

2. **Recomendado (isolamento de dados):** **2 bancos separados**, com credenciais diferentes:
   - `mysql-sandbox` em `datagrana-sandbox`
   - `mysql-prod` em `datagrana-prod`

3. **Importante (isolamento de rede):**
   - Se ambos os ambientes rodam **na mesma instância/cluster** do Dokploy, eles podem compartilhar rede/infra (ex: `dokploy-network`). Isso é bom para operar, mas não é uma "parede" de segurança perfeita.
   - Se você precisa de isolamento forte (ex: "mesmo que o sandbox seja comprometido, não consegue nem abrir conexão com produção"), o caminho mais seguro é **separar por instância**: outra VPS/VM/cluster (ou outro Dokploy) para produção.

4. **Isolamento de acesso humano (painel):** use permissões por projeto/serviço no Dokploy para que usuários de sandbox não tenham acesso ao projeto de produção.

---

## 1. Pré-requisitos

- Acesso administrativo à VPS com Dokploy instalado (Ubuntu 24.04 ou superior).
- Acesso administrativo ao repositório GitHub do `datagrana-portfolio`.
- **Domínio Ativo**: `datagrana.app` (acesso ao painel de DNS, ex: cPanel, Cloudflare, Registro.br, etc).

### 1.0.1. Build Web (Vite) opcional

Para manter o pipeline funcional mesmo sem a parte web ativa, o build de assets pode ser
**desabilitado no CI**. A flag usada no workflow é:

- `VITE_WEB_APP_ENABLED=false` (default no CI) → **pula** o `npm run build`
- `VITE_WEB_APP_ENABLED=true` → habilita o build da web

Isso **não precisa** existir no ambiente do Dokploy (runtime). É apenas para o build no CI.

### 1.0.2. Cache de dependências (NPM + Composer)

O pipeline usa cache para acelerar o CI:

- **NPM**: `~/.npm` usando a hash do `package-lock.json`
- **Composer**: `~/.composer/cache` usando a hash do `composer.lock`

Para **desativar o cache** no CI, basta:

- remover os steps `actions/cache` de NPM/Composer
- (opcional) remover `cache` do `setup-node`

### 1.1. Estratégia de Tags de Imagem

O GitHub Actions gera **automaticamente** dois tipos de tags para cada commit:

**Tags Móveis (Recomendado para uso diário - mais simples):**
- Formato: `:develop` (sandbox) e `:latest` (produção)
- Sempre apontam para a **versão mais recente** daquela branch
- **Vantagem**: Deploy totalmente automático via webhook
- **Como usar**: Configure uma vez no Dokploy e esqueça - tudo funciona automaticamente
- **Uso neste guia**: Esta é a abordagem que vamos usar! ✅

**Tags Imutáveis (Para rollback e auditoria):**
- Formato: `sha-<commit_sha_completo>` (ex: `sha-abc123def456...`)
- **Nunca mudam** - sempre apontam para aquele commit específico
- **Vantagem**: Rastreabilidade total e facilita rollback
- **Quando usar**: Quando precisar voltar para uma versão anterior ou "congelar" uma versão específica
- **Geradas automaticamente**: Você não precisa fazer nada, são criadas pelo workflow

### 1.2. GitHub Container Registry (GHCR)

Você precisa configurar credenciais para **dois consumidores**:

#### 1.2.1. GitHub Actions (para push de imagens)

Configure as secrets no repositório GitHub:

| Secret | Descrição | Como obter |
| :--- | :--- | :--- |
| `REGISTRY_USERNAME` | Seu usuário do GitHub (ex: `marcomamede`) | Seu username |
| `REGISTRY_PASSWORD` | Personal Access Token (PAT) com escopo `write:packages` | Settings → Developer settings → Personal access tokens → Tokens (classic) |

#### 1.2.2. Dokploy (para pull de imagens)

**Se a imagem for pública:** Não precisa de credenciais.

**Se a imagem for privada:** Configure um Registry no Dokploy (ver seção 1.3).

### 1.3. Como Configurar o Docker Provider no Dokploy

Antes de criar as aplicações, configure o acesso ao GitHub Container Registry:

#### Opção A: Registry Global (Recomendado - configurar uma vez)

1. No Dokploy, acesse **Registry** no menu lateral
2. Clique em **Add Registry**
3. Preencha o formulário:
   - **Registry Name**: `GHCR Production` (ou um nome descritivo)
   - **Registry URL**: `ghcr.io`
   - **Username**: Seu usuário do GitHub (ex: `marcomamede`)
   - **Password**: Um PAT (Personal Access Token) com escopo `read:packages`
     - Gerar em: GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
     - Marque apenas `read:packages` (suficiente para pull)
     - Se o package está associado a repositório privado, marque também `repo`
   - **Image Prefix**: Deixe vazio (não é necessário para este projeto)
4. Clique em **Test** para verificar a conexão
5. Clique em **Create** para salvar

**Vantagens:**
- Configure uma vez, use em múltiplas aplicações
- Credenciais armazenadas de forma segura
- Facilita login em servidores remotos (se usar cluster)

#### Opção B: Credenciais Diretas na Application

Ao criar a Application com Provider **Docker**, você pode preencher os campos de autenticação diretamente:
- `Registry URL`: `ghcr.io`
- `Username`: seu usuário do GitHub
- `Password`: PAT com `read:packages`

**Desvantagens:**
- Precisa repetir para cada aplicação
- Menos conveniente para múltiplos serviços

---

## 2. Configuração de DNS (Fazer Primeiro)

Configure os registros DNS **antes** de habilitar SSL no Dokploy. O Let's Encrypt precisa que o domínio esteja apontando para o servidor correto.

### 2.1. No Provedor de Domínio (cPanel)

1. **Acesse o cPanel** do seu provedor de hospedagem
2. Procure por **Zone Editor** ou **Editor de Zona DNS**
3. Localize o domínio `datagrana.app` e clique em **Manage** (Gerenciar)

### 2.2. Criar Registro para Sandbox

4. Adicione um novo registro do tipo **A**:
   - **Nome (Name/Host)**: `sandbox.datagrana` ou `sandbox.datagrana.app`
     - *Alguns painéis aceitam só o subdomínio, outros o FQDN completo*
   - **Tipo (Type)**: `A`
   - **Registro (Record/Points to/Address)**: `<IP_DA_VPS_ONDE_ESTA_O_DOKPLOY>`
     - *Exemplo: `203.0.113.50`*
   - **TTL**: `14400` (4 horas) ou deixe o padrão
5. Clique em **Add Record** ou **Salvar**

### 2.3. Criar Registro para Produção

6. Adicione outro registro do tipo **A**:
   - **Nome (Name/Host)**: `datagrana` ou `datagrana.app`
   - **Tipo (Type)**: `A`
   - **Registro (Record/Points to/Address)**: `<IP_DA_VPS_ONDE_ESTA_O_DOKPLOY>` (mesmo IP)
   - **TTL**: `14400` (4 horas) ou deixe o padrão
7. Clique em **Add Record** ou **Salvar**

### 2.4. Verificar Propagação

**Aguarde a propagação do DNS** (pode levar de 5 minutos a 24 horas, mas geralmente é rápido)

```bash
# Verificar Sandbox
nslookup sandbox.datagrana.app

# Verificar Produção
nslookup datagrana.app

# Ou use um serviço online
# https://dnschecker.org
```

**Importante**: Só prossiga para habilitar SSL no Dokploy **após** o DNS estar propagado e apontando para o IP correto. Caso contrário, o Let's Encrypt não conseguirá validar o domínio.

---

## 3. Ambiente Sandbox (`develop`)

**URL Alvo**: `sandbox.datagrana.app`

### 3.1. Criar Projeto no Dokploy

1. No Dokploy, crie um **Project** com o nome `datagrana-sandbox`.
2. Se o painel mostrar algo como `Mecanix Sandbox - Production environment`, trate apenas como **label** do projeto.
   - Opcional: renomeie o "ambiente"/label para algo como `Sandbox` (isso é só organização visual).

### 3.2. Criar Banco de Dados (Sandbox)

1. Dentro do projeto `datagrana-sandbox`, crie um serviço **Database** (MySQL/MariaDB).
2. Nome sugerido: `mysql-sandbox`.
3. Configure credenciais **exclusivas do sandbox**:
   - `DB_DATABASE=datagrana` (pode ser o mesmo nome lógico, mas em instância/serviço diferente)
   - `DB_USERNAME=<usuario_sandbox>`
   - `DB_PASSWORD=<senha_sandbox_forte>`
4. **Não exponha o banco para a internet** (evite "External Credentials"/porta pública) a menos que seja realmente necessário.
5. Anote os dados de conexão **internos**:
   - `DB_HOST`: use o "Internal Host" do serviço (geralmente o próprio nome do serviço no Dokploy/rede).
   - `DB_PORT`: geralmente `3306`.

### 3.3. Criar Aplicação Laravel (Provider Docker)

1. Ainda no projeto `datagrana-sandbox`, crie uma **Application** (ex: `datagrana-portfolio-sandbox`).
2. Vá em **Deploy Settings** (ou na etapa de criação da Application) e em **Provider** selecione **Docker**.
3. Preencha os campos do Provider Docker:
   - `Docker Image`: `ghcr.io/<SEU_USUARIO_GITHUB>/datagrana-portfolio:develop`
     - **Exemplo**: `ghcr.io/marcomamede/datagrana-portfolio:develop`
     - **Importante**: Use a tag `:develop` para que o Dokploy sempre busque a versão mais recente automaticamente
   - `Registry URL`: `ghcr.io` (se não configurou Registry global)
   - `Username`: seu usuário do GitHub (se não configurou Registry global)
   - `Password`: PAT com `read:packages` (se não configurou Registry global)

**Como funciona:**
- Você faz push → GitHub Actions gera a imagem com tag `:develop` → Webhook aciona Dokploy → Dokploy busca automaticamente a versão mais recente
- **Você não precisa atualizar nada manualmente!** ✅

**Nota sobre Deploy Method:**
- **NÃO** use "On Push" ou builders automáticos
- O deploy será disparado pelo **Deployments → Webhook** (GitHub Actions)

### 3.4. Configurar Domínio e HTTPS (Sandbox)

1. Na aplicação, vá em **Domains** (ou seção equivalente).
2. Adicione o domínio: `sandbox.datagrana.app`
3. Configure a porta do container:
   - **Container Port**: `80` (conforme seu `Dockerfile`/Nginx).
4. Habilite **HTTPS/SSL** (Let's Encrypt) e configure o e-mail (se solicitado).
5. Aguarde o provisionamento do certificado.

### 3.5. Variáveis de Ambiente (Sandbox)

Configure as variáveis **no Dokploy**, na aplicação `datagrana-portfolio-sandbox`:

**Essenciais**
- `APP_URL=https://sandbox.datagrana.app`
- `APP_ENV=production` (ou `staging`)
- `APP_DEBUG=true`
- `APP_KEY=base64:...` (gere **uma vez** com `php artisan key:generate --show` localmente e cole aqui)
- `DB_HOST=<internal-host-do-mysql-sandbox>`
- `DB_PORT=3306`
- `DB_DATABASE=datagrana`
- `DB_USERNAME=<usuario_sandbox>`
- `DB_PASSWORD=<senha_sandbox>`

**Recomendadas (observabilidade em container)**
- `LOG_CHANNEL=stderr`

**Opcional (rastreamento de versão)**
- `APP_VERSION=develop`
- `GIT_COMMIT=<será atualizado via webhook ou manualmente>`

### 3.6. Configurar Webhook de Deploy (Sandbox)

1. Vá na aba **Deployments**.
2. Habilite **Deploy via Webhook** e copie a URL.
3. Guarde essa URL para configurar no GitHub (próximo passo).

**IMPORTANTE - NÃO Configure Post Deploy Command Aqui:**

⚠️ **NÃO** use o campo "Post Deploy Command" para rodar migrations se você tiver múltiplas réplicas ou planeja escalar horizontalmente no futuro. Isso causaria race conditions.

**Estratégia Recomendada para Migrations:**
- **Opção A** (Simples): Execute migrations manualmente via Terminal/Shell do Dokploy após cada deploy
- **Opção B** (Intermediário): Use SSH via GitHub Actions para executar em um container específico
- **Opção C** (Avançado): Crie um job/container separado dedicado apenas para migrations

Para este guia, usaremos **Opção A** (manual):
```bash
# Após deploy bem-sucedido, acesse Terminal da aplicação e rode:
php artisan migrate --force
```

### 3.7. Configurar Webhook no GitHub (Sandbox)

1. No GitHub, vá em **Settings** do repositório `datagrana-portfolio`
2. Vá em **Secrets and variables** → **Actions**
3. Adicione um **New repository secret**:
   - Name: `DOKPLOY_WEBHOOK_SANDBOX`
   - Value: Cole a URL do webhook obtida na seção 3.6

### 3.8. (Recomendado) Proteger o Sandbox

Se o sandbox não deve ficar aberto ao público:

1. No Dokploy, na Application (Sandbox), use **Security / Basic Auth** para exigir usuário/senha.
2. Opcional: restrinja acesso por IP (se você tiver esse controle via Traefik/middlewares no seu setup).

---

## 4. Ambiente Produção (`main`)

**URL Alvo**: `datagrana.app`

### 4.1. Criar Projeto no Dokploy

1. Crie um **Project** separado chamado `datagrana-prod`.

### 4.2. Criar Banco de Dados (Produção)

1. Dentro do projeto `datagrana-prod`, crie um serviço **Database** (MySQL/MariaDB).
2. Nome sugerido: `mysql-prod`.
3. Configure credenciais **exclusivas da produção** (DIFERENTES do sandbox):
   - `DB_DATABASE=datagrana`
   - `DB_USERNAME=<usuario_prod>`
   - `DB_PASSWORD=<senha_prod_forte_e_diferente>`
4. Não exponha "External Credentials" do banco, salvo necessidade clara.
5. Anote o `DB_HOST` (Internal Host) e `DB_PORT=3306`.

### 4.3. Criar Aplicação Laravel (Provider Docker)

1. No projeto `datagrana-prod`, crie uma **Application** (ex: `datagrana-portfolio-prod`).
2. Vá em **Deploy Settings** e em **Provider** selecione **Docker**.
3. Preencha os campos do Provider Docker:
   - `Docker Image`: `ghcr.io/<SEU_USUARIO_GITHUB>/datagrana-portfolio:latest`
     - **Exemplo**: `ghcr.io/marcomamede/datagrana-portfolio:latest`
     - **Importante**: Use a tag `:latest` para que o Dokploy sempre busque a versão mais recente automaticamente
   - `Registry URL`: `ghcr.io` (se não configurou Registry global)
   - `Username`: seu usuário do GitHub (se não configurou Registry global)
   - `Password`: PAT com `read:packages` (se não configurou Registry global)

**Como funciona:**
- Merge para `main` → GitHub Actions gera a imagem com tag `:latest` → Webhook aciona Dokploy → Dokploy busca automaticamente a versão mais recente
- **Deploy totalmente automático!** ✅

### 4.4. Configurar Domínio e HTTPS (Produção)

1. Em **Domains**, adicione: `datagrana.app`
2. **Container Port**: `80`
3. Habilite **HTTPS/SSL** (Let's Encrypt).

### 4.5. Variáveis de Ambiente (Produção)

**Essenciais**
- `APP_URL=https://datagrana.app`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...` (gere **uma vez** com `php artisan key:generate --show` e use um **DIFERENTE** do sandbox)
- `DB_HOST=<internal-host-do-mysql-prod>`
- `DB_PORT=3306`
- `DB_DATABASE=datagrana`
- `DB_USERNAME=<usuario_prod>`
- `DB_PASSWORD=<senha_prod>`

**Recomendadas**
- `LOG_CHANNEL=stderr`

**Opcional (rastreamento de versão)**
- `APP_VERSION=1.0.0`
- `GIT_COMMIT=<será atualizado>`

### 4.6. Configurar Webhook de Deploy (Produção)

1. Em **Deployments**, habilite **Deploy via Webhook** e copie a URL.
2. Guarde essa URL para configurar no GitHub.

**IMPORTANTE - Estratégia de Migrations:**

Mesmo que seja tentador, **NÃO** configure "Post Deploy Command" para migrations automáticas em produção. Use migrations **manuais** ou via job dedicado para evitar race conditions.

Após deploy bem-sucedido:
```bash
# Acesse Terminal da aplicação e rode:
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4.7. Configurar Webhook no GitHub (Produção)

1. No GitHub, vá em **Settings** do repositório
2. Vá em **Secrets and variables** → **Actions**
3. Adicione um **New repository secret**:
   - Name: `DOKPLOY_WEBHOOK_PRODUCTION`
   - Value: Cole a URL do webhook de produção

---

## 5. Resumo das Secrets do GitHub

Certifique-se de que todas as secrets abaixo estão configuradas em **Settings → Secrets and variables → Actions**:

| Secret | Descrição | Obrigatório |
| :--- | :--- | :--- |
| `REGISTRY_USERNAME` | Seu usuário do GitHub (ex: `marcomamede`) | ✅ Sim |
| `REGISTRY_PASSWORD` | PAT (Classic) com escopo `write:packages` | ✅ Sim |
| `DOKPLOY_WEBHOOK_SANDBOX` | URL do webhook do projeto Sandbox no Dokploy | ✅ Sim |
| `DOKPLOY_WEBHOOK_PRODUCTION` | URL do webhook do projeto Produção no Dokploy | ✅ Sim |

---

## 6. Como Funciona o Pipeline

### 6.1. GitHub Actions Workflow (`deploy.yml`)

O workflow já está implementado e faz:

1. **tests**: Roda testes automatizados (PHPUnit/Pest)
2. **build-push**: Constrói a imagem Docker e publica no GHCR com as seguintes tags:
   - `sha-<commit_sha_completo>` (tag imutável - **PRINCIPAL**)
   - `develop` ou `latest` (tags móveis - conveniência)
3. **deploy**: Aciona o webhook do Dokploy correspondente

### 6.2. Estratégia de Tags Implementada

O arquivo `.github/workflows/deploy.yml` (linhas 75-78) já gera as tags corretamente:

```yaml
tags: |
  type=raw,value=latest,enable=${{ github.ref == 'refs/heads/main' }}
  type=raw,value=develop,enable=${{ github.ref == 'refs/heads/develop' }}
  type=sha,prefix=sha-,format=long
```

**Isso significa:**
- Cada commit gera uma tag imutável `sha-<commit_completo>` (para histórico/rollback)
- **E também atualiza** a tag `:develop` (branch develop) ou `:latest` (branch main)

**Como configuramos o Dokploy:**
- Sandbox usa `:develop` - sempre busca a versão mais recente automaticamente
- Produção usa `:latest` - sempre busca a versão mais recente automaticamente
- Tags `sha-xxx` ficam disponíveis para rollback se necessário

### 6.3. Fluxo de Deploy Recomendado

**Para Sandbox (branch `develop`):**

1. Faça push para `develop`
2. GitHub Actions roda testes, builda imagem, publica no GHCR
3. GitHub Actions aciona webhook do Dokploy Sandbox
4. Dokploy faz pull da imagem (usando a tag configurada) e reinicia
5. **Você acessa o Terminal da aplicação e roda migrations manualmente**
6. Valide se está tudo funcionando

**Para Produção (branch `main`):**

1. Crie Pull Request de `develop` para `main`
2. Revise e faça merge
3. GitHub Actions roda testes, builda imagem, publica no GHCR
4. GitHub Actions aciona webhook do Dokploy Produção
5. Dokploy faz pull da imagem e reinicia
6. **Você acessa o Terminal da aplicação e roda migrations + cache manualmente**
7. Valide se está tudo funcionando
8. **(Futuro)** Automatize criação de Release/Tag Git para marcar a versão

---

## 7. Entendendo as Tags de Imagem

O GitHub Actions gera **automaticamente** múltiplas tags para cada commit:

**Para branch `develop`:**
```
ghcr.io/marcomamede/datagrana-portfolio:develop              # tag móvel (sempre aponta para a última versão)
ghcr.io/marcomamede/datagrana-portfolio:sha-abc123def456...  # tag imutável (versão específica deste commit)
```

**Para branch `main`:**
```
ghcr.io/marcomamede/datagrana-portfolio:latest               # tag móvel (sempre aponta para a última versão)
ghcr.io/marcomamede/datagrana-portfolio:sha-abc123def456...  # tag imutável (versão específica deste commit)
```

### Como Usar no Dia a Dia

**Configuração Recomendada (já descrita no guia):**
- Sandbox: Use `:develop`
- Produção: Use `:latest`
- **Deploy é automático via webhook** - você não precisa fazer nada manualmente!

### Quando Usar Tags Imutáveis (`sha-xxx`)

As tags imutáveis são úteis para:

**1. Rollback (se algo der errado):**
```bash
# No Dokploy, edite a Application e mude a imagem para:
ghcr.io/marcomamede/datagrana-portfolio:sha-<commit_anterior>
# Salve e faça Redeploy
```

**2. Deploy de uma versão específica:**
- Útil se você quiser "congelar" uma versão em produção
- Ou se quiser testar uma versão antiga

**3. Auditoria:**
- Saber exatamente qual código rodou em qual momento
- Histórico completo no GitHub Container Registry

### Como Encontrar a Tag SHA de um Commit

**Opção 1: Via GitHub Actions**
1. Vá em **Actions** no repositório
2. Selecione o workflow do commit desejado
3. Veja a tag gerada no job `build-push`

**Opção 2: Via Git Local**
```bash
# Ver commit SHA completo
git log --oneline

# A tag será: sha-<commit_sha_completo>
# Exemplo: sha-abc123def456789...
```

**Opção 3: Via GitHub Container Registry**
1. Acesse `https://github.com/<usuario>/datagrana-portfolio/pkgs/container/datagrana-portfolio`
2. Veja todas as tags disponíveis

---

## 8. Checklist Pré-Deploy (Primeira Vez)

### No Provedor de Domínio (DNS)

- [ ] Criar registro A: `sandbox.datagrana.app` → IP da VPS
- [ ] Criar registro A: `datagrana.app` → IP da VPS
- [ ] Aguardar propagação DNS (testar com `nslookup`)

### No Dokploy - Global

- [ ] Configurar Registry (GHCR) com PAT de leitura (Opção A - Recomendado)

### No Dokploy - Sandbox

- [ ] Criar projeto `datagrana-sandbox`
- [ ] Criar database `mysql-sandbox` (credenciais exclusivas)
- [ ] Criar application `datagrana-portfolio-sandbox`
- [ ] Configurar Provider Docker com imagem GHCR (tag `:develop`)
- [ ] Configurar Container Port: `80`
- [ ] Adicionar domínio: `sandbox.datagrana.app`
- [ ] Habilitar HTTPS/SSL (Let's Encrypt)
- [ ] Configurar todas variáveis de ambiente
- [ ] Gerar `APP_KEY` exclusivo: `php artisan key:generate --show`
- [ ] Habilitar Deploy via Webhook e copiar URL

### No Dokploy - Produção

- [ ] Criar projeto `datagrana-prod`
- [ ] Criar database `mysql-prod` (credenciais exclusivas e **diferentes** do sandbox)
- [ ] Criar application `datagrana-portfolio-prod`
- [ ] Configurar Provider Docker com imagem GHCR (tag `:latest`)
- [ ] Configurar Container Port: `80`
- [ ] Adicionar domínio: `datagrana.app`
- [ ] Habilitar HTTPS/SSL (Let's Encrypt)
- [ ] Configurar todas variáveis de ambiente
- [ ] Gerar `APP_KEY` exclusivo (**DIFERENTE** do sandbox)
- [ ] Habilitar Deploy via Webhook e copiar URL

### No GitHub

- [ ] Criar secret `REGISTRY_USERNAME` (seu usuário do GitHub)
- [ ] Criar secret `REGISTRY_PASSWORD` (PAT com escopo `write:packages`)
- [ ] Criar secret `DOKPLOY_WEBHOOK_SANDBOX`
- [ ] Criar secret `DOKPLOY_WEBHOOK_PRODUCTION`
- [ ] Verificar se o package no GHCR está público OU se configurou Registry no Dokploy

### Testes Locais (Opcional mas Recomendado)

- [ ] Testar build local: `docker build -t test .`
- [ ] Testar container local: `docker run -p 8080:80 test`
- [ ] Acessar `http://localhost:8080` e verificar se o Laravel responde
- [ ] Verificar logs: `docker logs <container_id>`

---

## 9. Troubleshooting e Comandos Úteis

### 9.1. Ver Logs do Container no Dokploy

1. Acesse o painel do Dokploy
2. Vá em **Projects** → Selecione o projeto (ex: `datagrana-sandbox`)
3. Clique na **Application** (ex: `datagrana-portfolio-sandbox`)
4. Vá na aba **Logs**
5. Logs do Nginx e PHP-FPM aparecerão em tempo real (stdout/stderr)

### 9.2. Acessar o Shell do Container

**Via Dokploy UI**:
1. Na Application, vá em **Terminal**
2. Isso abre um shell dentro do container rodando

**Via SSH no servidor**:
```bash
# Listar containers
docker ps | grep datagrana

# Acessar shell
docker exec -it <container_id> bash

# Exemplos de comandos úteis dentro do container
php artisan --version
php artisan route:list
php artisan config:show database
```

### 9.3. Verificar se o Banco está Acessível

```bash
# Dentro do container da aplicação
php artisan tinker

# No tinker
DB::connection()->getPdo();
// Se retornar um objeto PDO, conexão OK

// Testar query
DB::select('SELECT 1');
```

Ou use o comando direto:
```bash
php artisan db:show
```

### 9.4. Como Fazer Rollback Manual

Se um deploy causou problemas em produção:

1. **Identificar a versão anterior**:
   - Acesse o GitHub Container Registry (GHCR)
   - Encontre a tag `sha-<commit>` da versão anterior funcionando
   - Ou use `git log` para ver commits anteriores

2. **No Dokploy**:
   - Vá na **Application** de produção
   - Em **Image**, mude para `:sha-<commit_anterior>`
   - Salve e faça **Redeploy**

3. **Rollback de migrations** (cuidado!):
   ```bash
   # Acesse o container
   docker exec -it <container_id> bash

   # Rode rollback (cuidado!)
   php artisan migrate:rollback
   ```

**Importante**: Rollback de migrations pode causar perda de dados. Sempre tenha backup.

### 9.5. Debug de Falha no Deploy

Se o deploy falhar, verifique na ordem:

1. **GitHub Actions**:
   - Vá em **Actions** no repositório
   - Veja qual job falhou (tests, build-push, deploy)
   - Leia os logs

2. **Se falhou no webhook**:
   - Verifique se o secret `DOKPLOY_WEBHOOK_SANDBOX` ou `DOKPLOY_WEBHOOK_PRODUCTION` está correto
   - Teste o webhook manualmente:
     ```bash
     curl -X POST "<URL_DO_WEBHOOK>"
     ```

3. **Se o container não sobe**:
   - No Dokploy, vá em **Logs** da Application
   - Procure por erros de Nginx ou PHP-FPM
   - Erros comuns:
     - `Permission denied` em `/var/www/html/storage` → Problema de permissões no Dockerfile
     - `Connection refused` no banco → `DB_HOST` incorreto ou banco não rodando
     - `APP_KEY` missing → Variável de ambiente não configurada

4. **Se migrations falharem**:
   - Verifique se o banco está acessível (seção 9.3)
   - Verifique logs da migration: `php artisan migrate --force -vvv`

### 9.6. Verificar Versão Rodando

Adicione um endpoint de health check que retorna a versão:

```php
// routes/web.php (ou api.php)
Route::get('/version', function () {
    return response()->json([
        'version' => env('APP_VERSION', 'unknown'),
        'commit' => env('GIT_COMMIT', 'unknown'),
        'environment' => app()->environment(),
    ]);
});
```

Configure no Dokploy:
- `APP_VERSION=1.0.0`
- `GIT_COMMIT=<sha_do_commit>` (pode automatizar via GitHub Actions)

Acesse: `https://datagrana.app/version`

### 9.7. Erro de Mixed Content (assets em HTTP num site HTTPS)

Sintoma (no console do navegador):
- `Mixed Content: ... requested an insecure script 'http://.../build/assets/...js'`

Causa mais comum:
- A aplicação está atrás de um proxy (Traefik/Dokploy) e o Laravel não está interpretando o esquema como HTTPS

Correção:
1. Garanta no Dokploy:
   - `APP_URL=https://sandbox.datagrana.app` (ou o domínio correto)
2. Limpe caches dentro do container:
   ```bash
   php artisan optimize:clear
   ```
3. Redeploy se necessário.

Obs.: O projeto deve incluir middleware de proxy para honrar `X-Forwarded-Proto` (necessário em setups com Traefik).

---

## 10. Próximos Passos (Melhorias Futuras)

### 10.1. Automatizar Migrations

Criar um job/container dedicado para migrations que:
- Roda **antes** do deploy da aplicação web
- Usa lock/semaphore para evitar execução concorrente
- Reporta status (sucesso/falha) antes de prosseguir

### 10.2. Automatizar Release Tagging

Adicionar step no workflow de produção para:
- Criar tag Git (ex: `v1.0.0`) após deploy bem-sucedido
- Gerar Release no GitHub com changelog

### 10.3. Monitoramento

- Configurar ferramentas de APM (ex: New Relic, Sentry)
- Adicionar alertas de downtime (ex: UptimeRobot, Pingdom)
- Configurar logs centralizados (ex: Papertrail, Loggly)

### 10.4. Performance

- Implementar Redis para cache e sessions
- Configurar CDN para assets estáticos (ex: Cloudflare)
- Adicionar queue workers (para jobs assíncronos)

### 10.5. Segurança

- Implementar rate limiting mais rigoroso
- Configurar WAF (Web Application Firewall)
- Habilitar 2FA para acesso ao Dokploy
- Implementar backup automático de banco de dados

### 10.6. CI/CD Avançado

- Adicionar testes E2E (Cypress, Playwright)
- Implementar análise de código (SonarQube)
- Adicionar smoke tests pós-deploy
- Configurar rollback automático em caso de falha no health check

---

## 11. Isolamento e Segurança

### 11.1. Separação por projetos (mínimo recomendado)

- Crie **dois projetos**: `datagrana-sandbox` e `datagrana-prod`.
- Nunca reutilize o mesmo serviço de banco.
- Nunca reutilize `APP_KEY` entre ambientes.
- Não compartilhe volumes/volumes mounts entre sandbox e produção.

### 11.2. Separação por permissões (quem acessa o quê no painel)

1. Crie usuários/grupos para operação do sandbox (se fizer sentido).
2. Dê acesso **somente** ao projeto `datagrana-sandbox`.
3. Restrinja o projeto `datagrana-prod` para admins.

### 11.3. Separação de rede (quando você precisa de isolamento forte)

Se sua exigência é "sandbox não consegue se conectar em produção mesmo que comprometido", considere:

- **Produção em outra VPS/instância do Dokploy** (mais seguro).
- Ou no mínimo, outra infra/cluster (se sua arquitetura permitir).

Em uma única instância, serviços frequentemente compartilham a rede usada pelo Traefik (ex: `dokploy-network`), o que é ótimo para roteamento mas não é uma barreira de segurança completa.

---

## 12. Referências

- **Documentação Dokploy**: https://docs.dokploy.com
- **Documentação salva localmente**: `/dokploy-docs.md` (na raiz do projeto)
- **Estrutura do Fluxo Pipeline**: `datagrana-portfolio/docs/pipelines/estrutura-fluxo-pipeline.md`
- **Workflow GitHub Actions**: `datagrana-portfolio/.github/workflows/deploy.yml`
