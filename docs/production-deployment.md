# Implantação FBSantos.com.br: site, panel e backend

> Esta versão usa **bind mounts para os dados de produção**. Imagens, PDFs, banco e cache ficam em pastas reais sob `backend/docker/production/data/`, **não em volumes nomeados ocultos do Docker**. Os uploads DEV também passam a ficar numa pasta real. **Antes de reiniciar o backend DEV com esta versão, copie o antigo volume de mídias** conforme a etapa 1. A troca de configuração NÃO move os dados automaticamente.

## Estrutura da máquina Linux (produção)

```text
/docker/fbsantos.com.br/
├── compose.yaml                       # centralizador dos três Compose
├── compose.override.yaml              # dependências/rede privada compartilhada
├── site/
│   ├── app/                           # Nuxt/SSR
│   └── docker/production/
│       ├── .env                       # segredo do Cloudflare Tunnel do site
│       ├── docker-compose.yml
│       └── nginx/default.conf
├── panel/
│   ├── app/                           # Vue/Vite
│   └── docker/production/
│       ├── .env                       # segredo do Cloudflare Tunnel do painel
│       ├── docker-compose.yml
│       └── nginx/default.conf
├── backend/
│   ├── app/                           # SlimPHP
│   ├── database/migrations/
│   └── docker/production/
│       ├── .env                       # banco, admin, JWT, SMTP, hCaptcha etc.
│       ├── docker-compose.yml
│       ├── nginx/default.conf
│       └── data/
│           ├── db/                    # arquivos persistentes do MariaDB
│           ├── uploads/               # arquivos reais enviados pelo CMS
│           └── cache/                 # cache de Analytics
└── backups/                            # exportações SQL e mídia (não versionar)
```

A raiz e os três módulos continuam separados. `site` e `panel` **não guardam cópias das imagens**. Os seus nginx encaminham `/uploads/...` ao `backend-nginx` pela rede interna `fbsantos-api`. A API não publica porta no servidor e os endpoints administrativos não são encaminhados pelo site público.

No código, os uploads ficam em `/var/www/html/public/uploads` **dentro** do PHP/nginx, mas o bind mount os espelha em **`backend/docker/production/data/uploads/` no host**. O MariaDB usa `data/db` e o cache `data/cache`. A pasta `data/` fica fora do build Docker e do versionamento. Em DEV, os uploads passam a ser espelhados em `backend/docker/dev/data/uploads/`; o banco DEV **continua** no volume nomeado `fbsantos-backend-dev_db_data`.

**Nunca** exclua `backend/docker/production/data/` ao enviar atualizações. Copiar o projeto com `rsync --delete`, excluir a pasta para subir uma versão nova, ou usar `docker compose down -v` sem avaliar a configuração pode destruir conteúdo importante. **Backup do MariaDB deve ser lógico (`mariadb-dump`), não cópia a quente dos arquivos de `data/db/`.** Os uploads devem ser copiados junto com o banco correspondente.

Pré-requisitos: Docker Engine, Docker Compose **v2.20.3+** (suporte a `include`), acesso para construir imagens, domínio configurado no Cloudflare e dois túneis (site e painel). Os containers não publicam portas; o acesso público ocorre pelos Cloudflare Tunnels.

## 1. PRIMEIRO: preservar os uploads existentes do DEV (Windows PowerShell)

> Execute **antes** de substituir/recriar os serviços DEV com o novo `backend/docker/dev/docker-compose.yml`. O antigo volume tem seus arquivos, mas o novo bind mount apontará inicialmente para uma pasta vazia se você não fizer a cópia.

Na raiz do projeto Windows, interrompa temporariamente os envios do CMS e pare somente o PHP e o nginx DEV (o banco continua ativo):

```powershell
cd D:\Projetos\fbsantos.com.br
docker compose -f backend/docker/dev/docker-compose.yml stop app nginx
```

