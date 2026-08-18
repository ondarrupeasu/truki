#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Despliegue de Truki Fase 1 → Infomaniak por FTP.
# Tus credenciales van en el archivo .ftp.env (que NO se sube a git y se queda
# SOLO en tu Mac). Este script lo ejecutas TÚ:   ./deploy.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .ftp.env ]; then
  echo "❌ Falta .ftp.env — copia .ftp.env.example a .ftp.env y rellena tus datos FTP."
  exit 1
fi
# shellcheck disable=SC1091
source .ftp.env
: "${FTP_HOST:?}"; : "${FTP_USER:?}"; : "${FTP_PASS:?}"; : "${FTP_DIR:?}"

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
  curl -sS --ssl --ftp-create-dirs -T "fase1/$f" \
       -u "$FTP_USER:$FTP_PASS" "ftp://${FTP_HOST}${FTP_DIR}/$f" \
    || { echo "  ❌ Error subiendo $f"; exit 1; }
done

echo "✅ Código subido."
echo "   Recuerda (1 sola vez): crea api/config.php en el servidor con tus datos MySQL"
echo "   (copia api/config.example.php y pon driver 'mysql')."
