# Como funciona a atualização automática (GitHub Releases)

O plugin verifica sozinho a **última release** de um repositório GitHub. Quando a
versão publicada é maior que a instalada, o site do cliente mostra a atualização
no painel e — por padrão — a aplica **automaticamente** em segundo plano.

## Configuração inicial (uma única vez)

1. Crie um repositório no GitHub para o plugin, ex.: `SEU-USUARIO/rokais-carrossel-wp`
   (pode ser **público**; se for **privado**, veja a seção de token abaixo).
2. No arquivo `sk-price-carousel.php`, ajuste a constante:
   ```php
   define( 'SKPC_GITHUB_REPO', 'SEU-USUARIO/rokais-carrossel-wp' );
   ```
3. Envie o código para o repositório (`git push`).

> Importante: a pasta do plugin no site do cliente deve se chamar
> `rokais-carrossel-wp` (é o que o `.zip` gerado já faz). Não renomeie a pasta.

## Publicando uma nova versão

Sempre que fizer mudanças e quiser distribuí-las:

```powershell
# 1) Gera o zip e já sobe a versão em todos os lugares certos
.\build-release.ps1 -Version 1.0.1

# 2) Publica no GitHub (o script imprime esses comandos no final)
git add -A
git commit -m "Versao 1.0.1"
git tag v1.0.1
git push origin main --tags

# 3) Cria a release anexando o zip como asset
gh release create v1.0.1 dist\rokais-carrossel-wp.zip --title "v1.0.1" --notes "O que mudou nesta versao"
```

Pronto. Em até **~12 horas** os sites dos clientes detectam e atualizam sozinhos.
Para forçar na hora em um site: **Painel → Atualizações → Verificar novamente**.

> A tag **precisa** ser maior que a versão instalada (ex.: `v1.0.1` > `1.0.0`).
> Anexe sempre o `dist\rokais-carrossel-wp.zip` como **asset** da release — é ele
> que o updater baixa (a pasta interna já vem com o nome correto).

## Repositório privado ou limite de API

Para repositório privado (ou para elevar o limite de requisições da API do GitHub),
gere um token de acesso e defina no `wp-config.php` de cada site:

```php
define( 'SKPC_GITHUB_TOKEN', 'ghp_seu_token_aqui' );
```

## Desligar a atualização automática em um site específico

A atualização automática vem **ligada**. Para deixar apenas o aviso (o cliente
clica em "Atualizar" manualmente), adicione no `wp-config.php` do site:

```php
define( 'SKPC_AUTO_UPDATE', false );
```

## Recomendação

Como a atualização vai para **todos** os sites automaticamente, **teste** cada
versão num site de homologação antes de criar a release definitiva.
