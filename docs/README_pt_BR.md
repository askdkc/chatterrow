# 茶多楼 chatterrow

## Reunimos no chat os recursos que apoiam o gerenciamento centralizado do trabalho
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


Construído com Laravel 13, Inertia 3 e Svelte 5, é um groupware baseado em projetos com uma interface no estilo do Discord.

Crie canais para cada projeto e gerencie de forma centralizada chats, tarefas, arquivos e gráficos de Gantt. Usa Laravel Reverb para transmissão em tempo real e ONLYOFFICE Document Server para visualizações prévias somente leitura de arquivos do Office.

## Vantagens do groupware on-premises (茶多楼 é o serviço da parte App Server)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Principais recursos

- **Gerenciamento de projetos**: configure nome, conteúdo, data de início, data de término e membros do projeto
- **Canais**: organize conversas, tarefas e arquivos dentro de um projeto por canal
- **Chat em tempo real**: sincronize mensagens, threads e número de respostas com Laravel Reverb
- **Markdown seguro**: escape de HTML, restrição a URLs HTTP(S), destaque de sintaxe de código com Shiki
- **Anexos**: D&D de arquivos/pastas, upload em lotes de 10, miniaturas de imagens, PDF e Office
- **Pré-visualização de arquivos**: visualizador central de imagens e PDF, pré-visualização de Office com ONLYOFFICE, sair com Esc
- **Conversão e armazenamento de Office/PDF em Markdown**: converta em Markdown em segundo plano para facilitar o uso no treinamento de IA
- **Gerenciamento de tarefas**: data de início, horário de início, data de término, horário de término, prioridade, notas e status de conclusão
- **Gráfico de Gantt**: visualização do período por projeto ou canal
- **Lembretes de prazo**: notificações automáticas por scheduler e queue worker
- **Temas**: suporte aos modos escuro e claro
- **Atalhos do teclado**: o envio de mensagens e a criação de tarefas usam `Cmd+Enter` ou `Ctrl+Enter`. O Enter de confirmação do IME não envia a mensagem

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
| Office     | ONLYOFFICE Document Server Community Edition (JWT, somente leitura) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Requisitos de produção

- Ubuntu 24.04 LTS ou Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM e extensão Redis
- Python 3.10 ou superior, MarkItDown 0.1.7, Redis Server
- Usuário comum com acesso ao sudo ou usuário root
- 2 CPUs, 2 GB de RAM e pelo menos 30 GB de espaço livre em disco (a recomendação oficial do ONLYOFFICE é de pelo menos 40 GB)
- Recomenda-se pelo menos 4 GB de swap
- Permitir que os TCP 80/443 sejam acessíveis pela Internet
- Um nome DNS para o aplicativo

Exemplo:

```text
chat.example.com  A/AAAA -> サーバー
```

O ONLYOFFICE será publicado em `/onlyoffice/` no mesmo domínio do aplicativo. Não exponha externamente o ONLYOFFICE, o Reverb nem as portas 8080, 8081 e 8090 usadas para obtenção interna pelo aplicativo. No firewall da nuvem ou do host, permita somente a porta usada pelo SSH e as portas 80/443.

## Configuração automática em ambiente Ubuntu

Em um Ubuntu Server novo, obtenha este repositório e execute `setup.sh`. O domínio, o banco de dados e o endereço de e-mail do Let's Encrypt serão confirmados interativamente. Pode ser executado por um usuário comum ou pelo usuário root.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

Para ambientes locais de desenvolvimento ou validação, redes fechadas e outros casos em que não sejam necessários um domínio público acessível pela Internet e HTTPS, execute com `--no-ssl`. O Let's Encrypt não será usado e somente HTTP será configurado. Ao usar um domínio local como `chatterrow.test`, configure previamente a resolução desse nome para este servidor via DNS ou `/etc/hosts`.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

Ao executar `setup.sh`, a senha do sudo será solicitada. Se você usa um usuário que pode usar sudo sem senha, execute com a opção `--sudo-nopasswd`, como no exemplo abaixo.


```bash
./setup.sh --sudo-nopasswd
```

Se for executado sem opções, quando for detectado um sudo que não exige senha, a configuração não será iniciada; o processo será encerrado exibindo como usar `--sudo-nopasswd`.

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

A URL pública do ONLYOFFICE é `https://<アプリドメイン>/onlyoffice`. No exemplo acima, é `https://chat.example.com/onlyoffice`.

A configuração executa automaticamente o seguinte.

