# 茶多楼 chatterrow

## Reunimos no chat as funcionalidades que apoiam a gestão centralizada do trabalho
<img width="100%" alt="Chatterrow サービス紹介" src="./assets/chatterrow.gif" />

Construído com Laravel 13, Inertia 3 e Svelte 5, é um groupware baseado em projetos com uma interface ao estilo do Discord.

Crie canais para cada projeto e faça a gestão centralizada de chats, tarefas, ficheiros e gráficos de Gantt. Utiliza Laravel Reverb para a transmissão em tempo real e ONLYOFFICE Document Server para pré-visualizações apenas de leitura de ficheiros do Office.

## Vantagens do groupware on-premises (茶多楼 é o serviço da parte App Server)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Principais funcionalidades

- **Gestão de projetos**: configurar o nome, o conteúdo, a data de início, a data de fim e os membros do projeto
- **Canais**: organizar as conversas, tarefas e ficheiros dentro de um projeto por canal
- **Chat em tempo real**: sincronizar mensagens, threads e o número de respostas através do Laravel Reverb
- **Markdown seguro**: escape de HTML, restrições a URLs HTTP(S), realce da sintaxe de código com Shiki
- **Ficheiros anexados**: D&D de ficheiros/pastas, carregamentos em lotes de 10, miniaturas de imagens, PDF e Office
- **Pré-visualização de ficheiros**: visualizador central para imagens e PDF, pré-visualização de Office com ONLYOFFICE, sair com Esc
- **Conversão e armazenamento de Office/PDF em Markdown**: converter em Markdown em segundo plano para facilitar a utilização no treino de IA
- **Gestão de tarefas**: data de início, hora de início, data de fim, hora de fim, prioridade, notas e estado de conclusão
- **Gráfico de Gantt**: visualização do período por projeto ou canal
- **Lembretes de prazo**: notificações automáticas através do scheduler e do queue worker
- **Temas**: suporte aos modos escuro e claro
- **Operação pelo teclado**: o envio de mensagens e a criação de tarefas usam `Cmd+Enter` ou `Ctrl+Enter`. O Enter de confirmação do IME não envia a mensagem

## Tecnologias

| Camada     | Tecnologia                                                     |
|------------|----------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite ou PostgreSQL                                          |
| Realtime   | Laravel Reverb (WebSocket)                                    |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7 (PDF / DOCX / XLSX / PPTX)         |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition (JWT, apenas leitura) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Requisitos de produção

- Ubuntu 24.04 LTS ou Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM e extensão Redis
- Python 3.10 ou superior, MarkItDown 0.1.7, Redis Server
- Um utilizador normal com acesso a sudo ou um utilizador root
- 2 CPU, 2 GB de RAM e pelo menos 30 GB de espaço livre em disco (a recomendação oficial do ONLYOFFICE é de pelo menos 40 GB)
- Recomenda-se pelo menos 4 GB de swap
- Tornar os TCP 80/443 acessíveis a partir da Internet
- Um nome DNS para a aplicação

Exemplo:

```text
chat.example.com  A/AAAA -> サーバー
```

O ONLYOFFICE é publicado em `/onlyoffice/` no mesmo domínio que a aplicação. Não exponha externamente o ONLYOFFICE, o Reverb nem as portas 8080, 8081 e 8090 utilizadas para a obtenção interna pela aplicação. Nas firewalls da cloud ou do anfitrião, permita apenas a porta utilizada pelo SSH e as portas 80/443.

## Configuração automática num ambiente Ubuntu

Num Ubuntu Server novo, obtenha este repositório e execute `setup.sh`. O domínio, a base de dados e o endereço de e-mail do Let's Encrypt são confirmados interactivamente. Pode ser executado por um utilizador normal ou pelo utilizador root.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

Para ambientes de desenvolvimento ou validação local, redes fechadas e outros casos em que não sejam necessários um domínio público acessível a partir da Internet e HTTPS, execute com `--no-ssl`. O Let's Encrypt não será utilizado e será configurado apenas HTTP. Ao utilizar um domínio local como `chatterrow.test`, configure previamente a resolução do nome para este servidor através de DNS ou de `/etc/hosts`.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

