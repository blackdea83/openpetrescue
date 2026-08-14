(function () {
  function parseAmount(text) {
    const match = String(text || "").replace(",", ".").match(/\d+(?:\.\d+)?/);
    return match ? Math.round(Number(match[0])) : null;
  }

  function setPressed(buttons, activeButton) {
    buttons.forEach(function (button) {
      button.setAttribute("aria-pressed", button === activeButton ? "true" : "false");
    });
  }

  function updateDonationWidget(widget, amount, cadence, method) {
    if (!widget || !amount) return;

    const input = widget.querySelector("#amount-input, .custom-input-wrap input");
    if (input) input.value = amount;

    const cta = widget.querySelector("#widget-cta");
    if (cta) {
      const tpl = cadence === "monthly"
        ? (widget.dataset.ctaMonthlyTpl || "Jetzt {amount} monatlich spenden ->")
        : (widget.dataset.ctaOnceTpl || "Jetzt {amount} spenden ->");
      cta.textContent = tpl.replace("{amount}", amount + " EUR");
      const ariaTpl = method === "paypal"
        ? (widget.dataset.ariaPaypalTpl || "PayPal-Zahlung mit {amount} Euro öffnen")
        : (widget.dataset.ariaBankTpl || "Bankdaten und QR-Code für {amount} Euro anzeigen");
      cta.setAttribute("aria-label", ariaTpl.replace("{amount}", amount));
    }

    const note = widget.querySelector(".secure-note");
    if (note) {
      note.textContent = method === "paypal"
        ? (widget.dataset.notePaypal || "PayPal-Zahlung wird extern geöffnet · Spendenbestätigung auf Anfrage")
        : (widget.dataset.noteBank || "Direkte Banküberweisung per IBAN oder QR-Code · Spendenbestätigung auf Anfrage");
    }
  }

  function setupDonationWidget() {
    document.querySelectorAll(".donation-widget").forEach(function (widget) {
      const customWrap = widget.querySelector(".custom-input-wrap");
      let input = widget.querySelector("#amount-input, .custom-input-wrap input");
      if (customWrap && !input) {
        input = document.createElement("input");
        input.id = "amount-input";
        input.type = "number";
        input.min = "1";
        input.step = "1";
        input.inputMode = "numeric";
        input.value = "50";
        input.setAttribute("aria-label", widget.dataset.amountInputLabel || "Eigener Spendenbetrag");
        customWrap.appendChild(input);
      }

      let cadence = widget.querySelector(".type-tab.active")?.dataset.cadence || "once";
      let method = widget.querySelector(".pay-method.active")?.dataset.method || "bank";
      const activeAmount = parseAmount(widget.querySelector(".amount-btn.active")?.textContent) || 50;
      const paypalForm = document.getElementById("sod-paypal-form");
      const typeTabs = Array.from(widget.querySelectorAll(".type-tab"));
      const amountButtons = Array.from(widget.querySelectorAll(".amount-btn"));
      const methodButtons = Array.from(widget.querySelectorAll(".pay-method"));

      widget.querySelector(".impact-box")?.setAttribute("aria-live", "polite");
      widget.querySelector(".secure-note")?.setAttribute("aria-live", "polite");
      setPressed(typeTabs, widget.querySelector(".type-tab.active"));
      setPressed(amountButtons, widget.querySelector(".amount-btn.active"));

      methodButtons.forEach(function (button) {
        if (button.dataset.method === "paypal" && !paypalForm) {
          button.disabled = true;
          button.classList.remove("active");
          button.setAttribute("aria-disabled", "true");
          button.title = widget.dataset.paypalDisabledTitle || "PayPal ist aktuell noch nicht eingerichtet.";
          if (method === "paypal") method = "bank";
        }
      });
      setPressed(methodButtons, widget.querySelector(".pay-method.active"));
      updateDonationWidget(widget, activeAmount, cadence, method);

      typeTabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
          typeTabs.forEach(function (item) {
            item.classList.remove("active");
          });
          tab.classList.add("active");
          setPressed(typeTabs, tab);
          cadence = tab.dataset.cadence || "once";
          updateDonationWidget(widget, parseAmount(input?.value) || activeAmount, cadence, method);
        });
      });

      amountButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          amountButtons.forEach(function (item) {
            item.classList.remove("active");
          });
          button.classList.add("active");
          setPressed(amountButtons, button);

          const amount = parseAmount(button.textContent);
          if (amount) {
            updateDonationWidget(widget, amount, cadence, method);
          } else if (input) {
            input.focus();
            input.select();
          }
        });
      });

      if (input) {
        input.addEventListener("input", function () {
          amountButtons.forEach(function (item) {
            item.classList.remove("active");
          });
          setPressed(amountButtons, null);
          updateDonationWidget(widget, parseAmount(input.value), cadence, method);
        });
      }

      methodButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          if (button.disabled) return;
          methodButtons.forEach(function (item) {
            item.classList.remove("active");
          });
          button.classList.add("active");
          setPressed(methodButtons, button);
          method = button.dataset.method || "bank";
          updateDonationWidget(widget, parseAmount(input?.value) || activeAmount, cadence, method);
        });
      });

      const cta = widget.querySelector("#widget-cta");
      if (cta) {
        cta.addEventListener("click", function (event) {
          if (method !== "paypal") return;
          if (!paypalForm) return;
          event.preventDefault();
          const amountField = document.getElementById("sod-paypal-amount");
          const currentAmount = parseAmount(input?.value) || activeAmount;
          if (amountField) amountField.value = currentAmount;
          paypalForm.submit();
        });
      }
    });
  }

  function setupGalleryPreview() {
    const gallery = document.querySelector(".galerie");
    const cards = document.querySelectorAll(".galerie-card");
    if (!cards.length) return;

    const overlay = document.createElement("button");
    overlay.type = "button";
    overlay.id = "sod-gallery-preview";
    overlay.className = "sod-gallery-preview";
    overlay.setAttribute("aria-label", gallery?.dataset.previewCloseLabel || "Bildvorschau schließen");
    overlay.style.cssText = "position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(3,9,14,.88);border:0;padding:24px;cursor:zoom-out;";
    overlay.innerHTML = '<img alt="" style="max-width:min(100%,1100px);max-height:88vh;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,.55);object-fit:contain;">';
    document.body.appendChild(overlay);

    const previewImg = overlay.querySelector("img");
    let opener = null;

    function closePreview() {
      if (overlay.style.display === "none") return;
      overlay.style.display = "none";
      previewImg.removeAttribute("src");
      document.body.style.removeProperty("overflow");
      opener?.setAttribute("aria-expanded", "false");
      opener?.focus();
      opener = null;
    }

    function openPreview(card) {
      const img = card.querySelector("img");
      if (!img) return;
      opener = card;
      opener.setAttribute("aria-expanded", "true");
      previewImg.src = img.currentSrc || img.src;
      previewImg.alt = img.alt || "";
      overlay.style.display = "flex";
      document.body.style.overflow = "hidden";
      overlay.focus();
    }

    overlay.addEventListener("click", closePreview);

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && overlay.style.display !== "none") {
        event.preventDefault();
        closePreview();
      } else if (event.key === "Tab" && overlay.style.display !== "none") {
        event.preventDefault();
        overlay.focus();
      }
    });

    cards.forEach(function (card) {
      card.addEventListener("click", function () {
        openPreview(card);
      });
      card.addEventListener("keydown", function (event) {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openPreview(card);
        }
      });
    });
  }

  function applyAutoplaySettings(video) {
    video.muted = true;
    video.loop = true;
    video.setAttribute("muted", "");
    video.setAttribute("autoplay", "");
    video.setAttribute("loop", "");
    video.setAttribute("playsinline", "");
  }

  function setupHighlightVideo() {
    const video = document.querySelector("#highlight-video video");
    if (!video) return;

    function loadSource() {
      if (!video.getAttribute("src") && video.dataset.src) {
        video.setAttribute("src", video.dataset.src);
        delete video.dataset.src;
      }
      video.setAttribute("preload", "metadata");
      video.controls = true;
      video.load();
    }

    if (!("IntersectionObserver" in window)) {
      loadSource();
      return;
    }

    const observer = new IntersectionObserver(function (entries) {
      if (!entries.some(function (entry) { return entry.isIntersecting; })) return;
      observer.disconnect();
      loadSource();
    }, { rootMargin: "300px 0px" });

    observer.observe(video);
  }

  function setupAllVideos() {
    const videos = Array.from(document.querySelectorAll("video"));
    if (!videos.length) return;

    let soundEnabled = false;

    const updateSoundButtons = function () {
      videos.forEach(function (video) {
        const button = video.closest(".video-frame")?.querySelector(".video-sound-toggle");
        if (!button) return;
        const soundOn = !video.muted && video.volume > 0;
        button.textContent = soundOn ? button.dataset.soundOff : button.dataset.soundOn;
        button.setAttribute("aria-pressed", soundOn ? "true" : "false");
      });
    };

    const applySoundPreference = function () {
      videos.forEach(function (video) {
        video.muted = !soundEnabled;
        if (soundEnabled) video.removeAttribute("muted");
        else video.setAttribute("muted", "");
      });
      updateSoundButtons();
    };

    const playIfVisible = function (video) {
      if (video.dataset.sodVideoVisible === "1" && !document.hidden) {
        video.play().catch(function () {});
      }
    };

    videos.forEach(function (video) {
      applyAutoplaySettings(video);
      const soundButton = video.closest(".video-frame")?.querySelector(".video-sound-toggle");
      if (soundButton) {
        soundButton.addEventListener("click", function () {
          soundEnabled = video.muted || video.volume === 0;
          if (soundEnabled && video.volume === 0) video.volume = 1;
          applySoundPreference();
          playIfVisible(video);
        });
      }
      video.addEventListener("volumechange", function () {
        if (!video.muted && video.volume > 0) soundEnabled = true;
        updateSoundButtons();
      });
      video.addEventListener("loadeddata", function () {
        playIfVisible(video);
      });
    });
    updateSoundButtons();

    if (!("IntersectionObserver" in window)) {
      videos.forEach(function (video) {
        video.dataset.sodVideoVisible = "1";
        playIfVisible(video);
      });
      return;
    }

    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const video = entry.target;
        const visible = entry.isIntersecting && entry.intersectionRatio >= 0.25;
        video.dataset.sodVideoVisible = visible ? "1" : "0";
        if (visible) {
          playIfVisible(video);
        } else {
          video.pause();
        }
      });
    }, { threshold: [0, 0.25, 0.75] });

    videos.forEach(function (video) {
      observer.observe(video);
    });

    document.addEventListener("visibilitychange", function () {
      videos.forEach(function (video) {
        if (document.hidden) {
          video.pause();
        } else {
          playIfVisible(video);
        }
      });
    });
  }

  function setupShelterBuildGallery() {
    const cards = document.querySelectorAll(".tierheim-bau-card");
    if (!cards.length) return;
    const popupTitle = document.querySelector("[data-shelter-popup-title]")?.dataset.shelterPopupTitle || "Aktueller Baufortschritt";

    const overlay = document.createElement("div");
    overlay.id = "sod-shelter-lightbox";
    overlay.className = "sod-shelter-lightbox";
    overlay.innerHTML =
      '<div class="sod-shelter-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="sod-shelter-lightbox-title">' +
      '<button type="button" class="sod-shelter-lightbox-close" aria-label="Schließen">&times;</button>' +
      '<h2 id="sod-shelter-lightbox-title" class="sod-shelter-lightbox-title"></h2>' +
      '<img alt="">' +
      '<div class="sod-shelter-lightbox-caption"><strong class="sod-shelter-lightbox-date"></strong><span class="sod-shelter-lightbox-text"></span></div>' +
      "</div>";
    document.body.appendChild(overlay);

    const titleEl = overlay.querySelector(".sod-shelter-lightbox-title");
    const img = overlay.querySelector("img");
    const dateEl = overlay.querySelector(".sod-shelter-lightbox-date");
    const textEl = overlay.querySelector(".sod-shelter-lightbox-text");
    const closeBtn = overlay.querySelector(".sod-shelter-lightbox-close");
    titleEl.textContent = popupTitle;
    let opener = null;

    function close() {
      overlay.classList.remove("is-open");
      document.body.style.removeProperty("overflow");
      opener?.focus();
      opener = null;
    }

    function open(card) {
      const cardImg = card.querySelector("img");
      if (!cardImg) return;
      opener = card;
      img.src = cardImg.currentSrc || cardImg.src;
      img.alt = cardImg.alt || "";
      dateEl.textContent = card.dataset.date || "";
      textEl.textContent = card.dataset.caption || "";
      overlay.classList.add("is-open");
      document.body.style.overflow = "hidden";
      closeBtn.focus();
    }

    overlay.addEventListener("click", function (event) {
      if (event.target === overlay) close();
    });
    closeBtn.addEventListener("click", close);
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && overlay.classList.contains("is-open")) close();
    });

    cards.forEach(function (card) {
      card.addEventListener("click", function () {
        open(card);
      });
    });

    // Beim ersten Seitenaufruf pro Sitzung automatisch den aktuellsten
    // Baufortschritt zeigen (Karten sind bereits nach Datum absteigend sortiert).
    const storageKey = "sod_tierheim_bau_popup_shown";
    try {
      if (!window.sessionStorage.getItem(storageKey)) {
        window.sessionStorage.setItem(storageKey, "1");
        open(cards[0]);
      }
    } catch (error) {
      open(cards[0]);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    setupDonationWidget();
    setupGalleryPreview();
    setupHighlightVideo();
    setupAllVideos();
    setupShelterBuildGallery();
  });
})();
