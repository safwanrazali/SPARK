const { join } = require('path');

/*
|--------------------------------------------------------------------------
| KONFIGURASI PUPPETEER — LOKASI CACHE CHROME
|--------------------------------------------------------------------------
|
| Puppeteer menyimpan binari Chrome di dalam direktori rumah pengguna
| (`~/.cache/puppeteer`) secara lalai. Itu TIDAK berfungsi pada pelayan:
|
|   - `php artisan` dijalankan oleh pengguna penyelenggara (contoh: safwan)
|   - Permintaan web dijalankan oleh pengguna pelayan web (www-data)
|
| Kedua-duanya mempunyai direktori rumah yang berlainan, jadi Chrome yang
| dipasang oleh seorang pengguna tidak dapat ditemui oleh seorang lagi.
| Kesannya: penjanaan PDF berjaya pada baris arahan tetapi GAGAL di dalam
| pelayar — kegagalan yang sukar dikesan kerana ujian CLI kelihatan lulus.
|
| Dengan menetapkan cache di dalam projek, kedua-dua pengguna menyelesaikan
| laluan yang sama. Browsershot menjalankan Node dengan direktori kerja pada
| akar projek, jadi fail ini turut dibaca semasa runtime, bukan hanya semasa
| pemasangan.
|
| Pemasangan binari (jalankan dari akar projek):
|
|   npx puppeteer browsers install chrome-headless-shell
|
| GUNAKAN `chrome-headless-shell`, BUKAN `chrome`: Browsershot melancarkan
| pelayar dengan mod `headless: 'shell'`.
|
| Direktori `.cache/` diabaikan oleh git — binari Chrome (~120 MB) tidak
| boleh masuk ke dalam repositori. Ia dipasang pada setiap pelayan.
|
*/

module.exports = {
    cacheDirectory: join(__dirname, '.cache', 'puppeteer'),
};