Ao executar `setup.sh`, será pedida a palavra-passe do sudo. Se utilizar um utilizador que pode usar sudo sem palavra-passe, execute com a opção `--sudo-nopasswd`, como no exemplo seguinte.


```bash
./setup.sh --sudo-nopasswd
```

Se for executado sem opções, quando for detectado um sudo que não necessita de palavra-passe, a configuração não é iniciada e o processo termina apresentando como utilizar `--sudo-nopasswd`.

Exemplo de entrada após executar `setup.sh`:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
（中略）
PostgreSQL password (leave blank to generate):
```

O URL público do ONLYOFFICE é `https://<アプリドメイン>/onlyoffice`. No exemplo acima, é `https://chat.example.com/onlyoffice`.

A configuração executa automaticamente as operações seguintes.

1. Configura o `nginx-extras` oficial do Ubuntu, PHP 8.5, PostgreSQL, Redis, RabbitMQ e Node.js 24
2. Instala extensões PHP, Poppler, ImageMagick, Ghostscript e fontes japonesas através do apt, e define o caminho absoluto do ImageMagick no `.env`
3. Constrói o MarkItDown 0.1.7 no ambiente virtual Python `.markitdown/venv` e valida `pip check` e a versão da CLI
4. Ajusta o PostgreSQL de acordo com o número de CPUs e a RAM instalada
5. Quando o PostgreSQL é seleccionado, configura uma role com o mesmo nome do utilizador que executa o processo (`DEPLOY_USER`) e com `SUPERUSER LOGIN`
6. Instala o ONLYOFFICE Document Server com JWT activo, na porta interna 8080 (`ONLYOFFICE_PORT` permite alterá-la)
7. Aplica as dependências, o frontend e as migrações ao repositório clonado
8. Configura através do nginx a aplicação, o ONLYOFFICE, o Reverb e o caminho interno de transferência do ONLYOFFICE
9. Mantém através do Supervisor 10 processos da fila Redis, o Reverb e o scheduler como `www-data`
10. Emite o certificado com o Certbot e activa `certbot.timer` e o hook de reload do nginx
11. Aplica diariamente as actualizações de segurança do Ubuntu, incluindo o nginx, através de `unattended-upgrades`
12. Executa verificações de integridade do PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor e da aplicação

O nginx utiliza apenas pacotes APT do Ubuntu.

## OnlyOffice local no macOS

No macOS, não instale o pacote do OnlyOffice para Linux; inicie o DocumentServer com o `container` da Apple. São necessários Apple silicon e macOS 26 ou posterior.

