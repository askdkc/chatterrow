# 茶多楼 chatterrow

## Funktionen für die zentrale Geschäftsverwaltung direkt im Chat
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


Aufgebaut mit Laravel 13, Inertia 3 und Svelte 5, handelt es sich um eine projektbasierte Groupware mit einer Discord-ähnlichen Benutzeroberfläche.

Für jedes Projekt werden Kanäle angelegt, über die Chats, Aufgaben, Dateien und Gantt-Diagramme zentral verwaltet werden. Für die Echtzeitübertragung wird Laravel Reverb verwendet, für schreibgeschützte Vorschauen von Office-Dateien der ONLYOFFICE Document Server.

## Vorteile einer On-Premises-Groupware (茶多楼 stellt den Dienst für den App-Server-Teil bereit)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Hauptfunktionen

- **Projektverwaltung**: Projektname, Inhalt, Startdatum, Enddatum und Mitglieder festlegen
- **Kanäle**: Gespräche, Aufgaben und Dateien innerhalb eines Projekts nach Kanälen organisieren
- **Echtzeit-Chat**: Nachrichten, Threads und Antwortanzahlen mit Laravel Reverb synchronisieren
- **Sicheres Markdown**: HTML-Escaping, Einschränkung von HTTP(S)-URLs und Shiki-Code-Highlighting
- **Anhänge**: D&D von Dateien/Ordnern, Uploads in Gruppen von 10 sowie Miniaturen für Bilder, PDFs und Office-Dateien
- **Dateivorschau**: Zentraler Viewer für Bilder und PDFs, Office-Vorschau mit ONLYOFFICE, Beenden mit Esc
- **Office/PDF-Markdown-Konvertierung und Speicherung**: Im Hintergrund in Markdown umwandeln, damit die Dateien leichter für das KI-Training verwendet werden können
- **Aufgabenverwaltung**: Startdatum/-zeit, Enddatum/-zeit, Priorität, Notizen und Erledigungsstatus
- **Gantt-Diagramme**: Zeiträume nach Projekt oder Kanal anzeigen
- **Frist-Erinnerungen**: Automatische Benachrichtigungen durch Scheduler und Queue-Worker
- **Themes**: Unterstützung für den dunklen und hellen Modus
- **Tastatursteuerung**: Nachrichten senden und Aufgaben mit `Cmd+Enter` oder `Ctrl+Enter` erstellen. Enter zur Bestätigung einer IME-Eingabe sendet nicht

## Technischer Aufbau

| Schicht    | Technologie                                                   |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite oder PostgreSQL                                        |
| Realtime   | Laravel Reverb (WebSocket)                                    |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7 (PDF / DOCX / XLSX / PPTX)         |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition (JWT, schreibgeschützt) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Produktionsanforderungen

- Ubuntu 24.04 LTS oder Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM und die Redis-Erweiterung
- Python 3.10 oder höher, MarkItDown 0.1.7 und Redis Server
- Ein normaler Benutzer mit sudo-Berechtigung oder der Benutzer root
- 2 CPUs, 2 GB RAM und mindestens 30 GB freier Festplattenspeicher (die offizielle ONLYOFFICE-Empfehlung liegt bei mindestens 40 GB)
- Mindestens 4 GB Swap werden empfohlen
- TCP 80/443 aus dem Internet erreichbar machen
- Ein DNS-Name für die Anwendung

Beispiel:

```text
chat.example.com  A/AAAA -> Server
```

ONLYOFFICE wird unter `/onlyoffice/` auf derselben Domain wie die Anwendung veröffentlicht. ONLYOFFICE, Reverb sowie die internen Abruf-Ports 8080, 8081 und 8090 der Anwendung dürfen nicht nach außen veröffentlicht werden. In Cloud- oder Host-Firewalls dürfen nur der SSH-Port und 80/443 freigegeben werden.

## Automatische Einrichtung unter Ubuntu

