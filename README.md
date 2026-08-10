<p align="center">
  <a>
    <img src="https://cloud.disroot.org/apps/files_sharing/publicpreview/fdYW4T7SFoKLzy9?file=/&fileId=295473319&x=1920&y=1080&a=true&etag=41040cd2bbba5481d2a94f75eaed74d3" alt="Sistema de Votação" width="500" height="100%">
  </a>
  <h3 align="center">Teste Sistema de Votação Simples com Drupal - Mazzatech</h3>
</p>

<!--ÍNDICE -->
<details open="open">
  <summary>Índice</summary>
  <ol>
    <li>
      <a href="#sobre-o-teste">Sobre o Teste</a>
      <ul>
        <li><a href="#tecnologias">Tecnologias</a></li>
      </ul>
    </li>
    <li>
      <a href="#iniciando">Iniciando</a>
      <ul>
        <li><a href="#requisitos">Requisitos</a></li>
      </ul>
    </li>
    <li>
      <a href="#passo-a-passo">Passo-a-Passo</a>
      <ul>
        <li><a href="#criando-os-containers-usando-o-lando">Criando os containers usando o Lando</a></li>
        <li><a href="#ajustando-o-aquivo-de-configuração">Ajustando o aquivo de configuração</a></li>
        <li><a href="#instalando-as-dependências-do-projeto-com-composer">Instalando as dependências do projeto com composer</a></li>
        <li><a href="#importando-o-banco-de-dados">Importando o banco de dados</a></li>
        <li><a href="#importando-o-diretório-de-arquivos">Importando o diretório de arquivos</a></li>
        <li><a href="#gerando-o-link-para-se-logar-no-sistema">Gerando o link para se logar no sistema</a></li>
        <li><a href="#acessando-o-projeto-pelo-navegador">Acessando o projeto pelo navegador</a></li>
        <li><a href="#altere-as-senhas-de-usuários-cadastrados-no-sistema-para-que-possam-votar">Altere as senhas de usuários cadastrados no sistema para que possam votar</a></li>
        <li>
          <a href="#acessando-o-sistema-de-votação-pela-api">Acessando o sistema de votação pela API</a>
          <ul>
            <li><a href="#visão-geral">Visão geral</a></li>
            <li><a href="#permissões-por-cenário">Permissões por cenário</a></li>
            <li><a href="#como-gerar-o-token">Como gerar o token</a></li>
            <li><a href="#como-chamar-a-api">Como chamar a API</a></li>
            <li><a href="#1-listar-votações-disponíveis">1. Listar votações disponíveis</a></li>
            <li><a href="#2-detalhar-uma-votação">2. Detalhar uma votação</a></li>
            <li><a href="#3-registrar-um-voto">3. Registrar um voto</a></li>
            <li><a href="#4-consultar-resultados-de-uma-votação">4. Consultar resultados de uma votação</a></li>
          </ul>
        </li>
        <li>
          <a href="#health-checks">Health checks</a>
          <ul>
            <li><a href="#1-liveness">1. Liveness</a></li>
            <li><a href="#2-readiness">2. Readiness</a></li>
            <li><a href="#códigos-de-resposta-mais-comuns">Códigos de resposta mais comuns</a></li>
            <li><a href="#exemplo-de-fluxo-completo">Exemplo de fluxo completo</a></li>
          </ul>
        </li>
      </ul>
    </li>
    <li><a href="#license">License</a></li>
  </ol>
</details>

<!-- ABOUT -->
## Sobre o Teste

O objetivo do desafio é criar um sistema de votação simples que permitirá que usuários autenticados votem em perguntas cadastradas pelo administrador.

### Tecnologias

