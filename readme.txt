=== Rokais Carrossel WP ===
Contributors: rokais
Tags: elementor, carousel, carrossel, preços, google sheets
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Elementor tested up to: 3.23.0

Carrossel inteligente para o Elementor com imagem, descrição, preço cheio e preço promocional. Fontes: Google Sheets, JSON e MySQL, com cache e atualização automática.

== Description ==

Adiciona ao Elementor o widget **Carrossel de Preços**, que exibe itens com imagem, descrição, preço cheio e preço promocional (quando houver). Os dados podem vir de:

* **Google Sheets** (CSV público, gviz ou API v4)
* **Link JSON** (com caminho da lista e mapeamento por dot-path)
* **MySQL externo** (tabela + mapeamento de colunas, sem SQL cru)
* **Itens manuais** cadastrados direto no widget

As fontes são cadastradas uma única vez como **conexões globais** e reutilizadas por qualquer carrossel. A atualização é feita por **cache no servidor (WP-Cron)** com intervalo configurável (TTL) e, opcionalmente, por **refresh ao vivo no navegador** (sem recarregar a página).

Todo o visual é ajustável pelos controles de Estilo do Elementor: fontes, tamanhos, bordas, cores, sombras e navegação.

== Segurança ==

* Credenciais (senha MySQL, chave/token) são cifradas em repouso (AES-256-CBC) e nunca expostas no frontend/REST.
* Nomes de tabela/coluna do MySQL são validados por whitelist e conferidos contra a introspecção do banco.
* URLs de Sheets/JSON passam por guarda anti-SSRF (bloqueio de loopback/redes privadas).

Para reforçar a cifragem, defina em `wp-config.php`:

`define( 'SKPC_ENCRYPTION_KEY', 'uma-chave-longa-e-aleatoria' );`

== Atualização automática (WP-Cron) ==

O WP-Cron depende de visitas ao site. Em sites de baixo tráfego, recomenda-se desativar o cron interno e usar um cron real do servidor:

`define( 'DISABLE_WP_CRON', true );`

e agendar uma chamada periódica a `wp-cron.php`.

== Changelog ==

= 1.0.0 =
* Versão inicial.
