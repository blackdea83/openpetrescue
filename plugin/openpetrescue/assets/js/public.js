(function () {
  function linksFor(link) {
    const gallery = link.closest("[data-sod-gallery]");
    const scope = gallery || document;
    return Array.from(scope.querySelectorAll(".sod-dog-gallery-link"))
      .filter((item) => item.getAttribute("data-sod-full") || item.getAttribute("href"));
  }

  function openGallery(items, startIndex) {
    if (!items.length) return;

    let index = Math.max(0, startIndex);
    const overlay = document.createElement("div");
    overlay.className = "sod-lightbox";
    overlay.innerHTML = [
      '<div class="sod-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Hundebilder">',
      '<button type="button" class="sod-lightbox-close" aria-label="Schließen">×</button>',
      '<button type="button" class="sod-lightbox-prev" aria-label="Vorheriges Bild">‹</button>',
      '<div class="sod-lightbox-media"></div>',
      '<button type="button" class="sod-lightbox-next" aria-label="Nächstes Bild">›</button>',
      '<div class="sod-lightbox-caption"></div>',
      '</div>'
    ].join("");

    const media = overlay.querySelector(".sod-lightbox-media");
    const caption = overlay.querySelector(".sod-lightbox-caption");
    const close = overlay.querySelector(".sod-lightbox-close");
    const prev = overlay.querySelector(".sod-lightbox-prev");
    const next = overlay.querySelector(".sod-lightbox-next");

    function render() {
      const item = items[index];
      const src = item.getAttribute("data-sod-full") || item.getAttribute("href");
      const text = item.getAttribute("data-sod-caption") || item.querySelector("img")?.alt || "";
      const isVideo = item.getAttribute("data-sod-type") === "video";

      // Ersetzen statt umschalten: Damit hoert ein vorheriges Video sofort auf zu laufen.
      media.replaceChildren();
      if (isVideo) {
        const video = document.createElement("video");
        video.className = "sod-lightbox-video";
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.preload = "metadata";
        // Erst hier starten - in der Auswahlleiste laeuft bewusst nichts von selbst.
        video.autoplay = true;
        media.appendChild(video);
      } else {
        const image = document.createElement("img");
        image.className = "sod-lightbox-image";
        image.src = src;
        image.alt = text || "Hundebild";
        media.appendChild(image);
      }

      caption.textContent = `${text || (isVideo ? "Video" : "Bild")} · ${index + 1} / ${items.length}`;
      prev.hidden = items.length < 2;
      next.hidden = items.length < 2;
    }

    function move(delta) {
      index = (index + delta + items.length) % items.length;
      render();
    }

    function remove() {
      document.removeEventListener("keydown", onKey);
      overlay.remove();
    }

    function onKey(event) {
      if (event.key === "Escape") remove();
      if (event.key === "ArrowLeft") move(-1);
      if (event.key === "ArrowRight") move(1);
    }

    close.addEventListener("click", remove);
    prev.addEventListener("click", () => move(-1));
    next.addEventListener("click", () => move(1));
    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) remove();
    });
    document.addEventListener("keydown", onKey);
    document.body.appendChild(overlay);
    render();
    close.focus();
  }

  document.addEventListener("click", function (event) {
    const consentButton = event.target.closest(".sod-video-consent-load");
    if (consentButton) {
      const gate = consentButton.closest(".sod-video-consent");
      const template = gate?.querySelector(".sod-video-embed-template");
      if (gate && template) {
        const content = template.content.cloneNode(true);
        gate.replaceChildren(content);
        gate.classList.add("sod-video-embed");
      }
      return;
    }

    const link = event.target.closest(".sod-dog-gallery-link");
    if (!link) return;
    event.preventDefault();
    const items = linksFor(link);
    openGallery(items, Math.max(0, items.indexOf(link)));
  });

  function setupApplicationForm(form) {
    const interest = form.querySelector("[data-sod-interest-select]");
    const adoptionFields = Array.from(form.querySelectorAll("[data-sod-adoption-only]"));
    if (!interest || !adoptionFields.length) return;

    function updateAdoptionFields() {
      const show = interest.value === "Vermittlung";
      adoptionFields.forEach((field) => {
        field.hidden = !show;
        field.querySelectorAll("input, textarea, select").forEach((control) => {
          control.disabled = !show;
        });
      });
    }

    interest.addEventListener("change", updateAdoptionFields);
    updateAdoptionFields();
  }

  document.querySelectorAll("form.sod-application-form").forEach(setupApplicationForm);

  // Der Namensvorschlag wird in ein separates Textfeld eingegeben, aber vor dem
  // Absenden an PayPal in das "custom"-Feld gehaengt - so kommt er zuverlaessig
  // per IPN zurueck, unabhaengig davon, ob PayPal os0 echot.
  document.querySelectorAll("form.sod-name-sponsorship-form").forEach((form) => {
    const customField = form.querySelector('input[name="custom"]');
    const nameField = form.querySelector('input[name="sod_name_suggestion_input"]');
    if (!customField || !nameField) return;
    const base = customField.value;
    form.addEventListener("submit", () => {
      const suggestion = nameField.value.trim();
      customField.value = suggestion !== "" ? base + ":" + encodeURIComponent(suggestion) : base;
    });
  });
})();