Com os arquivos atualizados desta entrega já copiados para o projeto, faça a migração do volume antigo para a pasta visível:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\migrate-dev-media.ps1
```

O script **lê** o volume antigo `fbsantos-backend-dev_media_data`, copia os arquivos para `backend/docker/dev/data/uploads/` e **não exclui nem modifica o volume original**. Ele recusa sobrescrever uma pasta que já contém arquivos. Se o volume tiver outro nome, veja `docker volume ls` e confira antes de prosseguir.

Confira alguns arquivos da pasta:

```powershell
Get-ChildItem .\backend\docker\dev\data\uploads -Recurse -File | Select-Object -First 10 FullName
```

Somente depois de verificar que os arquivos foram copiados:

```powershell
docker compose -f backend/docker/dev/docker-compose.yml up -d app nginx
```

Agora os próximos uploads feitos no painel DEV ficam visíveis em `backend/docker/dev/data/uploads`. **Não exclua o volume antigo até conferir as imagens, o painel e os backups.** Se o DEV atual já usa a nova pasta, não execute a migração uma segunda vez.

## 2. Exportar conteúdo atual do DEV para subir na produção

O conteúdo editável do CMS está no **banco MariaDB**, e as imagens e PDFs estão em **uploads**. É necessário levar os dois juntos, não apenas o ZIP do projeto.

Na máquina Windows, ainda na raiz do projeto, sem fazer novas alterações no CMS durante a exportação:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\export-dev-for-production.ps1
```

O script gera, sem usar redirecionamento binário do PowerShell:

```text
backups/fbsantos-db.sql          # dados do CMS, organizações, páginas, projetos, mídia etc.
backups/fbsantos-media.tar.gz    # arquivos armazenados nos uploads
```

O script não sobrescreve backups já existentes. Os dois arquivos podem conter **dados pessoais ou confidenciais**: transfira por conexão segura, mantenha cópia fora do servidor e não os coloque no Git. Se houver alterações em DEV depois da exportação, será necessário exportar novamente para levá-las à produção.

## 3. Copiar o projeto e os backups para o servidor

Exemplo com destino Linux `/docker/fbsantos.com.br` (pode usar outro diretório absoluto, pois o Compose resolve os caminhos relativos a cada módulo):

```bash
sudo mkdir -p /docker/fbsantos.com.br
sudo chown -R "$USER":"$USER" /docker/fbsantos.com.br
cd /docker/fbsantos.com.br
```

Envie as pastas `backend`, `site`, `panel`, `scripts`, `docs`, o `compose.yaml` e o `compose.override.yaml` para essa raiz. Envie também os dois arquivos de `backups/` gerados no Windows. Isso pode ser feito com SFTP, SCP ou outra transferência segura; **mantenha a hierarquia de pastas**. **Não transfira as pastas `docker/dev/data/`, `node_modules/`, `vendor/`, arquivos `.env` do DEV ou artefatos locais**. O conteúdo que vem do DEV deve entrar na produção **somente** pelo SQL e pelo arquivo `fbsantos-media.tar.gz`, restaurados nas pastas de produção.

Crie os diretórios persistentes (não estarão presentes no ZIP, pois não são arquivos de código):

```bash
mkdir -p backend/docker/production/data/{db,uploads,cache} backups
```

No primeiro deploy, **antes de subir o backend**, extraia os arquivos do CMS dentro da pasta visível:

```bash
tar -tzf backups/fbsantos-media.tar.gz | head
# Confira que o arquivo contém os uploads esperados.
tar -xzf backups/fbsantos-media.tar.gz -C backend/docker/production/data/uploads
find backend/docker/production/data/uploads -type f | head
```

Resultado esperado: imagens/PDFs em `backend/docker/production/data/uploads/2026/...` ou em subpastas como `backend/docker/production/data/uploads/page-.../2026/...`, preservando a árvore original do DEV.

**Nunca** tente montar a pasta `uploads` dentro de `site` ou `panel`; os dois usam o mesmo repositório de mídias no backend.

## 4. Criar os três .env de produção

Na raiz do servidor:

```bash
cp backend/docker/production/.env.example backend/docker/production/.env
cp site/docker/production/.env.example site/docker/production/.env
cp panel/docker/production/.env.example panel/docker/production/.env
chmod 600 backend/docker/production/.env site/docker/production/.env panel/docker/production/.env
```

