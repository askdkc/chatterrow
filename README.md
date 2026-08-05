# 茶多楼 chatterrow

Laravel 13、Inertia 3、Svelte 5で構築した、Discord風UIのプロジェクト型グループウェアです。

プロジェクトごとにチャンネルを作り、チャット、タスク、ファイル、ガントチャートを一元管理します。リアルタイム配信にはLaravel Reverb、Officeファイルの読取専用プレビューにはONLYOFFICE Document Serverを使用します。

## オンプレグループウェアの利点 (茶多楼はApp Server部分のサービスとなります)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## 主な機能

- **プロジェクト管理**: プロジェクト名、内容、開始日、終了日、メンバーを設定
- **チャンネル**: プロジェクト内の会話・タスク・ファイルをチャンネル単位で整理
- **リアルタイムチャット**: Laravel Reverbによるメッセージ、スレッド、返信数の同期
- **安全なMarkdown**: HTMLエスケープ、HTTP(S) URL制限、Shikiコードハイライト
- **添付ファイル**: ファイル／フォルダのD&D、10件単位アップロード、画像・PDF・Officeサムネイル
- **ファイルプレビュー**: 画像・PDFの中央ビューア、ONLYOFFICEによるOfficeプレビュー、Escで終了
- **Office/PDFのマークダウン変換保存**: バックグラウンドでMarkdown化してAI学習に利用しやすくします
- **タスク管理**: 開始日・開始時刻・終了日・終了時刻、優先度、メモ、完了状態
- **ガントチャート**: プロジェクトまたはチャンネル単位の期間表示
- **期限リマインダー**: スケジューラとキューワーカーによる自動通知
- **テーマ**: ダーク／ライトモード対応
- **キーボード操作**: メッセージ送信とタスク作成は`Cmd+Enter`または`Ctrl+Enter`。IME確定Enterは送信しません

## 技術構成

| レイヤー   | 技術                                                          |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLiteまたはPostgreSQL                                        |
| Realtime   | Laravel Reverb（WebSocket）                                   |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7（PDF / DOCX / XLSX / PPTX）        |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition（JWT、読取専用） |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot         |

## 本番要件

- Ubuntu 24.04 LTSまたはUbuntu 26.04 LTS（amd64）
- PHP 8.5 CLI/FPMとRedis拡張
- Python 3.10以上、MarkItDown 0.1.7、Redis Server
- sudoを使用できる一般ユーザー、またはrootユーザー
- 2 CPU、2 GB RAM、40 GB空きディスク以上
- 4 GB以上のswapを推奨
- TCP 80/443をインターネットから到達可能にする
- アプリ用のDNS名

例:

```text
chat.example.com  A/AAAA -> サーバー
```

ONLYOFFICEはアプリと同じドメインの`/onlyoffice/`で公開します。ONLYOFFICE、Reverb、アプリ内部取得用の8080、8081、8090番ポートは外部公開しないでください。クラウドファイアウォールやホスト側ファイアウォールでは、SSH用ポートと80/443だけを許可します。

## Ubuntu自動セットアップ

Ubuntuではリポジトリを取得して`setup.sh`を実行します。ドメイン、データベース、Let's Encryptメールアドレスを対話的に確認します。一般ユーザーまたはrootユーザーで実行できます。

```bash
git clone git@github.com:askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

sudoパスワードを入力しない運用ユーザーでは、`NOPASSWD`設定済みの一般ユーザーとして次のオプションを付けて実行します。指定時は`sudo -v`によるパスワード確認を行わず、セットアップ内のsudoコマンドを通常どおり実行します。

オプションなしで実行した場合、パスワード不要のsudoが検出されると、セットアップを開始せず`--sudo-nopasswd`の使用方法を表示して終了します。

```bash
./setup.sh --sudo-nopasswd
```

入力例:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
PostgreSQL password (leave blank to generate):
```

ONLYOFFICEの公開URLは`https://<アプリドメイン>/onlyoffice`です。上の例では`https://chat.example.com/onlyoffice`です。

セットアップは以下を自動実行します。

