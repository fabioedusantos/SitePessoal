# Arquitetura

## Organização física

O projeto mantém os módulos independentes para desenvolvimento e build, com **três Compose modulares em produção, integrados por um ponto único de entrada**.

```text
compose.yaml                            # include dos 3 módulos
compose.override.yaml                   # rede/API e dependências entre módulos
backend/{app,database,docker/production/docker-compose.yml}
panel/{app,docker/production/docker-compose.yml}
site/{app,docker/production/docker-compose.yml}
```

A pasta `app/` nunca contém arquivos de infraestrutura. A pasta `docker/` nunca contém o código-fonte da aplicação. Isso elimina a necessidade de montar uma árvore intermediária `www/` ou mover arquivos manualmente durante o deploy.

## Site público

O site usa **Nuxt + Vue 3 + TypeScript** com SSR. O conteúdo profissional precisa ser indexável sem depender da execução de JavaScript no navegador e deve suportar metadados por rota, canonical, JSON-LD, sitemap e páginas próprias de projeto.

Em produção:

```text
Cloudflare Tunnel -> site-nginx:80 -> site-app:3000 (Nuxt/Nitro)
```

## Painel

O painel usa **Vue 3 + TypeScript + Vite**. Como é uma aplicação autenticada, não precisa de SSR.

Em produção o build é feito dentro da imagem e o resultado é servido diretamente pelo nginx:

```text
Cloudflare Tunnel -> panel-nginx:80 -> arquivos estáticos do painel
```

## Backend

O backend usa **PHP 8.5.12 + SlimPHP 4 + MariaDB**. Em produção ele não possui entrada pública própria. Site e painel chegam ao nginx da API somente pela rede Docker interna `fbsantos-api-private`.

```text
Internet -> Cloudflare Tunnel -> nginx do site/painel -> rede privada -> nginx da API -> PHP-FPM -> MariaDB
```

Estrutura:

- `app/src`: regras e infraestrutura PHP;
- `app/config`: rotas/configuração executável;
- `app/public`: único webroot;
- `app/bin`: comandos CLI;
- `database/migrations`: migrations versionadas;
- `docker/production/.env`: configuração privada do backend, fora do build da imagem;
- `docker/dev`: ambiente local;
- `docker/production`: Compose modular, Dockerfile, nginx e configuração de produção (importados pelo Compose da raiz).

As migrations são executadas pelo serviço `backend-migrate` e contêm somente estrutura (DDL). Conteúdo editorial e dados profissionais não são versionados no repositório; a fonte de verdade é o banco administrado pelo CMS.

## Conteúdo e domínio

Principais entidades:

- `site_profile`
- `organizations`
- `experiences`
- `projects`
- `technologies`
- `education`
- `certifications`
- `publications`
- `site_links`
- `media`
- `pages`
- `page_sections`
- `page_section_items`
- `site_navigation`
- `site_settings`

Relacionamentos centrais:

```text
organizations 1 ---- N experiences
organizations 1 ---- N projects
experiences   1 ---- N projects

experiences N ---- N technologies
projects     N ---- N technologies
```

O CMS grava entidades reais uma única vez. A camada de apresentação é um page builder controlado: `pages` define as páginas, `page_sections` define sua composição e `page_section_items` seleciona/ordena entidades ou conteúdo manual. Home, trajetória, cases e futuras páginas reutilizam o mesmo mecanismo, sem HTML/CSS arbitrário.

## Configuração

- Site/painel DEV: `.env` em `docker/dev`.
- Site/painel PROD: `.env` em `docker/production`.
- Backend DEV: `.env` em `docker/dev`.
- Backend PROD: `.env` em `backend/docker/production/` (o cache fica em volume nomeado).
- Nenhum secret fica dentro de `app/`.

## Deploy

O deploy parte do `compose.yaml` na **raiz**, que inclui os três arquivos `*/docker/production/docker-compose.yml`. O `compose.override.yaml` é carregado automaticamente e estabelece as dependências cruzadas e a rede privada da API. É necessário Docker Compose >= 2.20.3:

```bash
docker compose config --quiet
docker compose up -d --build
```

O Compose usa `depends_on` e healthchecks para respeitar banco, migrations, API, site/painel e túneis. Os dois túneis conservam o alias DNS `nginx` em redes isoladas.

Não há etapa de mover conteúdo de `app/`, migrations ou arquivos de infraestrutura após o upload.