Edite os arquivos reais com senhas e tokens de produção. Nunca envie um `.env` do DEV sem revisar. Localização:

- `backend/docker/production/.env`: MariaDB, autenticação, JWT, hCaptcha, SMTP, Analytics, limites etc.
- `site/docker/production/.env`: token do túnel Cloudflare do site, ID do GA4 e sitekey pública do hCaptcha.
- `panel/docker/production/.env`: token do túnel Cloudflare do painel.

Para criar valores aleatórios e o hash da senha do administrador:

```bash
openssl rand -base64 64
docker compose build backend-app
docker compose run --rm --no-deps --entrypoint php backend-app bin/hash-admin-password.php
```

Registre **somente o hash** em `ADMIN_PASSWORD_HASH`. Confira `APP_ENV=production`, `APP_DEBUG=false`, `TRUST_PROXY_HEADERS=true`, `API_ALLOWED_HOSTS`, `JWT_SECRET`, URLs do site e os segredos. A API possui validações de configuração e pode recusar iniciar se estiver insegura.

## 5. Restaurar banco e iniciar os serviços

**Primeiro deploy, banco novo e vazio:**

```bash
cd /docker/fbsantos.com.br
docker compose version
docker compose config --quiet

docker compose up -d backend-db
# Acompanhe a inicialização até o banco ficar healthy.
docker compose ps backend-db
```

O MariaDB inicializa `backend/docker/production/data/db/`, diretamente no host. Quando estiver saudável, **antes das migrations** restaure o dump do DEV:

```bash
docker compose exec -T backend-db \
  sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
  < backups/fbsantos-db.sql
```

Se não estiver migrando conteúdo algum e realmente desejar um CMS vazio, omita a restauração. No seu caso, para preservar o site já cadastrado, **não omita**.

Agora suba todos os módulos de uma vez:

```bash
docker compose up -d --build
docker compose ps
```

A sequência usa `depends_on` e healthchecks: MariaDB, migrations, PHP/backend nginx, Nuxt/site nginx, painel nginx e Cloudflare Tunnels. **Não** há portas de API, banco ou painel expostas diretamente no host.

Após migrar do DEV, normalize apenas URLs antigas absolutas, se necessário:

```bash
docker compose exec backend-app php bin/normalize-upload-urls.php
```

Não precisa copiar PHP, imagens ou `vendor` manualmente para dentro dos contêineres.

## 6. Configurar o Cloudflare

Use os túneis do site e painel, com suas redes Docker independentes:

| Domínio | Túnel | Serviço de origem no túnel |
|---|---|---|
| `fbsantos.com.br` (e `www`, se utilizado) | Site | `http://nginx:80` |
| `painel.fbsantos.com.br` | Painel | `http://nginx:80` |
| API | **Nenhum túnel próprio** | Encaminhada internamente pelo nginx do site/painel |

O alias `nginx` se repete, mas cada túnel está numa rede Docker diferente. Recomendável proteger `painel.fbsantos.com.br` com **Cloudflare Access e MFA**.

## Se o site retornar 502 (Cloudflare Bad Gateway)

O painel pode continuar acessível enquanto o túnel do site ou o serviço Nuxt está
indisponível. **Não recrie o banco de dados nem apague volumes** para tentar
resolver um erro 502.

Na raiz `/docker/fbsantos.com.br`, rode estes comandos e confira as respostas:

```bash
docker compose ps --all
docker compose logs --tail=100 site-app site-nginx site-cloudflared
docker compose exec -T site-nginx nginx -t
docker compose exec -T site-nginx wget -S -O /dev/null http://site-app:3000/favicon.svg
docker compose exec -T site-nginx wget -S -O /dev/null http://127.0.0.1/favicon.svg
```

- **`site-app` parado ou reiniciando**: confira os erros do Nuxt no log de `site-app`.
- **`site-nginx` parado**: procure `emerg`, `host not found` ou problemas de sintaxe no nginx.
- **Nuxt e nginx saudáveis, mas o domínio retorna 502**: confira o log de
  `site-cloudflared` e a configuração *Public Hostname* do túnel **do site**.
  A URL de serviço deve ser `http://nginx:80` (ou `http://site-nginx:80`),
  **HTTP**, não HTTPS. Ambos os nomes são acessíveis na rede Docker `fbsantos-site`.
