#!/usr/bin/env bash
# تحميل ملصقات PNG من Telegram Stickers Directory (معاينات عامة)
# المصدر: https://telegram-stickers.github.io/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${STICKER_BASE_URL:-https://telegram-stickers.github.io/public/stickers}"
DEST="${ROOT}/public/chat/stickers"

download() {
  local rel="$1"
  local out="${DEST}/${rel}"
  mkdir -p "$(dirname "$out")"
  if [[ -f "$out" ]]; then
    echo "skip $rel"
    return 0
  fi
  echo "get  $rel"
  curl -fsSL "${BASE_URL}/${rel}" -o "$out"
}

# تفاعلات (أسلوب تيليجرام الرسمي غير الرسمي)
for i in $(seq 1 14); do
  download "unofficial/${i}.png"
done

# ميمز asdfmovie
for i in $(seq 1 10); do
  download "asdfmovie/${i}.png"
done

# College Dog — فريق / مكتب
for i in $(seq 1 6); do
  download "college-dog/${i}.png"
done

echo "Done. $(find "$DEST" -name '*.png' | wc -l | tr -d ' ') PNG files."
