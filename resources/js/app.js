import "../scss/app.scss";

import "bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";

import catatanSeksyen from "./catatan-seksyen";

document.addEventListener("DOMContentLoaded", () => {
    // Panel catatan seksyen laporan: satu terbuka pada satu masa.
    catatanSeksyen();

    const sidebar = document.getElementById("sidebar");
    const toggle = document.getElementById("toggleSidebar");
    const backdrop = document.getElementById("sidebarBackdrop");
    const content = document.querySelector(".main-content");

    // ── Navigasi sisi ────────────────────────────────────
    // Rel ikon ialah keadaan asal. Melayang tetikus (atau fokus papan kekunci)
    // mengembangkannya di atas kandungan; klik butang menyematnya supaya kekal
    // terbuka dan menolak kandungan. Kunci sengaja tidak disimpan — setiap
    // navigasi ke muka surat lain bermula semula pada rel.
    const skrinKecil = window.matchMedia("(max-width: 768px)");
    const bolehLayang = window.matchMedia("(hover: hover) and (pointer: fine)");

    let dikunci = false;

    const setKembang = (kembang) => {
        sidebar?.classList.toggle("is-expanded", kembang);
    };

    const kemasKiniToggle = () => {
        if (!toggle || !sidebar) return;

        const ikon = toggle.querySelector("i");
        const terbuka = sidebar.classList.contains("is-expanded");

        // Pada skrin kecil butang membuka/menutup menu; pada skrin besar ia
        // mengunci menu, jadi semantik ARIA turut berbeza.
        if (skrinKecil.matches) {
            toggle.removeAttribute("aria-pressed");
            toggle.setAttribute("aria-expanded", String(terbuka));
            toggle.setAttribute(
                "aria-label",
                terbuka ? "Tutup menu navigasi" : "Buka menu navigasi",
            );
            if (ikon) ikon.className = "bi bi-list";
            return;
        }

        toggle.removeAttribute("aria-expanded");
        toggle.setAttribute("aria-pressed", String(dikunci));
        toggle.setAttribute(
            "aria-label",
            dikunci
                ? "Buka kunci menu navigasi"
                : "Kunci menu navigasi supaya kekal terbuka",
        );
        if (ikon) {
            ikon.className = dikunci ? "bi bi-lock-fill" : "bi bi-unlock";
        }
    };

    const setKunci = (nilai) => {
        dikunci = nilai;
        sidebar?.classList.toggle("is-locked", nilai);
        content?.classList.toggle("is-locked", nilai);
        setKembang(nilai);
        kemasKiniToggle();
    };

    const tutupMenuKecil = () => {
        if (!sidebar || !skrinKecil.matches) return;
        setKembang(false);
        kemasKiniToggle();
    };

    toggle?.addEventListener("click", () => {
        if (skrinKecil.matches) {
            setKembang(!sidebar.classList.contains("is-expanded"));
            kemasKiniToggle();
            return;
        }

        setKunci(!dikunci);
    });

    // Layang hanya pada peranti berpenuding tepat; sentuhan guna butang.
    sidebar?.addEventListener("mouseenter", () => {
        if (dikunci || skrinKecil.matches || !bolehLayang.matches) return;
        setKembang(true);
    });

    sidebar?.addEventListener("mouseleave", () => {
        if (dikunci || skrinKecil.matches) return;
        setKembang(false);
    });

    // Pengguna papan kekunci perlu melihat label semasa menyusur rel.
    sidebar?.addEventListener("focusin", () => {
        if (dikunci || skrinKecil.matches) return;
        setKembang(true);
    });

    sidebar?.addEventListener("focusout", (e) => {
        if (dikunci || skrinKecil.matches) return;
        if (sidebar.contains(e.relatedTarget)) return;
        setKembang(false);
    });

    backdrop?.addEventListener("click", tutupMenuKecil);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") tutupMenuKecil();
    });

    // Kunci tidak bermakna pada skrin kecil — lepaskan apabila ambang dilintasi.
    skrinKecil.addEventListener("change", () => {
        if (skrinKecil.matches && dikunci) {
            setKunci(false);
            return;
        }

        setKembang(dikunci);
        kemasKiniToggle();
    });

    kemasKiniToggle();

    // ── Catatan wajib sebelum laporan boleh dikembalikan ────────────────
    // Carta aliran bahagian 8 dan 9: butang "Kembalikan" hanya hidup apabila
    // medan Catatan mempunyai nilai. Ini kemudahan sahaja — peraturan sebenar
    // dikuatkuasakan pada pelayan (LaporanSemakanService::kembalikan).
    document.querySelectorAll("[data-catatan-wajib]").forEach((borang) => {
        const medan = borang.querySelector("[data-catatan]");
        const butang = borang.querySelector("[data-catatan-butang]");

        if (!medan || !butang) return;

        const kemasKini = () => {
            butang.disabled = medan.value.trim() === "";
        };

        medan.addEventListener("input", kemasKini);
        kemasKini();
    });

    // ── Penerangan status data boleh berbilang ──────────────────────────
    // Templat laporan memaparkan beberapa penerangan bernombor bagi setiap
    // Jadual 0-2. Baris ditambah/dibuang di sini sahaja; pelayan menormalkan
    // nilainya melalui BorangAnalisis::penerangan(), termasuk membuang baris
    // kosong, jadi tiada pengesahan diperlukan di pihak pelayar.
    //
    // Pendengar didelegasikan pada document supaya baris yang BARU ditambah
    // turut berfungsi tanpa perlu memasang pendengar semula.
    // Senarai bertanda `data-berindeks` mempunyai LEBIH daripada satu medan
    // setiap baris, jadi namanya tidak boleh menggunakan `medan[]` — PHP akan
    // memisahkan pasangannya. Nama dinomborkan semula selepas setiap tambah
    // atau buang supaya indeksnya kekal rapat dan tiada baris bertindih.
    const susunSemulaIndeks = (senarai) => {
        if (!senarai.hasAttribute("data-berindeks")) return;

        const medan = senarai.dataset.penerangan;

        senarai.querySelectorAll(".penerangan-baris").forEach((baris, i) => {
            baris.querySelectorAll("[data-nama]").forEach((input) => {
                input.name = `${medan}[${i}][${input.dataset.nama}]`;
            });
        });
    };

    document.addEventListener("click", (e) => {
        const tambah = e.target.closest(".penerangan-tambah");

        if (tambah) {
            const senarai = document.querySelector(
                `[data-penerangan="${tambah.dataset.sasaran}"]`,
            );

            if (!senarai) return;

            const contoh = senarai.querySelector(".penerangan-baris");

            if (!contoh) return;

            const baris = contoh.cloneNode(true);

            // SEMUA medan dikosongkan, bukan yang pertama sahaja: baris
            // "Lain-lain" membawa nama DAN bilangan, dan menyalin bilangan
            // baris sebelumnya akan merekodkan kiraan palsu secara senyap.
            baris.querySelectorAll("input").forEach((input) => {
                input.value = "";
            });

            senarai.appendChild(baris);
            susunSemulaIndeks(senarai);
            baris.querySelector("input")?.focus();
            return;
        }

        const buang = e.target.closest(".penerangan-buang");

        if (!buang) return;

        const senarai = buang.closest(".penerangan-senarai");
        const baris = buang.closest(".penerangan-baris");

        if (!senarai || !baris) return;

        // Baris terakhir dikosongkan, bukan dibuang: mengeluarkannya akan
        // meninggalkan seksyen tanpa medan input langsung dan pegawai tidak
        // dapat menambah semula tanpa memuat semula halaman (butang Tambah
        // mengklon baris sedia ada).
        if (senarai.querySelectorAll(".penerangan-baris").length === 1) {
            baris.querySelectorAll("input").forEach((input) => {
                input.value = "";
            });
            return;
        }

        baris.remove();
        susunSemulaIndeks(senarai);
    });

    // ── Keadaan memuat pada penghantaran borang ─────────────────────────
    // Memberi maklum balas segera dan menghalang penghantaran berganda.
    const progres = document.getElementById("route-progress");

    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (e) => {
            if (e.defaultPrevented || form.dataset.tanpaMemuat !== undefined) {
                return;
            }

            const butang =
                e.submitter ||
                form.querySelector('button[type="submit"], input[type="submit"]');

            if (butang && !butang.hasAttribute("aria-busy")) {
                butang.setAttribute("aria-busy", "true");

                // Butang yang dilumpuhkan tidak dihantar bersama borang, jadi
                // nilainya dikekalkan melalui medan tersembunyi.
                if (butang.name && butang.value) {
                    const salinan = document.createElement("input");
                    salinan.type = "hidden";
                    salinan.name = butang.name;
                    salinan.value = butang.value;
                    form.appendChild(salinan);
                }

                // Lengahkan sedikit supaya penghantaran biasa tidak terganggu.
                window.setTimeout(() => {
                    butang.disabled = true;
                }, 0);
            }

            progres?.classList.add("is-active");
        });
    });

    // ── Papar / sembunyi jalur progres semasa meninggalkan halaman ──────
    window.addEventListener("pageshow", () => {
        progres?.classList.remove("is-active");
        document.querySelectorAll('[aria-busy="true"]').forEach((el) => {
            el.removeAttribute("aria-busy");
            el.disabled = false;
        });
    });
});