Auf einem neuen Ubuntu Server dieses Repository abrufen und `setup.sh` ausführen. Domain, Datenbank und Let's-Encrypt-E-Mail-Adresse werden interaktiv abgefragt. Die Ausführung ist mit einem normalen Benutzer oder root möglich.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

Für lokale Entwicklungs- und Testumgebungen, abgeschottete Netzwerke und andere Fälle, in denen keine aus dem Internet erreichbare öffentliche Domain und kein HTTPS erforderlich sind, mit `--no-ssl` ausführen. Let's Encrypt wird nicht verwendet; die Einrichtung erfolgt ausschließlich über HTTP. Bei Verwendung einer lokalen Domain wie `chatterrow.test` muss zuvor über DNS oder `/etc/hosts` sichergestellt werden, dass diese Domain zu diesem Server aufgelöst wird.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

Bei der Ausführung von `setup.sh` wird nach dem sudo-Passwort gefragt. Wenn sudo ohne Passwort verwendet werden kann, die Einrichtung wie im folgenden Beispiel mit der Option `--sudo-nopasswd` ausführen.


```bash
./setup.sh --sudo-nopasswd
```

Wenn der Befehl ohne Optionen ausgeführt wird und passwortloses sudo erkannt wird, startet die Einrichtung nicht. Stattdessen wird die Verwendung von `--sudo-nopasswd` angezeigt und das Programm beendet.

Beispieleingabe bei der Ausführung von `setup.sh`:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
(ausgelassen)
PostgreSQL password (leave blank to generate):
```

Die öffentliche URL von ONLYOFFICE lautet `https://<アプリドメイン>/onlyoffice`. Im obigen Beispiel ist es `https://chat.example.com/onlyoffice`.

Die Einrichtung führt automatisch Folgendes aus:

1. Ubuntu's offizielles `nginx-extras`, PHP 8.5, PostgreSQL, Redis, RabbitMQ und Node.js 24 konfigurieren
2. PHP-Erweiterungen, Poppler, ImageMagick, Ghostscript und japanische Schriftarten mit apt installieren und den absoluten ImageMagick-Pfad in `.env` eintragen
3. MarkItDown 0.1.7 in der Python-Virtualenv `.markitdown/venv` einrichten und mit `pip check` sowie der CLI-Version prüfen
4. PostgreSQL entsprechend der CPU-Anzahl und dem installierten RAM optimieren
5. Bei Auswahl von PostgreSQL eine `SUPERUSER LOGIN`-Rolle mit demselben Namen wie der ausführende Benutzer (`DEPLOY_USER`) einrichten
6. ONLYOFFICE Document Server mit aktiviertem JWT auf dem internen Port 8080 installieren (mit `ONLYOFFICE_PORT` änderbar)
7. Abhängigkeiten, Frontend und Migrationen auf das geklonte Repository anwenden
8. Anwendung, ONLYOFFICE, Reverb und die interne Download-Route für ONLYOFFICE in nginx konfigurieren
9. Mit Supervisor 10 Redis-Queue-Prozesse, Reverb und den Scheduler dauerhaft als `www-data` ausführen
10. Mit Certbot ein Zertifikat ausstellen und `certbot.timer` sowie den nginx-Reload-Hook aktivieren
11. Ubuntu-Sicherheitsupdates einschließlich nginx täglich mit `unattended-upgrades` anwenden
12. Healthchecks für PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor und die Anwendung ausführen

nginx verwendet ausschließlich Ubuntu-APT-Pakete.

## Lokales OnlyOffice unter macOS

Unter macOS nicht das Linux-OnlyOffice-Paket installieren, sondern den DocumentServer mit Apples `container` starten. Apple silicon und macOS 26 oder höher sind erforderlich.

