import "../scss/app.scss";

import "bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";

document.addEventListener("DOMContentLoaded", () => {
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