1. Instale o [Apple Container](https://github.com/apple/container).
2. Instale o ImageMagick com `brew install imagemagick`.
3. Prepare o `.env` da aplicação Laravel. Se não existir, o `setup.sh` copia `.env.example`.
4. Ajuste `APP_URL` para o URL local real.
5. Execute a configuração na raiz do repositório. No macOS, `--domain` e `--database` não são necessários.

```bash
cd /path/to/chatterrow
./setup.sh
```

O `.env` existente não é totalmente substituído. São actualizadas apenas as definições do ONLYOFFICE seguintes e o caminho absoluto detectado do ImageMagick.

```dotenv
ONLYOFFICE_ENABLED
ONLYOFFICE_DOCUMENT_SERVER_URL
ONLYOFFICE_PUBLIC_URL
APP_ONLYOFFICE_INTERNAL_URL
ONLYOFFICE_JWT_SECRET
ONLYOFFICE_ALLOW_DOWNLOAD
ONLYOFFICE_ALLOW_PRINT
IMAGEMAGICK_PATH
```

### Desenvolvimento no macOS: detecção automática de Valet, Herd e artisan serve

O `setup.sh` verifica a existência dos comandos `valet` e `herd`, bem como as respostas de `APP_URL/up` e `127.0.0.1:<ポート>/up`, e selecciona o método de ligação. Quando tanto o lado do Valet/Herd como o lado do artisan serve respondem, é dada prioridade ao lado do Valet/Herd de `APP_URL`.

| Servidor de desenvolvimento | Exemplo de `.env`: `APP_URL` | `APP_ONLYOFFICE_INTERNAL_URL` definida automaticamente |
|-----------------------------|--------------------------------|---------------------------------------------------------|
| Laravel Valet               | `http://chatterrow.test`        | `http://chatterrow.test`                                |
| Laravel Herd                | `http://chatterrow.test`        | `http://chatterrow.test`                                |
| `php artisan serve`         | `http://localhost:8000`         | `http://chatter-host.container.internal:8000`           |

Ao utilizar artisan serve, inicie-o antes da configuração ou antes de abrir a pré-visualização.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Para substituir a detecção automática:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

A configuração efectua as operações seguintes.

- Inicia o Apple Container com `container system start`
- Obtém `onlyoffice/documentserver:latest` através de pull em cada execução e inicia-o em arm64
- Publica o OnlyOffice em `127.0.0.1:8086`
- Atribui 4 CPU, 4 GB de memória e 2 GB de memória partilhada
- Fixa o servidor DNS do contentor em `1.1.1.1`
- Activa o JWT e define a chave secreta partilhada no `.env`
- Cada vez que o `setup.sh` é executado no macOS, recria o contentor do OnlyOffice mantendo os volumes nomeados
- Liga `chatter-host.container.internal` ao loopback do macOS através de `203.0.113.150`
- No Valet/Herd, atribui o nome do anfitrião da aplicação a `203.0.113.150` apenas dentro do contentor
- Obtém e valida Source Han Sans JP / Noto Serif CJK JP e regenera a lista de tipos de letra do OnlyOffice
- Actualiza `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL` e `APP_ONLYOFFICE_INTERNAL_URL`
- Verifica a integridade de `/up` do DocumentServer e do Laravel

Para substituir o servidor DNS, indique uma variável de ambiente. O valor deve ser um endereço IPv4.

Exemplo de alteração do 1.1.1.1 da Cloudflare para o 8.8.8.8 da Google:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

São utilizados os volumes nomeados seguintes para os dados persistentes.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Verificação e paragem do contentor:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

Verificação de integridade:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### Teste de inicialização completa do contentor e dos volumes

As operações seguintes eliminam o PostgreSQL interno do OnlyOffice, as definições, a cache e os registos. Não as execute se existirem dados necessários.

```bash
container stop chatterrow-onlyoffice-documentserver
container delete chatterrow-onlyoffice-documentserver
container volume delete \
    chatterrow-onlyoffice-data \
    chatterrow-onlyoffice-logs \
    chatterrow-onlyoffice-cache \
    chatterrow-onlyoffice-postgresql
./setup.sh
```

[Os volumes nomeados do Apple Container](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) são criados implicitamente quando não existem através de `container run --volume <名前>:<パス>`, pelo que o procedimento acima permite confirmar a configuração inicial, incluindo a criação dos volumes.

### Tipos de letra japoneses (macOS)

Mesmo que instale tipos de letra no anfitrião macOS, estes não podem ser utilizados pelo contentor isolado do OnlyOffice. O `setup.sh` descarrega os tipos de letra de peso fixo dos repositórios oficiais com versões fixas, verifica o SHA-256 e só depois os regista no OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, OTF do subconjunto JP)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, OTF japonês)

Os tipos de letra são guardados dentro do volume `chatterrow-onlyoffice-data` em `/var/www/onlyoffice/Data/custom-fonts`. Ao executar novamente a configuração, é verificado o SHA-256 dos ficheiros dentro do contentor e não é feito novo descarregamento se houver correspondência.

Depois de registar as versões de peso fixo, o `setup.sh` elimina os `AllFonts.js` e `font_selection.bin` existentes e executa `allfontsgen`. Se os ficheiros existentes forem mantidos, o `allfontsgen` pode reutilizar o catálogo e não registar os novos tipos de letra.

O converter do OnlyOffice 9.4 pode substituir os tipos de letra de tema japoneses do Microsoft Office por `NanumGothic` ou `Droid Sans Fallback`. Os aliases do fontconfig não têm efeito neste caminho de conversão. Por isso, [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) aplica as correcções seguintes ao `font_selection.bin` do servidor e aos dois `AllFonts.js` do browser.

