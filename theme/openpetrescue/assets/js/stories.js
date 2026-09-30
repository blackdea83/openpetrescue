/**
 * Schicksale: Filter der Uebersicht, Video-Popup mit Zustimmung, Link kopieren.
 * Videos (YouTube, youtube-nocookie) laden erst nach "Externe Videos erlauben";
 * die Wahl wird wie auf der Live-Seite unter localStorage "sodExternalMedia" gemerkt.
 */
(function () {
  var KEY = 'sodExternalMedia';
  function allowed() {
    try { return window.localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function allow() {
    try { window.localStorage.setItem(KEY, '1'); } catch (e) {}
  }
  var labels = document.querySelector('.sodst-consent-template');
  function label(name, fallback) {
    return (labels && labels.getAttribute('data-sodst-' + name)) || fallback;
  }

  // Filter "Braucht dich jetzt" / "Happy Ends"
  var tabs = document.querySelectorAll('[data-sodst-filter]');
  function applyFilter(type) {
    var visible = 0;
    document.querySelectorAll('.sodst-card').forEach(function (card) {
      var show = card.getAttribute('data-sodst-type') === type;
      card.hidden = !show;
      if (show) visible++;
    });
    document.querySelectorAll('[data-sodst-empty]').forEach(function (el) {
      el.hidden = !(el.getAttribute('data-sodst-empty') === type && visible === 0 && el.textContent.trim() !== '');
    });
    tabs.forEach(function (tab) {
      var on = tab.getAttribute('data-sodst-filter') === type;
      tab.classList.toggle('is-on', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }
  if (tabs.length) {
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () { applyFilter(tab.getAttribute('data-sodst-filter')); });
    });
    applyFilter('need');
  }

  // Video-Popup
  var dialog = null;
  var currentWide = false;
  function getDialog() {
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.className = 'sodst-dialog';
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'sodst-dialog-close';
    close.setAttribute('aria-label', label('close', 'Schließen'));
    close.textContent = '×';
    close.addEventListener('click', function () { dialog.close(); });
    var body = document.createElement('div');
    body.className = 'sodst-dialog-body';
    dialog.append(close, body);
    dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () { body.replaceChildren(); document.body.style.overflow = ''; });
    document.body.appendChild(dialog);
    return dialog;
  }
  function play(body, id) {
    dialog.classList.toggle('is-wide', currentWide);
    var iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&playsinline=1&rel=0';
    iframe.title = 'YouTube Video';
    iframe.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    body.replaceChildren(iframe);
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-sodst-video]');
    if (!link) return;
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (typeof document.createElement('dialog').showModal !== 'function') return;
    event.preventDefault();
    var id = link.getAttribute('data-sodst-video');
    currentWide = link.getAttribute('data-sodst-wide') === '1';
    var d = getDialog();
    var body = d.querySelector('.sodst-dialog-body');
    if (allowed()) {
      play(body, id);
    } else {
      var box = document.createElement('div');
      box.className = 'sodst-dialog-consent';
      var p = document.createElement('p');
      p.textContent = label('consent-text', 'Dieses Video kommt von YouTube. Nach deiner Zustimmung werden Videos von YouTube geladen.');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-primary btn-md';
      btn.textContent = label('consent-btn', 'Externe Videos erlauben');
      btn.addEventListener('click', function () { allow(); play(body, id); });
      box.append(p, btn);
      body.replaceChildren(box);
    }
    document.body.style.overflow = 'hidden';
    d.showModal();
  });

  // Link kopieren
  document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-sodst-copy]');
    if (!btn || !navigator.clipboard) return;
    navigator.clipboard.writeText(btn.getAttribute('data-sodst-copy')).then(function () {
      var old = btn.textContent;
      btn.textContent = btn.getAttribute('data-sodst-copied') || '✓';
      setTimeout(function () { btn.textContent = old; }, 1800);
    });
  });
})();
