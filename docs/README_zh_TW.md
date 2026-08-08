# 茶多楼 chatterrow

## 將支援業務集中管理的功能都融入聊天中
<img width="100%" alt="Chatterrow サービス紹介" src="./assets/chatterrow-introduction.gif" />

<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


這是一個使用 Laravel 13、Inertia 3、Svelte 5 建構的 Discord 風格 UI 專案型群組軟體。

可依專案建立頻道，集中管理聊天、任務、檔案與甘特圖。即時傳輸使用 Laravel Reverb，Office 檔案的唯讀預覽使用 ONLYOFFICE Document Server。

## 內部部署群組軟體的優點（茶多楼提供 App Server 部分的服務）
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## 主要功能

- **專案管理**：設定專案名稱、內容、開始日期、結束日期與成員
- **頻道**：以頻道為單位整理專案內的對話、任務與檔案
- **即時聊天**：透過 Laravel Reverb 同步訊息、討論串與回覆數
- **安全的 Markdown**：HTML 跳脫、HTTP(S) URL 限制、Shiki 程式碼高亮
- **附件**：檔案／資料夾 D&D、每 10 件一批上傳、圖片・PDF・Office 縮圖
- **檔案預覽**：圖片・PDF 的中央檢視器、使用 ONLYOFFICE 進行 Office 預覽、按 Esc 結束
- **Office/PDF 的 Markdown 轉換儲存**：在背景轉換為 Markdown，使其更容易用於 AI 學習
- **任務管理**：開始日期・開始時間・結束日期・結束時間、優先度、備註、完成狀態
- **甘特圖**：以專案或頻道為單位顯示期間
- **期限提醒**：透過排程器與佇列工作程序自動通知
- **主題**：支援深色／淺色模式
- **鍵盤操作**：傳送訊息和建立任務使用 `Cmd+Enter` 或 `Ctrl+Enter`。IME 確認用 Enter 不會傳送

## 技術架構

| 層級       | 技術                                                          |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite 或 PostgreSQL                                          |
| Realtime   | Laravel Reverb（WebSocket）                                   |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7（PDF / DOCX / XLSX / PPTX）        |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition（JWT、唯讀）     |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot         |

## 正式環境需求

- Ubuntu 24.04 LTS 或 Ubuntu 26.04 LTS（amd64）
- PHP 8.5 CLI/FPM 與 Redis 擴充功能
- Python 3.10 以上、MarkItDown 0.1.7、Redis Server
- 可以使用 sudo 的一般使用者，或 root 使用者
- 2 CPU、2 GB RAM、至少 30 GB 可用磁碟（ONLYOFFICE 官方建議至少 40 GB）
- 建議使用至少 4 GB 的 swap
- 讓 TCP 80/443 可從網際網路連線
- 應用程式使用的 DNS 名稱

例如：

```text
chat.example.com  A/AAAA -> 伺服器
```

ONLYOFFICE 會透過與應用程式相同網域的 `/onlyoffice/` 公開。請勿將 ONLYOFFICE、Reverb、供應用程式內部取得使用的 8080、8081、8090 埠對外公開。在雲端防火牆或主機端防火牆中，只允許 SSH 使用的埠以及 80/443。

## Ubuntu 環境自動設定

在全新的 Ubuntu Server 上取得本儲存庫並執行 `setup.sh`。系統會以互動方式確認網域、資料庫與 Let's Encrypt 電子郵件地址。可以使用一般使用者或 root 使用者執行。

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

若是本機開發・驗證環境或封閉網路等不需要可從網際網路存取的公開網域與 HTTPS 的情況，請加上 `--no-ssl` 執行。不使用 Let's Encrypt，僅以 HTTP 進行設定。使用 `chatterrow.test` 等本機網域時，請事先透過 DNS 或 `/etc/hosts` 讓該網域可以解析到此伺服器。

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

