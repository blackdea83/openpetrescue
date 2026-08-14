(function () {
  document.addEventListener("DOMContentLoaded", function () {
    if (document.body && document.body.classList.contains("post-type-sod_sponsor")) {
      document.querySelectorAll("a.submitdelete").forEach((link) => {
        link.addEventListener("click", (event) => {
          const ok = window.confirm(
            "Diesen Paten löschen?\n\n" +
            "Der verknüpfte Hund wird dadurch wieder für neue Patenschaften freigegeben " +
            "und erscheint wieder in der Patenschafts-Übersicht.\n\n" +
            "OK = löschen und Hund freigeben\nAbbrechen = nichts ändern"
          );
          if (!ok) {
            event.preventDefault();
            event.stopPropagation();
          }
        });
      });
    }

    document.querySelectorAll("[data-sod-wizard]").forEach((wizard, wizardIndex) => {
      const steps = Array.from(wizard.querySelectorAll("[data-sod-step]"));
      const buttons = Array.from(wizard.querySelectorAll("[data-sod-step-button]"));
      const prev = wizard.querySelector("[data-sod-prev]");
      const next = wizard.querySelector("[data-sod-next]");
      let current = Math.max(0, steps.findIndex((step) => step.classList.contains("is-active")));

      buttons.forEach((button, index) => {
        button.id = button.id || `sod-wizard-${wizardIndex}-tab-${index}`;
        button.setAttribute("role", "tab");
      });
      steps.forEach((step, index) => {
        step.id = step.id || `sod-wizard-${wizardIndex}-panel-${index}`;
        step.setAttribute("role", "tabpanel");
        if (buttons[index]) {
          step.setAttribute("aria-labelledby", buttons[index].id);
          buttons[index].setAttribute("aria-controls", step.id);
        }
      });

      function show(index, focusTab) {
        current = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, stepIndex) => step.classList.toggle("is-active", stepIndex === current));
        buttons.forEach((button, buttonIndex) => {
          const active = buttonIndex === current;
          button.classList.toggle("is-active", active);
          button.setAttribute("aria-selected", active ? "true" : "false");
          button.tabIndex = active ? 0 : -1;
        });
        if (prev) prev.disabled = current === 0;
        if (next) next.textContent = current === steps.length - 1 ? "Fertig" : "Weiter";
        if (focusTab && buttons[current]) buttons[current].focus();
      }

      buttons.forEach((button, index) => {
        button.addEventListener("click", () => show(index));
        button.addEventListener("keydown", (event) => {
          let target = null;
          if (event.key === "ArrowRight" || event.key === "ArrowDown") target = current + 1 > buttons.length - 1 ? 0 : current + 1;
          else if (event.key === "ArrowLeft" || event.key === "ArrowUp") target = current - 1 < 0 ? buttons.length - 1 : current - 1;
          else if (event.key === "Home") target = 0;
          else if (event.key === "End") target = buttons.length - 1;
          if (target === null) return;
          event.preventDefault();
          show(target, true);
        });
      });
      if (prev) prev.addEventListener("click", () => show(current - 1));
      if (next) {
        next.addEventListener("click", () => {
          if (current < steps.length - 1) show(current + 1);
          else {
            const publish = document.getElementById("publish");
            if (publish) publish.focus();
          }
        });
      }
      show(current);
    });

    const selectButton = document.getElementById("sodSelectDogImages");
    const clearButton = document.getElementById("sodClearDogImages");
    const input = document.getElementById("sod_dog_image_ids");
    const preview = document.getElementById("sodDogImagesPreview");

    document.querySelectorAll("[data-sod-open-images]").forEach((button) => {
      button.addEventListener("click", () => {
        if (selectButton) selectButton.click();
      });
    });

    document.querySelectorAll("[data-sod-select-video]").forEach((button) => {
      button.addEventListener("click", () => {
        const field = document.getElementById(button.getAttribute("data-sod-select-video"));
        if (!field || !window.wp || !wp.media) return;
        const frame = wp.media({
          title: "Hundevideo auswählen",
          button: { text: "Video übernehmen" },
          multiple: false,
          library: { type: "video" }
        });
        frame.on("select", function () {
          const attachment = frame.state().get("selection").first();
          if (attachment && attachment.attributes && attachment.attributes.url) {
            field.value = attachment.attributes.url;
            field.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });
        frame.open();
      });
    });

    document.querySelectorAll("[data-sod-home-image-select]").forEach((button) => {
      button.addEventListener("click", () => {
        const key = button.getAttribute("data-sod-home-image-select");
        const row = document.querySelector(`[data-sod-home-image-row="${key}"]`);
        const input = document.querySelector(`[data-sod-home-image-input="${key}"]`);
        if (!row || !input || !window.wp || !wp.media) return;
        const frame = wp.media({
          title: "Bild auswählen",
          button: { text: "Bild übernehmen" },
          multiple: false,
          library: { type: "image" }
        });
        frame.on("select", function () {
          const attachment = frame.state().get("selection").first();
          if (!attachment) return;
          const attrs = attachment.attributes;
          const sizes = attrs.sizes || {};
          const thumb = sizes.thumbnail ? sizes.thumbnail.url : attrs.url;
          input.value = attrs.id;
          const thumbBox = row.querySelector(".sod-home-image-thumb");
          if (thumbBox) thumbBox.innerHTML = `<img src="${thumb}" alt="" style="width:100%;height:100%;object-fit:cover">`;
          const desc = row.querySelector(".description");
          if (desc) desc.textContent = "Eigenes Bild ausgewählt.";
        });
        frame.open();
      });
    });

    document.querySelectorAll("[data-sod-home-image-clear]").forEach((button) => {
      button.addEventListener("click", () => {
        const key = button.getAttribute("data-sod-home-image-clear");
        const row = document.querySelector(`[data-sod-home-image-row="${key}"]`);
        const input = document.querySelector(`[data-sod-home-image-input="${key}"]`);
        if (!row || !input) return;
        input.value = "0";
        const thumbBox = row.querySelector(".sod-home-image-thumb");
        if (thumbBox) thumbBox.innerHTML = '<span style="font-size:.6875rem;color:#888;text-align:center">Standard</span>';
        const desc = row.querySelector(".description");
        if (desc) desc.textContent = "Standardbild wird verwendet.";
      });
    });

    document.querySelectorAll("[data-sod-img-select]").forEach((button) => {
      button.addEventListener("click", () => {
        const id = button.getAttribute("data-sod-img-select");
        const input = document.getElementById(id);
        const row = button.closest("[data-sod-img-picker]");
        if (!input || !row || !window.wp || !wp.media) return;
        const frame = wp.media({
          title: "Bild auswählen",
          button: { text: "Bild übernehmen" },
          multiple: false,
          library: { type: "image" }
        });
        frame.on("select", function () {
          const attachment = frame.state().get("selection").first();
          if (!attachment) return;
          const attrs = attachment.attributes;
          const sizes = attrs.sizes || {};
          const preview = sizes.medium ? sizes.medium.url : (sizes.thumbnail ? sizes.thumbnail.url : attrs.url);
          input.value = attrs.id;
          const thumbBox = row.querySelector(".sod-sponsor-imgpicker-thumb");
          if (thumbBox) thumbBox.innerHTML = `<img src="${preview}" alt="" style="width:100%;height:100%;object-fit:cover">`;
        });
        frame.open();
      });
    });

    document.querySelectorAll("[data-sod-img-clear]").forEach((button) => {
      button.addEventListener("click", () => {
        const id = button.getAttribute("data-sod-img-clear");
        const input = document.getElementById(id);
        const row = button.closest("[data-sod-img-picker]");
        if (!input || !row) return;
        input.value = "0";
        const thumbBox = row.querySelector(".sod-sponsor-imgpicker-thumb");
        if (thumbBox) thumbBox.innerHTML = '<span style="font-size:.6875rem;color:#888">Kein Bild</span>';
      });
    });

    document.querySelectorAll("[data-sod-copy]").forEach((button) => {
      const defaultText = button.textContent;
      button.setAttribute("aria-live", "polite");
      button.addEventListener("click", async () => {
        const value = button.getAttribute("data-sod-copy") || "";
        try {
          await navigator.clipboard.writeText(value);
          button.textContent = "Link kopiert";
          window.setTimeout(() => {
            button.textContent = defaultText;
          }, 2500);
        } catch (error) {
          window.prompt("Link kopieren", value);
        }
      });
    });

    document.querySelectorAll("[data-sod-focus]").forEach((button) => {
      button.addEventListener("click", () => {
        const field = document.getElementById(button.getAttribute("data-sod-focus"));
        if (!field) return;
        const step = field.closest("[data-sod-step]");
        const wizard = field.closest("[data-sod-wizard]");
        if (step && wizard) {
          const steps = Array.from(wizard.querySelectorAll("[data-sod-step]"));
          const index = steps.indexOf(step);
          const target = wizard.querySelector(`[data-sod-step-button="${index}"]`);
          if (target) target.click();
        }
        field.focus();
      });
    });

    document.querySelectorAll("[data-sod-confirm]").forEach((link) => {
      link.addEventListener("click", (event) => {
        const message = link.getAttribute("data-sod-confirm") || "Aktion wirklich ausführen?";
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });

    document.querySelectorAll("form[data-sod-confirm]").forEach((form) => {
      form.addEventListener("submit", (event) => {
        const message = form.getAttribute("data-sod-confirm") || "Aktion wirklich ausführen?";
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });

    const visibilityRequired = Array.from(document.querySelectorAll("[data-sod-visibility-required]"));
    if (visibilityRequired.length) {
      const updateVisibilityRequired = () => {
        const checked = visibilityRequired.some((checkbox) => checkbox.checked);
        visibilityRequired.forEach((checkbox, index) => {
          checkbox.required = !checked && index === 0;
          checkbox.setCustomValidity(checked || index !== 0 ? "" : "Bitte mindestens Vermittlung oder Patenschaft auswählen.");
        });
      };
      visibilityRequired.forEach((checkbox) => checkbox.addEventListener("change", updateVisibilityRequired));
      updateVisibilityRequired();
    }

    const caseStatus = document.getElementById("sod_case_status");
    const precheckPanel = document.querySelector("[data-sod-precheck-panel]");
    if (caseStatus && precheckPanel) {
      const updatePrecheckPanel = () => {
        precheckPanel.classList.toggle("is-visible", caseStatus.value === "vorkontrolle");
      };
      caseStatus.addEventListener("change", updatePrecheckPanel);
      updatePrecheckPanel();
    }

    if (!selectButton || !input || !preview || !window.wp || !wp.media) return;

    function render(attachments) {
      input.value = attachments.map((attachment) => attachment.id).join(",");
      preview.innerHTML = attachments.map((attachment) => {
        const sizes = attachment.attributes.sizes || {};
        const thumb = sizes.thumbnail ? sizes.thumbnail.url : attachment.attributes.url;
        return `<div class="sod-dog-image-thumb" data-image-id="${attachment.id}"><img src="${thumb}" alt=""></div>`;
      }).join("");
    }

    selectButton.addEventListener("click", function () {
      const currentIds = input.value.split(",").map((id) => Number(id.trim())).filter(Boolean);
      const frame = wp.media({
        title: "Hundebilder auswählen",
        button: { text: "Bilder übernehmen" },
        multiple: true,
        library: { type: "image" }
      });

      frame.on("open", function () {
        const selection = frame.state().get("selection");
        currentIds.forEach((id) => {
          const attachment = wp.media.attachment(id);
          attachment.fetch();
          selection.add(attachment ? [attachment] : []);
        });
      });

      frame.on("select", function () {
        render(frame.state().get("selection").toArray());
      });

      frame.open();
    });

    if (clearButton) {
      clearButton.addEventListener("click", function () {
        input.value = "";
        preview.innerHTML = "";
      });
    }
  });
})();