1. [Apple Container](https://github.com/apple/container) installieren.
2. ImageMagick mit `brew install imagemagick` installieren.
3. Die `.env`-Datei der Laravel-Anwendung vorbereiten. Falls sie nicht vorhanden ist, kopiert `setup.sh` `.env.example`.
4. `APP_URL` an die tatsächliche lokale URL anpassen.
5. Die Einrichtung im Repository-Root ausführen. Unter macOS sind `--domain` und `--database` nicht erforderlich.

```bash
cd /path/to/chatterrow
./setup.sh
```

Die vorhandene `.env` wird nicht vollständig überschrieben. Aktualisiert werden die folgenden ONLYOFFICE-Einstellungen sowie der erkannte absolute ImageMagick-Pfad.

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

### macOS-Entwicklung: automatische Erkennung von Valet, Herd und artisan serve

`setup.sh` prüft, ob die Befehle `valet` und `herd` vorhanden sind, sowie die Antworten von `APP_URL/up` und `127.0.0.1:<ポート>/up`, um die Verbindungsmethode auszuwählen. Wenn sowohl die Valet/Herd-Seite als auch die artisan-serve-Seite antworten, hat die Valet/Herd-Seite von `APP_URL` Vorrang.

| Entwicklungsserver      | Beispiel für `.env` `APP_URL`    | Automatisch gesetztes `APP_ONLYOFFICE_INTERNAL_URL` |
|-------------------------|----------------------------------|-----------------------------------------------------|
| Laravel Valet           | `http://chatterrow.test`         | `http://chatterrow.test`                            |
| Laravel Herd            | `http://chatterrow.test`         | `http://chatterrow.test`                            |
| `php artisan serve`     | `http://localhost:8000`          | `http://chatter-host.container.internal:8000`       |

Bei Verwendung von artisan serve diesen vor der Einrichtung oder vor dem Öffnen einer Vorschau starten.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

So wird die automatische Erkennung überschrieben:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

Die Einrichtung führt Folgendes aus:

- Apple Container mit `container system start` starten
- Bei jedem Durchlauf `onlyoffice/documentserver:latest` pullen und auf arm64 starten
- OnlyOffice unter `127.0.0.1:8086` veröffentlichen
- 4 CPUs, 4 GB Speicher und 2 GB Shared Memory zuweisen
- Den DNS-Server des Containers auf `1.1.1.1` festlegen
- JWT aktivieren und den gemeinsamen geheimen Schlüssel in `.env` setzen
- Bei jedem Aufruf von `setup.sh` unter macOS den OnlyOffice-Container neu erstellen, dabei benannte Volumes behalten
- `chatter-host.container.internal` über `203.0.113.150` mit dem macOS-Loopback verbinden
- Bei Valet/Herd den Hostnamen der Anwendung nur innerhalb des Containers `203.0.113.150` zuweisen
- Source Han Sans JP / Noto Serif CJK JP herunterladen und prüfen, anschließend die OnlyOffice-Schriftartenliste neu erzeugen
- `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL` und `APP_ONLYOFFICE_INTERNAL_URL` aktualisieren
- DocumentServer und Laravels `/up` einem Healthcheck unterziehen

Zum Überschreiben des DNS-Servers eine Umgebungsvariable angeben. Der Wert muss als IPv4-Adresse angegeben werden.

Beispiel für die Änderung von Cloudflares 1.1.1.1 zu Googles 8.8.8.8:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

Für dauerhafte Daten werden die folgenden benannten Volumes verwendet.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Container prüfen und stoppen:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

Healthcheck:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### Vollständiger Initialisierungstest für Container und Volumes

Die folgenden Aktionen löschen die interne PostgreSQL-Datenbank, Einstellungen, den Cache und die Logs von OnlyOffice. Nicht ausführen, wenn benötigte Daten vorhanden sind.

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

[Benannte Volumes von Apple Container](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) werden mit `container run --volume <名前>:<パス>` implizit erstellt, wenn sie nicht vorhanden sind. Mit dem obigen Ablauf lässt sich daher auch die vollständige Ersteinrichtung einschließlich der Volume-Erstellung prüfen.

### Japanische Schriftarten (macOS)

Auf dem macOS-Host installierte Schriftarten können vom isolierten OnlyOffice-Container nicht verwendet werden. `setup.sh` lädt die folgenden Schriftarten mit festgelegten Schnitten aus den offiziellen Repositories in festgelegten Versionen herunter, prüft SHA-256 und registriert sie anschließend bei OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, JP subset OTF)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, Japanese OTF)

