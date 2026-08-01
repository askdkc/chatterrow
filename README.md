# chatter

Laravel 13、Inertia 3、Svelte 5で構築した、Discord風UIのプロジェクト型グループウェアです。

プロジェクトごとにチャンネルを作り、チャット、タスク、ファイル、ガントチャートを一元管理します。リアルタイム配信にはLaravel Reverb、Officeファイルの読取専用プレビューにはONLYOFFICE Document Serverを使用します。

## 主な機能

- **プロジェクト管理**: プロジェクト名、内容、開始日、終了日、メンバーを設定
- **チャンネル**: プロジェクト内の会話・タスク・ファイルをチャンネル単位で整理
- **リアルタイムチャット**: Laravel Reverbによるメッセージ、スレッド、返信数の同期
- **安全なMarkdown**: HTMLエスケープ、HTTP(S) URL制限、Shikiコードハイライト
- **添付ファイル**: ファイル／フォルダのD&D、10件単位アップロード、画像・PDF・Officeサムネイル
- **ファイルプレビュー**: 画像・PDFの中央ビューア、ONLYOFFICEによるOfficeプレビュー、Escで終了
- **タスク管理**: 開始日・開始時刻・終了日・終了時刻、優先度、メモ、完了状態
- **ガントチャート**: プロジェクトまたはチャンネル単位の期間表示
- **期限リマインダー**: スケジューラとキューワーカーによる自動通知
- **テーマ**: ダーク／ライトモード対応
- **キーボード操作**: メッセージ送信とタスク作成は`Cmd+Enter`または`Ctrl+Enter`。IME確定Enterは送信しません

## 技術構成

| レイヤー | 技術 |
|---|---|
| Backend | Laravel 13 / PHP 8.4.1+ |
| Frontend | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8 |
| Database | SQLiteまたはPostgreSQL |
| Realtime | Laravel Reverb（WebSocket） |
| Preview | Shiki / libreoffice / poppler / ImageMagick |
| Office | ONLYOFFICE Document Server Community Edition（JWT、読取専用） |
| Production | nginx 1.30+ / PHP-FPM / Supervisor / Certbot |

## 本番要件

- Ubuntu 24.04 LTSまたはUbuntu 26.04 LTS（amd64）
- sudoを使用できる一般ユーザー。rootユーザーとして直接実行しないでください
- 2 CPU、2 GB RAM、40 GB空きディスク以上
- 4 GB以上のswapを推奨
- TCP 80/443をインターネットから到達可能にする
- アプリ用とONLYOFFICE用の異なる2つのDNS名

例:

```text
chat.example.com         A/AAAA -> サーバー
office.chat.example.com  A/AAAA -> サーバー
```

ONLYOFFICE、Reverb、アプリ内部取得用の8080、8081、8090番ポートは外部公開しないでください。クラウドファイアウォールやホスト側ファイアウォールでは、SSH用ポートと80/443だけを許可します。

## 自動セットアップ

リポジトリを取得して`setup.sh`を実行します。ドメイン、データベース、Let's Encryptメールアドレスを対話的に確認します。

```bash
git clone git@github.com:askdkc/chatter.git
cd chatter
./setup.sh
```

入力例:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database [sqlite/postgresql] (default: sqlite): postgresql
Let's Encrypt email (optional): admin@example.com
PostgreSQL password (leave blank to generate):
```

ONLYOFFICE用ドメインは既定で`office.<アプリドメイン>`になります。上の例では`office.chat.example.com`です。

セットアップは以下を自動実行します。

1. nginx公式署名済みリポジトリ、PHP 8.4+、PostgreSQL、Redis、RabbitMQ、Node.js 22を構成
2. PHP拡張、LibreOffice、Poppler、ImageMagick、Ghostscript、日本語フォントをaptで導入
3. PostgreSQLをCPU数と搭載RAMに合わせて調整
4. ONLYOFFICE Document ServerをJWT有効、内部8080番で導入
5. アプリを`/var/www/chatter`へ配備し、依存関係、フロントエンド、マイグレーションを実行
6. nginxでアプリ、ONLYOFFICE、Reverb、ONLYOFFICE内部ダウンロード経路を構成
7. Supervisorでキュー、Reverb、スケジューラを`www-data`として常駐
8. Certbotで証明書を発行し、`certbot.timer`とnginx reload hookを有効化
9. PostgreSQL、ONLYOFFICE、Supervisor、アプリのヘルスチェックを実行

## 非対話セットアップ

自動化環境では`--domain`と`--database`が必須です。

```bash
./setup.sh \
    --domain chat.example.com \
    --office-domain office.chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### オプション

| オプション | デフォルト | 説明 |
|---|---|---|
| `--domain <domain>` | 対話入力 | アプリの公開ドメイン |
| `--office-domain <domain>` | `office.<domain>` | ONLYOFFICEの公開ドメイン |
| `--email <email>` | 空 | Let's Encrypt登録・期限通知メール |
| `--database <driver>` | 対話時は`sqlite` | `sqlite`または`postgresql` |
| `--db-name <name>` | `chatter` | アプリ用PostgreSQL DB名 |
| `--db-user <name>` | `chatter` | アプリ用PostgreSQLロール |
| `--db-password <password>` | 自動生成 | アプリ用PostgreSQLパスワード |
| `--app-dir <path>` | `/var/www/chatter` | `/var/www`配下の配備先 |
| `--repo <url>` | GitHub SSH URL | 配備するGitリポジトリ |
| `--no-ssl` | off | Certbotを省略しHTTPで構成 |

