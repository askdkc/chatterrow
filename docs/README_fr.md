# 茶多楼 chatterrow

## Des fonctionnalités de gestion des activités intégrées au chat pour une gestion unifiée
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/f7032613-194f-46b6-ac77-cfb1a4f2f1a3" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/619f867d-9598-4628-8e94-89eac10558d1" />
<img width="100%" alt="image" src="https://github.com/user-attachments/assets/981ccc86-ae70-4122-bfb3-93a6d01cce29" />


Construit avec Laravel 13, Inertia 3 et Svelte 5, il s'agit d'un logiciel de travail collaboratif organisé par projets, avec une interface de type Discord.

Créez des canaux pour chaque projet et gérez de manière centralisée les chats, les tâches, les fichiers et les diagrammes de Gantt. Laravel Reverb assure la diffusion en temps réel, tandis que ONLYOFFICE Document Server fournit un aperçu en lecture seule des fichiers Office.

## Avantages d'un logiciel de travail collaboratif on-premises (茶多楼 fournit le service de la partie App Server)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Fonctionnalités principales

- **Gestion des projets** : définir le nom et le contenu du projet, les dates de début et de fin, ainsi que les membres
- **Canaux** : organiser par canal les conversations, tâches et fichiers d'un projet
- **Chat en temps réel** : synchroniser les messages, les fils de discussion et le nombre de réponses avec Laravel Reverb
- **Markdown sécurisé** : échappement HTML, restriction des URL HTTP(S) et coloration syntaxique Shiki
- **Pièces jointes** : D&D de fichiers/dossiers, téléversements par lots de 10, miniatures d'images, de PDF et de fichiers Office
- **Aperçu des fichiers** : visionneuse centrale pour les images et les PDF, aperçu Office avec ONLYOFFICE, fermeture avec Esc
- **Conversion et enregistrement Office/PDF en Markdown** : convertir les fichiers en Markdown en arrière-plan pour faciliter leur utilisation dans l'entraînement de l'IA
- **Gestion des tâches** : date et heure de début, date et heure de fin, priorité, notes et état d'achèvement
- **Diagrammes de Gantt** : afficher les périodes par projet ou par canal
- **Rappels d'échéance** : notifications automatiques via le planificateur et les workers de file d'attente
- **Thèmes** : prise en charge des modes sombre et clair
- **Commandes clavier** : l'envoi des messages et la création de tâches utilisent `Cmd+Enter` ou `Ctrl+Enter`. La touche Entrée utilisée pour valider une saisie IME n'envoie pas le message

## Stack technique

| Couche     | Technologie                                                   |
|------------|---------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite ou PostgreSQL                                          |
| Realtime   | Laravel Reverb (WebSocket)                                    |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7 (PDF / DOCX / XLSX / PPTX)         |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition (JWT, lecture seule) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Prérequis de production

- Ubuntu 24.04 LTS ou Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM et l'extension Redis
- Python 3.10 ou version ultérieure, MarkItDown 0.1.7 et Redis Server
- Un utilisateur standard pouvant utiliser sudo, ou l'utilisateur root
- 2 CPU, 2 Go de RAM et au moins 30 Go d'espace disque libre (la recommandation officielle d'ONLYOFFICE est d'au moins 40 Go)
- Au moins 4 Go de swap sont recommandés
- Rendre les ports TCP 80/443 accessibles depuis Internet
- Un nom DNS pour l'application

Exemple :

```text
chat.example.com  A/AAAA -> serveur
```

ONLYOFFICE est publié sous `/onlyoffice/` sur le même domaine que l'application. N'exposez pas ONLYOFFICE, Reverb ni les ports 8080, 8081 et 8090 utilisés pour les accès internes de l'application vers l'extérieur. Dans les pare-feu cloud ou côté hôte, n'autorisez que le port SSH et 80/443.

## Installation automatique sur Ubuntu

Sur un nouveau serveur Ubuntu, récupérez ce dépôt et exécutez `setup.sh`. Le domaine, la base de données et l'adresse e-mail Let's Encrypt sont demandés de manière interactive. L'exécution est possible avec un utilisateur standard ou root.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