1. Configura o `nginx-extras` oficial do Ubuntu, PHP 8.5, PostgreSQL, Redis, RabbitMQ e Node.js 24
2. Instala extensões do PHP, Poppler, ImageMagick, Ghostscript e fontes japonesas via apt, e define o caminho absoluto do ImageMagick no `.env`
3. Configura o MarkItDown 0.1.7 no ambiente virtual Python `.markitdown/venv` e valida `pip check` e a versão da CLI
4. Ajusta o PostgreSQL de acordo com o número de CPUs e a RAM instalada
5. Ao selecionar PostgreSQL, configura uma role com o mesmo nome do usuário que executa o processo (`DEPLOY_USER`) e com `SUPERUSER LOGIN`
6. Instala o ONLYOFFICE Document Server com JWT habilitado na porta interna 8080 (`ONLYOFFICE_PORT` pode alterá-la)
7. Aplica dependências, frontend e migrações ao repositório clonado
8. Configura pelo nginx o aplicativo, o ONLYOFFICE, o Reverb e a rota interna de download do ONLYOFFICE
9. Mantém pelo Supervisor 10 processos de fila Redis, Reverb e scheduler como `www-data`
10. Emite o certificado com o Certbot e habilita `certbot.timer` e o hook de reload do nginx
11. Aplica diariamente as atualizações de segurança do Ubuntu, incluindo nginx, com `unattended-upgrades`
12. Executa verificações de integridade do PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor e do aplicativo

O nginx usa somente pacotes APT do Ubuntu.

## OnlyOffice local no macOS

No macOS, não instale o pacote do OnlyOffice para Linux; inicie o DocumentServer com o `container` da Apple. É necessário usar Apple silicon e macOS 26 ou posterior.

1. Instale o [Apple Container](https://github.com/apple/container).
2. Instale o ImageMagick com `brew install imagemagick`.
3. Prepare o `.env` do aplicativo Laravel. Se ele não existir, `setup.sh` copiará `.env.example`.
4. Ajuste `APP_URL` para a URL local real.
5. Execute a configuração na raiz do repositório. No macOS, `--domain` e `--database` não são necessários.

```bash
cd /path/to/chatterrow
./setup.sh
```

O `.env` existente não é sobrescrito por completo. Apenas as configurações do ONLYOFFICE abaixo e o caminho absoluto detectado do ImageMagick são atualizados.

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

O `setup.sh` verifica a existência dos comandos `valet` e `herd`, as respostas de `APP_URL/up` e `127.0.0.1:<ポート>/up`, e escolhe o método de conexão. Quando tanto o lado do Valet/Herd quanto o lado do artisan serve respondem, o lado do Valet/Herd de `APP_URL` tem prioridade.

| Servidor de desenvolvimento | Exemplo de `.env`: `APP_URL` | `APP_ONLYOFFICE_INTERNAL_URL` definida automaticamente |
|-----------------------------|--------------------------------|---------------------------------------------------------|
| Laravel Valet               | `http://chatterrow.test`        | `http://chatterrow.test`                                |
| Laravel Herd                | `http://chatterrow.test`        | `http://chatterrow.test`                                |
| `php artisan serve`         | `http://localhost:8000`         | `http://chatter-host.container.internal:8000`           |

Ao usar artisan serve, inicie-o antes da configuração ou antes de abrir a pré-visualização.

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

A configuração faz o seguinte.

- Inicia o Apple Container com `container system start`
- Faz pull de `onlyoffice/documentserver:latest` todas as vezes e inicia em arm64
- Publica o OnlyOffice em `127.0.0.1:8086`
- Atribui 4 CPUs, 4 GB de memória e 2 GB de memória compartilhada
- Fixa o servidor DNS do contêiner em `1.1.1.1`
- Habilita JWT e define a chave secreta compartilhada no `.env`
- Cada execução do `setup.sh` no macOS recria o contêiner do OnlyOffice, mantendo os volumes nomeados
- Conecta `chatter-host.container.internal` ao loopback do macOS por meio de `203.0.113.150`
- No Valet/Herd, atribui o nome do host do aplicativo a `203.0.113.150` somente dentro do contêiner
- Baixa e valida Source Han Sans JP / Noto Serif CJK JP e gera novamente o catálogo de fontes do OnlyOffice
- Atualiza `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL` e `APP_ONLYOFFICE_INTERNAL_URL`
- Verifica a integridade de `/up` do DocumentServer e do Laravel

Para substituir o servidor DNS, informe uma variável de ambiente. O valor deve ser um endereço IPv4.

Exemplo alterando o 1.1.1.1 da Cloudflare para o 8.8.8.8 do Google:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

Os seguintes volumes nomeados são usados para os dados persistentes.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Verificação e parada do contêiner:

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

### Teste de inicialização completa do contêiner e dos volumes

As operações a seguir excluem o PostgreSQL interno do OnlyOffice, as configurações, o cache e os logs. Não as execute se houver dados necessários.

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

[Os volumes nomeados do Apple Container](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) são criados implicitamente quando não existem por `container run --volume <名前>:<パス>`, portanto o procedimento acima permite verificar a configuração inicial, incluindo a criação dos volumes.

### Fontes japonesas (macOS)

Mesmo que fontes sejam instaladas no host macOS, elas não podem ser usadas pelo contêiner isolado do OnlyOffice. O `setup.sh` baixa as fontes de peso fixo dos repositórios oficiais em versões fixas, verifica o SHA-256 e só então as registra no OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, OTF do subconjunto JP)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, OTF japonês)