同名の大文字環境変数も使用できます。例: `DOMAIN`、`DATABASE`、`DB_NAME`、`DB_USER`、`DB_PASSWORD`。

PostgreSQLパスワードを省略すると64桁のランダム値を生成し、次の場所へ保存します。

```text
/etc/chatter/database-password  root:root 0600
/var/www/chatter/.env           <deploy-user>:www-data 0640
```

## PostgreSQL自動調整

PostgreSQLはONLYOFFICEでも必要なため、アプリでSQLiteを選んだ場合もインストール・調整します。Ubuntu上に複数のPostgreSQLクラスタがある場合は、誤ったクラスタを変更しないようセットアップを停止します。

設定ファイル:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatter-tuning.conf
```

主な計算基準:

| 設定 | 基準 |
|---|---|
| `shared_buffers` | RAMの20%、128 MBから8 GBの範囲 |
| `effective_cache_size` | RAMの60%、256 MBから64 GBの範囲 |
| `maintenance_work_mem` | RAMの5%、64 MBから1 GBの範囲 |
| `work_mem` | RAM、`shared_buffers`、最大接続数から安全側に算出 |
| `max_connections` | CPUとRAMから50から300の範囲で算出 |
| parallel workers | CPU数から算出し上限を設定 |

このサーバーではPHP、ONLYOFFICE、Redis、RabbitMQも同居するため、PostgreSQL専用サーバーより保守的な割り当てです。再実行すると現在のCPU数とRAMから再計算されます。

## ポート構成

| ポート | 用途 | 公開範囲 |
|---|---|---|
| 80 / 443 | nginx、Certbot、公開Web | インターネット |
| 8080 | ONLYOFFICE Document Server | localhost向け |
| 8081 | Laravel Reverb | localhost向け |
| 8090 | ONLYOFFICEから署名済みファイルを取得 | 127.0.0.1のみ |
| 5432 | PostgreSQL | ローカル接続を推奨 |

## SSLと自動更新

Certbotはアプリ用とONLYOFFICE用の両ドメインを含む証明書をnginxへ設定します。`certbot.timer`が更新時期を定期確認し、更新成功後にnginxをreloadします。セットアップ時にはdry-runも実行します。

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
```

## 運用

### プロセス確認

```bash
sudo supervisorctl status
sudo supervisorctl restart chatter-queue chatter-reverb chatter-schedule
sudo tail -f /var/log/chatter-*.log
```

### アプリ更新

同じ設定で`setup.sh`を再実行できます。既存のPostgreSQLパスワードとTLS有効nginx設定は保持され、Gitはfast-forward可能な場合だけ更新されます。

```bash
cd /path/to/chatter-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### バックアップ

SQLite:

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /var/www/chatter/database/database.sqlite \
    ".backup /backup/chatter-$(date +%F).sqlite"
sudo rsync -a /var/www/chatter/storage/app/ /backup/storage-app/
```

PostgreSQL:

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatter > /backup/chatter-$(date +%F).dump
sudo rsync -a /var/www/chatter/storage/app/ /backup/storage-app/
```

## 主な環境変数

| 変数 | 説明 |
|---|---|
| `APP_URL` | アプリの公開URL |
| `DB_CONNECTION` | `sqlite`または`pgsql` |
| `REVERB_APP_ID/KEY/SECRET` | Reverb認証情報 |
| `REVERB_HOST/PORT/SCHEME` | ブラウザとLaravelが接続する公開WebSocket |
| `REVERB_SERVER_HOST/PORT` | Reverbの内部listen先。セットアップでは`127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS` | Reverbへの接続を許可する公開ドメイン |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | ブラウザから見えるONLYOFFICE URL |
| `APP_ONLYOFFICE_INTERNAL_URL` | ONLYOFFICEがファイルを取得する内部アプリURL |
| `ONLYOFFICE_JWT_SECRET` | ONLYOFFICEと共有するJWT秘密鍵 |

## トラブルシューティング

| 症状 | 確認 |
|---|---|
| 502 Bad Gateway | `sudo systemctl status php*-fpm`、`sudo nginx -t` |
| リアルタイム更新されない | `sudo supervisorctl status chatter-reverb`、ブラウザNetworkの`/app/`接続 |
| 添付プレビューが生成されない | `/var/log/chatter-queue-error.log`、LibreOffice/Poppler/ImageMagick |
| Officeプレビューが開かない | `curl http://127.0.0.1:8080/healthcheck`、JWT秘密鍵、8090番内部URL |
| PostgreSQLへ接続できない | `.env`、`sudo -u postgres pg_isready`、`/etc/chatter/database-password` |
| 証明書を発行できない | 両ドメインのA/AAAA、80番到達性、`/var/log/letsencrypt/` |

## ローカル開発

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run dev
```

別ターミナルでLaravelとReverbを起動します。

```bash
php artisan serve
php artisan reverb:start --port=8081
```

検証:

```bash
php artisan test
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## ライセンス

MIT。ONLYOFFICE Docs Community Editionを導入する場合はAGPLv3の条件も確認してください。