執行 `setup.sh` 時會詢問 sudo 密碼。若是可以不需要 sudo 密碼即可使用 sudo 的使用者，請如以下範例加上 `--sudo-nopasswd` 選項執行。


```bash
./setup.sh --sudo-nopasswd
```

如果不加任何選項執行，而偵測到不需要密碼的 sudo，程式不會開始設定，而是顯示 `--sudo-nopasswd` 的使用方式後結束。

執行 `setup.sh` 時的輸入範例：

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

ONLYOFFICE 的公開 URL 是 `https://<アプリドメイン>/onlyoffice`。以上範例中為 `https://chat.example.com/onlyoffice`。

設定會自動執行以下操作。

1. 設定 Ubuntu 官方的 `nginx-extras`、PHP 8.5、PostgreSQL、Redis、RabbitMQ、Node.js 24
2. 透過 apt 安裝 PHP 擴充功能、Poppler、ImageMagick、Ghostscript、日文軟體字型，並將 ImageMagick 的絕對路徑設定到 `.env`
3. 在 Python 虛擬環境 `.markitdown/venv` 建置 MarkItDown 0.1.7，並驗證 `pip check` 與 CLI 版本
4. 依 CPU 數量與安裝的 RAM 調整 PostgreSQL
5. 選擇 PostgreSQL 時，設定與執行使用者（`DEPLOY_USER`）同名的 `SUPERUSER LOGIN` 角色
6. 啟用 JWT，使用內部 8080 埠（可透過 `ONLYOFFICE_PORT` 變更）部署 ONLYOFFICE Document Server
7. 對已複製的儲存庫套用相依套件、前端與移轉
8. 以 nginx 設定應用程式、ONLYOFFICE、Reverb 與 ONLYOFFICE 內部下載路徑
9. 使用 Supervisor 讓 Redis 佇列 10 個程序、Reverb 與排程器以 `www-data` 身分常駐
10. 使用 Certbot 簽發憑證，並啟用 `certbot.timer` 與 nginx reload hook
11. 透過 `unattended-upgrades` 每日套用包含 nginx 在內的 Ubuntu 安全性更新
12. 執行 PHP 8.5、Redis、PostgreSQL、ONLYOFFICE、Supervisor 與應用程式的健康檢查

nginx 僅使用 Ubuntu 的 APT 套件。

## macOS 本機 OnlyOffice

在 macOS 上不安裝 Linux 用的 OnlyOffice 套件，而是使用 Apple 的 `container` 啟動 DocumentServer。需要 Apple silicon 與 macOS 26 以上版本。