As fontes são salvas dentro do volume `chatterrow-onlyoffice-data` em `/var/www/onlyoffice/Data/custom-fonts`. Ao executar a configuração novamente, o SHA-256 dos arquivos no contêiner é verificado e o download não é repetido se o valor corresponder.

Após registrar as versões de peso fixo, o `setup.sh` remove os `AllFonts.js` e `font_selection.bin` existentes e executa `allfontsgen`. Se os arquivos existentes forem mantidos, o `allfontsgen` poderá reutilizar o catálogo e não registrar as novas fontes.

O converter do OnlyOffice 9.4 pode substituir as fontes de tema japonesas do Microsoft Office por `NanumGothic` ou `Droid Sans Fallback`. Os aliases do fontconfig não têm efeito nesse caminho de conversão. Por isso, [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) aplica as correções a seguir ao `font_selection.bin` do servidor e aos dois `AllFonts.js` do navegador.

- Registra os aliases de 游ゴシック, Yu Gothic, Meiryo e MS Gothic no Source Han Sans JP
- Registra os aliases de 游明朝, Yu Mincho e MS Mincho no Noto Serif CJK JP
- Altera a referência da fonte real `NanumGothic` escolhida pelo converter para Source Han Sans JP
- Altera a referência da fonte real `Droid Sans Fallback` escolhida pelo converter para Noto Serif CJK JP

Esse catálogo é compartilhado pela conversão de DOCX, XLSX e PPTX e pela exibição no navegador. Após as correções, o cache de JS é gerado, o docservice e o converter são reiniciados e o cache do DocumentServer é limpo. Se o formato do catálogo ou os nomes de fontes obrigatórios mudarem em uma futura versão `latest`, a configuração será encerrada com erro sem usar um catálogo incorreto.

O `documentserver-generate-allfonts.sh` padrão não é chamado porque ele também regenera temas de apresentação desnecessários e, no ambiente Apple Container, essa etapa pode não terminar.

Ao atualizar o catálogo de fontes, a geração do cache de documentos do OnlyOffice também muda; portanto, DOCX já abertos são convertidos novamente, sem reutilizar um `Editor.bin` antigo.

Mesmo que um contêiner existente pare de responder em `Generating presentation themes`, execute novamente `./setup.sh` sem alterações. No macOS, cada execução faz pull de `onlyoffice/documentserver:latest` e recria à força o contêiner existente do OnlyOffice. Os quatro volumes nomeados acima não são excluídos, portanto os dados persistentes do OnlyOffice são mantidos.

As fontes 游明朝／游ゴシック da Microsoft não são incluídas nem redistribuídas. Dentro do contêiner do OnlyOffice, são usadas as seguintes fontes alternativas.

| Fonte especificada pelo Office (DOCX / XLSX / PPTX) | Fonte alternativa       |
|------------------------------------------------------|-------------------------|
| 游明朝 / Yu Mincho / MS Mincho                       | Noto Serif CJK JP       |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic          | Source Han Sans JP      |

Isso elimina caracteres ausentes e a substituição por fontes latinas inadequadas, mas, como há diferença na largura dos caracteres em relação às fontes 游, não há garantia de correspondência completa das quebras de linha ou do número de páginas. Se for necessária correspondência exata, coloque separadamente os arquivos reais das fontes 游 na área de fontes personalizadas do OnlyOffice, após confirmar a licença de uso.

Verificação do estado do registro:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

Os valores esperados são, nessa ordem, `1.1.1.1`, `NotoSerifCJKjp-Regular.otf` e `SourceHanSansJP-Light.otf`.

No macOS, o OnlyOffice não recebe permissão de edição e continua como pré-visualização ReadOnly. A API de conversão do DocumentServer também pode ser usada independentemente dessa configuração ReadOnly.