Die Schriftarten werden im Volume `chatterrow-onlyoffice-data` unter `/var/www/onlyoffice/Data/custom-fonts` gespeichert. Bei einer erneuten Einrichtung werden die SHA-256-Werte der Dateien im Container geprüft. Stimmen sie überein, werden die Dateien nicht erneut heruntergeladen.

Nach der Registrierung der Versionen mit festgelegten Schnitten löscht `setup.sh` die vorhandenen `AllFonts.js` und `font_selection.bin` und führt anschließend `allfontsgen` aus. Wenn die vorhandenen Dateien erhalten bleiben, kann `allfontsgen` den Katalog wiederverwenden und die neuen Schriftarten nicht registrieren.

Der Converter von OnlyOffice 9.4 kann japanische Microsoft-Office-Theme-Schriftarten durch `NanumGothic` oder `Droid Sans Fallback` ersetzen. Alleinige fontconfig-Aliase wirken in diesem Konvertierungspfad nicht. Deshalb wendet [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) die folgenden Korrekturen auf das serverseitige `font_selection.bin` und die beiden browserseitigen `AllFonts.js` an.

- Aliase für die Familien 游ゴシック, Yu Gothic, Meiryo und MS Gothic bei Source Han Sans JP registrieren
- Aliase für die Familien 游明朝, Yu Mincho und MS Mincho bei Noto Serif CJK JP registrieren
- Den vom Converter für `NanumGothic` ausgewählten tatsächlichen Font-Verweis auf Source Han Sans JP ändern
- Den vom Converter für `Droid Sans Fallback` ausgewählten tatsächlichen Font-Verweis auf Noto Serif CJK JP ändern

Dieser Katalog wird gemeinsam für die Konvertierung von DOCX, XLSX und PPTX sowie für die Darstellung im Browser verwendet. Nach der Korrektur wird ein JS-Cache erzeugt, docservice und converter werden neu gestartet und anschließend der DocumentServer-Cache gelöscht. Wenn sich das Katalogformat oder die erforderlichen Font-Namen in einem zukünftigen `latest` ändern, beendet sich die Einrichtung mit einem Fehler, anstatt einen falschen Katalog zu verwenden.

Das standardmäßige `documentserver-generate-allfonts.sh` wird nicht aufgerufen, weil es zusätzlich unnötige Präsentationsthemes neu erzeugt und dieser Vorgang in der Apple-Container-Umgebung möglicherweise nicht beendet wird.

Bei einer Aktualisierung des Font-Katalogs ändert sich auch die Generation des OnlyOffice-Dokumentencaches. Bereits geöffnete DOCX-Dateien werden daher neu konvertiert und verwenden nicht erneut ein altes `Editor.bin`.

Wenn ein vorhandener Container bei `Generating presentation themes` nicht mehr reagiert, einfach `./setup.sh` erneut ausführen. Unter macOS wird bei jedem Durchlauf `onlyoffice/documentserver:latest` gepullt und der vorhandene OnlyOffice-Container zwangsweise neu erstellt. Die vier oben genannten benannten Volumes werden nicht gelöscht, daher bleiben die persistenten OnlyOffice-Daten erhalten.

Microsofts 游明朝 / 游ゴシック werden weder mitgeliefert noch weiterverteilt. Im OnlyOffice-Container werden die folgenden Ersatzkonfigurationen verwendet.

| Von Office angegebener Font (DOCX / XLSX / PPTX)     | Ersatzfont             |
|-------------------------------------------------------|------------------------|
| 游明朝 / Yu Mincho / MS Mincho                        | Noto Serif CJK JP      |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic            | Source Han Sans JP     |

