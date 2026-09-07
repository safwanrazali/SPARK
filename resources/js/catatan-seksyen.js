// Panel catatan seksyen laporan — tingkah laku "satu terbuka pada satu masa".
//
// Widget berkenaan ialah resources/views/components/section-comment-widget.blade.php,
// yang muncul pada setiap seksyen pratonton laporan (laporan/partials/*).
// Setiap panel ialah Bootstrap collapse biasa; fail ini hanya menambah DUA
// peraturan penutupan di atasnya:
//
//   1. Klik di mana-mana di luar widget menutup panel yang terbuka.
//   2. Membuka catatan seksyen lain menutup panel seksyen sebelumnya.
//
// Panel DIKEKALKAN terbuka selagi klik berlaku DI DALAM widget yang sama —
// menaip catatan, menekan Hantar, membuka borang sunting atau menekan
// "Tindakan Diambil" semuanya tidak boleh menutupnya.
//
// KAEDAH: panel ditutup dengan mengklik butang togolnya sendiri, BUKAN melalui
// bootstrap.Collapse. Bootstrap diimport sebagai ESM (`import "bootstrap"` ->
// dist/js/bootstrap.esm.js), jadi `window.bootstrap` tidak wujud dan
// mengimport "bootstrap/js/dist/collapse" secara berasingan akan menghasilkan
// SALINAN KEDUA kelas Collapse dengan simpanan instance tersendiri — dua
// instance pada elemen yang sama, dan keadaan dalamannya boleh terpesong
// daripada data-api. Mengklik togol menggunakan laluan data-api yang sedia
// ada, jadi animasi dan peristiwa Bootstrap kekal sama seperti klik pengguna.

const WIDGET = ".section-comments-widget";
const PANEL = ".section-comments-collapse";
const TOGGLE = ".section-comments-toggle";

export default function catatanSeksyen() {
    // Klik togol yang dijana di bawah turut melantun ke document dan mencetus
    // pengendali ini semula. Tanpa pengawal ini, penutupan panel pertama akan
    // memasuki semula gelung yang sedang berjalan.
    let sedangTutup = false;

    document.addEventListener("click", (e) => {
        if (sedangTutup || !(e.target instanceof Element)) {
            return;
        }

        const widget = e.target.closest(WIDGET);

        const terbuka = Array.from(
            document.querySelectorAll(`${PANEL}.show`)
        ).filter((panel) => !(widget && widget.contains(panel)));

        if (terbuka.length === 0) {
            return;
        }

        sedangTutup = true;

        terbuka.forEach((panel) => {
            // Bootstrap membuang kelas `show` serta-merta pada permulaan hide,
            // jadi panel yang sama tidak boleh dipilih dua kali.
            panel.closest(WIDGET)?.querySelector(TOGGLE)?.click();
        });

        sedangTutup = false;
    });
}