## Configuração não interativa

Em ambientes de automação do Ubuntu, `--domain` e `--database` são obrigatórios. Eles não são necessários na configuração local do OnlyOffice no macOS.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Opções

| Opção                        | Padrão                               | Descrição                                             |
|------------------------------|--------------------------------------|-------------------------------------------------------|
| `--domain <domain>`          | entrada interativa                   | Domínio público do aplicativo                         |
| `--email <email>`            | vazio                                | E-mail de registro e de aviso de expiração do Let's Encrypt |
| `--database <driver>`        | `sqlite` durante a interação         | `sqlite` ou `postgresql`                               |
| `--db-name <name>`           | `chatterrow`                         | Nome do DB PostgreSQL do aplicativo                    |
| `--db-user <name>`           | `chatterrow`                         | Role PostgreSQL do aplicativo                          |
| `--db-password <password>`   | gerada automaticamente               | Senha do PostgreSQL do aplicativo                      |
| `--app-dir <path>`           | repositório onde está `setup.sh`     | Local de implantação sob `/home` ou `/var/www`         |
| `--repo <url>`               | URL SSH do GitHub                    | Repositório Git a ser implantado                       |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest`   | Imagem do DocumentServer usada após pull a cada vez no macOS |
| `--sudo-nopasswd`            | desativado                           | Para usuários sudo sem senha. Omite `sudo -v`          |
| `--no-ssl`                   | desativado                           | Omite o Certbot e configura com HTTP                   |

Também podem ser usadas as variáveis de ambiente em maiúsculas com o mesmo nome. Exemplos: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

Se a senha do PostgreSQL for omitida, um valor aleatório de 64 caracteres será gerado e salvo nos locais a seguir.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

Ao selecionar PostgreSQL, a role do PostgreSQL com o mesmo nome do usuário que executa o processo (`DEPLOY_USER`; quando não informado, `id -un` na execução por usuário comum ou `root` na execução como root) é criada ou atualizada como `LOGIN SUPERUSER`. O `DB_USER` usado pela conexão do aplicativo é mantido como uma role sem privilégios.

## Ajuste automático do PostgreSQL

Como o PostgreSQL também é necessário para o ONLYOFFICE, ele é instalado e ajustado mesmo quando o aplicativo escolhe SQLite. Se houver vários clusters PostgreSQL no Ubuntu, a configuração será interrompida para evitar alterar o cluster errado.

Arquivo de configuração:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Principais critérios de cálculo:

| Configuração          | Critério                                            |
|-----------------------|-----------------------------------------------------|
| `shared_buffers`       | 20% da RAM, entre 128 MB e 8 GB                     |
| `effective_cache_size` | 60% da RAM, entre 256 MB e 64 GB                    |
| `maintenance_work_mem` | 5% da RAM, entre 64 MB e 1 GB                       |
| `work_mem`             | Calculado de forma conservadora a partir da RAM, de `shared_buffers` e do número máximo de conexões |
| `max_connections`      | Calculado a partir de CPU e RAM, entre 50 e 300     |
| parallel workers       | Calculados a partir do número de CPUs, com limite definido |

Como este servidor também hospeda PHP, ONLYOFFICE, Redis e RabbitMQ, a alocação é mais conservadora que em um servidor dedicado ao PostgreSQL. Ao executar novamente, os valores são recalculados com base no número atual de CPUs e na RAM.

## Configuração de portas

Ambiente de produção Ubuntu:

| Porta    | Uso                                  | Escopo de publicação |
|----------|--------------------------------------|----------------------|
| 80 / 443 | nginx, Certbot e Web pública         | Internet             |
| 8080     | ONLYOFFICE Document Server           | voltada ao localhost |
| 8081     | Laravel Reverb                       | voltada ao localhost |
| 8090     | Obter arquivos assinados do ONLYOFFICE | somente 127.0.0.1  |
| 5432     | PostgreSQL                           | recomenda-se conexão local |

Ambiente local macOS:

| Porta | Uso                                           | Escopo de publicação |
|-------|-----------------------------------------------|----------------------|
| 8086  | ONLYOFFICE Document Server no Apple Container | somente `127.0.0.1` |
| 8000  | Porta padrão do `php artisan serve`           | somente `127.0.0.1` |

Ao usar Valet/Herd, o aplicativo Laravel funciona com o nome do host de `APP_URL` e a porta HTTP/HTTPS normal. A porta do artisan serve é determinada por `APP_URL` ou `MACOS_ARTISAN_PORT`.

## SSL e atualização automática

O Certbot usa `/var/www/letsencrypt` como webroot ACME fixo, configura no nginx o certificado do domínio do aplicativo sem encaminhar o challenge ao Laravel e também serve `/onlyoffice/` com o mesmo certificado. O `certbot.timer` verifica periodicamente o momento da renovação e, após uma renovação bem-sucedida, recarrega o nginx. Um dry-run também é executado durante a configuração.

O `unattended-upgrades` verifica diariamente a origem de segurança do Ubuntu e atualiza `nginx`, `nginx-extras` e os `libnginx-mod-*` correspondentes com suas dependências. Ele também mantém as atualizações de segurança que não são do nginx. O pocket normal `-updates` não é aplicado automaticamente.

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

Os workers de Queue executam `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` em 10 processos. Verifique se todos os 10 estão em `RUNNING`.

### Reprocessamento da conversão para Markdown

Arquivos que falharam e arquivos `pending`/`processing` que não foram atualizados por determinado período são colocados novamente na fila.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

O `files:markdown` não converte formatos antigos do Office (DOC, XLS, PPT e ODF) para Markdown. As funções de pré-visualização do ONLYOFFICE para esses formatos continuam disponíveis.

### Atualização do aplicativo

É possível executar o `setup.sh` novamente com as mesmas configurações. A senha existente do PostgreSQL e a configuração do nginx com TLS habilitado são preservadas, e o Git só é atualizado quando um fast-forward é possível.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Backup

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
| `APP_URL`                        | URL pública do aplicativo                               |
| `DB_CONNECTION`                  | `sqlite` ou `pgsql`                                     |
| `QUEUE_CONNECTION`               | Fila Redis (`redis`)                                    |
| `MARKITDOWN_PATH`                | Caminho da CLI do MarkItDown. Se não definido, fica em `.markitdown/venv` |
| `MARKITDOWN_TIMEOUT`             | Segundos de timeout da conversão por arquivo            |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Versão mínima do Python necessária no ambiente MarkItDown (3.10) |
| `IMAGEMAGICK_PATH`               | Caminho absoluto de `magick` ou `convert`               |
| `REVERB_APP_ID/KEY/SECRET`       | Credenciais do Reverb                                   |
| `REVERB_HOST/PORT/SCHEME`        | WebSocket público ao qual o navegador e o Laravel se conectam |
| `REVERB_SERVER_HOST/PORT`        | Destino interno de listen do Reverb. A configuração usa `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Domínios públicos autorizados a se conectar ao Reverb   |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | URL interna do ONLYOFFICE à qual o Laravel se conecta (127.0.0.1 no Ubuntu) |
| `ONLYOFFICE_PUBLIC_URL`           | URL pública do ONLYOFFICE visível no navegador          |
| `APP_ONLYOFFICE_INTERNAL_URL`    | URL interna do aplicativo de onde o ONLYOFFICE obtém arquivos |
| `ONLYOFFICE_JWT_SECRET`          | Chave secreta JWT compartilhada com o ONLYOFFICE         |

