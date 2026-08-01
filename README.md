# chatter

Discord風UIのチャット型グループウェア（Laravel 13 + Inertia 3 + Svelte 5）。

サーバー（プロジェクト）ごとにチャンネルを作り、チャンネル単位でチャット・タスク（todo）・ファイル・ガントチャートを管理します。Office ファイルは ONLYOFFICE Document Server（読取専用）でプレビュー、画像・動画・PDF はブラウザ内 JS プレビュー、リアルタイム通知は Laravel Reverb で配信します。

## 機能

- **Discord 風 3 カラム UI** — サーバーサイドバー / チャンネル一覧 / メッセージ欄
- **サーバー** — 開始日・終了期限の設定（カレンダー情報。利用期限ではない）、メンバー管理
- **チャンネル = タスク** — チャンネル自体に期間を持たせ、ガントチャート上でタスクとして表示
- **チャット** — メッセージ送信・スレッド・チャンネル切替・D&D ファイルアップロード・添付表示（Reverb でリアルタイム同期）
- **タスク一覧** — サーバー内の全チャンネル + チャンネル内 todo を一覧表示
- **ガントチャート** — サーバー = 全チャンネルのタスク、チャンネル = 配下タスク
- **ファイルビューワー** — 画像/動画/PDF は JS プレビュー、Office は ONLYOFFICE 読取専用ポップアップ
- **期限リマインダー** — タスク期限当日にチャンネルへ自動通知（scheduler + supervisor）

## 技術スタック

| レイヤー | 技術 |
|---|---|
| Backend | Laravel 13.23 / PHP 8.3+ |
| Frontend | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8 |
| DB | SQLite（アプリ本体）/ PostgreSQL（ONLYOFFICE 内部） |
| Realtime | Laravel Reverb (WebSocket) |
| プレビュー | @file-viewer/web + preset-office、libreoffice / poppler / imagemagick |
| Office | ONLYOFFICE Document Server（読取専用、JWT 署名） |
| サーバー | nginx + PHP-FPM + supervisor |

## クイックスタート（Ubuntu 24.04 / 26.04）

sudo が使える `ubuntu` ユーザーで、ドメインの A レコード（`chat.example.com` と `office.chat.example.com`）がこのサーバーを指すよう事前に設定しておきます。その上で:

```bash
git clone git@github.com:askdkc/chatter.git
cd chatter
./setup.sh --domain chat.example.com --email you@example.com
```

これだけで以下が自動で完了します。

1. **apt インストール**: nginx / PHP 8.3+（FPM + 拡張）/ composer / Node.js 22 / supervisor / certbot / libreoffice・poppler・imagemagick / ONLYOFFICE Document Server
2. **アプリ配備**: `/var/www/chatter` へ clone → `composer install` → `.env` 生成（APP_KEY・Reverb 鍵・ONLYOFFICE JWT を自動生成）→ `npm ci && npm run build` → `migrate`
3. **nginx**: `sites-available/chatter`（アプリ）+ `sites-available/onlyoffice`（Document Server リバースプロキシ、ポート 8080）を生成し有効化
4. **Let's Encrypt**: `certbot --nginx` で `chat.example.com` + `office.chat.example.com` に SSL 発行、`certbot.timer` で自動更新を有効化
5. **supervisor**: `chatter-queue`（キュー）/ `chatter-reverb`（WebSocket）/ `chatter-schedule`（スケジューラ）の3設定を自動生成
6. **自動起動**: nginx / php*-fpm / supervisor / postgresql / onlyoffice-documentserver を `systemctl enable`

完了後、`https://chat.example.com` にアクセスし、`/register` から最初のユーザーを作成します。

## setup.sh オプション

| オプション | デフォルト | 説明 |
|---|---|---|
| `--domain <d>` | — | アプリのドメイン（certbot 対象。`--no-ssl` 以外は必須） |
| `--email <m>` | — | Let's Encrypt の登録・更新通知メール |
| `--office-domain <d>` | `office.<domain>` | ONLYOFFICE Document Server のドメイン |
| `--app-dir <p>` | `/var/www/chatter` | アプリのインストール先 |
| `--repo <url>` | `git@github.com:askdkc/chatter.git` | 配備する Git リポジトリ |
| `--no-ssl` | off | certbot をスキップ（HTTP のみ・テスト用） |

> **SSH 鍵**: `--repo` 既定値は SSH 形式（`git@github.com:...`）です。サーバーに GitHub の SSH 鍵が登録されていない場合は HTTPS に自動フォールバックします。

## 手動インストール（setup.sh を使わない場合）

