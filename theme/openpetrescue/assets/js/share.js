/**
 * Teilen-Funktion (inc/share.php): schwebender Button, Symbole auf Hundekarten, Kasten auf Hundeseiten.
 * Keine Verbindung zu Facebook, WhatsApp, Instagram oder TikTok, bevor jemand selbst klickt.
 */
(function () {
  var cfg = window.sodShare || {};
  var L = cfg.l || {};
  var icons = {};
  var tpl = document.getElementById('sodsh-icons');
  if (tpl) {
    tpl.content.querySelectorAll('[data-icon]').forEach(function (el) { icons[el.getAttribute('data-icon')] = el.innerHTML; });
  }
  var isTouch = window.matchMedia('(pointer: coarse)').matches;

  function canonicalUrl() {
    var link = document.querySelector('link[rel="canonical"]');
    return link ? link.href : window.location.href.split('#')[0];
  }
  function pageImage() {
    var og = document.querySelector('meta[property="og:image"]');
    return og ? og.content : '';
  }

  // Kopiert und meldet immer Erfolg weiter: Wird die Zwischenablage verweigert, greift die alte Methode.
  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(function () { return legacyCopy(text); });
    }
    return legacyCopy(text);
  }
  function legacyCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    ta.remove();
    return Promise.resolve();
  }

  function toast(message, actionLabel, actionUrl) {
    document.querySelectorAll('.sodsh-toast').forEach(function (old) { old.remove(); });
    var t = document.createElement('div');
    t.className = 'sodsh-toast';
    t.setAttribute('role', 'status');
    var p = document.createElement('span');
    p.textContent = message;
    t.appendChild(p);
    if (actionLabel && actionUrl) {
      // Eigener Klick oeffnet die App – ein automatisches Oeffnen wuerde der Popup-Blocker verhindern.
      var a = document.createElement('a');
      a.href = actionUrl;
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = actionLabel;
      t.appendChild(a);
    }
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('is-on'); }, 10);
    setTimeout(function () { t.classList.remove('is-on'); setTimeout(function () { t.remove(); }, 300); }, 9000);
  }

  function openService(service, data) {
    var full = data.text + ' ' + data.url;
    if (service === 'whatsapp') {
      window.open('https://wa.me/?text=' + encodeURIComponent(full), '_blank', 'noopener');
    } else if (service === 'facebook') {
      window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(data.url), '_blank', 'noopener,width=620,height=560');
    } else if (service === 'instagram' || service === 'tiktok') {
      // Beide Dienste haben keinen Teilen-Link: Text + Link kopieren und App/Seite oeffnen.
      copy(full).then(function () {
        toast(
          service === 'instagram' ? L.igHint : L.ttHint,
          service === 'instagram' ? 'Instagram ↗' : 'TikTok ↗',
          service === 'instagram' ? 'https://www.instagram.com/' : 'https://www.tiktok.com/'
        );
      });
    }
  }

  // ---------------------------------------------------------------- Fenster
  var dialog = null;
  function buildDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'sodsh-dialog';
    dialog.innerHTML =
      '<button type="button" class="sodsh-close" aria-label="' + (L.close || 'Schließen') + '">×</button>' +
      '<p class="sodsh-dialog-title"></p>' +
      '<div class="sodsh-card"><img alt=""><div><strong></strong><small></small></div></div>' +
      '<div class="sodsh-grid">' +
      ['whatsapp', 'facebook', 'instagram', 'tiktok'].map(function (s) {
        var label = { whatsapp: 'WhatsApp', facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok' }[s];
        var cls = { whatsapp: 'is-wa', facebook: 'is-fb', instagram: 'is-ig', tiktok: 'is-tt' }[s];
        return '<button type="button" class="sodsh-btn ' + cls + '" data-sodsh-service="' + s + '">' + (icons[s] || '') + label + '</button>';
      }).join('') +
      '</div>' +
      '<div class="sodsh-link"><input type="text" readonly><button type="button" class="sodsh-copy"></button></div>';
    dialog.querySelector('.sodsh-close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.querySelector('.sodsh-copy').addEventListener('click', function () {
      var btn = this;
      copy(dialog._data.url).then(function () {
        btn.textContent = L.copied || '✓';
        setTimeout(function () { btn.textContent = L.copy || 'Link kopieren'; }, 1600);
      });
    });
    dialog.addEventListener('click', function (e) {
      var s = e.target.closest('[data-sodsh-service]');
      if (s) openService(s.getAttribute('data-sodsh-service'), dialog._data);
    });
    document.body.appendChild(dialog);
  }

  function share(data) {
    // Am Handy: Teilen-Menue des Telefons (enthaelt Instagram, TikTok, WhatsApp …)
    if (isTouch && navigator.share) {
      navigator.share({ title: data.title, text: data.text, url: data.url }).catch(function () {});
      return;
    }
    if (typeof document.createElement('dialog').showModal !== 'function') {
      openService('whatsapp', data);
      return;
    }
    if (!dialog) buildDialog();
    dialog._data = data;
    dialog.querySelector('.sodsh-dialog-title').textContent = L.share || 'Teilen';
    var img = dialog.querySelector('.sodsh-card img');
    img.src = data.image || pageImage();
    img.hidden = !img.src;
    dialog.querySelector('.sodsh-card strong').textContent = data.title;
    dialog.querySelector('.sodsh-card small').textContent = data.text;
    dialog.querySelector('.sodsh-link input').value = data.url;
    dialog.querySelector('.sodsh-copy').textContent = L.copy || 'Link kopieren';
    dialog.showModal();
  }

  // ---------------------------------------------------------------- Schwebender Button
  var fab = document.createElement('button');
  fab.type = 'button';
  fab.className = 'sodsh-fab';
  fab.innerHTML = (icons.share || '') + '<span>' + (L.sitebtn || 'Seite teilen') + '</span>' + (cfg.preview ? '<em>' + (L.preview || 'Vorschau') + '</em>' : '');
  fab.addEventListener('click', function () {
    var isHome = document.body.classList.contains('home');
    var title = document.title;
    share({
      title: isHome ? cfg.site.title : title,
      text: isHome ? cfg.site.text : title,
      url: canonicalUrl(),
      image: pageImage(),
    });
  });
  document.body.appendChild(fab);

  // ---------------------------------------------------------------- Kasten auf der Hundeseite
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.sodsh-box [data-sodsh-service]');
    if (!btn) return;
    var box = btn.closest('.sodsh-box');
    var data = {
      title: box.getAttribute('data-sodsh-title'),
      text: box.getAttribute('data-sodsh-text'),
      url: box.getAttribute('data-sodsh-url'),
      image: box.getAttribute('data-sodsh-image'),
    };
    openService(btn.getAttribute('data-sodsh-service'), data);
  });

  // ---------------------------------------------------------------- Symbole auf Hundekarten
  function decorateCards(root) {
    (root || document).querySelectorAll('.dog-card').forEach(function (card) {
      if (card.querySelector('.sodsh-card-btn')) return;
      var media = card.querySelector('.dog-card-media');
      var link = card.querySelector('.dog-card-name-link');
      if (!media || !link) return;
      var name = link.textContent.trim();
      var img = card.querySelector('.dog-card-media img');
      // Geschlecht steht als Text in den Kartenangaben; die Werte sind immer deutsch gespeichert.
      var meta = card.textContent || '';
      var gender = /Weiblich|Female|Ženk/i.test(meta) ? 'f' : (/M(ä|a)nnlich|Male|Mužjak|Muški/i.test(meta) ? 'm' : 'n');
      var cardText = (cfg.cardText && cfg.cardText[gender]) || (cfg.cardText && cfg.cardText.n) || '%s';
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sodsh-card-btn';
      b.setAttribute('aria-label', (L.shareDog || '%s teilen').replace('%s', name));
      b.innerHTML = icons.share || '';
      b.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        share({
          title: name + (cfg.orgName ? ' – ' + cfg.orgName : ''),
          text: cardText.replace('%s', name),
          url: link.href.split('#')[0],
          image: img ? (img.currentSrc || img.src) : '',
        });
      });
      if (getComputedStyle(media).position === 'static') media.style.position = 'relative';
      media.appendChild(b);
    });
  }
  decorateCards();
  // Karten, die spaeter nachgeladen werden (Filter, Seitenwechsel, Spenden-Popup)
  if ('MutationObserver' in window) {
    new MutationObserver(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].addedNodes.length) { decorateCards(); break; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
