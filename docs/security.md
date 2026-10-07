# Segurança de produção

## Limite de garantia

Nenhuma aplicação conectada à Internet pode oferecer garantia absoluta de que "nunca será invadida". A estratégia deste projeto é reduzir superfície de ataque, aplicar defesa em profundidade, falhar de forma segura e manter segredos fora do repositório.

## Superfície pública

O backend **não possui Cloudflare Tunnel próprio em produção**. A rede `fbsantos-api-private` é uma rede Docker interna compartilhada somente pelos consumidores HTTP da API e pelo nginx do backend. PHP-FPM e MariaDB ficam em redes internas separadas e não são alcançáveis diretamente pelo site ou pelo painel.

O site publica somente:

- `/api/v1/public/*`;
- `/uploads/*`;
- o frontend Nuxt.

O painel publica somente:

- `/api/v1/auth/login`;
- `/api/v1/admin/*`;
- `/uploads/*`;
- os arquivos estáticos do painel.

Qualquer outro `/api/*` é recusado no nginx. CORS não é usado como barreira de segurança; a barreira principal é a ausência de rota pública para o backend e as listas explícitas dos reverse proxies.

## Autenticação administrativa

- sessão somente por cookie `HttpOnly`;
- em produção o cookie usa o prefixo `__Host-`, `Secure`, `SameSite=Strict` e `Path=/`;
- Bearer token foi removido como caminho alternativo de autenticação;
- JWT valida emissor, audiência, expiração, `nbf`, `jti` e segredo forte;
- requisições mutáveis exigem token CSRF associado à sessão;
- o painel possui uma única credencial administrativa configurada fora do banco em `ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH`;
- a senha nunca é armazenada em texto puro: `ADMIN_PASSWORD_HASH` deve ser bcrypt com custo mínimo 12;
- alterar o hash da senha invalida imediatamente sessões administrativas já emitidas;
- login possui limitação por IP e por conta, atraso aleatório em falhas e comparação de senha com custo bcrypt adequado.


### Credencial única no `.env`

Para este projeto, que possui um único administrador, manter a credencial no `.env` é uma escolha deliberada de simplicidade. O arquivo não é servido pelo nginx, não é versionado e deve ter acesso restrito no servidor. O banco não possui tabela de usuários administrativos.

Isso não significa armazenar a senha no `.env`: somente o **hash bcrypt** é persistido. Gere-o com:

```bash
php bin/hash-admin-password.php
```

Depois configure `ADMIN_EMAIL`, `ADMIN_DISPLAY_NAME` e `ADMIN_PASSWORD_HASH`. Se o hash for trocado, os JWTs antigos deixam de ser aceitos automaticamente.

## Proteções HTTP e aplicação

- host allow-list em produção;
- CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e HSTS;
- respostas de autenticação/admin/contato usam `Cache-Control: no-store`;
- PHP sem `display_errors`/`expose_php`, OPcache habilitado e funções de execução de processos desabilitadas no FPM;
- prepared statements com emulação desabilitada;
- URLs persistidas pelo CMS aceitam somente destinos seguros;
- cores customizadas aceitam somente hexadecimal válido;
- uploads validam MIME real, tamanho e resolução; imagens são reprocessadas para WebP; SVG não é aceito;
- uploads são servidos com `nosniff` e CSP `sandbox`;
- hCaptcha permanece obrigatório em produção;
- rate limiting existe na aplicação e também nos proxies nginx.

## Dependências

- nginx de produção fixado em `1.30.5-alpine`;
- cloudflared fixado em `2026.10.0`, evitando deriva silenciosa de `latest`;
- Nuxt fixado em `3.21.11`, última versão da linha 3 e já contendo as correções de segurança publicadas em julho de 2026.

Nuxt 3 encerrou suporte em 31/07/2026. A migração para Nuxt 4 deve ser feita em uma entrega própria, com lockfile regenerado e build/testes completos. Não atualizar um major sem conseguir validar o build é preferível a publicar uma migração não testada.

## Cloudflare recomendado

No Cloudflare:

1. não crie Public Hostname para `api.fbsantos.com.br`; se já existir, remova-o;
2. mantenha somente os túneis do site e do painel;
3. proteja `painel.fbsantos.com.br` com Cloudflare Access e MFA;
4. mantenha WAF/Bot protection/rate limiting ativos conforme seu plano;
5. não publique portas dos containers diretamente no host;
6. ative alertas e revise logs de autenticação e do Cloudflare regularmente.

Cloudflare Access é uma segunda barreira. O painel continua exigindo sua própria autenticação mesmo atrás do Access.

## Secrets e repositório público

Nunca commitar:

- `.env`;
- dumps SQL e backups (grave-os sempre dentro de `backups/`, que é ignorada pelo Git);
- arquivos `.pem`, `.key`, `.p12` ou `.pfx`;
- JSON de conta de serviço do Google;
- tokens do Cloudflare;
- senhas SMTP ou MariaDB.

O `.gitignore` cobre os formatos usados por este projeto, mas a revisão antes do push continua obrigatória:

```bash
git status --ignored
git grep -nE 'PRIVATE KEY|TUNNEL_TOKEN=|PASSWORD=|SECRET='
git ls-files | grep -E '(^|/)(\.env($|\.)|.*\.(pem|key|p12|pfx|dump|bak)$|db\.sql$|fbsantos-db.*\.sql$)' && echo 'ERRO: arquivo sensível versionado'
```

Revogue imediatamente qualquer segredo que tenha sido exposto em histórico Git anterior; removê-lo do arquivo atual não torna o segredo novamente seguro.
