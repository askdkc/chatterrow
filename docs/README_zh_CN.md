# 茶多楼 chatterrow

## 将支持业务集中管理的功能都融入聊天中
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


这是一个使用 Laravel 13、Inertia 3、Svelte 5 构建的 Discord 风格 UI 项目型群件。

可以按项目创建频道，集中管理聊天、任务、文件和甘特图。实时传输使用 Laravel Reverb，Office 文件的只读预览使用 ONLYOFFICE Document Server。

## 本地部署群件的优势（茶多楼提供 App Server 部分的服务）
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## 主要功能

- **项目管理**：设置项目名称、内容、开始日期、结束日期和成员
- **频道**：按频道整理项目内的对话、任务和文件
- **实时聊天**：通过 Laravel Reverb 同步消息、线程和回复数
- **安全的 Markdown**：HTML 转义、HTTP(S) URL 限制、Shiki 代码高亮
- **附件**：文件／文件夹 D&D、每 10 个一批上传、图片・PDF・Office 缩略图
- **文件预览**：图片・PDF 的中央查看器、使用 ONLYOFFICE 进行 Office 预览、按 Esc 退出
- **Office/PDF 的 Markdown 转换保存**：在后台转换为 Markdown，使其更便于用于 AI 学习
- **任务管理**：开始日期・开始时间・结束日期・结束时间、优先级、备注、完成状态
- **甘特图**：按项目或频道显示时间范围
- **截止日期提醒**：通过调度器和队列工作进程自动通知
- **主题**：支持深色／浅色模式
- **键盘操作**：发送消息和创建任务使用 `Cmd+Enter` 或 `Ctrl+Enter`。IME 确认用的 Enter 不会发送

## 技术构成

| 层级       | 技术                                                          |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite 或 PostgreSQL                                          |
| Realtime   | Laravel Reverb（WebSocket）                                   |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7（PDF / DOCX / XLSX / PPTX）        |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition（JWT、只读）     |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot         |

## 生产环境要求

- Ubuntu 24.04 LTS 或 Ubuntu 26.04 LTS（amd64）
- PHP 8.5 CLI/FPM 和 Redis 扩展
- Python 3.10 以上、MarkItDown 0.1.7、Redis Server
- 可以使用 sudo 的普通用户，或 root 用户
- 2 CPU、2 GB RAM、至少 30 GB 可用磁盘（ONLYOFFICE 官方建议至少 40 GB）
- 建议使用至少 4 GB swap
- 允许互联网访问 TCP 80/443
- 用于应用的 DNS 名称

示例：

```text
chat.example.com  A/AAAA -> 服务器
```

ONLYOFFICE 将通过与应用相同域名下的 `/onlyoffice/` 发布。请勿将 ONLYOFFICE、Reverb、供应用内部获取使用的 8080、8081、8090 端口对外开放。在云防火墙或主机侧防火墙中，仅允许 SSH 使用的端口以及 80/443。

## Ubuntu 环境自动设置

在全新的 Ubuntu Server 上获取本仓库并执行 `setup.sh`。系统会以交互方式确认域名、数据库和 Let's Encrypt 电子邮件地址。可以使用普通用户或 root 用户执行。

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

如果是本地开发・验证环境或内网等不需要互联网可访问的公开域名和 HTTPS 的场景，请添加 `--no-ssl` 执行。不使用 Let's Encrypt，仅配置 HTTP。使用 `chatterrow.test` 等本地域名时，请事先通过 DNS 或 `/etc/hosts` 使其能够解析到此服务器。

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

执行 `setup.sh` 时会询问 sudo 密码。对于无需 sudo 密码即可使用 sudo 的用户，请按照以下示例添加 `--sudo-nopasswd` 选项执行。


```bash
./setup.sh --sudo-nopasswd
```

如果不带任何选项执行，而检测到无需密码的 sudo，程序不会开始设置，而是显示 `--sudo-nopasswd` 的使用方法后退出。

执行 `setup.sh` 时的输入示例：

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
（省略）
PostgreSQL password (leave blank to generate):
```

ONLYOFFICE 的公开 URL 是 `https://<アプリドメイン>/onlyoffice`。以上例子中为 `https://chat.example.com/onlyoffice`。

设置会自动执行以下操作。

