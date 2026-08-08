# 茶多楼 chatterrow

## 업무를 통합 관리할 수 있는 기능을 채팅에 담았습니다
<img width="100%" alt="Chatterrow サービス紹介" src="./assets/chatterrow-introduction.gif" />

<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


Laravel 13, Inertia 3, Svelte 5로 구축한 Discord 스타일 UI의 프로젝트형 그룹웨어입니다.

프로젝트별로 채널을 만들고 채팅, 작업, 파일, 간트 차트를 한곳에서 관리합니다. 실시간 전송에는 Laravel Reverb를, Office 파일의 읽기 전용 미리 보기에는 ONLYOFFICE Document Server를 사용합니다.

## 온프레미스 그룹웨어의 장점 (茶多楼는 App Server 부분의 서비스입니다)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## 주요 기능

- **프로젝트 관리**: 프로젝트 이름, 내용, 시작일, 종료일, 멤버 설정
- **채널**: 프로젝트 내 대화・작업・파일을 채널 단위로 정리
- **실시간 채팅**: Laravel Reverb를 통한 메시지, 스레드, 답글 수 동기화
- **안전한 Markdown**: HTML 이스케이프, HTTP(S) URL 제한, Shiki 코드 하이라이트
- **첨부 파일**: 파일／폴더 D&D, 10개 단위 업로드, 이미지・PDF・Office 썸네일
- **파일 미리 보기**: 이미지・PDF 중앙 뷰어, ONLYOFFICE를 통한 Office 미리 보기, Esc로 종료
- **Office/PDF Markdown 변환 저장**: 백그라운드에서 Markdown으로 변환하여 AI 학습에 활용하기 쉽게 합니다
- **작업 관리**: 시작일・시작 시각・종료일・종료 시각, 우선순위, 메모, 완료 상태
- **간트 차트**: 프로젝트 또는 채널 단위의 기간 표시
- **기한 알림**: 스케줄러와 큐 워커를 통한 자동 알림
- **테마**: 다크／라이트 모드 지원
- **키보드 조작**: 메시지 전송과 작업 생성은 `Cmd+Enter` 또는 `Ctrl+Enter`를 사용합니다. IME 확정 Enter는 전송하지 않습니다

## 기술 구성

| 레이어     | 기술                                                          |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite 또는 PostgreSQL                                        |
| Realtime   | Laravel Reverb（WebSocket）                                   |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7（PDF / DOCX / XLSX / PPTX）        |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition（JWT, 읽기 전용） |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot         |

## 운영 환경 요구 사항

- Ubuntu 24.04 LTS 또는 Ubuntu 26.04 LTS（amd64）
- PHP 8.5 CLI/FPM과 Redis 확장
- Python 3.10 이상, MarkItDown 0.1.7, Redis Server
- sudo를 사용할 수 있는 일반 사용자 또는 root 사용자
- 2 CPU, 2 GB RAM, 최소 30 GB의 여유 디스크（ONLYOFFICE 공식 권장 사항은 40 GB 이상）
- 4 GB 이상의 swap 권장
- 인터넷에서 TCP 80/443에 도달할 수 있도록 구성
- 애플리케이션용 DNS 이름

예:

```text
chat.example.com  A/AAAA -> 서버
```

ONLYOFFICE는 애플리케이션과 같은 도메인의 `/onlyoffice/`에서 공개합니다. ONLYOFFICE, Reverb, 애플리케이션 내부 취득에 사용하는 8080, 8081, 8090 포트는 외부에 공개하지 마세요. 클라우드 방화벽이나 호스트 측 방화벽에서는 SSH용 포트와 80/443만 허용합니다.

## Ubuntu 환경 자동 설정

새 Ubuntu Server에서 이 저장소를 가져와 `setup.sh`를 실행합니다. 도메인, 데이터베이스, Let's Encrypt 이메일 주소를 대화형으로 확인합니다. 일반 사용자 또는 root 사용자로 실행할 수 있습니다.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

로컬 개발・검증 환경이나 폐쇄망 등 인터넷에서 접근 가능한 공개 도메인과 HTTPS가 필요하지 않은 경우에는 `--no-ssl`을 붙여 실행하세요. Let's Encrypt를 사용하지 않고 HTTP만으로 구성합니다. `chatterrow.test`와 같은 로컬 도메인을 사용하는 경우 사전에 DNS 또는 `/etc/hosts`에서 이 서버로 이름이 해석되도록 설정하세요.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

