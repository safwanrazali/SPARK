import "../scss/app.scss";

import "bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";

document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const toggle = document.getElementById("toggleSidebar");
    const backdrop = document.getElementById("sidebarBackdrop");
    const content = document.querySelector(".main-content");

    // ── Navigasi sisi ───────────────────────────────────────────────────
    const kemasKiniToggle = () => {
        if (!toggle || !sidebar) return;

        // Pada skrin kecil, kelas `collapsed` bermaksud menu penuh dibuka.
        const kecil = window.matchMedia("(max-width: 768px)").matches;
        const terbuka = kecil
            ? sidebar.classList.contains("collapsed")
            : !sidebar.classList.contains("collapsed");

        toggle.setAttribute("aria-expanded", String(terbuka));
    };

    const tutupMenuKecil = () => {
        if (!sidebar) return;
        if (window.matchMedia("(max-width: 768px)").matches) {
            sidebar.classList.remove("collapsed");
            kemasKiniToggle();
        }
    };

    toggle?.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        content?.classList.toggle("expanded");
        kemasKiniToggle();
    });

    backdrop?.addEventListener("click", tutupMenuKecil);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") tutupMenuKecil();
    });

    window.addEventListener("resize", kemasKiniToggle);
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