```bash
# 1. パッケージ
sudo apt update
sudo apt install -y nginx php-cli php-fpm php-sqlite3 php-mbstring php-xml \
    php-zip php-bcmath php-intl php-curl php-gd composer supervisor \
    certbot python3-certbot-nginx libreoffice poppler-utils imagemagick

# 2. Node.js 22（Vite 8 は ^20.19 || >=22.12 が必要）
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# 3. ONLYOFFICE Document Server（ポート 8080 + JWT）
curl -fsSL https://download.onlyoffice.com/GPG-KEY-ONLYOFFICE | sudo gpg --dearmor -o /usr/share/keyrings/onlyoffice.gpg
echo "deb [signed-by=/usr/share/keyrings/onlyoffice.gpg] https://download.onlyoffice.com/repo/debian squeeze main" | sudo tee /etc/apt/sources.list.d/onlyoffice.list
sudo apt update && sudo apt install -y onlyoffice-documentserver

# 4. アプリ
sudo git clone git@github.com:askdkc/chatter.git /var/www/chatter
cd /var/www/chatter
composer install --no-dev
cp .env.example .env        # → APP_KEY / REVERB_* / ONLYOFFICE_* を設定
php artisan key:generate
npm ci && npm run build
touch database/database.sqlite && php artisan migrate --force
sudo chown -R www-data:www-data storage bootstrap/cache

# 5. nginx / supervisor / certbot
#    setup.sh の「nginx conf 生成」「supervisor conf 生成」「Let's Encrypt」セクションと同内容
```

## 環境変数（.env）

| 変数 | 説明 |
|---|---|
| `APP_URL` | アプリの公開 URL（`https://chat.example.com`） |
| `BROADCAST_CONNECTION=reverb` | WebSocket ブロードキャスト |
| `REVERB_APP_ID/KEY/SECRET` | Reverb アプリ鍵（`openssl rand -hex 16/24` で生成） |
| `REVERB_HOST/PORT/SCHEME` | クライアント側 WebSocket 接続先（`chat.example.com` / 443 / https） |
| `REVERB_SERVER_HOST/PORT` | Reverb サーバー側 listen 先（127.0.0.1:8080、nginx が `/apps` をプロキシ） |
| `ONLYOFFICE_ENABLED=true` | ONLYOFFICE プレビュー有効化 |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Document Server の公開 URL（`https://office.chat.example.com`） |
| `ONLYOFFICE_JWT_SECRET` | Document Server の `local.json` と同じ JWT シークレット |
| `ONLYOFFICE_ALLOW_DOWNLOAD/PRINT` | プレビュー時のダウンロード・印刷許可 |

## 運用

### サービス構成（supervisor が管理）

```text
chatter-queue     php artisan queue:work database          # プレビュー生成等のジョブ
chatter-reverb    php artisan reverb:start                 # WebSocket (127.0.0.1:8080)
chatter-schedule  php artisan schedule:work                # 期限リマインダー（毎時）
```

```bash
sudo supervisorctl status                      # 状態確認
sudo supervisorctl restart chatter-reverb      # 再起動
sudo tail -f /var/log/chatter-*.log            # ログ
```

### SSL 自動更新

`certbot.timer`（systemd）が毎日証明書の有効期限を確認し、30日以内なら自動更新します。

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run                   # 動作確認
```

### アプリ更新

```bash
cd /var/www/chatter
git pull
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
sudo supervisorctl restart chatter-queue chatter-reverb chatter-schedule
```

### バックアップ

```bash
# SQLite DB + アップロードファイル
sudo sqlite3 /var/www/chatter/database/database.sqlite ".backup /backup/chatter-$(date +%F).sqlite"
sudo rsync -a /var/www/chatter/storage/app /backup/
```

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| アプリが 502 | `sudo systemctl status php*-fpm`（FPM が落ちていないか）、`sudo nginx -t` |
| プレビューが生成されない | `sudo tail -f /var/log/chatter-queue-error.log`（libreoffice/poppler 未導入なら `sudo apt install libreoffice poppler-utils imagemagick`） |
| Office プレビューが開かない | `ONLYOFFICE_JWT_SECRET` と `/etc/onlyoffice/documentserver/local.json` の `services.CoAuthoring.secret` が一致しているか確認 |
| チャットがリアルタイム更新されない | `sudo supervisorctl status chatter-reverb`、ブラウザ開発者ツールの Network で `/apps/` WebSocket 接続を確認 |
| リマインダーが来ない | `sudo supervisorctl status chatter-schedule`（`schedule:work` が動いているか） |

## 開発（ローカル）

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm ci
npm run dev          # 別ターミナルで: php artisan serve
php artisan test     # 43 tests (149 assertions)
```

## ライセンス

MIT（Laravel 本体は MIT、ONLYOFFICE は AGPLv3 Community Edition を同梱する場合に注意）。