- **`favicon.svg` funciona, mas a home falha**: examine os logs Nuxt e verifique
  a comunicação da API a partir do contêiner do site:

```bash
docker compose exec -T site-app node -e \
  "fetch('http://fbsantos-api/health').then(r => { console.log('API HTTP', r.status); process.exit(r.ok ? 0 : 1) }).catch(e => { console.error(e.message); process.exit(1) })"
```

O nginx do site usa **resolução dinâmica via DNS interno do Docker** para o Nuxt
(`site-app`) e para a API (`fbsantos-api`). Isso evita que um endereço IP antigo
continue sendo utilizado após a recriação desses contêineres. Não elimina
indisponibilidade real de aplicações e túneis.

Após aplicar alterações no nginx, valide a configuração e reinicie **somente**
o proxy do site, sem parar banco ou painel:

```bash
docker compose exec -T site-nginx nginx -t
docker compose restart site-nginx
docker compose ps site-app site-nginx site-cloudflared
```

Se o contêiner `site-nginx` ainda não iniciou, use
`docker compose up -d site-nginx site-cloudflared` em vez de `exec`/`restart`.

## 7. Conferência obrigatória antes de liberar

1. `docker compose ps` deve mostrar os serviços de longa duração saudáveis. `backend-migrate` deve ter concluído com sucesso (pode aparecer como `Exited (0)`).
2. `docker compose logs --tail=100 backend-app backend-nginx site-app site-nginx panel-nginx` não deve registrar erros críticos.
3. Abra `https://fbsantos.com.br/` e as páginas `/sobre`, `/trajetoria`, `/projetos` e `/formacao`.
4. No painel, abra uma imagem antiga; copie sua URL `/uploads/...` e teste no navegador `https://fbsantos.com.br/uploads/...` e `https://painel.fbsantos.com.br/uploads/...`. Ambos devem servir **a imagem real**, não retornar HTML ou 404.
5. Confira também um certificado PDF, faça **um upload de teste** no painel e confirme que o arquivo novo aparece na pasta `backend/docker/production/data/uploads` do host.
6. Confira autenticação, contatos/hCaptcha, mídia, telas do painel e SEO/SSR.
7. `https://fbsantos.com.br/api/v1/admin/...` deve retornar 404: a API administrativa é exposta somente pelo domínio do painel, com autenticação.

Caminhos essenciais:

```bash
ls -la backend/docker/production/data/
find backend/docker/production/data/uploads -type f | head
sudo du -sh backend/docker/production/data/db backend/docker/production/data/uploads
```

## 8. Backup e atualizações futuras

**Cuidado especial com os arquivos do banco:** a pasta `data/db` é o *armazenamento ativo* do MariaDB. Não faça uma cópia dessa pasta com o contêiner rodando achando que é um backup consistente. Gere um `mariadb-dump`, e archive `data/uploads` no mesmo período de manutenção:

```bash
mkdir -p backups
# Ideal: bloquear edições do CMS por alguns minutos.
docker compose exec -T backend-db \
  sh -lc 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' \
  > backups/fbsantos-db-$(date +%Y%m%d-%H%M).sql

tar -czf backups/fbsantos-media-$(date +%Y%m%d-%H%M).tar.gz \
  -C backend/docker/production/data/uploads .
```

Mantenha esses backups **fora do servidor** e teste sua restauração. Não faça backup somente de imagens: a referência de cada imagem, projeto ou página está no MariaDB.

Com dados preservados, uma atualização normal é:

```bash
# Atualize apenas os arquivos de código e configuração, NUNCA exclua data/.
cd /docker/fbsantos.com.br
docker compose config --quiet
docker compose up -d --build
```

### Atualização de segurança do PHP (8.5.12)