`setup.sh` 실행 중 sudo 비밀번호를 묻습니다. sudo 비밀번호 없이 sudo를 사용할 수 있는 사용자는 아래 예와 같이 `--sudo-nopasswd` 옵션을 붙여 실행합니다.


```bash
./setup.sh --sudo-nopasswd
```

옵션 없이 실행했을 때 비밀번호가 필요 없는 sudo가 감지되면 설정을 시작하지 않고 `--sudo-nopasswd` 사용 방법을 표시한 뒤 종료합니다.

`setup.sh` 실행 시 입력 예:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
（생략）
PostgreSQL password (leave blank to generate):
```

ONLYOFFICE 공개 URL은 `https://<アプリドメイン>/onlyoffice`입니다. 위 예에서는 `https://chat.example.com/onlyoffice`입니다.

설정은 다음을 자동으로 실행합니다.

1. Ubuntu 공식 `nginx-extras`, PHP 8.5, PostgreSQL, Redis, RabbitMQ, Node.js 24 구성
2. PHP 확장, Poppler, ImageMagick, Ghostscript, 일본어 글꼴을 apt로 설치하고 ImageMagick 절대 경로를 `.env`에 설정
3. Python 가상 환경 `.markitdown/venv`에 MarkItDown 0.1.7을 구축하고 `pip check`와 CLI 버전 검증
4. CPU 수와 설치된 RAM에 맞춰 PostgreSQL 조정
5. PostgreSQL을 선택하면 실행 사용자（`DEPLOY_USER`）와 같은 이름의 `SUPERUSER LOGIN` 롤 구성
6. JWT를 활성화하고 내부 8080번（`ONLYOFFICE_PORT`로 변경 가능）에서 ONLYOFFICE Document Server 설치
7. 복제된 저장소에 의존성, 프런트엔드, 마이그레이션 적용
8. nginx에서 애플리케이션, ONLYOFFICE, Reverb, ONLYOFFICE 내부 다운로드 경로 구성
9. Supervisor로 Redis 큐 10개 프로세스, Reverb, 스케줄러를 `www-data`로 상시 실행
10. Certbot으로 인증서를 발급하고 `certbot.timer`와 nginx reload hook 활성화
11. `unattended-upgrades`로 nginx를 포함한 Ubuntu 보안 업데이트를 매일 적용
12. PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor, 애플리케이션 헬스 체크 실행

nginx는 Ubuntu의 APT 패키지만 사용합니다.

## macOS 로컬 OnlyOffice

macOS에서는 Linux용 OnlyOffice 패키지를 설치하지 않고 Apple의 `container`로 DocumentServer를 시작합니다. Apple silicon과 macOS 26 이상이 필요합니다.

