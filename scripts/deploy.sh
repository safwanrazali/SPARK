#!/usr/bin/env bash
#
# ------------------------------------------------------------------------------
# PENEMPATAN (DEPLOY) — Sistem Pemantauan & Pelaporan Analisis Data Migrasi PQC
# ------------------------------------------------------------------------------
#
# Menarik perubahan terkini bagi cabang yang sedang digunakan, kemudian
# menjalankan langkah selepas-penempatan yang BERKAITAN sahaja.
#
# Skrip yang sama digunakan pada kedua-dua pelayan:
#
#   pelayan pementasan (staging)   : cabang master
#   pelayan pengeluaran (production): cabang production
#
# Penggunaan:
#   bash scripts/deploy.sh
#   bash scripts/deploy.sh --dry-run    # papar rancangan tanpa mengubah apa-apa
#
# PENTING — cache:
#   Aplikasi berjalan dengan config/route/view yang DI-CACHE. Tanpa pembinaan
#   semula cache, kod baharu yang ditarik TIDAK akan berkuat kuasa dan tiada
#   ralat dipaparkan. Kerana itu langkah cache sentiasa dijalankan, bukan
#   bersyarat.
#
# Kod keluar: 0 = berjaya, 1 = gagal.
#
set -euo pipefail

ASAS="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ASAS"

KERING=0
[[ "${1:-}" == "--dry-run" ]] && KERING=1

jalan() {
    if [[ $KERING -eq 1 ]]; then
        echo "       [dry-run] $*"
    else
        "$@"
    fi
}

tajuk() { echo; echo "==> $*"; }

# ------------------------------------------------------------------------------
# 1. Pemeriksaan awal
# ------------------------------------------------------------------------------
CABANG="$(git rev-parse --abbrev-ref HEAD)"

tajuk "Cabang: ${CABANG}   Direktori: ${ASAS}"

if [[ -n "$(git status --porcelain)" ]]; then
    echo
    echo "RALAT: pokok kerja tidak bersih. Perubahan setempat pada pelayan akan"
    echo "       menghalang 'git pull'. Semak dengan 'git status', kemudian"
    echo "       buang atau commit perubahan tersebut sebelum menempatkan."
    echo
    git status --short
    echo
    echo "       Fail yang SELAMAT dibuang pada pelayan (dijana semula, bukan"
    echo "       diselenggara di sini):"
    echo
    echo "         git checkout -- package-lock.json composer.lock"
    echo
    echo "       Jika fail SUMBER yang berubah, seseorang telah menyunting terus"
    echo "       pada pelayan. Pindahkan perubahan itu ke repositori; jangan"
    echo "       commit daripada pelayan."
    exit 1
fi

# ------------------------------------------------------------------------------
# 2. Tarik perubahan
# ------------------------------------------------------------------------------
SEBELUM="$(git rev-parse HEAD)"

tajuk "Menarik perubahan daripada origin/${CABANG}"
jalan git pull --ff-only origin "$CABANG"

SELEPAS="$(git rev-parse HEAD)"

if [[ "$SEBELUM" == "$SELEPAS" ]]; then
    echo "    Tiada commit baharu. Cache tetap dibina semula di bawah."
    FAIL_BERUBAH=""
else
    FAIL_BERUBAH="$(git diff --name-only "$SEBELUM" "$SELEPAS")"
    echo "    $(echo "$FAIL_BERUBAH" | wc -l) fail berubah."
fi

berubah() { echo "$FAIL_BERUBAH" | grep -qE "$1"; }

# ------------------------------------------------------------------------------
# 3. Langkah bersyarat — hanya jika fail berkenaan berubah
# ------------------------------------------------------------------------------
if berubah '^composer\.(json|lock)$'; then
    tajuk "Kebergantungan PHP berubah"
    jalan composer install --no-dev --optimize-autoloader
fi

if berubah '^database/migrations/'; then
    tajuk "Migrasi baharu dikesan — menyandar pangkalan data terlebih dahulu"
    jalan php scripts/backup-database.php
    tajuk "Menjalankan migrasi"
    jalan php artisan migrate --force
fi

if berubah '^package(-lock)?\.json$'; then
    tajuk "Kebergantungan JavaScript berubah"
    # `npm ci`, BUKAN `npm install`:
    #   - `npm install` boleh MENULIS SEMULA package-lock.json. Pada pelayan itu
    #     mengotorkan pokok kerja dan menyekat penempatan BERIKUTNYA.
    #   - `npm ci` memasang tepat seperti dalam fail kunci dan tidak pernah
    #     mengubahnya.
    #
    # Tiada --omit=dev: puppeteer ialah devDependency tetapi DIPERLUKAN pada
    # masa runtime oleh Browsershot untuk menjana PDF.
    #
    # `npm ci` memadam node_modules, tetapi binari Chrome berada dalam
    # .cache/puppeteer (di luar node_modules) jadi ia tidak dimuat turun semula.
    jalan npm ci
fi

if berubah '^(resources/|package(-lock)?\.json$|vite\.config\.js$)'; then
    tajuk "Aset hadapan berubah — membina semula"
    jalan npm run build
fi

# ------------------------------------------------------------------------------
# 4. Cache — SENTIASA
# ------------------------------------------------------------------------------
tajuk "Membina semula cache aplikasi"
jalan php artisan optimize:clear
jalan php artisan config:cache
jalan php artisan route:cache
jalan php artisan view:cache

# ------------------------------------------------------------------------------
# 5. Keizinan fail
# ------------------------------------------------------------------------------
# Fail baharu daripada 'git pull' mewarisi pemilikan pengguna yang menjalankan
# skrip ini. Pelayan web mesti tetap boleh membacanya, dan menulis ke storage,
# bootstrap/cache serta database.
KUMPULAN_WEB="${SPARK_WEB_GROUP:-www-data}"

if [[ $KERING -eq 0 ]] && command -v sudo >/dev/null && sudo -n true 2>/dev/null; then
    tajuk "Menyelaraskan keizinan fail (kumpulan: ${KUMPULAN_WEB})"
    sudo chgrp -R "$KUMPULAN_WEB" .
    sudo chmod -R g+rX .
    sudo chmod -R g+w storage bootstrap/cache database
else
    tajuk "Keizinan fail — LANGKAU"
    echo "    sudo tanpa kata laluan tidak tersedia. Jalankan secara manual jika"
    echo "    fail baharu ditambah oleh penempatan ini:"
    echo
    echo "      sudo chgrp -R ${KUMPULAN_WEB} ${ASAS}"
    echo "      sudo chmod -R g+rX ${ASAS}"
    echo "      sudo chmod -R g+w ${ASAS}/storage ${ASAS}/bootstrap/cache ${ASAS}/database"
fi

# ------------------------------------------------------------------------------
# 6. Pengesahan
# ------------------------------------------------------------------------------
tajuk "Pengesahan"

if [[ $KERING -eq 0 ]]; then
    php artisan about --only=environment
    echo
    echo "    Semakan manual yang disyorkan:"
    echo "      curl -I http://localhost/            # jangkaan: 302 -> /login"
    echo "      php artisan migrate:status           # setiap migrasi 'Ran'"
    echo "      muat turun satu laporan PDF melalui pelayar"
fi

echo
echo "SELESAI. ${SEBELUM:0:7} -> ${SELEPAS:0:7} (${CABANG})"