* [PHP 8.3](https://www.php.net/)
* [Apache 2](https://www.apache.org/)
* [MySQL 8](https://www.postgresql.org/)
* [Lando](https://lando.dev/)

<!-- INICIANDO -->
## Iniciando

Para rodar o projeto localmente você precisa instalar todos os requisitos listados abaixo:

### Requisitos

Tenha em sua máquina o Docker e o Lando instalados:
* Docker version 29.6.2, build dfc4efb
  ```sh
  docker -v
  ```
* Lando - v3.26.7
  ```sh
  lando version
  ```
## Passo-a-Passo

### Criando os containers usando o Lando

* Clone o repositório
  ```sh
  git clone https://github.com/ferox/simple-voting-system.git
  ```
* Tenha certeza de estar dentro do diretório clonado, exemplo: ~/Projetos/Github.com/simple-voting-system
  ```sh
  pwd
  ```
* Criando e iniciando os containers
  ```sh
  lando start
  ```

### Ajustando o aquivo de configuração

#### Em sites/default copie o script the settings do ambiente lando

- example.lando.settings.php

#### Renomeie ele, como mostrado abaixo:

- lando.settings.php

### Instalando as dependências do projeto com composer

* Instale através do lando
  ```sh
  lando composer install
  ```

### Importando o banco de dados

* Baixe o arquivo de backup do banco de dados pelo endereço abaixo:

[https://cloud.disroot.org/s/bQNz3f56KyoyEcd](https://cloud.disroot.org/s/bQNz3f56KyoyEcd)

* Vamos usar a ferramenta do próprio lando
  ```sh
  lando db-import voting-sistemd-db.sql
  ```

### Gerando o link para se logar no sistema

* Gere o login do usuário 'number 1' usando o drush:
  ```sh
  lando drush @lando uli
  ```

### Importando o diretório de arquivos

* Baixe o arquivo de backup do files pelo endereço abaixo:

[https://cloud.disroot.org/s/qjwY6SCPBct2BPq](https://cloud.disroot.org/s/qjwY6SCPBct2BPq)

* Descompacte o arquivo e mova para o diretório `web/sites/default/files`


### Gerando o link para se logar no sistema

* Gere o login do usuário 'number 1' usando o drush:
  ```sh
  lando drush @lando uli
  ```


### Acessando o projeto pelo navegador

[https://voting-system.lndo.site](https://voting-system.lndo.site)

### Altere as senhas de usuários cadastrados no sistema para que possam votar

[https://voting-system.lndo.site/pt-br/admin/people](https://voting-system.lndo.site/pt-br/admin/people)

### Acessando o sistema de votação pela API:

Este documento descreve como gerar um token OAuth 2.0 e como consumir a API de votação do sistema.

Na raiz do projeto há o arquivo de collection no formato json que poderá ser usado pelo postman para teste da api. O arquivo gerado foi através da ferramenta Open Source Bruno https://www.usebruno.com/ . O Bruno usa o padrão OpenCollection para o schema das apis. O nome do arquivo na raiz é:

```bash
VotingSystem.json
```

#### Visão geral

- Base URL local de exemplo: `https://voting-system.lndo.site`
- Provider de autenticação das rotas protegidas: `oauth2`
- Endpoint de token: `POST /oauth/token`
- Formato de autenticação da API: `Authorization: Bearer SEU_ACCESS_TOKEN`

Use sempre HTTPS/TLS ao enviar o header `Authorization`.

#### Permissões por cenário:

a) Consultar votações: `access voting api`

b) Votar: `access voting api` e `participate in voting`

c) Ver resultados: `access voting api` e `access voting results`

#### Como gerar o token:

O fluxo mais simples para este projeto é `client_credentials`. Acesse https://voting-system.lndo.site/pt-br/admin/config/services/consumer para pegar o id do consumer e a sua senha, por padrão a senha é 12345678.

Exemplo com `curl`:

```bash
curl --request POST 'https://voting-system.lndo.site/oauth/token' \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=client_credentials' \
  --data-urlencode 'client_id=SEU_CLIENT_ID' \
  --data-urlencode 'client_secret=SEU_CLIENT_SECRET'
```

Resposta esperada:

```json
{
  "token_type": "Bearer",
  "expires_in": 300,
  "access_token": "..."
}
```

Guarde o valor de `access_token`.

#### Como chamar a API

Para os exemplos abaixo, exporte variáveis no terminal:

```bash
export BASE_URL='https://voting-system.lndo.site'
export ACCESS_TOKEN='SEU_ACCESS_TOKEN'
```

#### 1. Listar votações disponíveis

```bash
curl --request GET "$BASE_URL/api/v1/votings" \
  --header "Authorization: Bearer $ACCESS_TOKEN" \
  --header 'Accept: application/json'
```

Retorna as votações disponíveis para o usuário autenticado.

#### 2. Detalhar uma votação

Substitua `1` pelo ID real da votação:

```bash
curl --request GET "$BASE_URL/api/v1/votings/1" \
  --header "Authorization: Bearer $ACCESS_TOKEN" \
  --header 'Accept: application/json'
```

Retorna título, pergunta, respostas e status de participação.

#### 3. Registrar um voto

Substitua:

- `1` pelo ID da votação
- `10` pelo ID da resposta

```bash
curl --request POST "$BASE_URL/api/v1/votings/1/votes" \
  --header "Authorization: Bearer $ACCESS_TOKEN" \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --header 'Idempotency-Key: 0198f2cc-4e92-7aef-86be-7e61ef8837cf' \
  --data '{"answer_id":10}'
```

Observações:

- `answer_id` é obrigatório.
- O usuário só pode votar uma vez por votação.
- Se a mesma `Idempotency-Key` for reenviada com o mesmo payload, a API devolve o voto já criado.
- Se a mesma `Idempotency-Key` for usada com payload diferente, a API retorna `409`.

#### 4. Consultar resultados de uma votação

Esse endpoint exige a permissão `access voting results`.

```bash
curl --request GET "$BASE_URL/api/v1/votings/1/results" \
  --header "Authorization: Bearer $ACCESS_TOKEN" \
  --header 'Accept: application/json'
```

Retorna o total de votos e a contagem por resposta, incluindo respostas com zero votos.

### Health checks

Os health checks atuais não exigem token.

#### 1. Liveness

```bash
curl --request GET "$BASE_URL/health/live" \
  --header 'Accept: application/json'
```

#### 2. Readiness

```bash
curl --request GET "$BASE_URL/health/ready" \
  --header 'Accept: application/json'
```

O endpoint `/health/ready` pode retornar `503` se:

- o banco não estiver pronto
- o módulo `simple_oauth` não estiver ativo
- as chaves do Simple OAuth ainda não estiverem configuradas

#### Códigos de resposta mais comuns

- `200`: consulta executada com sucesso
- `201`: voto criado com sucesso
- `400`: JSON inválido
- `401`: token ausente, inválido ou expirado
- `403`: usuário sem permissão
- `404`: votação não encontrada ou indisponível
- `409`: voto duplicado ou conflito de idempotência
- `422`: resposta inválida para a votação
- `503`: dependência indisponível

#### Exemplo de fluxo completo

1. Criar um `Consumer` vinculado a um usuário com papel `participant_voting`.
2. Gerar o token em `/oauth/token`.
3. Chamar `GET /api/v1/votings`.
4. Escolher um `id` de votação.
5. Chamar `GET /api/v1/votings/{id}` para descobrir as respostas.
6. Enviar `POST /api/v1/votings/{id}/votes`.
7. Se o usuário tiver permissão de resultados, chamar `GET /api/v1/votings/{id}/results`.


## License

[GNU General Public License v3](https://www.gnu.org/licenses/gpl-3.0.en.html)