1. 配置 Ubuntu 官方的 `nginx-extras`、PHP 8.5、PostgreSQL、Redis、RabbitMQ、Node.js 24
2. 通过 apt 安装 PHP 扩展、Poppler、ImageMagick、Ghostscript、日语字体，并将 ImageMagick 的绝对路径设置到 `.env`
3. 在 Python 虚拟环境 `.markitdown/venv` 中构建 MarkItDown 0.1.7，并验证 `pip check` 和 CLI 版本
4. 根据 CPU 数量和安装的 RAM 调整 PostgreSQL
5. 选择 PostgreSQL 时，配置与执行用户（`DEPLOY_USER`）同名的 `SUPERUSER LOGIN` 角色
6. 启用 JWT、使用内部 8080 端口（可通过 `ONLYOFFICE_PORT` 更改）部署 ONLYOFFICE Document Server
7. 为已克隆的仓库应用依赖项、前端和迁移
8. 使用 nginx 配置应用、ONLYOFFICE、Reverb 和 ONLYOFFICE 内部下载路径
9. 使用 Supervisor 让 10 个 Redis 队列进程、Reverb 和调度器以 `www-data` 身份常驻运行
10. 使用 Certbot 签发证书，并启用 `certbot.timer` 和 nginx reload hook
11. 使用 `unattended-upgrades` 每日应用包括 nginx 在内的 Ubuntu 安全更新
12. 执行 PHP 8.5、Redis、PostgreSQL、ONLYOFFICE、Supervisor 和应用的健康检查

nginx 仅使用 Ubuntu 的 APT 软件包。

## macOS 本地 OnlyOffice

在 macOS 上不安装 Linux 版 OnlyOffice 软件包，而是使用 Apple 的 `container` 启动 DocumentServer。需要 Apple silicon 和 macOS 26 或更高版本。