- Regista no Source Han Sans JP os aliases das famílias 游ゴシック, Yu Gothic, Meiryo e MS Gothic
- Regista no Noto Serif CJK JP os aliases das famílias 游明朝, Yu Mincho e MS Mincho
- Altera para Source Han Sans JP a referência ao tipo de letra real `NanumGothic` escolhido pelo converter
- Altera para Noto Serif CJK JP a referência ao tipo de letra real `Droid Sans Fallback` escolhido pelo converter

Este catálogo é partilhado pela conversão de DOCX, XLSX e PPTX e pela apresentação no browser. Após as correcções, é gerada a cache de JS, o docservice e o converter são reiniciados e a cache do DocumentServer é eliminada. Se o formato do catálogo ou os nomes de tipos de letra obrigatórios mudarem numa futura versão `latest`, a configuração termina com um erro sem utilizar um catálogo incorrecto.

O `documentserver-generate-allfonts.sh` padrão não é chamado porque também regenera temas de apresentação desnecessários e, no ambiente Apple Container, esse processo pode não terminar.

Quando o catálogo de tipos de letra é actualizado, a geração da cache de documentos do OnlyOffice também muda; assim, os DOCX já abertos são novamente convertidos sem reutilizar um `Editor.bin` antigo.

Se um contentor existente deixar de responder em `Generating presentation themes`, execute novamente `./setup.sh` tal como está. No macOS, cada execução faz pull de `onlyoffice/documentserver:latest` e força a recriação do contentor OnlyOffice existente. Os quatro volumes nomeados acima não são eliminados, pelo que os dados persistentes do OnlyOffice são mantidos.

Os tipos de letra 游明朝／游ゴシック da Microsoft não são incluídos nem redistribuídos. Dentro do contentor OnlyOffice são utilizadas as alternativas seguintes.

| Tipo de letra especificado pelo Office (DOCX / XLSX / PPTX) | Tipo de letra alternativo |
|--------------------------------------------------------------|---------------------------|
| 游明朝 / Yu Mincho / MS Mincho                               | Noto Serif CJK JP         |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic                  | Source Han Sans JP        |

Os caracteres em falta e a substituição por tipos de letra latinos inadequados são corrigidos, mas, como existe uma diferença na largura dos caracteres em relação aos tipos de letra 游, não é garantida a correspondência exacta das quebras de linha ou do número de páginas. Se for necessária uma correspondência exacta, coloque separadamente os ficheiros reais dos tipos de letra 游 na área de tipos de letra personalizados do OnlyOffice, depois de confirmar a licença de utilização.

Verificação do estado do registo:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

Os valores esperados são, pela ordem indicada, `1.1.1.1`, `NotoSerifCJKjp-Regular.otf` e `SourceHanSansJP-Light.otf`.

No macOS, não são concedidas permissões de edição ao OnlyOffice, que permanece como pré-visualização ReadOnly. A API de conversão do DocumentServer também pode ser utilizada independentemente desta definição ReadOnly.

## Configuração não interactiva

Nos ambientes de automatização Ubuntu, `--domain` e `--database` são obrigatórios. Não são necessários na configuração local do OnlyOffice para macOS.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Opções