1. 安裝 [Apple Container](https://github.com/apple/container)。
2. 使用 `brew install imagemagick` 安裝 ImageMagick。
3. 準備 Laravel 應用程式的 `.env`。若不存在，`setup.sh` 會複製 `.env.example`。
4. 依實際的本機 URL 設定 `APP_URL`。
5. 在儲存庫根目錄執行設定。macOS 不需要 `--domain` 與 `--database`。

```bash
cd /path/to/chatterrow
./setup.sh
```

不會覆寫現有的整個 `.env`。更新對象是以下 ONLYOFFICE 設定，以及偵測到的 ImageMagick 絕對路徑。

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

### macOS 開發時：自動判定 Valet・Herd・artisan serve

`setup.sh` 會確認是否有 `valet` 與 `herd` 指令、`APP_URL/up` 以及 `127.0.0.1:<ポート>/up` 的回應，並選擇連線方式。如果 Valet/Herd 端與 artisan serve 端都能回應，會優先使用 `APP_URL` 的 Valet/Herd 端。

| 開發伺服器          | `.env` 的 `APP_URL` 範例          | 自動設定的 `APP_ONLYOFFICE_INTERNAL_URL`   |
|---------------------|----------------------------------|--------------------------------------------|
| Laravel Valet       | `http://chatterrow.test`         | `http://chatterrow.test`                   |
| Laravel Herd        | `http://chatterrow.test`         | `http://chatterrow.test`                   |
| `php artisan serve`  | `http://localhost:8000`          | `http://chatter-host.container.internal:8000` |

使用 artisan serve 時，請在設定前或開啟預覽前啟動它。

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

覆寫自動判定時：

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

設定會執行以下操作。

- 透過 `container system start` 啟動 Apple Container
- 每次 pull `onlyoffice/documentserver:latest`，並以 arm64 啟動
- 將 OnlyOffice 公開至 `127.0.0.1:8086`
- 配置 CPU 4、記憶體 4 GB、共享記憶體 2 GB
- 將容器的 DNS 伺服器固定為 `1.1.1.1`
- 啟用 JWT，並將共用密鑰設定到 `.env`
- 每次在 macOS 執行 `setup.sh` 時，保留具名磁碟區並重新建立 OnlyOffice 容器
- 透過 `203.0.113.150` 將 `chatter-host.container.internal` 連線到 macOS 的 loopback
- 在 Valet/Herd 中，僅在容器內將應用程式主機名稱指向 `203.0.113.150`
- 取得並驗證 Source Han Sans JP／Noto Serif CJK JP，然後重新產生 OnlyOffice 的字型清單
- 更新 `ONLYOFFICE_DOCUMENT_SERVER_URL`、`ONLYOFFICE_PUBLIC_URL`、`APP_ONLYOFFICE_INTERNAL_URL`
- 對 DocumentServer 與 Laravel 的 `/up` 執行健康檢查

若要覆寫 DNS 伺服器，請指定環境變數。值請使用 IPv4 位址。

從 Cloudflare 的 1.1.1.1 改為 Google 的 8.8.8.8 執行範例：
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

持久資料使用以下具名磁碟區。

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

確認・停止容器：

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

健康檢查：

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### 容器與磁碟區的完全初始化測試

以下操作會刪除 OnlyOffice 內部的 PostgreSQL、設定、快取與日誌。若有重要資料，請勿執行。

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

[Apple Container 的具名磁碟區](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management)在不存在時，會透過 `container run --volume <名前>:<パス>` 隱含建立，因此可藉由上述步驟確認包含磁碟區建立在內的初始建置。

### 日文字型（macOS）

即使在 macOS 主機安裝字型，隔離的 OnlyOffice 容器也無法使用。`setup.sh` 會從官方儲存庫下載以下固定字重字型的固定版本，驗證 SHA-256 後再註冊到 OnlyOffice。

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold（2.005R、JP subset OTF）
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold（Serif2.003、Japanese OTF）

字型會儲存在 `chatterrow-onlyoffice-data` 磁碟區內的 `/var/www/onlyoffice/Data/custom-fonts`。重新執行設定時會確認容器內檔案的 SHA-256，若一致則不會重新下載。

註冊固定字重版本後，`setup.sh` 會先刪除既有的 `AllFonts.js` 與 `font_selection.bin`，再執行 `allfontsgen`。若保留既有檔案，`allfontsgen` 可能會重用目錄而不註冊新字型。

OnlyOffice 9.4 的 converter 可能會將 Microsoft Office 的日文主題字型替換成 `NanumGothic` 或 `Droid Sans Fallback`。只有 fontconfig 的 alias 無法影響這條轉換路徑。因此，[scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) 會對伺服器端的 `font_selection.bin` 與瀏覽器端的 2 個 `AllFonts.js` 套用以下修正。

- 將游ゴシック、Yu Gothic、Meiryo、MS Gothic 系列的別名註冊到 Source Han Sans JP
- 將游明朝、Yu Mincho、MS Mincho 系列的別名註冊到 Noto Serif CJK JP
- 將 converter 選擇的 `NanumGothic` 實際字型參照改為 Source Han Sans JP
- 將 converter 選擇的 `Droid Sans Fallback` 實際字型參照改為 Noto Serif CJK JP

此字型目錄共用於 DOCX、XLSX、PPTX 的轉換與瀏覽器顯示。修正後會產生 JS 快取，重新啟動 docservice 與 converter，再清除 DocumentServer 的快取。若未來的 `latest` 變更目錄格式或必要字型名稱，將不使用錯誤目錄，而讓設定以錯誤結束。

不會呼叫標準的 `documentserver-generate-allfonts.sh`，因為它還會重新產生不必要的簡報主題，而在 Apple Container 環境中該步驟可能無法結束。

更新字型目錄時，OnlyOffice 的文件快取世代也會改變，因此已開啟的 DOCX 也不會重新使用舊的 `Editor.bin`，而會重新轉換。

即使現有容器卡在 `Generating presentation themes` 而不再回應，也請直接重新執行 `./setup.sh`。在 macOS 上每次執行都會 pull `onlyoffice/documentserver:latest`，並強制重新建立現有的 OnlyOffice 容器。上述 4 個具名磁碟區不會刪除，因此 OnlyOffice 的持久資料會保留。

不隨附或重新散布 Microsoft 的游明朝／游ゴシック。OnlyOffice 容器內使用以下替代設定。

| Office 指定字型（DOCX / XLSX / PPTX）            | 替代字型           |
|---------------------------------------------|--------------------|
| 游明朝 / Yu Mincho / MS Mincho              | Noto Serif CJK JP  |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic | Source Han Sans JP |

這會解決缺字與被替換成不適當西文字型的問題，但由於與游字型存在字寬差異，無法保證換行位置與頁數完全一致。若需要完全一致，請確認使用授權後，將游字型實體另外放置到 OnlyOffice 的自訂字型區域。

確認註冊狀態：

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

預期值依序為 `1.1.1.1`、`NotoSerifCJKjp-Regular.otf`、`SourceHanSansJP-Light.otf`。

在 macOS 上不授予 OnlyOffice 編輯權限，維持唯讀預覽。即使使用 DocumentServer 的轉換 API，也可以獨立於此唯讀設定使用。

## 非互動設定

在 Ubuntu 自動化環境中，`--domain` 與 `--database` 為必要項目。macOS 本機 OnlyOffice 設定不需要它們。

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### 選項

| 選項                        | 預設值                             | 說明                                              |
|----------------------------|------------------------------------|---------------------------------------------------|
| `--domain <domain>`          | 互動輸入                           | 應用程式的公開網域                                |
| `--email <email>`            | 空                                 | Let's Encrypt 註冊與到期通知電子郵件              |
| `--database <driver>`        | 互動時為 `sqlite`                  | `sqlite` 或 `postgresql`                          |
| `--db-name <name>`           | `chatterrow`                       | 應用程式用 PostgreSQL DB 名稱                     |
| `--db-user <name>`           | `chatterrow`                       | 應用程式用 PostgreSQL 角色                        |
| `--db-password <password>`   | 自動產生                           | 應用程式用 PostgreSQL 密碼                        |
| `--app-dir <path>`           | `setup.sh` 所在的儲存庫            | `/home` 或 `/var/www` 下的部署位置                |
| `--repo <url>`               | GitHub SSH URL                     | 要部署的 Git 儲存庫                               |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | macOS 每次 pull 並使用的 DocumentServer 映像檔    |
| `--sudo-nopasswd`            | off                                | 適用於免密碼 sudo 使用者。省略 `sudo -v`           |
| `--no-ssl`                   | off                                | 省略 Certbot，以 HTTP 設定                       |

也可以使用同名的大寫環境變數。例如：`DOMAIN`、`DATABASE`、`DB_NAME`、`DB_USER`、`DB_PASSWORD`、`DEPLOY_USER`、`SUDO_NOPASSWD`、`ONLYOFFICE_PORT`、`ONLYOFFICE_JWT_SECRET`。

省略 PostgreSQL 密碼時，會產生 64 位元的隨機值並儲存到以下位置。

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

選擇 PostgreSQL 時，會將與執行使用者（`DEPLOY_USER`，未指定時，一般使用者執行則為 `id -un`，root 執行則為 `root`）同名的 PostgreSQL 角色建立或更新為 `LOGIN SUPERUSER`。應用程式連線用的 `DB_USER` 會維持為另一個非特權角色。

## PostgreSQL 自動調整

因為 ONLYOFFICE 也需要 PostgreSQL，即使應用程式選擇 SQLite，也會安裝與調整 PostgreSQL。在 Ubuntu 上有多個 PostgreSQL 叢集時，為避免變更錯誤的叢集，設定會停止執行。

設定檔：

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

主要計算基準：

| 設定                   | 基準                                              |
|------------------------|---------------------------------------------------|
| `shared_buffers`       | RAM 的 20%，範圍為 128 MB 至 8 GB                 |
| `effective_cache_size` | RAM 的 60%，範圍為 256 MB 至 64 GB                |
| `maintenance_work_mem` | RAM 的 5%，範圍為 64 MB 至 1 GB                   |
| `work_mem`             | 根據 RAM、`shared_buffers`、最大連線數以安全側計算 |
| `max_connections`      | 根據 CPU 與 RAM 在 50 至 300 的範圍內計算          |
| parallel workers       | 根據 CPU 數量計算並設定上限                       |

由於此伺服器也同時運行 PHP、ONLYOFFICE、Redis、RabbitMQ，因此配置比 PostgreSQL 專用伺服器更保守。重新執行時會依目前的 CPU 數量與 RAM 重新計算。

## 埠配置

Ubuntu 正式環境：

| 埠        | 用途                                 | 公開範圍           |
|----------|--------------------------------------|--------------------|
| 80 / 443 | nginx、Certbot、公開 Web              | 網際網路            |
| 8080     | ONLYOFFICE Document Server           | 僅 localhost        |
| 8081     | Laravel Reverb                       | 僅 localhost        |
| 8090     | 從 ONLYOFFICE 取得已簽署檔案          | 僅限 127.0.0.1      |
| 5432     | PostgreSQL                           | 建議本機連線        |

macOS 本機環境：

| 埠   | 用途                                          | 公開範圍        |
|------|-----------------------------------------------|-----------------|
| 8086 | Apple Container 上的 ONLYOFFICE Document Server | 僅限 `127.0.0.1` |
| 8000 | `php artisan serve` 的預設埠                   | 僅限 `127.0.0.1` |

使用 Valet/Herd 時，Laravel 應用程式會以 `APP_URL` 的主機名稱與一般 HTTP/HTTPS 埠運作。artisan serve 的埠由 `APP_URL` 或 `MACOS_ARTISAN_PORT` 決定。

## SSL 與自動更新

Certbot 使用 `/var/www/letsencrypt` 作為固定 ACME webroot，將 challenge 設定給 nginx，而不交給 Laravel，並將應用程式網域的憑證設定至 nginx。`/onlyoffice/` 也使用相同憑證提供服務。`certbot.timer` 會定期確認更新時機，更新成功後 reload nginx。設定時也會執行 dry-run。

`unattended-upgrades` 每日確認 Ubuntu 的 security origin，並連同相依性更新 `nginx`、`nginx-extras` 與對應的 `libnginx-mod-*`。nginx 以外的安全性更新也會維持。一般的 `-updates` pocket 不會自動套用。

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## 維運

### 程序確認

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queue 工作程序以 10 個程序執行 `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600`。請確認全部 10 個都為 `RUNNING`。

### Markdown 轉換重新處理

重新投入失敗的檔案，以及長時間未更新的 `pending`／`processing` 檔案。

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` 不會將舊版 Office 格式（DOC、XLS、PPT、ODF）列為 Markdown 轉換對象。這些檔案的 ONLYOFFICE 預覽功能仍可繼續使用。

### 應用程式更新

可以使用相同設定重新執行 `setup.sh`。現有的 PostgreSQL 密碼與已啟用 TLS 的 nginx 設定會保留，Git 僅在可以 fast-forward 時更新。

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### 備份

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

## 主要環境變數

| 變數                             | 說明                                                   |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | 應用程式的公開 URL                                      |
| `DB_CONNECTION`                  | `sqlite` 或 `pgsql`                                     |
| `QUEUE_CONNECTION`               | Redis 佇列（`redis`）                                  |
| `MARKITDOWN_PATH`                | MarkItDown CLI 的路徑。未指定時位於 `.markitdown/venv` 內 |
| `MARKITDOWN_TIMEOUT`             | 每個檔案的轉換逾時秒數                                  |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | MarkItDown 環境所需的最低 Python 版本（3.10）           |
| `IMAGEMAGICK_PATH`               | `magick` 或 `convert` 的絕對路徑                        |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb 驗證資訊                                         |
| `REVERB_HOST/PORT/SCHEME`        | 瀏覽器與 Laravel 連線的公開 WebSocket                  |
| `REVERB_SERVER_HOST/PORT`        | Reverb 的內部監聽位置。設定為 `127.0.0.1:8081`          |
| `REVERB_ALLOWED_ORIGINS`         | 允許連線到 Reverb 的公開網域                            |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Laravel 連線的 ONLYOFFICE 內部 URL（Ubuntu 為 127.0.0.1） |
| `ONLYOFFICE_PUBLIC_URL`           | 瀏覽器可見的 ONLYOFFICE 公開 URL                         |
| `APP_ONLYOFFICE_INTERNAL_URL`    | ONLYOFFICE 取得檔案時使用的內部應用程式 URL             |
| `ONLYOFFICE_JWT_SECRET`          | 與 ONLYOFFICE 共用的 JWT 秘密金鑰                        |

## 疑難排解

| 症狀                                 | 確認                                                                                                              |
|--------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`、`sudo nginx -t`                                                                 |
| 沒有即時更新                         | `sudo supervisorctl status chatterrow-reverb`、瀏覽器 Network 的 `/app/` 連線                                   |
| 無法產生附件預覽                     | `/var/log/chatterrow-queue-error_*.log`、ONLYOFFICE/Poppler/ImageMagick                                           |
| `exec: convert: not found`            | 將 `command -v magick` 或 `command -v convert` 的絕對路徑設定到 `IMAGEMAGICK_PATH` 後，執行 `php artisan optimize:clear` |
| Markdown 轉換失敗                    | `storage/logs/laravel.log`、`/var/log/chatterrow-queue-error_*.log`、`php artisan files:markdown`                 |
| Redis 佇列未處理                     | `redis-cli ping`、`php8.5 -m` 的 `redis`、`sudo supervisorctl status 'chatterrow-queue:*'`                       |
| Office 預覽無法開啟（Ubuntu）        | `curl http://127.0.0.1:8080/healthcheck`、JWT 秘密金鑰、8090 內部 URL、`php artisan files:previews`              |
| Office 預覽無法開啟（macOS）         | `curl http://127.0.0.1:8086/healthcheck`、JWT 秘密金鑰、`APP_ONLYOFFICE_INTERNAL_URL`、從容器內連線到 `/up` 的可達性 |
| 無法連線至 PostgreSQL                | `.env`、`sudo -u postgres pg_isready`、`/etc/chatterrow/database-password`                                        |
| 無法簽發憑證                         | 應用程式網域的 A/AAAA、從網際網路連線到 80 埠的能力、`/var/log/letsencrypt/`                                     |

## 本機開發

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

在另一個終端機啟動 Laravel 與 Reverb。

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

若 Redis Server 尚未啟動，macOS 請執行 `brew services start redis`，Ubuntu 請執行 `sudo systemctl enable --now redis-server`。

驗證：

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## 授權條款

MIT。導入 ONLYOFFICE Docs Community Edition 時，也請確認 AGPLv3 的條款。