Fehlende Zeichen und die Ersetzung durch ungeeignete lateinische Schriftarten werden behoben. Aufgrund der unterschiedlichen Zeichenbreiten gegenüber den 游-Fonts kann jedoch nicht garantiert werden, dass Zeilenumbrüche oder Seitenzahlen vollständig übereinstimmen. Wenn eine vollständige Übereinstimmung erforderlich ist, die lizenzierten Originaldateien der 游-Fonts nach Prüfung der Nutzungsbedingungen separat im benutzerdefinierten Font-Bereich von OnlyOffice ablegen.

Registrierung prüfen:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

Die erwarteten Werte sind der Reihe nach `1.1.1.1`, `NotoSerifCJKjp-Regular.otf` und `SourceHanSansJP-Light.otf`.

Unter macOS werden OnlyOffice keine Bearbeitungsrechte erteilt; die Vorschau bleibt ReadOnly. Die Konvertierungs-API des DocumentServer kann unabhängig von dieser ReadOnly-Einstellung verwendet werden.

## Nicht interaktive Einrichtung

In Ubuntu-Automatisierungsumgebungen sind `--domain` und `--database` erforderlich. Bei der lokalen macOS-OnlyOffice-Einrichtung sind sie nicht erforderlich.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Optionen

| Option                       | Standardwert                       | Beschreibung                                           |
|------------------------------|------------------------------------|-------------------------------------------------------|
| `--domain <domain>`          | Interaktive Eingabe                | Öffentliche Domain der Anwendung                      |
| `--email <email>`            | Leer                               | Let's-Encrypt-Registrierung und Ablaufbenachrichtigung |
| `--database <driver>`        | Bei interaktiver Eingabe `sqlite`  | `sqlite` oder `postgresql`                            |
| `--db-name <name>`           | `chatterrow`                       | PostgreSQL-Datenbankname der Anwendung                |
| `--db-user <name>`           | `chatterrow`                       | PostgreSQL-Rolle der Anwendung                         |
| `--db-password <password>`   | Automatisch generiert              | PostgreSQL-Passwort der Anwendung                      |
| `--app-dir <path>`           | Repository mit `setup.sh`         | Bereitstellungspfad unter `/home` oder `/var/www`     |
| `--repo <url>`               | GitHub-SSH-URL                     | Bereitzustellendes Git-Repository                      |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | DocumentServer-Image, das unter macOS bei jedem Durchlauf gepullt und verwendet wird |
| `--sudo-nopasswd`            | off                                | Für Benutzer mit passwortlosem sudo; überspringt `sudo -v` |
| `--no-ssl`                   | off                                | Certbot überspringen und HTTP konfigurieren            |

Die gleichnamigen Umgebungsvariablen in Großbuchstaben können ebenfalls verwendet werden. Beispiele: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

Wenn das PostgreSQL-Passwort weggelassen wird, wird ein zufälliger Wert mit 64 Zeichen erzeugt und an den folgenden Orten gespeichert.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

Bei Auswahl von PostgreSQL wird eine PostgreSQL-Rolle mit demselben Namen wie der ausführende Benutzer (`DEPLOY_USER`, falls nicht angegeben bei Ausführung durch einen normalen Benutzer `id -un`, bei Ausführung als root `root`) als `LOGIN SUPERUSER` erstellt oder aktualisiert. Der für die Anwendungsverbindung verwendete `DB_USER` bleibt eine separate nicht privilegierte Rolle.

## Automatische PostgreSQL-Optimierung

PostgreSQL wird auch von ONLYOFFICE benötigt und daher selbst bei Auswahl von SQLite für die Anwendung installiert und optimiert. Wenn unter Ubuntu mehrere PostgreSQL-Cluster vorhanden sind, wird die Einrichtung beendet, damit nicht der falsche Cluster geändert wird.

Konfigurationsdatei:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Wichtige Berechnungsgrundlagen:

