# Aptos — fon laporan PDF

Fail `.woff2` di sini digunakan HANYA oleh penjanaan PDF
(`resources/views/laporan/pdf/fonts.blade.php` membenamkannya sebagai
data URI base64 ke dalam dokumen yang diserahkan kepada Browsershot).
Ia TIDAK dihidangkan melalui HTTP dan TIDAK digunakan oleh WebView —
paparan skrin kekal pada fon aplikasi sedia ada.

## Sumber

Dimuat turun daripada Microsoft Download Center, "Microsoft Aptos Fonts"
versi 4.40 (2024-06-04):

    https://www.microsoft.com/en-us/download/details.aspx?id=106087

TTF asal ditukar kepada WOFF2 tanpa subset (fontTools) semata-mata untuk
mengecilkan saiz dokumen; glif kekal lengkap.

## Lesen

Lihat `Microsoft Aptos Fonts EULA.rtf` dalam direktori ini. Fon ini milik
Microsoft dan bukan sumber terbuka — sebarang pengedaran semula tertakluk
kepada EULA tersebut.

## Berat yang dibenamkan

| Fail                      | font-weight | font-style |
|---------------------------|-------------|------------|
| `Aptos.woff2`             | 400         | normal     |
| `Aptos-Italic.woff2`      | 400         | italic     |
| `Aptos-Bold.woff2`        | 700         | normal     |
| `Aptos-Bold-Italic.woff2` | 700         | italic     |
| `Aptos-ExtraBold.woff2`   | 800         | normal     |

Hanya berat yang benar-benar digunakan oleh templat laporan dibenamkan.
Jika templat memperkenalkan berat atau gaya baharu, tambah fail yang
sepadan di sini DAN peraturan `@font-face`-nya dalam `fonts.blade.php`,
jika tidak Chrome akan mensintesis (memalsukan) berat tersebut.
