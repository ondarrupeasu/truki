#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Despliegue de Truki Fase 1 → Infomaniak por FTP.
# Tus credenciales van en el archivo .ftp.env (que NO se sube a git y se queda
# SOLO en tu Mac). Este script lo ejecutas TÚ:   ./deploy.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")"

# Credenciales: preferimos un fichero FUERA del repo (convención del ecosistema
# CinemaFilmak); si no, .ftp.env local. Nunca van en git ni en el chat.
CRED=""
for c in "$HOME/.truki/infomaniak_ftp.env" ".ftp.env"; do
  [ -f "$c" ] && { CRED="$c"; break; }
done
if [ -z "$CRED" ]; then
  echo "❌ No encuentro las credenciales FTP."
  echo "   Crea ~/.truki/infomaniak_ftp.env (recomendado) o .ftp.env con FTP_HOST/USER/PASS/DIR."
  echo "   Tienes la plantilla en .ftp.env.example"
  exit 1
fi
echo "🔑 Usando credenciales de: $CRED"
set -a; # shellcheck disable=SC1090
. "$CRED"; set +a
: "${FTP_HOST:?}"; : "${FTP_USER:?}"; : "${FTP_PASS:?}"
# FTP_DIR admite vacío: con un usuario ACOTADO a la carpeta del sitio, los
# ficheros van a la raíz de ese usuario. Normalizamos las barras.
FTP_DIR="${FTP_DIR-}"; FTP_DIR="${FTP_DIR#/}"; FTP_DIR="${FTP_DIR%/}"

# Archivos a subir (OJO: NUNCA subimos api/config.php para no pisar tu config MySQL
# del servidor, ni las fotos de /uploads de la gente).
FILES=(
  index.html
  manifest.webmanifest
  sw.js
  api/index.php
  api/db.php
  api/helpers.php
  api/config.example.php
  api/.htaccess
)

echo "Subiendo fase1/ → ${FTP_HOST}${FTP_DIR}"
for f in "${FILES[@]}"; do
  printf '  → %s\n' "$f"
  # --ssl-reqd fuerza FTPS EXPLÍCITO (puerto 21). El usuario FTP "acotado" de
  # Infomaniak lo requiere; si da error de auth, casi siempre es esto.
  curl -sS --ssl-reqd --ftp-create-dirs -T "fase1/$f" \
       -u "$FTP_USER:$FTP_PASS" "ftp://${FTP_HOST}/${FTP_DIR:+$FTP_DIR/}$f" \
    || { echo "  ❌ Error subiendo $f"; exit 1; }
done

echo "✅ Código subido."
echo "   Recuerda (1 sola vez): crea api/config.php en el servidor con tus datos MySQL"
echo "   (copia api/config.example.php y pon driver 'mysql')."
