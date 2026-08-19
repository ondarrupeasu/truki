#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Despliegue de Truki Fase 1 → Infomaniak por FTPS (con lftp).
# Credenciales en ~/.truki/infomaniak_ftp.env (o .ftp.env), NUNCA en git ni en el chat.
# Uso:   ./deploy.sh
# Requiere lftp:  brew install lftp
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")"

CRED=""
for c in "$HOME/.truki/infomaniak_ftp.env" ".ftp.env"; do
  [ -f "$c" ] && { CRED="$c"; break; }
done
if [ -z "$CRED" ]; then
  echo "❌ No encuentro las credenciales FTP (crea ~/.truki/infomaniak_ftp.env — ver .ftp.env.example)"; exit 1
fi
echo "🔑 Credenciales: $CRED"
set -a; # shellcheck disable=SC1090
. "$CRED"; set +a
: "${FTP_HOST:?}"; : "${FTP_USER:?}"; : "${FTP_PASS:?}"
FTP_DIR="${FTP_DIR-}"; FTP_DIR="${FTP_DIR#/}"; FTP_DIR="${FTP_DIR%/}"
TARGET="/${FTP_DIR}"

command -v lftp >/dev/null || { echo "❌ Falta lftp:  brew install lftp"; exit 1; }

echo "📤 Subiendo fase1/ → ${FTP_HOST}:${TARGET} (FTPS)"
# mirror -R sube el árbol local. Sin --delete: nunca borra ficheros del servidor
# (p. ej. las fotos de /uploads). Excluye la config con credenciales y datos locales.
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" <<LFTP
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ftp:ssl-allow yes
set ssl:verify-certificate no
set net:max-retries 3
set net:timeout 15
set mirror:parallel-transfer-count 1
mirror -R --verbose --no-perms \
  --exclude 'api/config\.php\$' \
  --exclude 'api/config\.prod\.php\$' \
  --exclude-glob 'data/*' \
  --exclude-glob 'uploads/*' \
  fase1/ ${TARGET}
bye
LFTP

echo "✅ Código subido a ${FTP_HOST}:${TARGET}"
echo "   Falta (1 sola vez): crear api/config.php en el servidor con tus datos MySQL."