Pour les environnements de développement ou de validation locaux, les réseaux privés et les autres cas où un domaine public accessible depuis Internet et HTTPS ne sont pas nécessaires, exécutez la commande avec `--no-ssl`. Let's Encrypt ne sera pas utilisé et la configuration se fera uniquement en HTTP. Si vous utilisez un domaine local tel que `chatterrow.test`, configurez au préalable le DNS ou `/etc/hosts` afin que ce nom soit résolu vers ce serveur.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

Lors de l'exécution de `setup.sh`, le mot de passe sudo vous sera demandé. Si vous êtes un utilisateur autorisé à utiliser sudo sans mot de passe, exécutez la commande avec l'option `--sudo-nopasswd`, comme dans l'exemple ci-dessous.


```bash
./setup.sh --sudo-nopasswd
```

Si la commande est exécutée sans option et qu'un sudo sans mot de passe est détecté, l'installation ne démarre pas : elle se termine après avoir affiché comment utiliser `--sudo-nopasswd`.

Exemple de saisie lors de l'exécution de `setup.sh` :

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
(omission)
PostgreSQL password (leave blank to generate):
```

L'URL publique d'ONLYOFFICE est `https://<アプリドメイン>/onlyoffice`. Dans l'exemple ci-dessus, il s'agit de `https://chat.example.com/onlyoffice`.

L'installation exécute automatiquement les opérations suivantes :

1. Configurer `nginx-extras` officiel d'Ubuntu, PHP 8.5, PostgreSQL, Redis, RabbitMQ et Node.js 24
2. Installer avec apt les extensions PHP, Poppler, ImageMagick, Ghostscript et les polices japonaises, puis définir le chemin absolu d'ImageMagick dans `.env`
3. Installer MarkItDown 0.1.7 dans l'environnement virtuel Python `.markitdown/venv`, puis le vérifier avec `pip check` et la version de la CLI
4. Ajuster PostgreSQL selon le nombre de CPU et la quantité de RAM installée
5. Lorsque PostgreSQL est sélectionné, configurer un rôle `SUPERUSER LOGIN` portant le même nom que l'utilisateur d'exécution (`DEPLOY_USER`)
6. Installer ONLYOFFICE Document Server avec JWT activé sur le port interne 8080 (modifiable avec `ONLYOFFICE_PORT`)
7. Appliquer les dépendances, le frontend et les migrations au dépôt cloné
8. Configurer dans nginx l'application, ONLYOFFICE, Reverb et le chemin interne de téléchargement d'ONLYOFFICE
9. Maintenir sous Supervisor, en tant que `www-data`, 10 processus de file Redis, Reverb et le planificateur en fonctionnement permanent
10. Émettre un certificat avec Certbot et activer `certbot.timer` ainsi que le hook de rechargement de nginx
11. Appliquer quotidiennement avec `unattended-upgrades` les mises à jour de sécurité Ubuntu, y compris nginx
12. Exécuter les contrôles de santé de PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor et l'application

nginx utilise uniquement les paquets APT d'Ubuntu.

## OnlyOffice local sur macOS

Sur macOS, n'installez pas le paquet OnlyOffice destiné à Linux ; démarrez DocumentServer avec le `container` d'Apple. Apple silicon et macOS 26 ou version ultérieure sont requis.