| Opção                        | Predefinição                         | Descrição                                             |
|------------------------------|--------------------------------------|-------------------------------------------------------|
| `--domain <domain>`          | entrada interactiva                  | Domínio público da aplicação                         |
| `--email <email>`            | vazio                                | E-mail de registo e de avisos de expiração do Let's Encrypt |
| `--database <driver>`        | `sqlite` durante a interacção       | `sqlite` ou `postgresql`                               |
| `--db-name <name>`           | `chatterrow`                         | Nome da base de dados PostgreSQL da aplicação          |
| `--db-user <name>`           | `chatterrow`                         | Role PostgreSQL da aplicação                           |
| `--db-password <password>`   | gerada automaticamente               | Palavra-passe PostgreSQL da aplicação                  |
| `--app-dir <path>`           | repositório onde se encontra `setup.sh` | Local de implementação sob `/home` ou `/var/www`    |
| `--repo <url>`               | URL SSH do GitHub                    | Repositório Git a implementar                          |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest`   | Imagem do DocumentServer que é obtida por pull e utilizada sempre no macOS |
| `--sudo-nopasswd`            | desactivado                          | Para utilizadores sudo sem palavra-passe. Omite `sudo -v` |
| `--no-ssl`                   | desactivado                          | Omite o Certbot e configura através de HTTP             |

Também podem ser utilizadas variáveis de ambiente maiúsculas com o mesmo nome. Exemplos: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

Se a palavra-passe do PostgreSQL for omitida, é gerado um valor aleatório de 64 caracteres e guardado nos locais seguintes.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

Quando o PostgreSQL é seleccionado, a role PostgreSQL com o mesmo nome do utilizador que executa o processo (`DEPLOY_USER`; se não for especificado, `id -un` quando executado por um utilizador normal ou `root` quando executado como root) é criada ou actualizada como `LOGIN SUPERUSER`. O `DB_USER` de ligação da aplicação é mantido como uma role sem privilégios.

## Ajuste automático do PostgreSQL

O PostgreSQL também é necessário para o ONLYOFFICE, pelo que é instalado e ajustado mesmo quando a aplicação selecciona SQLite. Se existirem vários clusters PostgreSQL no Ubuntu, a configuração é interrompida para não alterar o cluster errado.

Ficheiro de configuração:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Principais critérios de cálculo:

| Definição             | Critério                                             |
|-----------------------|------------------------------------------------------|
| `shared_buffers`       | 20% da RAM, entre 128 MB e 8 GB                      |
| `effective_cache_size` | 60% da RAM, entre 256 MB e 64 GB                     |
| `maintenance_work_mem` | 5% da RAM, entre 64 MB e 1 GB                        |
| `work_mem`             | Calculado de forma conservadora a partir da RAM, de `shared_buffers` e do número máximo de ligações |
| `max_connections`      | Calculado a partir da CPU e da RAM, entre 50 e 300   |
| parallel workers       | Calculados a partir do número de CPUs e com um limite |

Como este servidor também aloja PHP, ONLYOFFICE, Redis e RabbitMQ, a atribuição é mais conservadora do que num servidor dedicado ao PostgreSQL. Numa nova execução, é recalculada a partir do número actual de CPUs e da RAM.

## Configuração de portas

Ambiente de produção Ubuntu:

| Porta    | Utilização                           | Âmbito de publicação |
|----------|--------------------------------------|----------------------|
| 80 / 443 | nginx, Certbot e Web pública         | Internet             |
| 8080     | ONLYOFFICE Document Server           | para localhost       |
| 8081     | Laravel Reverb                       | para localhost       |
| 8090     | Obter ficheiros assinados do ONLYOFFICE | apenas 127.0.0.1  |
| 5432     | PostgreSQL                           | recomenda-se ligação local |

Ambiente local macOS:

| Porta | Utilização                                  | Âmbito de publicação |
|-------|----------------------------------------------|----------------------|
| 8086  | ONLYOFFICE Document Server no Apple Container | apenas `127.0.0.1` |
| 8000  | Porta predefinida de `php artisan serve`     | apenas `127.0.0.1` |

Ao utilizar Valet/Herd, a aplicação Laravel funciona com o nome do anfitrião de `APP_URL` e a porta HTTP/HTTPS habitual. A porta do artisan serve é determinada por `APP_URL` ou `MACOS_ARTISAN_PORT`.

## SSL e actualização automática

O Certbot utiliza `/var/www/letsencrypt` como webroot ACME fixo, configura no nginx o certificado do domínio da aplicação sem encaminhar o challenge para o Laravel e disponibiliza também `/onlyoffice/` com o mesmo certificado. O `certbot.timer` verifica periodicamente quando é altura de renovar e, após uma renovação bem-sucedida, faz reload do nginx. Durante a configuração também é executado um dry-run.

O `unattended-upgrades` verifica diariamente a origem de segurança do Ubuntu e actualiza, com as respectivas dependências, `nginx`, `nginx-extras` e os `libnginx-mod-*` correspondentes. Mantém também as actualizações de segurança que não são do nginx. O pocket normal `-updates` não é aplicado automaticamente.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## Operações

### Verificação de processos

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Os workers de Queue executam `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` em 10 processos. Confirme que os 10 se encontram em `RUNNING`.

### Reprocessamento da conversão Markdown

São novamente colocados na fila os ficheiros que falharam e os ficheiros `pending`/`processing` que não tenham sido actualizados durante um determinado período.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

O `files:markdown` não converte os formatos antigos do Office (DOC, XLS, PPT e ODF) para Markdown. As funcionalidades de pré-visualização do ONLYOFFICE para estes formatos continuam disponíveis.

### Actualização da aplicação

Pode executar novamente o `setup.sh` com as mesmas definições. A palavra-passe PostgreSQL existente e a configuração nginx com TLS activo são mantidas, e o Git só é actualizado quando é possível fazer fast-forward.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Cópias de segurança

SQLite:

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /home/ubuntu/chatterrow/database/database.sqlite \
    ".backup /backup/chatterrow-$(date +%F).sqlite"
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

PostgreSQL:

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatterrow > /backup/chatterrow-$(date +%F).dump
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

## Principais variáveis de ambiente

| Variável                         | Descrição                                               |
|----------------------------------|---------------------------------------------------------|
| `APP_URL`                        | URL pública da aplicação                                |
| `DB_CONNECTION`                  | `sqlite` ou `pgsql`                                     |
| `QUEUE_CONNECTION`               | Fila Redis (`redis`)                                    |
| `MARKITDOWN_PATH`                | Caminho da CLI do MarkItDown. Se não for definido, está dentro de `.markitdown/venv` |
| `MARKITDOWN_TIMEOUT`             | Segundos de tempo limite da conversão por ficheiro      |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Versão mínima de Python necessária para o ambiente MarkItDown (3.10) |
| `IMAGEMAGICK_PATH`               | Caminho absoluto de `magick` ou `convert`               |
| `REVERB_APP_ID/KEY/SECRET`       | Credenciais do Reverb                                    |
| `REVERB_HOST/PORT/SCHEME`        | WebSocket público ao qual o browser e o Laravel se ligam |
| `REVERB_SERVER_HOST/PORT`        | Destino interno de escuta do Reverb. A configuração usa `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Domínios públicos autorizados a ligar-se ao Reverb      |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | URL interna do ONLYOFFICE à qual o Laravel se liga (127.0.0.1 no Ubuntu) |
| `ONLYOFFICE_PUBLIC_URL`           | URL pública do ONLYOFFICE visível no browser             |
| `APP_ONLYOFFICE_INTERNAL_URL`    | URL interna da aplicação de onde o ONLYOFFICE obtém os ficheiros |
| `ONLYOFFICE_JWT_SECRET`          | Chave secreta JWT partilhada com o ONLYOFFICE            |

