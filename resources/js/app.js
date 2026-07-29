import "../scss/app.scss";

import "bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";

document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");

    const toggle = document.getElementById("toggleSidebar");

    const content = document.querySelector(".main-content");

    toggle?.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");

        content.classList.toggle("expanded");
    });
});
