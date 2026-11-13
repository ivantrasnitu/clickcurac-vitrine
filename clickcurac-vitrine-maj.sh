#!/usr/bin/env bash
# Mise à jour automatique du fichier ClickCurac (index.php, .htaccess) sur les domaines des restaurants choisis.
#
# Chaque ligne de /etc/clickcurac-vitrine.conf (root seulement, chmod 600) : <utilisateur> <version> [<dossier du site>]
#   cafechezgaston  main              → version standard (la dernière)
#   cafedutemple    v2                → version figée (étiquette du dépôt)
#   cafelebistro    special-bistro    → version spéciale (branche du dépôt pour ce restaurant)
#   cafepierre      -                 → jamais mis à jour automatiquement
# Dossier du site : /home/<utilisateur>/public_html par défaut.
#
# Sécurité :
# - chaque fichier doit porter la signature de ClickCurac (clé publique ci-dessous) : un dépôt GitHub piraté ne peut
#   rien installer ;
# - les fichiers du site sont écrits au nom de l'utilisateur du site, jamais en root (un site piraté ne peut pas
#   détourner l'outil vers les fichiers du serveur) ;
# - l'ancien fichier est gardé dans /home/<utilisateur>/clickcurac-sauvegardes/ ; rien ne change s'il est identique.
# Cet outil ne se met jamais à jour lui-même.
# Lancer à la main : sudo clickcurac-vitrine-maj   (option --essai : montre sans rien changer)
set -euo pipefail
umask 077

