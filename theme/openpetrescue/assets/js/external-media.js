/**
 * Externe Videos (YouTube, TikTok, Instagram) – Zustimmung gilt site-weit.
 * Ohne Zustimmung wird nichts von den Anbietern geladen. Nach einem Klick auf
 * "Externe Videos erlauben" merkt sich der Browser die Wahl (localStorage
 * sodExternalMedia) und alle eingebetteten Videos laden, sobald sie ins Bild
 * scrollen, und starten ohne Ton. Widerruf ueber den Button in der
 * Datenschutzerklaerung. Beschrieben in Datenschutz Punkt 6 und 10.
 */
(function () {
  var KEY = 'sodExternalMedia';

  function allowed() {
    try { return window.localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function setAllowed(value) {
    try {
      if (value) { window.localStorage.setItem(KEY, '1'); } else { window.localStorage.removeItem(KEY); }
    } catch (e) {}
  }

  // YouTube ueber youtube-nocookie.com, Autoplay nur stumm (Browser-Vorgabe).
  function prepareIframe(iframe) {
    var src = iframe.getAttribute('src') || '';
    if (!src) return;
    try {
      var url = new URL(src, window.location.href);
      if (/(^|\.)youtube\.com$/.test(url.hostname) || url.hostname === 'youtu.be') {
        url.hostname = 'www.youtube-nocookie.com';
      }
      if (/youtube-nocookie\.com$/.test(url.hostname)) {
        url.searchParams.set('autoplay', '1');
        url.searchParams.set('mute', '1');
        url.searchParams.set('playsinline', '1');
        url.searchParams.set('rel', '0');
      }
      iframe.setAttribute('src', url.toString());
    } catch (e) {}
    var allow = iframe.getAttribute('allow') || '';
    if (allow.indexOf('autoplay') === -1) {
      iframe.setAttribute('allow', (allow ? allow + '; ' : '') + 'autoplay; encrypted-media; picture-in-picture');
    }
  }

  function loadGate(gate) {
    if (!gate || !gate.classList.contains('sod-video-consent')) return;
    var template = gate.querySelector('.sod-video-embed-template');
    if (!template) return;
    var content = template.content.cloneNode(true);
    content.querySelectorAll('iframe').forEach(prepareIframe);
    gate.replaceChildren(content);
    gate.classList.remove('sod-video-consent');
    gate.classList.add('sod-video-embed');
  }

  var observer = null;
  function loadAllWhenVisible() {
    var targets = document.querySelectorAll('.sod-video-consent');
    if (!targets.length) return;
    if (!('IntersectionObserver' in window)) {
      targets.forEach(loadGate);
      return;
    }
    if (!observer) {
      observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          observer.unobserve(entry.target);
          loadGate(entry.target);
        });
      }, { rootMargin: '200px 0px' });
    }
    targets.forEach(function (el) { observer.observe(el); });
  }

  // TikTok-Startseite: eigene Karten (woechentlich aktualisiert, inc/tiktok-feed.php),
  // Video im Popup. TikTok erlaubt kein erzwungenes Autoplay – Start per Tipp.
  function hideTikTokConsent() {
    // hidden-Attribut reicht nicht, die Theme-CSS setzt display explizit.
    document.querySelectorAll('.tiktok-consent').forEach(function (box) { box.style.display = 'none'; });
  }

  function tiktokDialog(section) {
    var dialog = document.getElementById('sod-tiktok-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.id = 'sod-tiktok-dialog';
    dialog.className = 'sod-tiktok-dialog';
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'sod-tiktok-dialog-close';
    close.setAttribute('aria-label', (section && section.getAttribute('data-tiktok-close')) || 'Schließen');
    close.textContent = '×';
    close.addEventListener('click', function () { dialog.close(); });
    var body = document.createElement('div');
    body.className = 'sod-tiktok-dialog-body';
    dialog.append(close, body);
    dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
    // Beim Schliessen iframe entfernen, damit das Video stoppt.
    dialog.addEventListener('close', function () {
      body.replaceChildren();
      document.body.style.overflow = '';
    });
    document.body.appendChild(dialog);
    return dialog;
  }

  function showTikTokVideo(body, id) {
    var iframe = document.createElement('iframe');
    iframe.src = 'https://www.tiktok.com/embed/v2/' + id;
    iframe.title = 'TikTok Video';
    iframe.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
    iframe.setAttribute('allowfullscreen', '');
    body.replaceChildren(iframe);
  }

  function openTikTok(card) {
    var match = (card.getAttribute('href') || '').match(/\/video\/(\d+)/);
    var dialogSupported = typeof document.createElement('dialog').showModal === 'function';
    if (!match || !dialogSupported) return false;
    var id = match[1];
    var section = card.closest('.tiktok-section');
    var dialog = tiktokDialog(section);
    var body = dialog.querySelector('.sod-tiktok-dialog-body');
    if (allowed()) {
      showTikTokVideo(body, id);
    } else {
      var consentText = section ? section.querySelector('.tiktok-consent p') : null;
      var consentBtn = section ? section.querySelector('.tiktok-load') : null;
      var box = document.createElement('div');
      box.className = 'sod-tiktok-dialog-consent';
      var p = document.createElement('p');
      p.textContent = consentText ? consentText.textContent : '';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-md';
      btn.textContent = consentBtn ? consentBtn.textContent : 'Externe Videos erlauben';
      btn.addEventListener('click', function () {
        setAllowed(true);
        hideTikTokConsent();
        updateStatus();
        loadAllWhenVisible();
        showTikTokVideo(body, id);
      });
      box.append(p, btn);
      body.replaceChildren(box);
    }
    document.body.style.overflow = 'hidden';
    dialog.showModal();
    return true;
  }

  document.addEventListener('click', function (event) {
    var card = event.target.closest('.tiktok-card[href]');
    if (!card) return;
    // Neuer Tab / Mittelklick behaelt den normalen Link zu TikTok.
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (openTikTok(card)) event.preventDefault();
  });

  // Capture-Phase: vor dem alten Einzel-Klick-Handler im Plugin.
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.sod-video-consent-load, .tiktok-load');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    setAllowed(true);
    var own = button.closest('.sod-video-consent');
    if (own) loadGate(own);
    if (button.classList.contains('tiktok-load')) hideTikTokConsent();
    loadAllWhenVisible();
    updateStatus();
  }, true);

  // Datenschutzerklaerung: Status anzeigen und Widerruf.
  function updateStatus() {
    document.querySelectorAll('[data-sod-media-status]').forEach(function (box) {
      var on = allowed();
      var a = box.querySelector('[data-sod-media-allowed]');
      var b = box.querySelector('[data-sod-media-blocked]');
      var btn = box.querySelector('[data-sod-media-revoke]');
      if (a) a.hidden = !on;
      if (b) b.hidden = on;
      // .btn setzt display selbst, daher nicht nur das hidden-Attribut.
      if (btn) { btn.hidden = !on; btn.style.display = on ? '' : 'none'; }
    });
  }
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-sod-media-revoke]')) return;
    setAllowed(false);
    updateStatus();
  });

  function init() {
    updateStatus();
    if (allowed()) {
      hideTikTokConsent();
      loadAllWhenVisible();
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