## Resolução de problemas

| Sintoma                              | Verificação                                                                                              |
|--------------------------------------|-----------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                         |
| As actualizações em tempo real não ocorrem | `sudo supervisorctl status chatterrow-reverb`, ligação a `/app/` na Network do browser                 |
| A pré-visualização do anexo não é gerada | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                |
| `exec: convert: not found`           | Depois de obter o caminho absoluto através de `command -v magick` ou `command -v convert`, defina `IMAGEMAGICK_PATH` e execute `php artisan optimize:clear` |
| A conversão Markdown falha           | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`       |
| A fila Redis não é processada        | `redis-cli ping`, confirme que `php8.5 -m` inclui `redis`, e `sudo supervisorctl status 'chatterrow-queue:*'` |
| A pré-visualização do Office não abre (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, chave secreta JWT, URL interna da porta 8090, `php artisan files:previews` |
| A pré-visualização do Office não abre (macOS) | `curl http://127.0.0.1:8086/healthcheck`, chave secreta JWT, `APP_ONLYOFFICE_INTERNAL_URL`, alcance de `/up` a partir do contentor |
| Não é possível ligar ao PostgreSQL   | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                              |
| Não é possível emitir o certificado | A/AAAA do domínio da aplicação, acessibilidade da porta 80 a partir da Internet, `/var/log/letsencrypt/` |

## Desenvolvimento local

```bash
composer install
composer markitdown:install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run dev
```

Inicie o Laravel e o Reverb em terminais separados.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Se o Redis Server não estiver iniciado, no macOS execute `brew services start redis` e, no Ubuntu, execute `sudo systemctl enable --now redis-server`.

Verificação:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## Licença

MIT. Se instalar o ONLYOFFICE Docs Community Edition, consulte também as condições da AGPLv3.