1. Installez [Apple Container](https://github.com/apple/container).
2. Installez ImageMagick avec `brew install imagemagick`.
3. Préparez le `.env` de l'application Laravel. S'il n'existe pas, `setup.sh` copie `.env.example`.
4. Adaptez `APP_URL` à l'URL locale réelle.
5. Exécutez l'installation à la racine du dépôt. Sur macOS, `--domain` et `--database` ne sont pas nécessaires.

```bash
cd /path/to/chatterrow
./setup.sh
```

L'ensemble du `.env` existant n'est pas écrasé. La mise à jour concerne les paramètres ONLYOFFICE suivants ainsi que le chemin absolu détecté d'ImageMagick.

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

### Développement macOS : détection automatique de Valet, Herd et artisan serve

`setup.sh` vérifie la présence des commandes `valet` et `herd`, ainsi que les réponses de `APP_URL/up` et `127.0.0.1:<ポート>/up`, afin de choisir le mode de connexion. Si les côtés Valet/Herd et artisan serve répondent tous les deux, le côté Valet/Herd de `APP_URL` est prioritaire.

| Serveur de développement | Exemple de `APP_URL` dans `.env` | `APP_ONLYOFFICE_INTERNAL_URL` configurée automatiquement |
|--------------------------|----------------------------------|----------------------------------------------------------|
| Laravel Valet            | `http://chatterrow.test`         | `http://chatterrow.test`                                 |
| Laravel Herd             | `http://chatterrow.test`         | `http://chatterrow.test`                                 |
| `php artisan serve`      | `http://localhost:8000`          | `http://chatter-host.container.internal:8000`            |

Avec artisan serve, démarrez-le avant l'installation ou avant d'ouvrir un aperçu.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Pour remplacer la détection automatique :

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

L'installation effectue les opérations suivantes :

- Démarrer Apple Container avec `container system start`
- Télécharger `onlyoffice/documentserver:latest` à chaque exécution et le démarrer en arm64
- Publier OnlyOffice sur `127.0.0.1:8086`
- Allouer 4 CPU, 4 Go de mémoire et 2 Go de mémoire partagée
- Fixer le serveur DNS du conteneur à `1.1.1.1`
- Activer JWT et définir la clé secrète partagée dans `.env`
- Recréer le conteneur OnlyOffice à chaque exécution de `setup.sh` sur macOS tout en conservant les volumes nommés
- Connecter `chatter-host.container.internal` à la boucle locale de macOS via `203.0.113.150`
- Avec Valet/Herd, associer le nom d'hôte de l'application à `203.0.113.150` uniquement à l'intérieur du conteneur
- Télécharger et vérifier Source Han Sans JP / Noto Serif CJK JP, puis régénérer la liste des polices OnlyOffice
- Mettre à jour `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL` et `APP_ONLYOFFICE_INTERNAL_URL`
- Vérifier l'état de santé de DocumentServer et du `/up` de Laravel

Pour remplacer le serveur DNS, indiquez la variable d'environnement. La valeur doit être une adresse IPv4.

Exemple de remplacement du 1.1.1.1 de Cloudflare par le 8.8.8.8 de Google :
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

Les volumes nommés suivants sont utilisés pour les données persistantes.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Vérifier et arrêter le conteneur :

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

Contrôle de santé :

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### Test d'initialisation complète du conteneur et des volumes

Les opérations suivantes suppriment PostgreSQL interne, les paramètres, le cache et les journaux d'OnlyOffice. Ne les exécutez pas si des données sont nécessaires.

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

Les [volumes nommés d'Apple Container](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) sont créés implicitement lorsqu'ils n'existent pas avec `container run --volume <名前>:<パス>`. La procédure ci-dessus permet donc aussi de vérifier l'initialisation complète, y compris la création des volumes.

### Polices japonaises (macOS)

Installer des polices sur l'hôte macOS ne les rend pas disponibles dans le conteneur OnlyOffice isolé. `setup.sh` télécharge les polices à poids fixes suivantes depuis les dépôts officiels, à des versions figées, vérifie leur SHA-256, puis les enregistre dans OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, JP subset OTF)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, Japanese OTF)

Les polices sont enregistrées dans `/var/www/onlyoffice/Data/custom-fonts` du volume `chatterrow-onlyoffice-data`. Si l'installation est relancée, elle vérifie le SHA-256 des fichiers dans le conteneur et ne les télécharge pas à nouveau s'ils correspondent.

Après l'enregistrement des versions à poids fixes, `setup.sh` supprime `AllFonts.js` et `font_selection.bin` existants, puis exécute `allfontsgen`. Si les fichiers existants sont conservés, `allfontsgen` peut réutiliser le catalogue et ne pas enregistrer les nouvelles polices.

Le convertisseur d'OnlyOffice 9.4 peut remplacer les polices de thème japonaises de Microsoft Office par `NanumGothic` ou `Droid Sans Fallback`. Les alias fontconfig seuls n'agissent pas sur ce chemin de conversion. C'est pourquoi [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) applique les corrections suivantes au `font_selection.bin` côté serveur et aux deux fichiers `AllFonts.js` côté navigateur.

- Enregistrer les alias des familles 游ゴシック, Yu Gothic, Meiryo et MS Gothic vers Source Han Sans JP
- Enregistrer les alias des familles 游明朝, Yu Mincho et MS Mincho vers Noto Serif CJK JP
- Remplacer par Source Han Sans JP la référence réelle sélectionnée par le convertisseur pour `NanumGothic`
- Remplacer par Noto Serif CJK JP la référence réelle sélectionnée par le convertisseur pour `Droid Sans Fallback`

Ce catalogue est commun à la conversion DOCX, XLSX et PPTX ainsi qu'à l'affichage dans le navigateur. Après correction, un cache JS est généré, docservice et converter sont redémarrés, puis le cache de DocumentServer est supprimé. Si le format du catalogue ou les noms de polices requis changent dans un futur `latest`, l'installation se termine en erreur au lieu d'utiliser un catalogue incorrect.

Le `documentserver-generate-allfonts.sh` standard n'est pas appelé, car il régénère également des thèmes de présentation inutiles et cette étape peut ne pas se terminer dans l'environnement Apple Container.

Lors de la mise à jour du catalogue de polices, la génération du cache des documents OnlyOffice change également : les DOCX déjà ouverts sont donc reconvertis au lieu de réutiliser un ancien `Editor.bin`.

Si un conteneur existant ne répond plus à `Generating presentation themes`, relancez simplement `./setup.sh`. Sur macOS, chaque exécution télécharge `onlyoffice/documentserver:latest` et recrée de force le conteneur OnlyOffice existant. Les quatre volumes nommés ci-dessus ne sont pas supprimés : les données persistantes d'OnlyOffice sont donc conservées.

Les polices 游明朝 / 游ゴシック de Microsoft ne sont ni incluses ni redistribuées. Dans le conteneur OnlyOffice, les paramètres de remplacement suivants sont utilisés.

| Police indiquée par Office (DOCX / XLSX / PPTX)    | Police de remplacement |
|-----------------------------------------------------|------------------------|
| 游明朝 / Yu Mincho / MS Mincho                      | Noto Serif CJK JP      |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic         | Source Han Sans JP     |

Les caractères manquants et le remplacement par des polices latines inappropriées sont évités, mais les différences de largeur des caractères avec les polices 游 empêchent de garantir une correspondance parfaite des retours à la ligne et du nombre de pages. Si une correspondance parfaite est nécessaire, placez séparément les fichiers des polices 游, après vérification de leur licence, dans la zone de polices personnalisées d'OnlyOffice.

Vérifier l'état de l'enregistrement :

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

Les valeurs attendues sont respectivement `1.1.1.1`, `NotoSerifCJKjp-Regular.otf` et `SourceHanSansJP-Light.otf`.

Sur macOS, aucun droit de modification n'est accordé à OnlyOffice : l'aperçu reste en ReadOnly. L'API de conversion de DocumentServer peut également être utilisée indépendamment de ce réglage ReadOnly.

## Installation non interactive

Dans les environnements d'automatisation Ubuntu, `--domain` et `--database` sont obligatoires. Ils ne sont pas nécessaires pour l'installation locale d'OnlyOffice sur macOS.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Options

| Option                       | Valeur par défaut                 | Description                                           |
|------------------------------|------------------------------------|-------------------------------------------------------|
| `--domain <domain>`          | Saisie interactive                 | Domaine public de l'application                       |
| `--email <email>`            | Vide                               | Inscription Let's Encrypt et e-mail d'expiration      |
| `--database <driver>`        | `sqlite` en mode interactif       | `sqlite` ou `postgresql`                              |
| `--db-name <name>`           | `chatterrow`                       | Nom de la base PostgreSQL de l'application             |
| `--db-user <name>`           | `chatterrow`                       | Rôle PostgreSQL de l'application                       |
| `--db-password <password>`   | Généré automatiquement             | Mot de passe PostgreSQL de l'application               |
| `--app-dir <path>`           | Dépôt contenant `setup.sh`       | Chemin de déploiement sous `/home` ou `/var/www`      |
| `--repo <url>`               | URL SSH GitHub                     | Dépôt Git à déployer                                   |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest` | Image DocumentServer téléchargée et utilisée à chaque fois sur macOS |
| `--sudo-nopasswd`            | off                                | Pour les utilisateurs sudo sans mot de passe ; omet `sudo -v` |
| `--no-ssl`                   | off                                | Omet Certbot et configure HTTP                         |

Les variables d'environnement en majuscules portant le même nom peuvent également être utilisées. Exemples : `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

Si le mot de passe PostgreSQL est omis, une valeur aléatoire de 64 caractères est générée et enregistrée aux emplacements suivants.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

Si PostgreSQL est sélectionné, un rôle PostgreSQL portant le même nom que l'utilisateur d'exécution (`DEPLOY_USER`, ou `id -un` en cas d'exécution par un utilisateur standard si non indiqué, ou `root` en cas d'exécution en tant que root) est créé ou mis à jour avec `LOGIN SUPERUSER`. Le `DB_USER` utilisé pour la connexion de l'application reste un rôle non privilégié distinct.

## Ajustement automatique de PostgreSQL

PostgreSQL étant également nécessaire à ONLYOFFICE, il est installé et ajusté même si SQLite est choisi pour l'application. Si plusieurs clusters PostgreSQL sont présents sur Ubuntu, l'installation s'arrête afin d'éviter de modifier le mauvais cluster.

Fichier de configuration :

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Principaux critères de calcul :

| Paramètre              | Critère                                              |
|------------------------|------------------------------------------------------|
| `shared_buffers`       | 20 % de la RAM, de 128 Mo à 8 Go                     |
| `effective_cache_size` | 60 % de la RAM, de 256 Mo à 64 Go                   |
| `maintenance_work_mem` | 5 % de la RAM, de 64 Mo à 1 Go                      |
| `work_mem`             | Calcul prudent à partir de la RAM, de `shared_buffers` et du nombre maximal de connexions |
| `max_connections`      | Calculé à partir du CPU et de la RAM, entre 50 et 300 |
| parallel workers       | Calculés à partir du nombre de CPU, avec une limite |

PHP, ONLYOFFICE, Redis et RabbitMQ partageant également ce serveur, les allocations sont plus prudentes que sur un serveur dédié à PostgreSQL. Une nouvelle exécution recalcule les valeurs à partir du nombre actuel de CPU et de la RAM.

## Configuration des ports

Environnement de production Ubuntu :

| Port     | Utilisation                          | Exposition          |
|----------|--------------------------------------|---------------------|
| 80 / 443 | nginx, Certbot, Web public           | Internet            |
| 8080     | ONLYOFFICE Document Server           | localhost           |
| 8081     | Laravel Reverb                       | localhost           |
| 8090     | Récupération des fichiers signés depuis ONLYOFFICE | 127.0.0.1 uniquement |
| 5432     | PostgreSQL                           | Connexion locale recommandée |

Environnement local macOS :

| Port | Utilisation                                  | Exposition        |
|------|----------------------------------------------|-------------------|
| 8086 | ONLYOFFICE Document Server sur Apple Container | `127.0.0.1` uniquement |
| 8000 | Port par défaut de `php artisan serve`       | `127.0.0.1` uniquement |

Avec Valet/Herd, l'application Laravel fonctionne sur le nom d'hôte d'`APP_URL` et le port HTTP/HTTPS habituel. Le port d'artisan serve est déterminé à partir d'`APP_URL` ou de `MACOS_ARTISAN_PORT`.

## SSL et renouvellement automatique

Certbot utilise `/var/www/letsencrypt` comme webroot ACME fixe et configure dans nginx le certificat du domaine de l'application sans transmettre le challenge à Laravel. `/onlyoffice/` est également servi avec le même certificat. `certbot.timer` vérifie périodiquement si le renouvellement est nécessaire et recharge nginx après un renouvellement réussi. Un dry-run est également effectué lors de l'installation.

`unattended-upgrades` vérifie quotidiennement les origines de sécurité Ubuntu et met à jour `nginx`, `nginx-extras` et les paquets `libnginx-mod-*` correspondants avec leurs dépendances. Les mises à jour de sécurité autres que celles de nginx sont également maintenues. Le pocket normal `-updates` n'est pas appliqué automatiquement.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## Exploitation

### Vérification des processus

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Les workers de file exécutent `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` dans 10 processus. Vérifiez que les 10 sont à l'état `RUNNING`.

### Re-traitement des conversions Markdown

Les fichiers échoués ainsi que les fichiers `pending`/`processing` qui n'ont pas été mis à jour depuis un certain temps sont remis en file.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` ne prend pas en charge les anciens formats Office (DOC, XLS, PPT, ODF) pour la conversion Markdown. Leur fonction d'aperçu ONLYOFFICE reste disponible.

### Mise à jour de l'application

Vous pouvez réexécuter `setup.sh` avec les mêmes paramètres. Le mot de passe PostgreSQL existant et la configuration nginx avec TLS activé sont conservés, et Git n'est mis à jour que lorsqu'un fast-forward est possible.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Sauvegardes

SQLite :

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /home/ubuntu/chatterrow/database/database.sqlite \
    ".backup /backup/chatterrow-$(date +%F).sqlite"
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

PostgreSQL :

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatterrow > /backup/chatterrow-$(date +%F).dump
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

## Principales variables d'environnement

| Variable                         | Description                                            |
|----------------------------------|--------------------------------------------------------|
| `APP_URL`                        | URL publique de l'application                          |
| `DB_CONNECTION`                  | `sqlite` ou `pgsql`                                    |
| `QUEUE_CONNECTION`               | File Redis (`redis`)                                   |
| `MARKITDOWN_PATH`                | Chemin de la CLI MarkItDown ; dans `.markitdown/venv` s'il n'est pas indiqué |
| `MARKITDOWN_TIMEOUT`             | Délai d'expiration de conversion en secondes par fichier |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Version minimale de Python requise par l'environnement MarkItDown (3.10) |
| `IMAGEMAGICK_PATH`               | Chemin absolu vers `magick` ou `convert`               |
| `REVERB_APP_ID/KEY/SECRET`       | Identifiants Reverb                                     |
| `REVERB_HOST/PORT/SCHEME`        | WebSocket public auquel se connectent le navigateur et Laravel |
| `REVERB_SERVER_HOST/PORT`        | Adresse d'écoute interne de Reverb ; l'installation utilise `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Domaines publics autorisés à se connecter à Reverb     |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | URL interne d'ONLYOFFICE utilisée par Laravel (127.0.0.1 sur Ubuntu) |
| `ONLYOFFICE_PUBLIC_URL`           | URL publique d'ONLYOFFICE visible dans le navigateur    |
| `APP_ONLYOFFICE_INTERNAL_URL`    | URL interne de l'application utilisée par ONLYOFFICE pour récupérer les fichiers |
| `ONLYOFFICE_JWT_SECRET`          | Secret JWT partagé avec ONLYOFFICE                    |

## Dépannage

| Symptôme                              | Vérification                                                                                                  |
|---------------------------------------|----------------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                       | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                              |
| Les mises à jour en temps réel n'apparaissent pas | `sudo supervisorctl status chatterrow-reverb`, connexion `/app/` dans l'onglet Network du navigateur          |
| L'aperçu d'une pièce jointe n'est pas généré | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                      |
| `exec: convert: not found`            | Définir dans `IMAGEMAGICK_PATH` le chemin absolu obtenu avec `command -v magick` ou `command -v convert`, puis `php artisan optimize:clear` |
| La conversion Markdown échoue         | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`             |
| La file Redis n'est pas traitée       | `redis-cli ping`, `redis` dans `php8.5 -m`, `sudo supervisorctl status 'chatterrow-queue:*'`                 |
| L'aperçu Office ne s'ouvre pas (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, secret JWT, URL interne du port 8090, `php artisan files:previews` |
| L'aperçu Office ne s'ouvre pas (macOS)  | `curl http://127.0.0.1:8086/healthcheck`, secret JWT, `APP_ONLYOFFICE_INTERNAL_URL`, accessibilité de `/up` depuis le conteneur |
| Connexion à PostgreSQL impossible      | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                                   |
| Impossible d'émettre le certificat     | A/AAAA du domaine de l'application, accessibilité du port 80 depuis Internet, `/var/log/letsencrypt/`       |

## Développement local

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

Démarrez Laravel et Reverb dans des terminaux séparés.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Si Redis Server n'est pas démarré, exécutez `brew services start redis` sur macOS et `sudo systemctl enable --now redis-server` sur Ubuntu.

Vérification :

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## Licence

MIT. Si vous installez ONLYOFFICE Docs Community Edition, vérifiez également les conditions de l'AGPLv3.