1. [Apple Container](https://github.com/apple/container)를 설치합니다.
2. `brew install imagemagick`으로 ImageMagick을 설치합니다.
3. Laravel 애플리케이션의 `.env`를 준비합니다. 없으면 `setup.sh`가 `.env.example`을 복사합니다.
4. 실제 로컬 URL에 맞춰 `APP_URL`을 설정합니다.
5. 저장소 루트에서 설정을 실행합니다. macOS에서는 `--domain`과 `--database`가 필요하지 않습니다.

```bash
cd /path/to/chatterrow
./setup.sh
```

기존 `.env` 전체를 덮어쓰지는 않습니다. 다음 ONLYOFFICE 설정과 감지된 ImageMagick 절대 경로만 업데이트합니다.

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

### macOS 개발 시: Valet・Herd・artisan serve 자동 판별

`setup.sh`는 `valet`과 `herd` 명령의 존재 여부, `APP_URL/up`, `127.0.0.1:<ポート>/up`의 응답을 확인하여 연결 방법을 선택합니다. Valet/Herd 측과 artisan serve 측이 모두 응답하면 `APP_URL`의 Valet/Herd 측을 우선합니다.

| 개발 서버            | `.env`의 `APP_URL` 예       | 자동 설정되는 `APP_ONLYOFFICE_INTERNAL_URL`   |
|---------------------|----------------------------|-----------------------------------------------|
| Laravel Valet       | `http://chatterrow.test`   | `http://chatterrow.test`                      |
| Laravel Herd        | `http://chatterrow.test`   | `http://chatterrow.test`                      |
| `php artisan serve`  | `http://localhost:8000`    | `http://chatter-host.container.internal:8000` |

artisan serve를 사용하는 경우 설정 전 또는 미리 보기를 열기 전에 시작합니다.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

자동 판별을 덮어쓰는 경우:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

설정은 다음을 수행합니다.

- `container system start`로 Apple Container 시작
- 매번 `onlyoffice/documentserver:latest`를 pull하고 arm64로 시작
- OnlyOffice를 `127.0.0.1:8086`에 공개
- CPU 4, 메모리 4 GB, 공유 메모리 2 GB 할당
- 컨테이너의 DNS 서버를 `1.1.1.1`로 고정
- JWT를 활성화하고 공유 비밀 키를 `.env`에 설정
- macOS에서 `setup.sh`를 실행할 때마다 이름 있는 볼륨을 유지한 채 OnlyOffice 컨테이너 재생성
- `chatter-host.container.internal`을 `203.0.113.150`을 통해 macOS loopback에 연결
- Valet/Herd에서는 컨테이너 내부에서만 애플리케이션 호스트 이름을 `203.0.113.150`으로 지정
- Source Han Sans JP／Noto Serif CJK JP를 가져오고 검증한 뒤 OnlyOffice 글꼴 목록 재생성
- `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL`, `APP_ONLYOFFICE_INTERNAL_URL` 업데이트
- DocumentServer와 Laravel의 `/up` 헬스 체크

DNS 서버를 덮어쓰려면 환경 변수를 지정합니다. 값은 IPv4 주소로 지정하세요.

Cloudflare의 1.1.1.1을 Google의 8.8.8.8로 변경하여 실행하는 예:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

영속 데이터에는 다음 이름 있는 볼륨을 사용합니다.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

컨테이너 확인・중지:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

헬스 체크:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### 컨테이너와 볼륨 완전 초기화 테스트

다음 작업은 OnlyOffice 내부의 PostgreSQL, 설정, 캐시, 로그를 삭제합니다. 필요한 데이터가 있다면 실행하지 마세요.

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

[Apple Container의 이름 있는 볼륨](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management)은 존재하지 않을 때 `container run --volume <名前>:<パス>`로 암묵적으로 생성되므로 위 절차로 볼륨 생성을 포함한 초기 구축을 확인할 수 있습니다.

### 일본어 글꼴（macOS）

macOS 호스트에 글꼴을 설치해도 격리된 OnlyOffice 컨테이너에서는 사용할 수 없습니다. `setup.sh`는 공식 저장소에서 다음 고정 웨이트 글꼴을 고정 버전으로 다운로드하고 SHA-256을 검증한 뒤 OnlyOffice에 등록합니다.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold（2.005R, JP subset OTF）
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold（Serif2.003, Japanese OTF）

글꼴은 `chatterrow-onlyoffice-data` 볼륨 안의 `/var/www/onlyoffice/Data/custom-fonts`에 저장됩니다. 설정을 다시 실행하면 컨테이너 내부 파일의 SHA-256을 확인하고 일치하면 다시 다운로드하지 않습니다.

고정 웨이트 버전을 등록한 뒤 `setup.sh`는 기존 `AllFonts.js`와 `font_selection.bin`을 삭제하고 `allfontsgen`을 실행합니다. 기존 파일을 남겨 두면 `allfontsgen`이 카탈로그를 재사용하여 새 글꼴을 등록하지 않을 수 있습니다.

OnlyOffice 9.4의 converter는 Microsoft Office 일본어 테마 글꼴을 `NanumGothic`이나 `Droid Sans Fallback`으로 바꾸는 경우가 있습니다. fontconfig alias만으로는 이 변환 경로에 적용되지 않습니다. 따라서 [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php)가 서버 측 `font_selection.bin`과 브라우저 측 2개의 `AllFonts.js`에 다음 보정을 적용합니다.

- 游ゴシック, Yu Gothic, Meiryo, MS Gothic 계열의 별칭을 Source Han Sans JP에 등록
- 游明朝, Yu Mincho, MS Mincho 계열의 별칭을 Noto Serif CJK JP에 등록
- converter가 선택한 `NanumGothic`의 실제 글꼴 참조를 Source Han Sans JP로 변경
- converter가 선택한 `Droid Sans Fallback`의 실제 글꼴 참조를 Noto Serif CJK JP로 변경

이 카탈로그는 DOCX, XLSX, PPTX 변환과 브라우저 표시에서 공유됩니다. 보정 후 JS 캐시를 생성하고 docservice와 converter를 재시작한 다음 DocumentServer의 캐시를 삭제합니다. 향후 `latest`에서 카탈로그 형식이나 필수 글꼴 이름이 바뀌면 잘못된 카탈로그를 사용하지 않고 설정을 오류로 종료합니다.

표준 `documentserver-generate-allfonts.sh`는 불필요한 프레젠테이션 테마도 다시 생성하며 Apple Container 환경에서는 해당 단계가 끝나지 않을 수 있으므로 호출하지 않습니다.

글꼴 카탈로그를 업데이트하면 OnlyOffice 문서 캐시 세대도 바뀌므로 이미 열었던 DOCX도 이전 `Editor.bin`을 재사용하지 않고 다시 변환됩니다.

기존 컨테이너가 `Generating presentation themes`에서 응답하지 않게 된 경우에도 그대로 `./setup.sh`를 다시 실행하세요. macOS에서는 실행할 때마다 `onlyoffice/documentserver:latest`를 pull하고 기존 OnlyOffice 컨테이너를 강제로 다시 만듭니다. 위의 이름 있는 볼륨 4개는 삭제하지 않으므로 OnlyOffice의 영속 데이터는 유지됩니다.

Microsoft의 游明朝／游ゴシック은 포함하거나 재배포하지 않습니다. OnlyOffice 컨테이너 안에서는 다음 대체 설정을 사용합니다.

| Office 지정 글꼴（DOCX / XLSX / PPTX）           | 대체 글꼴           |
|---------------------------------------------|--------------------|
| 游明朝 / Yu Mincho / MS Mincho              | Noto Serif CJK JP  |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic | Source Han Sans JP |

문자 누락과 부적절한 서양 글꼴로의 대체는 해결되지만 游 글꼴과 글자 폭이 다르므로 줄바꿈 위치나 페이지 수까지 완전히 일치한다고 보장할 수 없습니다. 완전한 일치가 필요하면 사용 허가를 확인한 뒤 游 글꼴 파일을 OnlyOffice의 사용자 지정 글꼴 영역에 별도로 배치하세요.

등록 상태 확인:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

기대값은 순서대로 `1.1.1.1`, `NotoSerifCJKjp-Regular.otf`, `SourceHanSansJP-Light.otf`입니다.

macOS에서는 OnlyOffice에 편집 권한을 부여하지 않고 ReadOnly 미리 보기 상태로 유지합니다. DocumentServer의 변환 API를 사용하는 경우에도 이 ReadOnly 설정과 독립적으로 사용할 수 있습니다.

## 비대화형 설정

Ubuntu 자동화 환경에서는 `--domain`과 `--database`가 필수입니다. macOS 로컬 OnlyOffice 설정에서는 필요하지 않습니다.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### 옵션

| 옵션                        | 기본값                             | 설명                                              |
|----------------------------|------------------------------------|---------------------------------------------------|
| `--domain <domain>`          | 대화형 입력                        | 애플리케이션 공개 도메인                           |
| `--email <email>`            | 비어 있음                          | Let's Encrypt 등록・만료 알림 이메일               |
| `--database <driver>`        | 대화형일 때 `sqlite`               | `sqlite` 또는 `postgresql`                        |
| `--db-name <name>`           | `chatterrow`                       | 애플리케이션용 PostgreSQL DB 이름                  |
| `--db-user <name>`           | `chatterrow`                       | 애플리케이션용 PostgreSQL 롤                      |
| `--db-password <password>`   | 자동 생성                          | 애플리케이션용 PostgreSQL 비밀번호                 |
| `--app-dir <path>`           | `setup.sh`가 있는 저장소           | `/home` 또는 `/var/www` 아래 배포 위치             |
| `--repo <url>`               | GitHub SSH URL                     | 배포할 Git 저장소                                  |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | macOS에서 매번 pull하여 사용할 DocumentServer 이미지 |
| `--sudo-nopasswd`            | off                                | 비밀번호 없는 sudo 사용자용. `sudo -v` 생략        |
| `--no-ssl`                   | off                                | Certbot을 생략하고 HTTP로 구성                    |

같은 이름의 대문자 환경 변수도 사용할 수 있습니다. 예: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

PostgreSQL 비밀번호를 생략하면 64자리 무작위 값을 생성하여 다음 위치에 저장합니다.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

PostgreSQL을 선택하면 실행 사용자（`DEPLOY_USER`, 지정하지 않은 경우 일반 사용자 실행 시 `id -un`, root 실행 시 `root`）와 같은 이름의 PostgreSQL 롤을 `LOGIN SUPERUSER`로 생성하거나 업데이트합니다. 애플리케이션 연결에 사용하는 `DB_USER`는 별도의 비특권 롤로 유지합니다.

## PostgreSQL 자동 조정

ONLYOFFICE에도 PostgreSQL이 필요하므로 애플리케이션에서 SQLite를 선택한 경우에도 설치・조정합니다. Ubuntu에 PostgreSQL 클러스터가 여러 개 있으면 잘못된 클러스터를 변경하지 않도록 설정을 중지합니다.

설정 파일:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

주요 계산 기준:

| 설정                   | 기준                                              |
|------------------------|---------------------------------------------------|
| `shared_buffers`       | RAM의 20%, 128 MB에서 8 GB 범위                   |
| `effective_cache_size` | RAM의 60%, 256 MB에서 64 GB 범위                  |
| `maintenance_work_mem` | RAM의 5%, 64 MB에서 1 GB 범위                     |
| `work_mem`             | RAM, `shared_buffers`, 최대 연결 수에서 안전하게 계산 |
| `max_connections`      | CPU와 RAM으로 50에서 300 범위에서 계산            |
| parallel workers       | CPU 수로 계산하고 상한 설정                        |

이 서버에는 PHP, ONLYOFFICE, Redis, RabbitMQ도 함께 실행되므로 PostgreSQL 전용 서버보다 보수적으로 할당합니다. 다시 실행하면 현재 CPU 수와 RAM으로 다시 계산합니다.

## 포트 구성

Ubuntu 운영 환경:

| 포트     | 용도                                 | 공개 범위           |
|----------|--------------------------------------|--------------------|
| 80 / 443 | nginx, Certbot, 공개 Web              | 인터넷             |
| 8080     | ONLYOFFICE Document Server           | localhost용         |
| 8081     | Laravel Reverb                       | localhost용         |
| 8090     | ONLYOFFICE에서 서명된 파일 가져오기   | 127.0.0.1만         |
| 5432     | PostgreSQL                           | 로컬 연결 권장      |

macOS 로컬 환경:

| 포트 | 용도                                          | 공개 범위        |
|------|-----------------------------------------------|-----------------|
| 8086 | Apple Container의 ONLYOFFICE Document Server  | `127.0.0.1`만    |
| 8000 | `php artisan serve`의 기본 포트               | `127.0.0.1`만    |

Valet/Herd 사용 시 Laravel 애플리케이션은 `APP_URL`의 호스트 이름과 일반 HTTP/HTTPS 포트에서 실행됩니다. artisan serve 포트는 `APP_URL` 또는 `MACOS_ARTISAN_PORT`에서 결정합니다.

## SSL 및 자동 업데이트

Certbot은 `/var/www/letsencrypt`를 고정 ACME webroot로 사용하고 challenge를 Laravel에 전달하지 않고 nginx에 애플리케이션 도메인의 인증서를 설정합니다. `/onlyoffice/`도 같은 인증서로 제공합니다. `certbot.timer`가 갱신 시기를 정기적으로 확인하고 갱신 성공 후 nginx를 reload합니다. 설정 시 dry-run도 실행합니다.

`unattended-upgrades`는 Ubuntu의 security origin을 매일 확인하고 `nginx`, `nginx-extras`, 해당 `libnginx-mod-*`를 의존성과 함께 업데이트합니다. nginx 이외의 보안 업데이트도 유지합니다. 일반 `-updates` pocket은 자동 적용하지 않습니다.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## 운영

### 프로세스 확인

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queue 워커는 `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600`을 10개 프로세스로 실행합니다. 10개 모두 `RUNNING`인지 확인하세요.

### Markdown 변환 재처리

실패한 파일과 일정 시간 동안 갱신되지 않은 `pending`／`processing` 파일을 다시 큐에 넣습니다.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown`은 이전 Office 형식（DOC, XLS, PPT, ODF）을 Markdown 변환 대상으로 삼지 않습니다. 이러한 파일의 ONLYOFFICE 미리 보기 기능은 계속 사용할 수 있습니다.

### 애플리케이션 업데이트

같은 설정으로 `setup.sh`를 다시 실행할 수 있습니다. 기존 PostgreSQL 비밀번호와 TLS가 활성화된 nginx 설정은 유지되며 Git은 fast-forward가 가능한 경우에만 업데이트됩니다.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### 백업

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

## 주요 환경 변수

| 변수                             | 설명                                                   |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | 애플리케이션 공개 URL                                   |
| `DB_CONNECTION`                  | `sqlite` 또는 `pgsql`                                   |
| `QUEUE_CONNECTION`               | Redis 큐（`redis`）                                     |
| `MARKITDOWN_PATH`                | MarkItDown CLI 경로. 지정하지 않으면 `.markitdown/venv` 내부 |
| `MARKITDOWN_TIMEOUT`             | 파일당 변환 타임아웃 초                                 |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | MarkItDown 환경에 필요한 최소 Python 버전（3.10）       |
| `IMAGEMAGICK_PATH`               | `magick` 또는 `convert`의 절대 경로                     |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb 인증 정보                                        |
| `REVERB_HOST/PORT/SCHEME`        | 브라우저와 Laravel이 연결하는 공개 WebSocket           |
| `REVERB_SERVER_HOST/PORT`        | Reverb의 내부 listen 위치. 설정에서는 `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Reverb 연결을 허용하는 공개 도메인                      |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Laravel에서 연결하는 ONLYOFFICE 내부 URL（Ubuntu에서는 127.0.0.1） |
| `ONLYOFFICE_PUBLIC_URL`           | 브라우저에서 보이는 ONLYOFFICE 공개 URL                 |
| `APP_ONLYOFFICE_INTERNAL_URL`    | ONLYOFFICE가 파일을 가져오는 내부 애플리케이션 URL      |
| `ONLYOFFICE_JWT_SECRET`          | ONLYOFFICE와 공유하는 JWT 비밀 키                       |

## 문제 해결

| 증상                                 | 확인                                                                                                              |
|--------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                                 |
| 실시간 업데이트가 되지 않음          | `sudo supervisorctl status chatterrow-reverb`, 브라우저 Network의 `/app/` 연결                                   |
| 첨부 미리 보기가 생성되지 않음       | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                           |
| `exec: convert: not found`            | `command -v magick` 또는 `command -v convert`의 절대 경로를 `IMAGEMAGICK_PATH`에 설정한 후 `php artisan optimize:clear` |
| Markdown 변환 실패                   | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`                 |
| Redis 큐가 처리되지 않음              | `redis-cli ping`, `php8.5 -m`의 `redis`, `sudo supervisorctl status 'chatterrow-queue:*'`                          |
| Office 미리 보기가 열리지 않음（Ubuntu） | `curl http://127.0.0.1:8080/healthcheck`, JWT 비밀 키, 8090 내부 URL, `php artisan files:previews`              |
| Office 미리 보기가 열리지 않음（macOS）  | `curl http://127.0.0.1:8086/healthcheck`, JWT 비밀 키, `APP_ONLYOFFICE_INTERNAL_URL`, 컨테이너 내부에서 `/up`으로의 도달성 |
| PostgreSQL에 연결할 수 없음           | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                                        |
| 인증서를 발급할 수 없음               | 애플리케이션 도메인의 A/AAAA, 인터넷에서 80번 포트에 도달 가능한지, `/var/log/letsencrypt/`                     |

## 로컬 개발

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

별도 터미널에서 Laravel과 Reverb를 시작합니다.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Redis Server가 실행 중이 아니면 macOS에서는 `brew services start redis`, Ubuntu에서는 `sudo systemctl enable --now redis-server`를 실행하세요.

검증:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## 라이선스

MIT. ONLYOFFICE Docs Community Edition을 도입하는 경우 AGPLv3 조건도 확인하세요.
