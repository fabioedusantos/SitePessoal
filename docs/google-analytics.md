# Google Analytics 4

A integração possui duas partes independentes.

## Métricas do site

O site usa o Google tag com o Measurement ID público (`G-...`). Configure em `site/docker/production/.env`:

```dotenv
NUXT_PUBLIC_GA_MEASUREMENT_ID=G-XXXXXXXXXX
```

O plugin `site/app/plugins/analytics.client.ts` carrega o tag somente quando o ID é válido e registra `page_view` após cada navegação concluída pelo Nuxt, evitando a dupla contagem do page view automático.

O Measurement ID não é um segredo. Mesmo assim, ele fica em configuração para separar ambientes de desenvolvimento e produção.

## Relatórios no CMS

A tela **Google Analytics** do painel lê a Google Analytics Data API exclusivamente pelo backend. A chave privada nunca é enviada ao navegador nem incluída no build Vue/Nuxt.

O painel apresenta, para os últimos 28 dias com comparação aos 28 dias anteriores:

- usuários ativos;
- novos usuários;
- sessões;
- visualizações;
- sessões engajadas;
- taxa de engajamento;
- duração média de sessão;
- evolução diária;
- páginas mais vistas;
- canais de aquisição;
- países;
- categorias de dispositivo.

Os relatórios são armazenados em cache por 5 minutos por padrão para reduzir chamadas à API.

## Configuração no Google Cloud / GA4

1. Crie ou escolha um projeto no Google Cloud.
2. Ative a **Google Analytics Data API**.
3. Crie uma conta de serviço somente para leitura do Analytics.
4. Na propriedade GA4, conceda ao e-mail da conta de serviço acesso de leitura/Viewer.
5. Anote o **Property ID numérico** da propriedade, não o Measurement ID `G-...`.
6. Gere uma chave JSON para a conta de serviço e mantenha esse arquivo fora do repositório.

No `backend/docker/production/.env` configure:

```dotenv
GA_PROPERTY_ID=123456789
GA_SERVICE_ACCOUNT_EMAIL=analytics-reader@seu-projeto.iam.gserviceaccount.com
GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64=BASE64_DA_CHAVE_PRIVADA_PEM
GA_CACHE_SECONDS=300
```

### Extrair os valores no PowerShell

```powershell
$sa = Get-Content .\ga-service-account.json -Raw | ConvertFrom-Json
$sa.client_email
[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($sa.private_key))
```

Copie a primeira saída para `GA_SERVICE_ACCOUNT_EMAIL` e a segunda para `GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64`.

Depois de copiar os valores para o `.env`, guarde ou elimine o JSON em local seguro. **Nunca** envie esse JSON ao GitHub.

## Falha segura

Analytics é opcional para o funcionamento do CMS. Se as três credenciais de relatório não estiverem configuradas, a tela informa que a integração está pendente. Se uma delas estiver configurada sem as demais em produção, o backend recusa inicialização para evitar uma configuração parcialmente quebrada.