1. Ubuntu公式`nginx-extras`、PHP 8.5、PostgreSQL、Redis、RabbitMQ、Node.js 24を構成
2. PHP拡張、Poppler、ImageMagick、Ghostscript、日本語フォントをaptで導入し、ImageMagickの絶対パスを`.env`へ設定
3. Python仮想環境`.markitdown/venv`へMarkItDown 0.1.7を構築し、`pip check`とCLIバージョンを検証
4. PostgreSQLをCPU数と搭載RAMに合わせて調整
5. PostgreSQL選択時は、実行ユーザー（`DEPLOY_USER`）と同名の`SUPERUSER LOGIN`ロールを構成
6. ONLYOFFICE Document ServerをJWT有効、内部8080番（`ONLYOFFICE_PORT`で変更可能）で導入
7. クローン済みリポジトリへ依存関係、フロントエンド、マイグレーションを適用
8. nginxでアプリ、ONLYOFFICE、Reverb、ONLYOFFICE内部ダウンロード経路を構成
9. SupervisorでRedisキュー10プロセス、Reverb、スケジューラを`www-data`として常駐
10. Certbotで証明書を発行し、`certbot.timer`とnginx reload hookを有効化
11. `unattended-upgrades`でnginxを含むUbuntuセキュリティ更新を日次適用
12. PHP 8.5、Redis、PostgreSQL、ONLYOFFICE、Supervisor、アプリのヘルスチェックを実行

nginxはUbuntuのAPTパッケージだけを使用します。

## macOSローカルOnlyOffice

macOSではLinux用のOnlyOfficeパッケージをインストールせず、Appleの`container`でDocumentServerを起動します。Apple siliconとmacOS 26以降が必要です。

