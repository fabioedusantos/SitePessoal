# CMS e editabilidade do site público

## Princípio

O banco é a fonte de verdade. O CMS separa **dados da carreira** de **composição visual das páginas**:

- entidades como experiências, projetos, tecnologias, formação e certificações são cadastradas uma única vez;
- páginas escolhem quais seções possuem, em qual ordem e quais entidades aparecem em cada seção;
- o frontend mantém os componentes e limites visuais do design system, sem HTML/CSS arbitrário no CMS.

Esse modelo permite criar e reorganizar páginas sem alterar Vue, sem duplicar conteúdo profissional e sem adicionar colunas como `show_on_home`, `show_on_about` etc. nas entidades.

## Entidades da carreira

O CMS mantém, entre outras:

- apresentação profissional;
- organizações;
- experiências e cargos;
- projetos/cases;
- tecnologias;
- formação;
- certificações;
- publicações/palestras;
- bancas avaliadoras;
- mídias, links e relações profissionais.

Relacionamentos de domínio continuam normalizados, como projeto × tecnologia, experiência × tecnologia e projeto × experiência. Eles descrevem fatos da carreira e não a posição visual de um item em uma página.

## Construtor de páginas

A apresentação pública é composta por três estruturas genéricas:

### `pages`

Representa uma página publicável. Armazena:

- título e caminho/slug;
- status de publicação;
- tipo/uso da página;
- vínculo opcional com uma entidade, como um projeto;
- SEO: title, description, canonical, Open Graph, noindex e sitemap;
- ordem administrativa.

A página `home` é publicada em `/`. Caminhos podem ser aninhados, por exemplo `projetos/contaokay`.

### `page_sections`

Cada página possui quantas seções forem necessárias. A seção define:

- tipo visual/funcional;
- nome interno;
- chamada, título, trecho de destaque, texto e imagem;
- CTA;
- visibilidade e ordem;
- preset visual: padrão FBSantos, claro, escuro, azul da marca ou personalizado;
- fundo, cor de texto e cor de destaque quando personalizado;
- largura;
- espaçamento superior e inferior;
- configurações específicas em `settings`.

A liberdade visual é deliberadamente controlada. O CMS não recebe CSS arbitrário: utiliza presets e propriedades aprovadas para preservar responsividade, acessibilidade e consistência.

### `page_section_items`

Armazena os itens de uma seção. Um item pode:

- apontar para uma entidade existente, como projeto, experiência, organização ou tecnologia;
- ser um conteúdo manual da própria seção;
- ter título, texto, imagem e link sobrescritos quando necessário;
- possuir ordem e visibilidade próprias.

Assim, uma grade de projetos, por exemplo, seleciona e ordena projetos sem alterar o cadastro desses projetos.

## Tipos de seção

O editor oferece blocos controlados pelo design system, incluindo Hero, texto, texto + imagem, organizações, indicadores, projeto em destaque, grade de projetos, trajetória, cards, tecnologias, formação, certificações, publicações, bancas, galeria, links, conteúdo de projeto, experiências relacionadas, CTA e contato.

Novos tipos podem ser acrescentados ao design system quando houver uma necessidade real, sem transformar o CMS em um editor de HTML/CSS livre.

## Home e demais páginas

A Home não possui regra arquitetural especial. Ela usa o mesmo construtor das demais páginas.

Por exemplo, a presença do ContaOkay na Home depende de uma seção que o selecione. Marcar um projeto como destaque não o insere automaticamente em nenhuma página. Da mesma forma, organizações e experiências só aparecem em uma seção quando a composição daquela página assim determinar.

`Fábio Santos Desenvolvimentos` permanece fora da faixa inicial de organizações da Home na carga inicial, pois essa vitrine representa os contextos externos da trajetória. Como a composição é explícita, a empresa não reaparece automaticamente por flags da entidade.

## Páginas de projetos/cases

Projetos continuam armazenando seus dados estruturados — contexto, participação, solução, arquitetura, desafios, resultados, tecnologias, links e mídias.

A página pública do case, porém, é uma página do construtor vinculada ao projeto. Isso permite reorganizar, ocultar ou acrescentar seções para cada case individualmente sem modificar o componente Vue.

Ao criar um projeto pelo CMS, o backend cria uma página inicial de case com as seções padrão. Depois disso, o editor de páginas pode personalizar a composição. Alterações posteriores no projeto sincronizam somente o caminho/status necessários e não sobrescrevem a composição ou SEO personalizados da página.

## Navegação

`site_navigation` referencia preferencialmente uma página interna por `page_id`. URLs manuais ficam para links externos.

A ordem é controlada por local + `sort_order`, e o painel permite ordenar as tabelas e editar a composição sem exigir que o usuário conheça detalhes do banco.

## Cabeçalho e rodapé

Configurações globais que não pertencem a uma página ficam em `site_settings`, como logotipo do cabeçalho, logotipo do rodapé e textos institucionais do rodapé.

## Uploads

Imagens editoriais usam upload no painel. Arquivos gerenciados ficam em armazenamento persistente e podem ser removidos fisicamente quando deixam de ser referenciados. URLs externas permanecem apenas onde fazem sentido, como vídeos ou recursos externos.

## Pré-visualização

O editor de páginas permite abrir a versão salva da página no próprio painel e em uma nova aba. Não há, nesta etapa, um sistema paralelo de preview de alterações ainda não salvas; isso evita complexidade de drafts/transações sem benefício proporcional.

## Banco em instalação nova

O schema é mantido por migrations estruturais. Conteúdo editorial e dados profissionais não são distribuídos pelo repositório; em desenvolvimento, use um backup próprio do banco quando precisar reproduzir o conteúdo do CMS. Em instalações novas, as migrations criam somente a estrutura vazia.