1. 安装 [Apple Container](https://github.com/apple/container)。
2. 使用 `brew install imagemagick` 安装 ImageMagick。
3. 准备 Laravel 应用的 `.env`。如果不存在，`setup.sh` 会复制 `.env.example`。
4. 根据实际本地 URL 设置 `APP_URL`。
5. 在仓库根目录执行设置。macOS 不需要 `--domain` 和 `--database`。

```bash
cd /path/to/chatterrow
./setup.sh
```

不会覆盖现有的整个 `.env`。更新目标是以下 ONLYOFFICE 设置以及检测到的 ImageMagick 绝对路径。

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

### macOS 开发时：自动判断 Valet、Herd、artisan serve

`setup.sh` 会检查是否存在 `valet` 和 `herd` 命令、`APP_URL/up` 以及 `127.0.0.1:<ポート>/up` 的响应，并选择连接方式。如果 Valet/Herd 侧和 artisan serve 侧都能响应，则优先使用 `APP_URL` 的 Valet/Herd 侧。

| 开发服务器          | `.env` 中的 `APP_URL` 示例       | 自动设置的 `APP_ONLYOFFICE_INTERNAL_URL`   |
|---------------------|----------------------------------|--------------------------------------------|
| Laravel Valet       | `http://chatterrow.test`         | `http://chatterrow.test`                   |
| Laravel Herd        | `http://chatterrow.test`         | `http://chatterrow.test`                   |
| `php artisan serve`  | `http://localhost:8000`          | `http://chatter-host.container.internal:8000` |

使用 artisan serve 时，请在设置前或打开预览前启动它。

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

覆盖自动判断时：

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

设置会执行以下操作。

- 通过 `container system start` 启动 Apple Container
- 每次 pull `onlyoffice/documentserver:latest`，并以 arm64 启动
- 将 OnlyOffice 发布到 `127.0.0.1:8086`
- 分配 CPU 4、内存 4 GB、共享内存 2 GB
- 将容器的 DNS 服务器固定为 `1.1.1.1`
- 启用 JWT，并将共享密钥设置到 `.env`
- 每次在 macOS 上执行 `setup.sh` 时，保留命名卷并重新创建 OnlyOffice 容器
- 通过 `203.0.113.150` 将 `chatter-host.container.internal` 连接到 macOS 的 loopback
- 在 Valet/Herd 中，仅在容器内将应用主机名指向 `203.0.113.150`
- 获取并验证 Source Han Sans JP／Noto Serif CJK JP，然后重新生成 OnlyOffice 的字体列表
- 更新 `ONLYOFFICE_DOCUMENT_SERVER_URL`、`ONLYOFFICE_PUBLIC_URL`、`APP_ONLYOFFICE_INTERNAL_URL`
- 对 DocumentServer 和 Laravel 的 `/up` 执行健康检查

如果要覆盖 DNS 服务器，请指定环境变量。值请使用 IPv4 地址。

从 Cloudflare 的 1.1.1.1 改为 Google 的 8.8.8.8 执行示例：
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

持久化数据使用以下命名卷。

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

检查・停止容器：

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

健康检查：

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### 容器和卷的完全初始化测试

以下操作会删除 OnlyOffice 内部的 PostgreSQL、设置、缓存和日志。如果存在重要数据，请勿执行。

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

[Apple Container 的命名卷](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management)在不存在时会通过 `container run --volume <名前>:<パス>` 隐式创建，因此可以通过以上步骤确认包括卷创建在内的初始构建。

### 日语字体（macOS）

即使将字体安装到 macOS 主机，也无法从隔离的 OnlyOffice 容器中使用。`setup.sh` 会从官方仓库下载以下固定字重字体的固定版本，在验证 SHA-256 后注册到 OnlyOffice。

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold（2.005R、JP subset OTF）
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold（Serif2.003、Japanese OTF）

字体会保存到 `chatterrow-onlyoffice-data` 卷内的 `/var/www/onlyoffice/Data/custom-fonts`。重新运行设置时，会检查容器内文件的 SHA-256；如果一致则不会重新下载。

注册固定字重版本后，`setup.sh` 会先删除现有的 `AllFonts.js` 和 `font_selection.bin`，再执行 `allfontsgen`。如果保留现有文件，`allfontsgen` 可能会复用目录而不注册新字体。

OnlyOffice 9.4 的 converter 可能会将 Microsoft Office 的日语主题字体替换为 `NanumGothic` 或 `Droid Sans Fallback`。仅使用 fontconfig alias 无法影响这条转换路径。因此，[scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) 会对服务器端的 `font_selection.bin` 和浏览器端的两个 `AllFonts.js` 应用以下修正。

- 将游ゴシック、Yu Gothic、Meiryo、MS Gothic 系列的别名注册到 Source Han Sans JP
- 将游明朝、Yu Mincho、MS Mincho 系列的别名注册到 Noto Serif CJK JP
- 将 converter 选择的 `NanumGothic` 实际字体引用改为 Source Han Sans JP
- 将 converter 选择的 `Droid Sans Fallback` 实际字体引用改为 Noto Serif CJK JP

DOCX、XLSX、PPTX 的转换和浏览器显示共用此字体目录。修正后会生成 JS 缓存，重启 docservice 和 converter，然后清除 DocumentServer 的缓存。如果未来的 `latest` 更改了目录格式或必需的字体名称，则不会使用错误的目录，而是以错误结束设置。

不会调用标准的 `documentserver-generate-allfonts.sh`，因为它还会重新生成不必要的演示文稿主题，而在 Apple Container 环境中该步骤可能无法结束。

更新字体目录时，OnlyOffice 的文档缓存世代也会改变，因此已经打开的 DOCX 不会重新使用旧的 `Editor.bin`，而会重新转换。

如果现有容器在 `Generating presentation themes` 处不再响应，也请直接重新执行 `./setup.sh`。在 macOS 上，每次执行都会 pull `onlyoffice/documentserver:latest`，并强制重新创建现有的 OnlyOffice 容器。不会删除上述 4 个命名卷，因此 OnlyOffice 的持久化数据会保留。

不附带或再分发 Microsoft 的游明朝／游ゴシック。在 OnlyOffice 容器内使用以下替代设置。

| Office 指定字体（DOCX / XLSX / PPTX）          | 替代字体           |
|---------------------------------------------|--------------------|
| 游明朝 / Yu Mincho / MS Mincho              | Noto Serif CJK JP  |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic | Source Han Sans JP |

这可以解决缺字和被替换为不合适西文字体的问题，但由于与游字体存在字宽差异，无法保证换行位置和页数完全一致。如果需要完全一致，请确认使用许可后，将游字体的实际文件另行放置到 OnlyOffice 的自定义字体区域。

确认注册状态：

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

预期值依次为 `1.1.1.1`、`NotoSerifCJKjp-Regular.otf`、`SourceHanSansJP-Light.otf`。

在 macOS 上不授予 OnlyOffice 编辑权限，保持只读预览。即使使用 DocumentServer 的转换 API，也可以独立于此只读设置使用。

## 非交互式设置

在 Ubuntu 自动化环境中，`--domain` 和 `--database` 是必需的。macOS 本地 OnlyOffice 设置不需要它们。

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### 选项

| 选项                        | 默认值                             | 说明                                              |
|----------------------------|------------------------------------|---------------------------------------------------|
| `--domain <domain>`          | 交互输入                           | 应用的公开域名                                    |
| `--email <email>`            | 空                                 | Let's Encrypt 注册及到期通知邮件                 |
| `--database <driver>`        | 交互时为 `sqlite`                  | `sqlite` 或 `postgresql`                          |
| `--db-name <name>`           | `chatterrow`                       | 应用用 PostgreSQL DB 名称                         |
| `--db-user <name>`           | `chatterrow`                       | 应用用 PostgreSQL 角色                            |
| `--db-password <password>`   | 自动生成                           | 应用用 PostgreSQL 密码                            |
| `--app-dir <path>`           | `setup.sh` 所在的仓库              | `/home` 或 `/var/www` 下的部署目标                |
| `--repo <url>`               | GitHub SSH URL                     | 要部署的 Git 仓库                                 |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | macOS 上每次 pull 并使用的 DocumentServer 镜像    |
| `--sudo-nopasswd`            | off                                | 面向无密码 sudo 用户。省略 `sudo -v`              |
| `--no-ssl`                   | off                                | 省略 Certbot，以 HTTP 配置                        |

也可以使用同名的大写环境变量。例如：`DOMAIN`、`DATABASE`、`DB_NAME`、`DB_USER`、`DB_PASSWORD`、`DEPLOY_USER`、`SUDO_NOPASSWD`、`ONLYOFFICE_PORT`、`ONLYOFFICE_JWT_SECRET`。

省略 PostgreSQL 密码时，会生成 64 位随机值并保存到以下位置。

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

选择 PostgreSQL 时，会将与执行用户（`DEPLOY_USER`；未指定时，普通用户执行则为 `id -un`，root 执行则为 `root`）同名的 PostgreSQL 角色创建或更新为 `LOGIN SUPERUSER`。应用连接使用的 `DB_USER` 会保持为另一个非特权角色。

## PostgreSQL 自动调整

由于 ONLYOFFICE 也需要 PostgreSQL，即使应用选择 SQLite，也会安装并调整 PostgreSQL。如果 Ubuntu 上存在多个 PostgreSQL 集群，为避免修改错误的集群，设置会停止执行。

配置文件：

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

主要计算基准：

| 设置                   | 基准                                              |
|------------------------|---------------------------------------------------|
| `shared_buffers`       | RAM 的 20%，范围为 128 MB 至 8 GB                 |
| `effective_cache_size` | RAM 的 60%，范围为 256 MB 至 64 GB                |
| `maintenance_work_mem` | RAM 的 5%，范围为 64 MB 至 1 GB                   |
| `work_mem`             | 根据 RAM、`shared_buffers`、最大连接数以安全侧计算 |
| `max_connections`      | 根据 CPU 和 RAM 在 50 至 300 的范围内计算          |
| parallel workers       | 根据 CPU 数量计算并设置上限                       |

由于此服务器还同时运行 PHP、ONLYOFFICE、Redis、RabbitMQ，因此分配比 PostgreSQL 专用服务器更保守。重新执行时，会根据当前 CPU 数量和 RAM 重新计算。

## 端口配置

Ubuntu 生产环境：

| 端口     | 用途                                 | 公开范围           |
|----------|--------------------------------------|--------------------|
| 80 / 443 | nginx、Certbot、公开 Web              | 互联网             |
| 8080     | ONLYOFFICE Document Server           | 面向 localhost      |
| 8081     | Laravel Reverb                       | 面向 localhost      |
| 8090     | 从 ONLYOFFICE 获取已签名文件          | 仅限 127.0.0.1      |
| 5432     | PostgreSQL                           | 建议本地连接        |

macOS 本地环境：

| 端口 | 用途                                          | 公开范围        |
|------|-----------------------------------------------|-----------------|
| 8086 | Apple Container 上的 ONLYOFFICE Document Server | 仅限 `127.0.0.1` |
| 8000 | `php artisan serve` 的默认端口                 | 仅限 `127.0.0.1` |

使用 Valet/Herd 时，Laravel 应用通过 `APP_URL` 的主机名和通常的 HTTP/HTTPS 端口运行。artisan serve 的端口由 `APP_URL` 或 `MACOS_ARTISAN_PORT` 决定。

## SSL 和自动更新

Certbot 使用 `/var/www/letsencrypt` 作为固定的 ACME webroot，将 challenge 设置到 nginx 而不交给 Laravel，并为应用域名配置证书。`/onlyoffice/` 也使用同一证书提供服务。`certbot.timer` 会定期检查更新时间，更新成功后 reload nginx。设置时也会执行 dry-run。

`unattended-upgrades` 每日检查 Ubuntu 的 security origin，并连同依赖关系一起更新 `nginx`、`nginx-extras` 以及对应的 `libnginx-mod-*`。也会保持 nginx 之外的安全更新。普通的 `-updates` pocket 不会自动应用。

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## 运营

### 进程确认

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queue 工作进程以 10 个进程执行 `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600`。请确认全部 10 个都处于 `RUNNING` 状态。

### Markdown 转换重新处理

重新提交失败的文件，以及长时间未更新的 `pending`／`processing` 文件。

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` 不会将旧版 Office 格式（DOC、XLS、PPT、ODF）作为 Markdown 转换对象。这些文件仍可继续使用 ONLYOFFICE 预览功能。

### 应用更新

可以使用相同的设置重新执行 `setup.sh`。现有的 PostgreSQL 密码和已启用 TLS 的 nginx 配置会保留，Git 仅在可以 fast-forward 时更新。

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### 备份

SQLite：

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /home/ubuntu/chatterrow/database/database.sqlite \
    ".backup /backup/chatterrow-$(date +%F).sqlite"
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

PostgreSQL：

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatterrow > /backup/chatterrow-$(date +%F).dump
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

## 主要环境变量

| 变量                             | 说明                                                   |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | 应用的公开 URL                                          |
| `DB_CONNECTION`                  | `sqlite` 或 `pgsql`                                     |
| `QUEUE_CONNECTION`               | Redis 队列（`redis`）                                   |
| `MARKITDOWN_PATH`                | MarkItDown CLI 路径。未指定时位于 `.markitdown/venv` 内 |
| `MARKITDOWN_TIMEOUT`             | 每个文件的转换超时秒数                                  |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | MarkItDown 环境所需的最低 Python 版本（3.10）           |
| `IMAGEMAGICK_PATH`               | `magick` 或 `convert` 的绝对路径                        |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb 认证信息                                         |
| `REVERB_HOST/PORT/SCHEME`        | 浏览器和 Laravel 连接的公开 WebSocket                  |
| `REVERB_SERVER_HOST/PORT`        | Reverb 的内部监听地址。设置为 `127.0.0.1:8081`          |
| `REVERB_ALLOWED_ORIGINS`         | 允许连接到 Reverb 的公开域名                            |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Laravel 连接的 ONLYOFFICE 内部 URL（Ubuntu 中为 127.0.0.1） |
| `ONLYOFFICE_PUBLIC_URL`           | 浏览器可见的 ONLYOFFICE 公开 URL                         |
| `APP_ONLYOFFICE_INTERNAL_URL`    | ONLYOFFICE 获取文件时使用的内部应用 URL                 |
| `ONLYOFFICE_JWT_SECRET`          | 与 ONLYOFFICE 共享的 JWT 密钥                            |

## 问题排查

| 症状                                 | 检查                                                                                                              |
|--------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`、`sudo nginx -t`                                                                 |
| 不进行实时更新                       | `sudo supervisorctl status chatterrow-reverb`、浏览器 Network 中的 `/app/` 连接                                  |
| 无法生成附件预览                     | `/var/log/chatterrow-queue-error_*.log`、ONLYOFFICE/Poppler/ImageMagick                                           |
| `exec: convert: not found`            | 将 `command -v magick` 或 `command -v convert` 的绝对路径设置到 `IMAGEMAGICK_PATH` 后执行 `php artisan optimize:clear` |
| Markdown 转换失败                    | `storage/logs/laravel.log`、`/var/log/chatterrow-queue-error_*.log`、`php artisan files:markdown`                 |
| Redis 队列不处理                     | `redis-cli ping`、`php8.5 -m` 中的 `redis`、`sudo supervisorctl status 'chatterrow-queue:*'`                      |
| Office 预览无法打开（Ubuntu）        | `curl http://127.0.0.1:8080/healthcheck`、JWT 密钥、8090 内部 URL、`php artisan files:previews`                  |
| Office 预览无法打开（macOS）         | `curl http://127.0.0.1:8086/healthcheck`、JWT 密钥、`APP_ONLYOFFICE_INTERNAL_URL`、从容器内访问 `/up` 的连通性 |
| 无法连接 PostgreSQL                  | `.env`、`sudo -u postgres pg_isready`、`/etc/chatterrow/database-password`                                        |
| 无法签发证书                         | 应用域名的 A/AAAA、从互联网访问 80 端口的连通性、`/var/log/letsencrypt/`                                         |

## 本地开发

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

在另一个终端启动 Laravel 和 Reverb。

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

如果 Redis Server 未启动，请在 macOS 上执行 `brew services start redis`，在 Ubuntu 上执行 `sudo systemctl enable --now redis-server`。

验证：

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## 许可证

MIT。导入 ONLYOFFICE Docs Community Edition 时，也请确认 AGPLv3 的条款。