## Solução de problemas

| Sintoma                              | Verificação                                                                                              |
|--------------------------------------|-----------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                         |
| Atualizações em tempo real não ocorrem | `sudo supervisorctl status chatterrow-reverb`, conexão `/app/` na Network do navegador                 |
| A pré-visualização do anexo não é gerada | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                |
| `exec: convert: not found`           | Depois de obter o caminho absoluto com `command -v magick` ou `command -v convert`, defina `IMAGEMAGICK_PATH` e execute `php artisan optimize:clear` |
| Falha na conversão para Markdown     | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`       |
| A fila Redis não é processada        | `redis-cli ping`, verifique se `php8.5 -m` inclui `redis`, e `sudo supervisorctl status 'chatterrow-queue:*'` |
| A pré-visualização do Office não abre (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, chave secreta JWT, URL interna da porta 8090, `php artisan files:previews` |
| A pré-visualização do Office não abre (macOS) | `curl http://127.0.0.1:8086/healthcheck`, chave secreta JWT, `APP_ONLYOFFICE_INTERNAL_URL`, alcance de `/up` a partir do contêiner |
| Não é possível conectar ao PostgreSQL | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                              |
| Não é possível emitir o certificado | A/AAAA do domínio do aplicativo, acessibilidade da porta 80 pela Internet, `/var/log/letsencrypt/`     |

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

Se o Redis Server não estiver iniciado, no macOS execute `brew services start redis`; no Ubuntu, execute `sudo systemctl enable --now redis-server`.

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

MIT. Ao instalar o ONLYOFFICE Docs Community Edition, verifique também as condições da AGPLv3.