1. [Apple Container](https://github.com/apple/container)をインストールします。
2. `brew install imagemagick`でImageMagickをインストールします。
3. Laravelアプリの`.env`を用意します。存在しない場合、`setup.sh`は`.env.example`をコピーします。
4. `APP_URL`を実際のローカルURLに合わせます。
5. リポジトリのルートでセットアップを実行します。macOSでは`--domain`と`--database`は不要です。

```bash
cd /path/to/chatterrow
./setup.sh
```

既存の`.env`全体を上書きすることはありません。更新対象は次のONLYOFFICE設定と、検出したImageMagickの絶対パスです。

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

### Valet・Herd・artisan serveの自動判定

`setup.sh`は`valet`と`herd`コマンドの有無、`APP_URL/up`、`127.0.0.1:<ポート>/up`の応答を確認して接続方法を選択します。Valet/Herd側とartisan serve側の両方が応答する場合は、`APP_URL`のValet/Herd側を優先します。

| 開発サーバー        | `.env`の`APP_URL`例      | 自動設定される`APP_ONLYOFFICE_INTERNAL_URL`   |
|---------------------|--------------------------|-----------------------------------------------|
| Laravel Valet       | `http://chatterrow.test` | `http://chatterrow.test`                      |
| Laravel Herd        | `http://chatterrow.test` | `http://chatterrow.test`                      |
| `php artisan serve` | `http://localhost:8000`  | `http://chatter-host.container.internal:8000` |

artisan serveを使う場合は、セットアップ前またはプレビューを開く前に起動します。

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

自動判定を上書きする場合:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

セットアップは以下を行います。

- `container system start`でApple Containerを起動
- `onlyoffice/documentserver:latest`を毎回pullし、arm64で起動
- OnlyOfficeを`127.0.0.1:8086`へ公開
- CPU 4、メモリ4 GB、共有メモリ2 GBを割り当て
- コンテナのDNSサーバーを`1.1.1.1`へ固定
- JWTを有効化し、`.env`へ共有秘密鍵を設定
- macOSで`setup.sh`を実行するたび、名前付きボリュームを残してOnlyOfficeコンテナを再作成
- `chatter-host.container.internal`を`203.0.113.150`経由でmacOSのloopbackへ接続
- Valet/Herdではアプリのホスト名をコンテナ内だけ`203.0.113.150`へ割り当て
- Source Han Sans JP／Noto Serif CJK JPを取得・検証し、OnlyOfficeのフォント一覧を再生成
- `ONLYOFFICE_DOCUMENT_SERVER_URL`、`ONLYOFFICE_PUBLIC_URL`、`APP_ONLYOFFICE_INTERNAL_URL`を更新
- DocumentServerとLaravelの`/up`をヘルスチェック

DNSサーバーを上書きする場合は環境変数を指定します。値はIPv4アドレスで指定してください。

```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

永続データには次の名前付きボリュームを使用します。

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

コンテナの確認・停止:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

ヘルスチェック:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### コンテナとボリュームの完全初期化テスト

次の操作はOnlyOffice内部のPostgreSQL、設定、キャッシュ、ログを削除します。必要なデータがある場合は実行しないでください。

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

[Apple Containerの名前付きボリューム](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management)は`container run --volume <名前>:<パス>`で存在しない場合に暗黙に作成されるため、上記手順でボリューム作成を含む初期構築を確認できます。

### 日本語フォント（macOS）

macOSホストへフォントをインストールしても、隔離されたOnlyOfficeコンテナからは使用できません。`setup.sh`は次の固定ウェイトフォントを公式リポジトリから固定バージョンでダウンロードし、SHA-256を検証してからOnlyOfficeへ登録します。

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold（2.005R、JP subset OTF）
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold（Serif2.003、Japanese OTF）

フォントは`chatterrow-onlyoffice-data`ボリューム内の`/var/www/onlyoffice/Data/custom-fonts`へ保存されます。セットアップを再実行した場合はコンテナ内ファイルのSHA-256を確認し、一致していれば再ダウンロードしません。

固定ウェイト版の登録後、`setup.sh`は既存の`AllFonts.js`と`font_selection.bin`を削除してから`allfontsgen`を実行します。既存ファイルを残すと`allfontsgen`がカタログを再利用し、新しいフォントを登録しない場合があるためです。

OnlyOffice 9.4のconverterは、Microsoft Officeの日本語テーマフォントを`NanumGothic`や`Droid Sans Fallback`へ置換する場合があります。fontconfigのaliasだけではこの変換経路に効きません。そのため[scripts/patch-onlyoffice-font-catalog.php](scripts/patch-onlyoffice-font-catalog.php)が、サーバー側`font_selection.bin`とブラウザ側2個の`AllFonts.js`へ次の補正を適用します。

- 游ゴシック、Yu Gothic、Meiryo、MS Gothic系の別名をSource Han Sans JPへ登録
- 游明朝、Yu Mincho、MS Mincho系の別名をNoto Serif CJK JPへ登録
- converterが選択した`NanumGothic`の実フォント参照をSource Han Sans JPへ変更
- converterが選択した`Droid Sans Fallback`の実フォント参照をNoto Serif CJK JPへ変更

このカタログはDOCX、XLSX、PPTXの変換とブラウザ表示で共用されます。補正後にJSキャッシュを生成し、docserviceとconverterを再起動してからDocumentServerのキャッシュを消去します。カタログ形式や必須フォント名が将来の`latest`で変わった場合は、誤ったカタログを使わずセットアップをエラー終了します。

標準の`documentserver-generate-allfonts.sh`は不要なプレゼンテーションテーマも再生成し、Apple Container環境ではその工程が終了しない場合があるため呼び出しません。

フォントカタログ更新時はOnlyOfficeの文書キャッシュ世代も変わるため、既に開いたDOCXも古い`Editor.bin`を再利用せず再変換されます。

既存コンテナが`Generating presentation themes`で応答しなくなっている場合も、そのまま`./setup.sh`を再実行してください。macOSでは実行のたびに`onlyoffice/documentserver:latest`をpullし、既存のOnlyOfficeコンテナを強制的に作り直します。上記4個の名前付きボリュームは削除しないため、OnlyOfficeの永続データは保持されます。

Microsoftの游明朝／游ゴシックは同梱・再配布しません。OnlyOfficeコンテナ内では次の代替設定を使用します。

| Office指定フォント（DOCX / XLSX / PPTX）    | 代替フォント       |
|---------------------------------------------|--------------------|
| 游明朝 / Yu Mincho / MS Mincho              | Noto Serif CJK JP  |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic | Source Han Sans JP |

文字欠けと不適切な欧文フォントへの置換は解消しますが、游フォントとの字幅差があるため改行位置やページ数まで完全一致する保証はありません。完全一致が必要なら、利用許諾を確認した游フォント実体をOnlyOfficeのカスタムフォント領域へ別途配置してください。

登録状態の確認:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

期待値は順に`1.1.1.1`、`NotoSerifCJKjp-Regular.otf`、`SourceHanSansJP-Light.otf`です。

macOSではOnlyOfficeの編集権限は付与せず、ReadOnlyプレビューのままです。DocumentServerの変換APIを利用する場合も、このReadOnly設定とは独立して使用できます。

## 非対話セットアップ

Ubuntuの自動化環境では`--domain`と`--database`が必須です。macOSローカルOnlyOfficeセットアップでは不要です。

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### オプション

| オプション                   | デフォルト                         | 説明                                              |
|------------------------------|------------------------------------|---------------------------------------------------|
| `--domain <domain>`          | 対話入力                           | アプリの公開ドメイン                              |
| `--email <email>`            | 空                                 | Let's Encrypt登録・期限通知メール                 |
| `--database <driver>`        | 対話時は`sqlite`                   | `sqlite`または`postgresql`                        |
| `--db-name <name>`           | `chatterrow`                       | アプリ用PostgreSQL DB名                           |
| `--db-user <name>`           | `chatterrow`                       | アプリ用PostgreSQLロール                          |
| `--db-password <password>`   | 自動生成                           | アプリ用PostgreSQLパスワード                      |
| `--app-dir <path>`           | `setup.sh`のあるリポジトリ         | `/home`または`/var/www`配下の配備先               |
| `--repo <url>`               | GitHub SSH URL                     | 配備するGitリポジトリ                             |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | macOSで毎回pullして使用するDocumentServerイメージ |
| `--sudo-nopasswd`            | off                                | パスワード無しsudoユーザー向け。`sudo -v`を省略    |
| `--no-ssl`                   | off                                | Certbotを省略しHTTPで構成                         |

同名の大文字環境変数も使用できます。例: `DOMAIN`、`DATABASE`、`DB_NAME`、`DB_USER`、`DB_PASSWORD`、`DEPLOY_USER`、`SUDO_NOPASSWD`、`ONLYOFFICE_PORT`、`ONLYOFFICE_JWT_SECRET`。

PostgreSQLパスワードを省略すると64桁のランダム値を生成し、次の場所へ保存します。

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

PostgreSQLを選択した場合、実行ユーザー（`DEPLOY_USER`、未指定時は一般ユーザー実行なら`id -un`、root実行なら`root`）と同名のPostgreSQLロールを`LOGIN SUPERUSER`として作成または更新します。アプリ接続用の`DB_USER`は別の非特権ロールとして維持されます。

## PostgreSQL自動調整

PostgreSQLはONLYOFFICEでも必要なため、アプリでSQLiteを選んだ場合もインストール・調整します。Ubuntu上に複数のPostgreSQLクラスタがある場合は、誤ったクラスタを変更しないようセットアップを停止します。

設定ファイル:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

主な計算基準:

| 設定                   | 基準                                              |
|------------------------|---------------------------------------------------|
| `shared_buffers`       | RAMの20%、128 MBから8 GBの範囲                    |
| `effective_cache_size` | RAMの60%、256 MBから64 GBの範囲                   |
| `maintenance_work_mem` | RAMの5%、64 MBから1 GBの範囲                      |
| `work_mem`             | RAM、`shared_buffers`、最大接続数から安全側に算出 |
| `max_connections`      | CPUとRAMから50から300の範囲で算出                 |
| parallel workers       | CPU数から算出し上限を設定                         |

このサーバーではPHP、ONLYOFFICE、Redis、RabbitMQも同居するため、PostgreSQL専用サーバーより保守的な割り当てです。再実行すると現在のCPU数とRAMから再計算されます。

## ポート構成

Ubuntu本番環境:

| ポート   | 用途                                 | 公開範囲           |
|----------|--------------------------------------|--------------------|
| 80 / 443 | nginx、Certbot、公開Web              | インターネット     |
| 8080     | ONLYOFFICE Document Server           | localhost向け      |
| 8081     | Laravel Reverb                       | localhost向け      |
| 8090     | ONLYOFFICEから署名済みファイルを取得 | 127.0.0.1のみ      |
| 5432     | PostgreSQL                           | ローカル接続を推奨 |

macOSローカル環境:

| ポート | 用途                                          | 公開範囲        |
|--------|-----------------------------------------------|-----------------|
| 8086   | Apple Container上のONLYOFFICE Document Server | `127.0.0.1`のみ |
| 8000   | `php artisan serve`の既定ポート               | `127.0.0.1`のみ |

Valet/Herd使用時、Laravelアプリは`APP_URL`のホスト名と通常のHTTP/HTTPSポートで動作します。artisan serveのポートは`APP_URL`または`MACOS_ARTISAN_PORT`から決定します。

## SSLと自動更新

Certbotはアプリドメインの証明書をnginxへ設定します。同じ証明書で`/onlyoffice/`も配信します。`certbot.timer`が更新時期を定期確認し、更新成功後にnginxをreloadします。セットアップ時にはdry-runも実行します。

`unattended-upgrades`はUbuntuのsecurity originを日次で確認し、`nginx`、`nginx-extras`、対応する`libnginx-mod-*`を依存関係ごと更新します。nginx以外のセキュリティ更新も維持します。通常の`-updates` pocketは自動適用しません。

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## 運用

### プロセス確認

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queueワーカーは`/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600`を10プロセスで実行します。10件すべてが`RUNNING`であることを確認してください。

### Markdown変換の再処理

失敗したファイルと、一定時間更新されていない`pending`／`processing`ファイルを再投入します。

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown`は旧Office形式（DOC、XLS、PPT、ODF）をMarkdown変換対象にしません。これらのONLYOFFICEプレビュー機能は引き続き利用できます。

### アプリ更新

同じ設定で`setup.sh`を再実行できます。既存のPostgreSQLパスワードとTLS有効nginx設定は保持され、Gitはfast-forward可能な場合だけ更新されます。

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### バックアップ

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

## 主な環境変数

| 変数                             | 説明                                                   |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | アプリの公開URL                                        |
| `DB_CONNECTION`                  | `sqlite`または`pgsql`                                  |
| `QUEUE_CONNECTION`               | Redisキュー（`redis`）                                 |
| `MARKITDOWN_PATH`                | MarkItDown CLIのパス。未指定時は`.markitdown/venv`内   |
| `MARKITDOWN_TIMEOUT`             | 1ファイルあたりの変換タイムアウト秒数                  |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | MarkItDown環境に必要なPythonの最低バージョン（3.10）   |
| `IMAGEMAGICK_PATH`               | `magick`または`convert`の絶対パス                     |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb認証情報                                         |
| `REVERB_HOST/PORT/SCHEME`        | ブラウザとLaravelが接続する公開WebSocket               |
| `REVERB_SERVER_HOST/PORT`        | Reverbの内部listen先。セットアップでは`127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Reverbへの接続を許可する公開ドメイン                   |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Laravelから接続するONLYOFFICE内部URL（Ubuntuでは127.0.0.1） |
| `ONLYOFFICE_PUBLIC_URL`           | ブラウザから見えるONLYOFFICE公開URL                    |
| `APP_ONLYOFFICE_INTERNAL_URL`    | ONLYOFFICEがファイルを取得する内部アプリURL            |
| `ONLYOFFICE_JWT_SECRET`          | ONLYOFFICEと共有するJWT秘密鍵                          |

## トラブルシューティング

| 症状                                 | 確認                                                                                                              |
|--------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`、`sudo nginx -t`                                                                 |
| リアルタイム更新されない             | `sudo supervisorctl status chatterrow-reverb`、ブラウザNetworkの`/app/`接続                                       |
| 添付プレビューが生成されない         | `/var/log/chatterrow-queue-error_*.log`、ONLYOFFICE/Poppler/ImageMagick                                           |
| `exec: convert: not found`            | `command -v magick`または`command -v convert`の絶対パスを`IMAGEMAGICK_PATH`へ設定後、`php artisan optimize:clear` |
| Markdown変換に失敗する               | `storage/logs/laravel.log`、`/var/log/chatterrow-queue-error_*.log`、`php artisan files:markdown`                 |
| Redisキューが処理されない            | `redis-cli ping`、`php8.5 -m`の`redis`、`sudo supervisorctl status 'chatterrow-queue:*'`                          |
| Officeプレビューが開かない（Ubuntu） | `curl http://127.0.0.1:8080/healthcheck`、JWT秘密鍵、8090番内部URL、`php artisan files:previews`                  |
| Officeプレビューが開かない（macOS）  | `curl http://127.0.0.1:8086/healthcheck`、JWT秘密鍵、`APP_ONLYOFFICE_INTERNAL_URL`、コンテナ内から`/up`への到達性 |
| PostgreSQLへ接続できない             | `.env`、`sudo -u postgres pg_isready`、`/etc/chatterrow/database-password`                                        |
| 証明書を発行できない                 | アプリドメインのA/AAAA、インターネットからの80番到達性、`/var/log/letsencrypt/`                                  |

## ローカル開発

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

別ターミナルでLaravelとReverbを起動します。

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Redis Serverが起動していない場合は、macOSでは`brew services start redis`、Ubuntuでは`sudo systemctl enable --now redis-server`を実行してください。

検証:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## ライセンス

MIT。ONLYOFFICE Docs Community Editionを導入する場合はAGPLv3の条件も確認してください。