| Einstellung             | Grundlage                                            |
|-------------------------|------------------------------------------------------|
| `shared_buffers`        | 20 % des RAM, im Bereich von 128 MB bis 8 GB         |
| `effective_cache_size`  | 60 % des RAM, im Bereich von 256 MB bis 64 GB        |
| `maintenance_work_mem`  | 5 % des RAM, im Bereich von 64 MB bis 1 GB           |
| `work_mem`              | Vorsichtig aus RAM, `shared_buffers` und maximaler Verbindungszahl berechnet |
| `max_connections`       | Aus CPU und RAM berechnet, im Bereich von 50 bis 300 |
| parallel workers        | Aus der CPU-Anzahl berechnet und begrenzt             |

Da PHP, ONLYOFFICE, Redis und RabbitMQ ebenfalls auf diesem Server laufen, ist die Zuweisung konservativer als bei einem reinen PostgreSQL-Server. Bei einer erneuten Ausführung wird aus der aktuellen CPU-Anzahl und dem aktuellen RAM neu berechnet.

## Portkonfiguration

Ubuntu-Produktionsumgebung:

| Port     | Zweck                                 | Öffnungsbereich      |
|----------|---------------------------------------|----------------------|
| 80 / 443 | nginx, Certbot, öffentliches Web      | Internet             |
| 8080     | ONLYOFFICE Document Server            | Für localhost        |
| 8081     | Laravel Reverb                        | Für localhost        |
| 8090     | Signierte Dateien von ONLYOFFICE abrufen | Nur 127.0.0.1      |
| 5432     | PostgreSQL                            | Lokale Verbindung empfohlen |

Lokale macOS-Umgebung:

| Port | Zweck                                           | Öffnungsbereich  |
|------|-------------------------------------------------|------------------|
| 8086 | ONLYOFFICE Document Server auf Apple Container | Nur `127.0.0.1` |
| 8000 | Standardport von `php artisan serve`           | Nur `127.0.0.1` |

Bei Verwendung von Valet/Herd läuft die Laravel-Anwendung unter dem Hostnamen von `APP_URL` und dem normalen HTTP/HTTPS-Port. Der Port von artisan serve wird aus `APP_URL` oder `MACOS_ARTISAN_PORT` bestimmt.

## SSL und automatische Erneuerung

Certbot verwendet `/var/www/letsencrypt` als festes ACME-Webroot und richtet das Zertifikat der Anwendungsdomain in nginx ein, ohne die Challenge an Laravel weiterzugeben. `/onlyoffice/` wird mit demselben Zertifikat ausgeliefert. `certbot.timer` prüft regelmäßig, wann eine Erneuerung fällig ist, und lädt nginx nach erfolgreicher Erneuerung neu. Während der Einrichtung wird ebenfalls ein Dry-Run ausgeführt.

`unattended-upgrades` prüft täglich die Ubuntu-Sicherheitsquellen und aktualisiert `nginx`, `nginx-extras` sowie die zugehörigen `libnginx-mod-*` mit ihren Abhängigkeiten. Auch Sicherheitsupdates außerhalb von nginx werden beibehalten. Das normale `-updates`-Pocket wird nicht automatisch angewendet.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## Betrieb

### Prozessprüfung

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Queue-Worker führen `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` in 10 Prozessen aus. Prüfen, dass alle 10 den Status `RUNNING` haben.

### Markdown-Konvertierungen erneut verarbeiten