A API e o serviço de migrations utilizam a imagem oficial `php:8.5.12-fpm-alpine`, corrigida para CVE-2026-109618 e CVE-2026-109619. Depois de enviar os arquivos modificados, reconstruir **as duas imagens PHP** garante que tanto o serviço HTTP quanto o CLI das migrations executem PHP 8.5.12. Faça o backup lógico do banco e dos uploads conforme acima antes da atualização.

```bash
cd /docker/fbsantos.com.br

docker compose config --quiet
docker compose build --pull --no-cache backend-app backend-migrate
# Recria o backend, executando as migrations via depends_on.
docker compose up -d backend-app backend-nginx
# Atualiza a resolução do upstream PHP-FPM pelo nginx.
docker compose restart backend-nginx

# Confirmar versão e dependências no contêiner em execução:
docker compose exec -T backend-app php -v
docker compose exec -T backend-app composer check-platform-reqs --no-dev
docker compose ps
```

O resultado de `php -v` deve começar com `PHP 8.5.12`. Se `docker compose wait` ou qualquer validação falhar, confira `docker compose logs --tail=100 backend-migrate backend-app backend-nginx` e não considere o deploy concluído. Essa operação não executa `down -v` nem remove `data/`.

Para parar sem apagar dados:

```bash
docker compose down
```

**Se já existir uma instalação anterior de produção usando os volumes Docker nomeados** `fbsantos-backend-production_media_data` e `fbsantos-backend-production_db_data`, **NÃO aplique os novos mounts diretamente.** Antes, exporte o SQL do backend antigo e copie o volume antigo de mídia para as pastas novas, com os serviços antigos parados. Este guia assume a situação informada: **primeira publicação com dados migrados do DEV**. Nunca rode dois MariaDBs diferentes usando os mesmos arquivos de dados ou contêineres de produção diferentes com o mesmo token de túnel.

## Limitação de validação

O pacote foi inspecionado e os arquivos Compose foram revisados estaticamente. A inicialização integral e a resposta dos túneis Cloudflare exigem testes no servidor real com Docker, que não está disponível no ambiente de edição dos arquivos.

## Relatório de e-mails (a partir da migração 007)

O painel dispõe da rota **/emails** com consumo do SMTP, histórico por mês, detalhamento por dia e contatos que precisam de acompanhamento quando uma notificação falha ou não pode ser enviada.

No arquivo `backend/docker/production/.env`, inclua:

```dotenv
MAIL_DAILY_LIMIT=450
MAIL_QUOTA_TIMEZONE=America/Sao_Paulo
```

O backend impõe no máximo 450 envios mesmo que a variável seja configurada com um valor superior. A quota vale tanto para o **dia local** quanto para uma **janela móvel de 24 horas**. No fluxo padrão, cada contato reserva 2 slots de forma atômica, um para a notificação do administrador e outro para a confirmação ao visitante, desde que `CONTACT_AUTO_REPLY=true`. Para evitar exceder o limite durante falhas ou confirmações SMTP ambíguas, slots reservados não são devolvidos ao mesmo dia; o painel diferencia reservas, envios aceitos, falhas e e-mails ignorados.

A migration `007_mail_delivery_tracking.sql` é executada pelo serviço `backend-migrate` na inicialização via `docker compose up -d --build`. Verifique `docker compose logs backend-migrate` e o retorno `ok 007_mail_delivery_tracking.sql` antes de testar o formulário. **Ela não modifica nem apaga os contatos existentes** e não cria registros falsos de e-mails anteriores.

Se não houver quota para a solicitação inteira, o contato permanece salvo com status `pending` e pode ser lido em **Painel → Envio de e-mails → Contatos sem notificação confirmada**. Não há disparo posterior automático: responda manualmente esses contatos ou implemente uma fila antes de ativar reenvios. Caso o SMTP falhe, o painel também mostra os contatos com status `failed`.

**Limitação importante:** o relatório conta somente envios realizados pela API deste site, não mensagens enviadas pelo Gmail Web, celular ou outros programas usando a mesma conta. A aceitação SMTP não equivale a entrega ou leitura do destinatário. Considere os outros envios da conta ao definir sua margem de segurança.
