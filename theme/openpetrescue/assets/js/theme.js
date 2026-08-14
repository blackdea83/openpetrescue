(function () {
  document.addEventListener("DOMContentLoaded", function () {
    const nav = document.querySelector(".site-nav");
    const button = document.querySelector(".nav-hamburger");
    if (!nav || !button) return;

    button.addEventListener("click", function () {
      const open = nav.classList.toggle("nav-open");
      button.setAttribute("aria-expanded", open ? "true" : "false");
    });
  });
})();