CONF=/etc/clickcurac-vitrine.conf
DEPOT=https://raw.githubusercontent.com/ivantrasnitu/clickcurac-vitrine
PROTO='=https'
# Essais seulement (dépôt sur la machine même) : CLICKCURAC_DEPOT=http://127.0.0.1:…
if [[ "${CLICKCURAC_DEPOT:-}" =~ ^http://127\.0\.0\.1:[0-9]+$ ]]; then DEPOT=$CLICKCURAC_DEPOT; PROTO='=http'; fi
ESSAI=${1:-}
[ "$(id -u)" = 0 ] || { echo "À lancer en root."; exit 1; }
[ -f "$CONF" ] || { echo "Pas de $CONF : rien à faire."; exit 0; }
# Le fichier de réglages ne doit être modifiable que par root.
[ "$(stat -c '%u' "$CONF")" = 0 ] && [ "$(( 0$(stat -c '%a' "$CONF") & 022 ))" = 0 ] || { echo "$CONF doit appartenir à root et ne pas être modifiable par d'autres (chmod 600)."; exit 1; }

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cat > "$TMP/cle.pem" <<'CLE'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAv/p9WYxr3y2gsb9EQmKd
1lvKwn3VfNPRU3cbAVRwYzJRqAmks2hRog+YI1kHydbF8hBUgdiZaNBl4LKqVgaj
6KkLiKS17eyn8SxNEm5kW2gJGHuWauNtSxgQMjNx3dchwlYNjeYn+dktM6FatLC0
63LGHRNomFeYhL98R8Y59CK6RI2GzyQMdCPsRh5cTIGH4nPv5WCjUBS6fohP7Dt7
wyuMZsV3x5pmoLvvX8kVw5cTV4RK3KAL0n1bU0BK5FdPF/kGOX8hlnQMsqtW0R8+
9hjLq4I37S9VOdmQX6R95haZmXPavs8zZId2acnCZ4u89J0+17QPIp0WDQFKJ3uK
3HOfYK8sMazwEDUHN27o15C8oopkOkpHX/NQs9GE06xyqi63bILgcWtwaZrkg5rw
zso/jiN3wB0n9hG0rFs6aByycc19gsqjLvWErbmTw4z4o4WxgX152BB2HE+kBAX3
MXg0hiVnRRhRvIOfuMKXd2D08yrt/6A2dzlhCtUrng83AgMBAAE=
-----END PUBLIC KEY-----
CLE

while read -r USER_SITE VERSION DOSSIER _; do
  [[ -z "${USER_SITE:-}" || "$USER_SITE" == \#* || "${VERSION:-}" == "-" ]] && continue
  [[ "$USER_SITE" =~ ^[a-z0-9._-]+$ && "$VERSION" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Ligne ignorée : $USER_SITE ${VERSION:-}"; continue; }
  id "$USER_SITE" >/dev/null 2>&1 && [ "$(id -u "$USER_SITE")" != 0 ] || { echo "$USER_SITE : utilisateur inconnu ou root"; continue; }
  DOSSIER=${DOSSIER:-/home/$USER_SITE/public_html}
  [ -d "$DOSSIER" ] && [ ! -L "$DOSSIER" ] || { echo "$USER_SITE : dossier $DOSSIER introuvable"; continue; }
  R="$TMP/$USER_SITE"; mkdir "$R"
  # Téléchargement (en root, dans un dossier privé) et vérification de la signature de chaque fichier.
  for F in index.php .htaccess; do
    if ! curl -fsSL --proto "$PROTO" --max-time 20 --max-filesize 200000 "$DEPOT/$VERSION/$F" -o "$R/$F" \
      || ! curl -fsSL --proto "$PROTO" --max-time 20 --max-filesize 2000 "$DEPOT/$VERSION/$F.sig" -o "$R/$F.sig"; then
      echo "$USER_SITE : $F introuvable pour la version $VERSION"; continue 2
    fi
    if ! base64 -d "$R/$F.sig" > "$R/$F.sig.bin" 2>/dev/null || ! openssl dgst -sha256 -verify "$TMP/cle.pem" -signature "$R/$F.sig.bin" "$R/$F" >/dev/null 2>&1; then
      echo "$USER_SITE : $F REFUSÉ, signature invalide (version $VERSION) : rien n'est changé"; continue 2
    fi
  done
  php -l "$R/index.php" >/dev/null 2>&1 || { echo "$USER_SITE : index.php refusé (PHP invalide)"; continue; }
  if cmp -s "$R/index.php" "$DOSSIER/index.php" && cmp -s "$R/.htaccess" "$DOSSIER/.htaccess"; then
    echo "$USER_SITE : déjà à jour ($VERSION)"; continue
  fi
  if [ "$ESSAI" = "--essai" ]; then echo "$USER_SITE : serait mis à jour vers $VERSION"; continue; fi
  # Écriture au nom de l'utilisateur du site : copie de l'ancien fichier, puis remplacement d'un seul coup.
  chmod 755 "$TMP" "$R"; chmod 644 "$R/index.php" "$R/.htaccess"
  D=$(date +%Y-%m-%d-%H%M)
  HOME_SITE=$(getent passwd "$USER_SITE" | cut -d: -f6)
  if runuser -u "$USER_SITE" -- bash -c '
      set -e; S="$4/clickcurac-sauvegardes"; mkdir -p "$S"; chmod 700 "$S"
      for F in index.php .htaccess; do
        [ -f "$2/$F" ] && [ ! -L "$2/$F" ] && cp "$2/$F" "$S/$F.$3"
        cp "$1/$F" "$2/.$F.clickcurac-nouveau"; chmod 644 "$2/.$F.clickcurac-nouveau"
        mv -f "$2/.$F.clickcurac-nouveau" "$2/$F"
      done' _ "$R" "$DOSSIER" "$D" "$HOME_SITE"; then
    echo "$USER_SITE : mis à jour vers $VERSION (ancien fichier dans ~$USER_SITE/clickcurac-sauvegardes)"
  else
    echo "$USER_SITE : ÉCHEC de la mise à jour (rien n'a été remplacé à moitié : vérifier $DOSSIER)"
  fi
  chmod 700 "$TMP"
done < "$CONF"