Fehlgeschlagene Dateien sowie `pending`-/`processing`-Dateien, die seit einer bestimmten Zeit nicht aktualisiert wurden, werden erneut in die Queue gestellt.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` berücksichtigt alte Office-Formate (DOC, XLS, PPT, ODF) nicht für die Markdown-Konvertierung. Die ONLYOFFICE-Vorschaufunktion für diese Formate bleibt verfügbar.

### Anwendung aktualisieren

`setup.sh` kann mit denselben Einstellungen erneut ausgeführt werden. Das vorhandene PostgreSQL-Passwort und die aktivierte TLS-nginx-Konfiguration bleiben erhalten, und Git wird nur aktualisiert, wenn ein Fast-Forward möglich ist.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Backups

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

## Wichtige Umgebungsvariablen

| Variable                         | Beschreibung                                           |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | Öffentliche URL der Anwendung                          |
| `DB_CONNECTION`                  | `sqlite` oder `pgsql`                                  |
| `QUEUE_CONNECTION`               | Redis-Queue (`redis`)                                  |
| `MARKITDOWN_PATH`                | Pfad zur MarkItDown-CLI; bei Nichtangabe innerhalb von `.markitdown/venv` |
| `MARKITDOWN_TIMEOUT`             | Zeitüberschreitung der Konvertierung pro Datei in Sekunden |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Für die MarkItDown-Umgebung erforderliche minimale Python-Version (3.10) |
| `IMAGEMAGICK_PATH`               | Absoluter Pfad zu `magick` oder `convert`              |
| `REVERB_APP_ID/KEY/SECRET`       | Reverb-Zugangsdaten                                    |
| `REVERB_HOST/PORT/SCHEME`        | Öffentliches WebSocket, mit dem Browser und Laravel verbunden werden |
| `REVERB_SERVER_HOST/PORT`        | Interne Listen-Adresse von Reverb; die Einrichtung verwendet `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Öffentliche Domains, die Verbindungen zu Reverb herstellen dürfen |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | Interne ONLYOFFICE-URL, die Laravel verwendet (unter Ubuntu 127.0.0.1) |
| `ONLYOFFICE_PUBLIC_URL`           | Im Browser sichtbare öffentliche ONLYOFFICE-URL        |
| `APP_ONLYOFFICE_INTERNAL_URL`    | Interne Anwendungs-URL, über die ONLYOFFICE Dateien abruft |
| `ONLYOFFICE_JWT_SECRET`          | Mit ONLYOFFICE gemeinsam verwendetes JWT-Geheimnis    |

## Fehlerbehebung

| Symptom                              | Prüfung                                                                                                       |
|--------------------------------------|----------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                              |
| Echtzeitaktualisierungen erscheinen nicht | `sudo supervisorctl status chatterrow-reverb`, die `/app/`-Verbindung im Network-Panel des Browsers       |
| Anhangvorschau wird nicht erzeugt    | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                         |
| `exec: convert: not found`           | Den absoluten Pfad aus `command -v magick` oder `command -v convert` in `IMAGEMAGICK_PATH` setzen, danach `php artisan optimize:clear` |
| Markdown-Konvertierung schlägt fehl  | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`              |
| Redis-Queue wird nicht verarbeitet    | `redis-cli ping`, `redis` in `php8.5 -m`, `sudo supervisorctl status 'chatterrow-queue:*'`                    |
| Office-Vorschau öffnet sich nicht (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, JWT-Geheimnis, interne URL von Port 8090, `php artisan files:previews` |
| Office-Vorschau öffnet sich nicht (macOS)  | `curl http://127.0.0.1:8086/healthcheck`, JWT-Geheimnis, `APP_ONLYOFFICE_INTERNAL_URL`, Erreichbarkeit von `/up` aus dem Container |
| Verbindung zu PostgreSQL nicht möglich | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                                    |
| Zertifikat kann nicht ausgestellt werden | A/AAAA der Anwendungsdomain, Erreichbarkeit von Port 80 aus dem Internet, `/var/log/letsencrypt/`          |

## Lokale Entwicklung

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

Laravel und Reverb in separaten Terminals starten.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Wenn Redis Server nicht läuft, unter macOS `brew services start redis` und unter Ubuntu `sudo systemctl enable --now redis-server` ausführen.

Überprüfung:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## Lizenz

MIT. Bei Installation der ONLYOFFICE Docs Community Edition zusätzlich die Bedingungen der AGPLv3 prüfen.
