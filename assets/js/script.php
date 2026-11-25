<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?>
/* ==========================================================================
   CAIRS - assets/js/script.php
   Plain JavaScript, no libraries. Each section is a small function that
   only runs if the element it needs exists on the current page.

   1  Helpers              6  Toast messages (success fades out)
   2  Sidebar drawer       7  Count-up numbers on stat cards
   3  Dropdown menu        8  Photo preview (with Change / Remove)
   4  Live clock           9  Print / Save as PDF
   5  Modals + confirm    10  Maps (Leaflet): picker, report map, admin map
                          11  Live updates (bell count, SOS toast)
   ========================================================================== */
(function () {
  'use strict';

  /* 1. HELPERS ----------------------------------------------------------- */
  function $(selector, root) { return (root || document).querySelector(selector); }
  function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;


  /* 2. SIDEBAR DRAWER & COLLAPSIBLE SIDE PANEL --------------------------- */
  var sidebarNavApi = null;

  function initSidebarNavAnimation() {
    var nav = $('.sidebar nav');
    if (!nav) return null;

    var pill = $('.nav-active-pill', nav);
    if (!pill) {
      pill = document.createElement('span');
      pill.className = 'nav-active-pill';
      pill.setAttribute('aria-hidden', 'true');
      nav.insertBefore(pill, nav.firstChild);
    }
    nav.classList.add('has-pill');

    var links = $all('a[href]', nav);
    links.forEach(function (a, idx) {
      a.style.setProperty('--nav-idx', String(Math.min(idx, 10)));
    });

    var lastLink = null;
    var moveTimer = null;
    var revealTimer = null;

    function resolveInitialActive() {
      var curPath = window.location.pathname;
      var curHash = window.location.hash;
      if (curHash) {
        for (var i = 0; i < links.length; i++) {
          try {
            var u = new URL(links[i].href, window.location.href);
            if (u.pathname === curPath && u.hash === curHash) {
              links.forEach(function (l) { l.classList.toggle('active', l === links[i]); });
              return links[i];
            }
          } catch (_) {}
        }
      }
      return $('a.active', nav);
    }

    function positionPill(animate) {
      var active = $('a.active', nav);
      if (!active || active.offsetHeight === 0) {
        pill.classList.remove('is-ready');
        lastLink = active;
        return;
      }
      var y = active.offsetTop;
      var h = active.offsetHeight;

      if (!animate || !pill.classList.contains('is-ready')) {
        var prevTransition = pill.style.transition;
        pill.style.transition = 'none';
        pill.style.transform = 'translate3d(0,' + y + 'px,0)';
        pill.style.height = h + 'px';
        pill.offsetHeight; // flush layout before restoring transition
        pill.style.transition = prevTransition || '';
        pill.classList.add('is-ready');
      } else {
        if (lastLink && lastLink !== active) {
          pill.classList.remove('is-moving');
          pill.offsetWidth;
          pill.classList.add('is-moving');
          clearTimeout(moveTimer);
          moveTimer = setTimeout(function () { pill.classList.remove('is-moving'); }, 360);
        }
        pill.style.transform = 'translate3d(0,' + y + 'px,0)';
        pill.style.height = h + 'px';
      }
      lastLink = active;
    }

    function setActiveByUrl(urlObj) {
      var targetPath = urlObj.pathname;
      var targetHash = urlObj.hash || '';
      var exact = null;
      var pathOnly = null;

      links = $all('a[href]', nav);
      links.forEach(function (a) {
        try {
          var u = new URL(a.href, window.location.href);
          if (u.pathname === targetPath && u.hash === targetHash && !exact) exact = a;
          else if (u.pathname === targetPath && !u.hash && !pathOnly) pathOnly = a;
        } catch (_) {}
      });

      var matched = exact || pathOnly;
      if (matched) {
        links.forEach(function (a) { a.classList.toggle('active', a === matched); });
        positionPill(true);
      }
    }

    function syncFromDoc(newNav, destUrl) {
      if (!newNav) return;
      var oldLinks = $all('a[href]', nav);
      var newLinks = $all('a[href]', newNav);

      if (oldLinks.length !== newLinks.length) {
        nav.innerHTML = '';
        nav.appendChild(pill);
        newLinks.forEach(function (nl, idx) {
          var clone = nl.cloneNode(true);
          clone.style.setProperty('--nav-idx', String(Math.min(idx, 10)));
          nav.appendChild(clone);
        });
        links = $all('a[href]', nav);
        if (destUrl) setActiveByUrl(destUrl);
        else positionPill(true);
        return;
      }

      for (var i = 0; i < oldLinks.length; i++) {
        var oldA = oldLinks[i];
        var newA = newLinks[i];
        oldA.classList.toggle('active', newA.classList.contains('active'));
        var oldBadge = $('.count', oldA);
        var newBadge = $('.count', newA);
        if (newBadge && oldBadge) {
          oldBadge.textContent = newBadge.textContent;
        } else if (newBadge && !oldBadge) {
          oldA.appendChild(newBadge.cloneNode(true));
        } else if (!newBadge && oldBadge) {
          oldBadge.remove();
        }
      }
      if (destUrl) setActiveByUrl(destUrl);
      else positionPill(true);
    }

    function reveal() {
      if (reduceMotion) { positionPill(false); return; }
      nav.classList.remove('is-revealing');
      nav.offsetWidth;
      nav.classList.add('is-revealing');
      clearTimeout(revealTimer);
      revealTimer = setTimeout(function () { nav.classList.remove('is-revealing'); }, 650);
      requestAnimationFrame(function () { positionPill(false); });
    }

    resolveInitialActive();
    requestAnimationFrame(function () {
      positionPill(false);
      reveal();
    });

    if (window.ResizeObserver) {
      var ro = new ResizeObserver(function () { positionPill(false); });
      ro.observe(nav);
    } else {
      window.addEventListener('resize', function () { positionPill(false); });
    }

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () { positionPill(false); });
    }

    return {
      positionPill: positionPill,
      setActiveByUrl: setActiveByUrl,
      syncFromDoc: syncFromDoc,
      reveal: reveal,
    };
  }

  function initSidebar() {
    var sidebar = $('.sidebar');
    var toggle = $('.nav-toggle');
    var closeBtn = $('#sidebar-close-btn');
    var overlay = $('.sidebar-overlay');
    if (!sidebar || !toggle) return;

    sidebarNavApi = initSidebarNavAnimation();

    var STORAGE_KEY = 'cairs_sidebar_closed';
    function isMobile() { return window.innerWidth <= 820; }

    function setMobileOpen(open) {
      sidebar.classList.toggle('open', open);
      if (overlay) overlay.classList.toggle('is-visible', open);
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && sidebarNavApi) sidebarNavApi.reveal();
    }

    function setDesktopClosed(closed) {
      document.body.classList.toggle('sidebar-closed', closed);
      toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
      if (closeBtn) {
        var lbl = closed ? 'Expand side panel' : 'Collapse side panel';
        closeBtn.setAttribute('aria-label', lbl);
        closeBtn.setAttribute('title', lbl);
        closeBtn.setAttribute('aria-expanded', closed ? 'false' : 'true');
      }
      try {
        if (closed) localStorage.setItem(STORAGE_KEY, '1');
        else localStorage.removeItem(STORAGE_KEY);
      } catch (_) {}
      if (sidebarNavApi) {
        sidebarNavApi.reveal();
        setTimeout(function () { sidebarNavApi.positionPill(false); }, 270);
      }
    }

    try {
      if (!isMobile() && localStorage.getItem(STORAGE_KEY) === '1') {
        setDesktopClosed(true);
      } else if (!isMobile()) {
        toggle.setAttribute('aria-expanded', 'true');
        if (closeBtn) closeBtn.setAttribute('aria-expanded', 'true');
      }
    } catch (_) {}

    toggle.addEventListener('click', function () {
      if (isMobile()) setMobileOpen(!sidebar.classList.contains('open'));
      else setDesktopClosed(!document.body.classList.contains('sidebar-closed'));
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        if (isMobile()) setMobileOpen(false);
        else setDesktopClosed(!document.body.classList.contains('sidebar-closed'));
      });
    }

    if (overlay) overlay.addEventListener('click', function () { setMobileOpen(false); });
    sidebar.addEventListener('click', function (e) {
      var link = e.target && e.target.closest ? e.target.closest('a') : null;
      if (link && isMobile()) setMobileOpen(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isMobile()) setMobileOpen(false); });
    window.addEventListener('resize', function () {
      if (!isMobile()) setMobileOpen(false);
    });
  }


  /* 3. DROPDOWN MENU (user menu in the top bar) -------------------------- */
  function initDropdowns() {
    var wraps = $all('.dropdown');
    function closeAll() {
      wraps.forEach(function (w) {
        w.classList.remove('open');
        var b = $('[data-dropdown]', w);
        if (b) b.setAttribute('aria-expanded', 'false');
      });
    }
    wraps.forEach(function (wrap) {
      var button = $('[data-dropdown]', wrap);
      if (!button) return;
      button.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !wrap.classList.contains('open');
        closeAll();
        wrap.classList.toggle('open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });
    document.addEventListener('click', closeAll);                                              // click outside
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
  }


  /* 4. LIVE CLOCK -------------------------------------------------------- */
  function initClock() {
    var clocks = $all('[data-clock]');
    var dates = $all('[data-clock-date]');
    if (!clocks.length && !dates.length) return;

    function tick() {
      var now = new Date();
      var short = now.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
      var long = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
      clocks.forEach(function (el) { el.textContent = short; });
      dates.forEach(function (el) { el.textContent = long; });
    }
    tick();
    setInterval(tick, 30000);
  }


  /* 5. MODALS + CONFIRM DIALOG ------------------------------------------- */
  var lastFocus = null;

  function portalPageModals() {
    var mainEl = document.getElementById('main');
    if (!mainEl) return;
    $all('.modal', mainEl).forEach(function (m) {
      if (m.id) {
        $all('.modal[data-spa-modal]', document.body).forEach(function (existing) {
          if (existing !== m && existing.id === m.id) existing.remove();
        });
      }
      m.setAttribute('data-spa-modal', '1');
      document.body.appendChild(m);
    });
  }

  function canScrollInsideModal(target, modal, deltaY) {
    var el = target && target.nodeType === 1 ? target : (target && target.parentElement ? target.parentElement : null);
    while (el && el !== modal) {
      if (el.scrollHeight > el.clientHeight + 1) {
        var st = window.getComputedStyle ? getComputedStyle(el) : null;
        var oy = st ? st.overflowY : '';
        if (oy === 'auto' || oy === 'scroll') {
          if (typeof deltaY !== 'number' || deltaY === 0) return true;
          if (deltaY < 0 && el.scrollTop > 0) return true;
          if (deltaY > 0 && el.scrollTop + el.clientHeight < el.scrollHeight - 1) return true;
        }
      }
      el = el.parentElement;
    }
    return false;
  }

  function openModal(modal) {
    if (modal.parentNode !== document.body) {
      modal.setAttribute('data-spa-modal', '1');
      document.body.appendChild(modal);
    }
    lastFocus = document.activeElement;
    document.documentElement.classList.add('has-modal');
    document.body.classList.add('has-modal');
    var mBody = $('.modal-body', modal);
    if (mBody) mBody.scrollTop = 0;
    modal.classList.add('is-mounted');
    void modal.offsetWidth;                         // force a reflow so the CSS transition plays
    modal.classList.add('is-open');
    var focusable = $('[data-autofocus]', modal) || $('button, a[href], input:not([disabled]), select, textarea', modal);
    if (focusable && focusable.focus) {
      try { focusable.focus({ preventScroll: true }); } catch (_) { focusable.focus(); }
    }
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    if (!$('.modal.is-open')) {
      document.documentElement.classList.remove('has-modal');
      document.body.classList.remove('has-modal');
    }
    setTimeout(function () { modal.classList.remove('is-mounted'); }, reduceMotion ? 0 : 260);
    if (lastFocus && lastFocus.focus) {
      try { lastFocus.focus({ preventScroll: true }); } catch (_) { lastFocus.focus(); }
    }
  }

  function initModals() {
    if (initModals._bound) return;
    initModals._bound = true;

    // Any element with data-modal-open="modal-id" opens that modal
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-modal-open]') : null;
      if (btn) {
        var m = document.getElementById(btn.getAttribute('data-modal-open'));
        if (m) openModal(m);
        return;
      }
      var closer = e.target.closest ? e.target.closest('[data-modal-close]') : null;
      if (closer) { var cm = closer.closest('.modal'); if (cm) closeModal(cm); }
    });
    document.addEventListener('keydown', function (e) {
      var open = $('.modal.is-open');
      if (!open) return;
      if (e.key === 'Escape') { closeModal(open); return; }
      if (e.key === 'Tab') {                         // keep keyboard focus inside the dialog
        var items = $all('button, a[href], input, select, textarea', open);
        if (!items.length) return;
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    // Prevent wheel and touch scrolling from leaking to the background page
    document.addEventListener('wheel', function (e) {
      if (!document.body.classList.contains('has-modal')) return;
      var open = $('.modal.is-open');
      if (!open) return;
      if (!open.contains(e.target) || !canScrollInsideModal(e.target, open, e.deltaY)) {
        e.preventDefault();
      }
    }, { passive: false });
    document.addEventListener('touchmove', function (e) {
      if (!document.body.classList.contains('has-modal')) return;
      var open = $('.modal.is-open');
      if (!open) return;
      if (!open.contains(e.target) || !canScrollInsideModal(e.target, open, 0)) {
        e.preventDefault();
      }
    }, { passive: false });
  }

  // <form data-confirm="Are you sure?"> asks first, using a styled dialog
  function initConfirmForms() {
    $all('.spa-confirm-modal').forEach(function (el) { el.remove(); });
    var forms = $all('form[data-confirm]');
    if (!forms.length) return;

    var modal = document.createElement('div');
    modal.className = 'modal spa-confirm-modal';
    modal.innerHTML =
      '<div class="modal-backdrop" data-modal-close></div>' +
      '<div class="modal-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">' +
      '<h3 id="confirm-title">Please confirm</h3>' +
      '<p id="confirm-text" class="muted"></p>' +
      '<div class="modal-actions">' +
      '<button type="button" class="btn ghost" data-modal-close>Cancel</button>' +
      '<button type="button" class="btn" id="confirm-yes" data-autofocus>Yes, continue</button>' +
      '</div></div>';
    document.body.appendChild(modal);

    var pendingForm = null;
    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        pendingForm = form;
        $('#confirm-text', modal).textContent = form.getAttribute('data-confirm');
        openModal(modal);
      });
    });
    $('#confirm-yes', modal).addEventListener('click', function () {
      var form = pendingForm;
      closeModal(modal);
      if (form) form.submit();                       // form.submit() skips the submit event, so no loop
    });
  }


  /* 6. TOAST MESSAGES ---------------------------------------------------- */
  function toastIcon(type) {
    var path = type === 'error'
      ? '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>'
      : '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>';
    return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
  }

  function toastRegion() {
    var region = document.getElementById('toast-region');
    if (!region) {
      region = document.createElement('div');
      region.id = 'toast-region';
      region.className = 'toast-region';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'false');
      document.body.appendChild(region);
    }
    return region;
  }

  function bindToastDismiss(toast, timeout) {
    var timer = null;
    var done = false;
    function dismiss() {
      if (done) return;
      done = true;
      if (timer) clearTimeout(timer);
      toast.classList.add('hide');
      setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
    }
    function schedule() {
      if (done) return;
      if (timer) clearTimeout(timer);
      timer = setTimeout(dismiss, timeout);
    }
    var close = $('.toast-close', toast);
    if (close) close.addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', function () { if (timer) clearTimeout(timer); });
    toast.addEventListener('mouseleave', schedule);
    toast.addEventListener('focusin', function () { if (timer) clearTimeout(timer); });
    toast.addEventListener('focusout', schedule);
    schedule();
  }

  function createToast(type, timeout) {
    var toast = document.createElement('div');
    toast.className = 'flash ' + (type || 'success') + ' toast';
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var icon = document.createElement('span');
    icon.className = 'toast-ic';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = toastIcon(type);
    var body = document.createElement('div');
    body.className = 'toast-body';
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';
    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(close);
    bindToastDismiss(toast, timeout || (type === 'error' ? 9000 : 5000));
    toastRegion().appendChild(toast);
    return body;
  }

  function showToast(msg, type, timeout) {
    createToast(type, timeout).textContent = msg;
  }

  function initToasts() {
    $all('.toast').forEach(function (toast) {
      bindToastDismiss(toast, toast.classList.contains('error') ? 9000 : 5000);
    });
    // Old-style inline success message (kept so nothing breaks)
    var inline = $('.flash.success:not(.toast)');
    if (inline) setTimeout(function () { inline.style.display = 'none'; }, 5000);
  }


  /* 7. COUNT-UP NUMBERS (12, 85%, 4.5 ...) ------------------------------- */
  function initCountUp() {
    if (reduceMotion) return;
    $all('.stat b').forEach(function (el) {
      var match = el.textContent.trim().match(/^(\d+(?:\.\d+)?)(.*)$/);
      if (!match) return;                            // e.g. "-" stays as it is
      var end = parseFloat(match[1]);
      var suffix = match[2];
      var decimals = (match[1].split('.')[1] || '').length;
      var start = null;

      el.textContent = (0).toFixed(decimals) + suffix;
      function step(time) {
        if (el.dataset.staticLock === '1') return;
        if (!start) start = time;
        var progress = Math.min((time - start) / 900, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = (end * eased).toFixed(decimals) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      }
      setTimeout(function () { if (el.dataset.staticLock !== '1') requestAnimationFrame(step); }, 250);
    });
  }


  /* 8. PHOTO PREVIEW ----------------------------------------------------- */
  function initPhotoPreview() {
    var input = document.getElementById('photo');
    var box = document.getElementById('preview');
    if (!input || !box) return;
    var url = null;

    function clearPreview() {
      if (url) URL.revokeObjectURL(url);
      url = null;
      box.innerHTML = '';
    }

    input.addEventListener('change', function () {
      clearPreview();
      var file = input.files[0];
      if (!file) return;

      var figure = document.createElement('figure');
      figure.className = 'photo-preview';

      var img = document.createElement('img');
      url = URL.createObjectURL(file);
      img.src = url;
      img.alt = 'Selected photo preview';

      var actions = document.createElement('div');
      actions.className = 'actions';

      var change = document.createElement('button');
      change.type = 'button';
      change.className = 'btn ghost small';
      change.textContent = 'Change photo';
      change.addEventListener('click', function () { input.click(); });

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn ghost small';
      remove.textContent = 'Remove';
      remove.addEventListener('click', function () { input.value = ''; clearPreview(); });   // nothing is uploaded until Submit

      actions.appendChild(change);
      actions.appendChild(remove);
      figure.appendChild(img);
      figure.appendChild(actions);

      if (file.size > 3 * 1024 * 1024) {
        var warn = document.createElement('p');
        warn.className = 'muted';
        warn.textContent = 'This photo is over 3 MB. Please choose a smaller one.';
        figure.appendChild(warn);
      }
      box.appendChild(figure);
    });
  }


  /* 9. PRINT / SAVE AS PDF ----------------------------------------------- */
  function initPrint() {
    $all('[data-print]').forEach(function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
  }


  /* 10. MAPS (Leaflet is only loaded on pages that need it) -------------- */
  var MAP_SELECTOR = '#map-picker, #map-view, #map-all, #dispatch-map, #settings-map-picker';

  function ensureMapSkeleton(el) {
    if (!el || el.querySelector(':scope > .map-skeleton')) return null;
    var sk = document.createElement('div');
    sk.className = 'map-skeleton';
    sk.setAttribute('aria-hidden', 'true');
    var pill = document.createElement('div');
    pill.className = 'map-skeleton-pill';
    var dot = document.createElement('span');
    dot.className = 'map-skeleton-dot';
    var txt = document.createElement('span');
    txt.textContent = 'Loading map tiles...';
    pill.appendChild(dot);
    pill.appendChild(txt);
    sk.appendChild(pill);
    el.appendChild(sk);
    return sk;
  }

  function primeMapSkeletons() {
    $all(MAP_SELECTOR).forEach(function (el) {
      if (!el._leafletMap) ensureMapSkeleton(el);
    });
  }

  function initMaps() {
    if (!window.L) return;

    var worldBounds = [[-85.0511, -180], [85.0511, 180]];
    var mapOpts = {
      minZoom: 3,
      maxBounds: worldBounds,
      maxBoundsViscosity: 1.0,
      worldCopyJump: false,
      scrollWheelZoom: true
    };

    function addTiles(map) {
      return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        noWrap: true,
        bounds: worldBounds,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
    }

    function attachMapLifecycle(el, map, tileLayer) {
      el._leafletMap = map;
      var sk = el.querySelector(':scope > .map-skeleton') || ensureMapSkeleton(el);
      var done = false;
      function finishSkeleton() {
        if (done) return;
        done = true;
        if (sk) {
          sk.classList.add('is-done');
          setTimeout(function () { if (sk.parentNode) sk.parentNode.removeChild(sk); }, 280);
        }
      }
      if (tileLayer && tileLayer.once) tileLayer.once('load', finishSkeleton);
      setTimeout(finishSkeleton, 1500);

      setTimeout(function () {
        if (map && map.invalidateSize) map.invalidateSize(false);
      }, 120);

      if (window.ResizeObserver && !el._mapResizeObs) {
        var ro = new ResizeObserver(function () {
          if (map && map.invalidateSize) map.invalidateSize(false);
        });
        ro.observe(el);
        el._mapResizeObs = ro;
      }
    }

    function centerOf(el) { return [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)]; }

    function fetchApproxIpLocation(onSuccess, onError) {
      var providers = [
        {
          url: 'https://get.geojs.io/v1/ip/geo.json',
          parse: function (d) {
            if (!d) return null;
            return {
              lat: parseFloat(d.latitude),
              lng: parseFloat(d.longitude),
              label: [d.city, d.region, d.country].filter(Boolean).slice(0, 2).join(', ')
            };
          }
        },
        {
          url: 'https://ipwho.is/',
          parse: function (d) {
            if (!d || d.success === false) return null;
            return {
              lat: parseFloat(d.latitude),
              lng: parseFloat(d.longitude),
              label: [d.city, d.region, d.country].filter(Boolean).slice(0, 2).join(', ')
            };
          }
        }
      ];

      function tryNext(idx) {
        if (idx >= providers.length) {
          onError();
          return;
        }
        var p = providers[idx];
        var ctrl = window.AbortController ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 4500) : null;
        fetch(p.url, { signal: ctrl ? ctrl.signal : undefined, cache: 'no-store' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (timer) clearTimeout(timer);
            var parsed = p.parse(data);
            if (parsed && !isNaN(parsed.lat) && !isNaN(parsed.lng) &&
                Math.abs(parsed.lat) <= 90 && Math.abs(parsed.lng) <= 180 &&
                (parsed.lat !== 0 || parsed.lng !== 0)) {
              onSuccess(parsed.lat, parsed.lng, parsed.label);
            } else {
              tryNext(idx + 1);
            }
          })
          .catch(function () {
            if (timer) clearTimeout(timer);
            tryNext(idx + 1);
          });
      }

      tryNext(0);
    }

    function requestCurrentLocation(btn, onFound) {
      if (btn && btn.dataset.locating === '1') return;

      var savedNodes = btn ? Array.prototype.slice.call(btn.childNodes) : [];
      if (btn) {
        btn.dataset.locating = '1';
        btn.disabled = true;
        btn.classList.add('is-locating');
        btn.textContent = '';
        btn.appendChild(createSvgIcon('loader', 15));
        btn.appendChild(document.createTextNode(' Locating...'));
      }

      function restoreBtn() {
        if (!btn) return;
        btn.dataset.locating = '0';
        btn.disabled = false;
        btn.classList.remove('is-locating');
        btn.textContent = '';
        savedNodes.forEach(function (n) { btn.appendChild(n); });
      }

      function finishOk(lat, lng, approximate, areaLabel) {
        restoreBtn();
        onFound(lat, lng, { approximate: !!approximate, label: areaLabel || '' });
        if (approximate) {
          var where = areaLabel ? ('near ' + areaLabel) : 'near your network location';
          showToast('Centered ' + where + ' (approximate \u2014 exact GPS requires HTTPS). Drag the pin or search to refine.');
        } else {
          showToast('Centered map on your current location.');
        }
      }

      function showLookupError(err) {
        restoreBtn();
        var code = err && err.code;
        var msg = 'Could not detect your location. Search a place above or click the map.';
        if (!window.isSecureContext) {
          msg = 'Exact GPS requires HTTPS and network lookup is unavailable. Search a place above or click the map.';
        } else if (code === 1) {
          msg = 'Location access is blocked in browser/site settings. Search a place above or click the map.';
        } else if (code === 2) {
          msg = 'Location unavailable on this device/browser. Search a place above or click the map.';
        } else if (code === 3) {
          msg = 'Location lookup timed out. Try again, search an address, or click the map.';
        }
        showToast(msg, 'error');
      }

      function fallbackToIp(gpsErr) {
        fetchApproxIpLocation(function (lat, lng, areaLabel) {
          finishOk(lat, lng, true, areaLabel);
        }, function () {
          showLookupError(gpsErr);
        });
      }

      if (!window.isSecureContext || !navigator.geolocation) {
        fallbackToIp(null);
        return;
      }

      function handleGpsOk(pos) {
        finishOk(pos.coords.latitude, pos.coords.longitude, false, '');
      }

      navigator.geolocation.getCurrentPosition(handleGpsOk, function (err) {
        if (err && (err.code === 2 || err.code === 3)) {
          navigator.geolocation.getCurrentPosition(handleGpsOk, function (err2) {
            fallbackToIp(err2 || err);
          }, {
            enableHighAccuracy: false,
            timeout: 7000,
            maximumAge: 300000
          });
        } else {
          fallbackToIp(err);
        }
      }, { enableHighAccuracy: true, timeout: 7000, maximumAge: 60000 });
    }

    function parseCoordinates(raw) {
      var m = String(raw || '').trim().match(/^([+-]?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*([+-]?\d{1,3}(?:\.\d+)?)$/);
      if (!m) return null;
      var lat = parseFloat(m[1]);
      var lng = parseFloat(m[2]);
      if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
      return { lat: lat, lng: lng };
    }

    function attachMapSearch(el, map, onSelect, placeholder) {
      if (!el || el.querySelector(':scope > .map-search-bar')) return;

      var bar = document.createElement('div');
      bar.className = 'map-search-bar';

      var row = document.createElement('div');
      row.className = 'map-search-row';

      var field = document.createElement('div');
      field.className = 'map-search-field';

      var ic = document.createElement('span');
      ic.className = 'map-search-ic';
      ic.appendChild(createSvgIcon('search', 14));

      var input = document.createElement('input');
      input.type = 'search';
      input.className = 'map-search-input';
      input.autocomplete = 'off';
      input.placeholder = placeholder || 'Search place or lat, lng...';
      input.setAttribute('aria-label', 'Search map location or coordinates');

      var clearBtn = document.createElement('button');
      clearBtn.type = 'button';
      clearBtn.className = 'map-search-clear';
      clearBtn.setAttribute('aria-label', 'Clear map search');
      clearBtn.hidden = true;
      clearBtn.appendChild(createSvgIcon('x', 13));

      field.appendChild(ic);
      field.appendChild(input);
      field.appendChild(clearBtn);

      var findBtn = document.createElement('button');
      findBtn.type = 'button';
      findBtn.className = 'btn ghost small map-search-btn';
      findBtn.textContent = 'Find';

      row.appendChild(field);
      row.appendChild(findBtn);

      var resultsEl = document.createElement('div');
      resultsEl.className = 'map-search-results';
      resultsEl.setAttribute('role', 'listbox');
      resultsEl.hidden = true;

      bar.appendChild(row);
      bar.appendChild(resultsEl);
      el.appendChild(bar);

      if (window.L && L.DomEvent) {
        L.DomEvent.disableClickPropagation(bar);
        L.DomEvent.disableScrollPropagation(bar);
      }
      ['click', 'dblclick', 'mousedown', 'pointerdown', 'touchstart', 'wheel'].forEach(function (evName) {
        bar.addEventListener(evName, function (ev) { ev.stopPropagation(); }, { passive: true });
      });

      var debounceTimer = null;
      var activeCtrl = null;
      var currentItems = [];
      var activeIdx = -1;

      function hideResults() {
        resultsEl.hidden = true;
        resultsEl.textContent = '';
        currentItems = [];
        activeIdx = -1;
      }

      function chooseItem(item) {
        if (!item) return;
        input.value = item.inputLabel || item.title;
        clearBtn.hidden = !input.value;
        hideResults();
        onSelect(item.lat, item.lng, item.shortPlace || '');
      }

      function highlightIndex(idx) {
        var btns = $all('.map-search-item', resultsEl);
        if (!btns.length) { activeIdx = -1; return; }
        activeIdx = Math.max(0, Math.min(idx, btns.length - 1));
        btns.forEach(function (b, i) { b.classList.toggle('is-active', i === activeIdx); });
      }

      function renderResults(items, message) {
        resultsEl.textContent = '';
        currentItems = items || [];
        activeIdx = -1;
        if (message) {
          var st = document.createElement('div');
          st.className = 'map-search-state';
          st.textContent = message;
          resultsEl.appendChild(st);
          resultsEl.hidden = false;
          return;
        }
        if (!currentItems.length) {
          hideResults();
          return;
        }
        currentItems.forEach(function (item) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'map-search-item';
          btn.setAttribute('role', 'option');

          var iconBox = document.createElement('span');
          iconBox.className = 'map-search-item-ic';
          iconBox.appendChild(createSvgIcon('pin', 14));

          var copy = document.createElement('span');
          copy.className = 'map-search-item-copy';
          var title = document.createElement('span');
          title.className = 'map-search-item-title';
          title.textContent = item.title;
          var sub = document.createElement('span');
          sub.className = 'map-search-item-sub';
          sub.textContent = item.subtitle;
          copy.appendChild(title);
          copy.appendChild(sub);

          var coords = document.createElement('span');
          coords.className = 'map-search-item-coords';
          coords.textContent = item.lat.toFixed(4) + ', ' + item.lng.toFixed(4);

          btn.appendChild(iconBox);
          btn.appendChild(copy);
          btn.appendChild(coords);
          btn.addEventListener('click', function () { chooseItem(item); });
          resultsEl.appendChild(btn);
        });
        resultsEl.hidden = false;
      }

      function runSearch(immediateJump) {
        var q = input.value.trim();
        clearBtn.hidden = !q;
        if (!q) { hideResults(); return; }

        var coords = parseCoordinates(q);
        if (coords) {
          var coordItem = {
            lat: coords.lat,
            lng: coords.lng,
            title: 'Jump to coordinates',
            subtitle: coords.lat.toFixed(5) + ', ' + coords.lng.toFixed(5),
            inputLabel: coords.lat.toFixed(5) + ', ' + coords.lng.toFixed(5),
            shortPlace: ''
          };
          if (immediateJump) {
            chooseItem(coordItem);
            return;
          }
          renderResults([coordItem]);
          return;
        }

        if (q.length < 2) {
          if (immediateJump) renderResults([], 'Type at least 2 characters or paste coordinates (e.g. 8.4542, 124.6319).');
          else hideResults();
          return;
        }

        if (activeCtrl) activeCtrl.abort();
        activeCtrl = window.AbortController ? new AbortController() : null;
        renderResults([], 'Searching places...');

        var b = map.getBounds ? map.getBounds() : null;
        var vb = b ? ('&viewbox=' + [b.getWest().toFixed(4), b.getNorth().toFixed(4), b.getEast().toFixed(4), b.getSouth().toFixed(4)].join(',')) : '';
        var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6' + vb + '&q=' + encodeURIComponent(q);

        fetch(url, {
          headers: { 'Accept': 'application/json' },
          signal: activeCtrl ? activeCtrl.signal : undefined
        })
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (rows) {
            if (!Array.isArray(rows) || !rows.length) {
              renderResults([], 'No matching places found. Try a street, landmark, city, or paste lat, lng.');
              return;
            }
            var mapped = rows.map(function (row) {
              var full = String(row.display_name || '');
              var parts = full.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
              var head = String(row.name || parts[0] || 'Pinned location');
              var tail = parts.slice(parts[0] === head ? 1 : 0, 4).join(', ');
              return {
                lat: parseFloat(row.lat),
                lng: parseFloat(row.lon),
                title: head,
                subtitle: tail || full,
                inputLabel: head + (tail ? ', ' + tail : ''),
                shortPlace: head + (parts[1] && parts[1] !== head ? ', ' + parts[1] : '')
              };
            }).filter(function (x) { return !isNaN(x.lat) && !isNaN(x.lng); });

            if (immediateJump && mapped.length === 1) {
              chooseItem(mapped[0]);
              return;
            }
            renderResults(mapped);
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') return;
            renderResults([], 'Place search could not reach the map directory. Paste coordinates (lat, lng) or click the map.');
          });
      }

      input.addEventListener('input', function () {
        clearBtn.hidden = !input.value.trim();
        clearTimeout(debounceTimer);
        if (!input.value.trim()) { hideResults(); return; }
        var coords = parseCoordinates(input.value);
        if (coords) { runSearch(false); return; }
        debounceTimer = setTimeout(function () { runSearch(false); }, 340);
      });

      input.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowDown') {
          if (!resultsEl.hidden) { ev.preventDefault(); highlightIndex(activeIdx + 1); }
        } else if (ev.key === 'ArrowUp') {
          if (!resultsEl.hidden) { ev.preventDefault(); highlightIndex(activeIdx - 1); }
        } else if (ev.key === 'Escape') {
          hideResults();
        } else if (ev.key === 'Enter') {
          ev.preventDefault();
          if (!resultsEl.hidden && activeIdx >= 0 && currentItems[activeIdx]) {
            chooseItem(currentItems[activeIdx]);
          } else {
            clearTimeout(debounceTimer);
            runSearch(true);
          }
        }
      });

      findBtn.addEventListener('click', function () {
        clearTimeout(debounceTimer);
        runSearch(true);
      });

      clearBtn.addEventListener('click', function () {
        clearTimeout(debounceTimer);
        if (activeCtrl) activeCtrl.abort();
        input.value = '';
        clearBtn.hidden = true;
        hideResults();
        input.focus();
      });

      document.addEventListener('click', function (ev) {
        if (!bar.contains(ev.target)) hideResults();
      });
    }

    function createReverseGeocoder() {
      var timer = null;
      var ctrl = null;
      function cancel() {
        clearTimeout(timer);
        if (ctrl) { ctrl.abort(); ctrl = null; }
      }
      function formatAddress(data) {
        if (!data) return '';
        var a = data.address || {};
        var primary = a.amenity || a.building || a.road || a.pedestrian || a.footway || a.neighbourhood || a.suburb || a.village || '';
        var secondary = a.neighbourhood || a.suburb || a.village || a.town || a.city_district || a.city || '';
        var parts = [];
        if (primary) parts.push(primary);
        if (secondary && secondary.toLowerCase() !== primary.toLowerCase()) parts.push(secondary);
        if (parts.length) return parts.join(', ');
        var raw = String(data.display_name || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        return raw.slice(0, 2).join(', ');
      }
      function lookup(lat, lng, onAddress) {
        cancel();
        timer = setTimeout(function () {
          ctrl = window.AbortController ? new AbortController() : null;
          var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&lat=' +
            encodeURIComponent(lat.toFixed(6)) + '&lon=' + encodeURIComponent(lng.toFixed(6));
          fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: ctrl ? ctrl.signal : undefined
          })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
              var label = formatAddress(data);
              if (label && onAddress) onAddress(label);
            })
            .catch(function () {});
        }, 240);
      }
      return { lookup: lookup, cancel: cancel };
    }

    // 10a. Report form: click the map (or use GPS) to drop a pin
    var picker = document.getElementById('map-picker');
    if (picker && !picker._leafletMap) {
      var pickMap = L.map(picker, mapOpts).setView(centerOf(picker), picker.dataset.pin === '1' ? 17 : 14);
      var pin = null;
      var latInput = document.getElementById('latitude');
      var lngInput = document.getElementById('longitude');
      var repLocInput = document.getElementById('location');
      var repLastAuto = '';
      var repRevGeo = createReverseGeocoder();
      attachMapLifecycle(picker, pickMap, addTiles(pickMap));

      var applyAutoRepPlace = function (label) {
        if (!repLocInput || !label) return;
        var cur = repLocInput.value.trim();
        if (!cur || cur === repLastAuto) {
          repLocInput.value = label;
          repLastAuto = label;
        }
      };

      var setPin = function (lat, lng, resolvePlace) {
        if (pin) {
          pin.setLatLng([lat, lng]);
        } else {
          pin = L.marker([lat, lng], { draggable: true }).addTo(pickMap);
          pin.on('dragend', function () {
            var pos = pin.getLatLng();
            setPin(pos.lat, pos.lng, true);
          });
        }
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        if (resolvePlace) repRevGeo.lookup(lat, lng, applyAutoRepPlace);
      };
      if (picker.dataset.pin === '1') setPin(centerOf(picker)[0], centerOf(picker)[1], false);
      pickMap.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng, true); });

      attachMapSearch(picker, pickMap, function (lat, lng, placeLabel) {
        repRevGeo.cancel();
        setPin(lat, lng, false);
        pickMap.setView([lat, lng], 17);
        applyAutoRepPlace(placeLabel);
      });

      var locate = document.getElementById('locate');
      if (locate) locate.addEventListener('click', function () {
        requestCurrentLocation(locate, function (lat, lng, meta) {
          setPin(lat, lng, true);
          pickMap.setView([lat, lng], (meta && meta.approximate) ? 14 : 17);
        });
      });
    }

    // 10b. Report details: show the saved pin
    var view = document.getElementById('map-view');
    if (view && !view._leafletMap) {
      var viewMap = L.map(view, mapOpts).setView(centerOf(view), 17);
      attachMapLifecycle(view, viewMap, addTiles(viewMap));
      L.marker(centerOf(view)).addTo(viewMap);
    }

    // 10c. Admin: every pinned report, colored by status
    var all = document.getElementById('map-all');
    if (all && !all._leafletMap) {
      var allMap = L.map(all, mapOpts).setView(centerOf(all), 14);
      var points = JSON.parse(all.dataset.points);
      var markers = [];
      attachMapLifecycle(all, allMap, addTiles(allMap));

      points.forEach(function (p) {
        var icon = L.divIcon({
          className: 'pin pin-' + p.key,
          html: '<span></span>',
          iconSize: [24, 24], iconAnchor: [12, 24], popupAnchor: [0, -24]
        });
        var box = document.createElement('div');            // popup built with textContent = safe from HTML injection
        var link = document.createElement('a');
        var status = document.createElement('div');
        link.href = p.url;
        link.textContent = p.ref + ' ' + p.title;
        status.textContent = p.status;
        box.appendChild(link);
        box.appendChild(status);
        markers.push(L.marker([p.lat, p.lng], { icon: icon }).addTo(allMap).bindPopup(box));
      });
      if (markers.length) allMap.fitBounds(L.featureGroup(markers).getBounds().pad(0.2), { maxZoom: 16 });
      attachMapSearch(all, allMap, function (lat, lng) {
        allMap.setView([lat, lng], 17);
      }, 'Jump to place or lat, lng...');
    }

    // 10d. Admin Pin & Dispatch: click/drag/search to pin destination + filter existing reports
    var dispatchEl = document.getElementById('dispatch-map');
    if (dispatchEl && !dispatchEl._leafletMap) {
      var dMap = L.map(dispatchEl, mapOpts).setView(centerOf(dispatchEl), dispatchEl.dataset.pin === '1' ? 16 : 14);
      attachMapLifecycle(dispatchEl, dMap, addTiles(dMap));
      var dLatIn = document.getElementById('dispatch-lat');
      var dLngIn = document.getElementById('dispatch-lng');
      var dLocIn = document.getElementById('location');
      var dCoords = document.getElementById('dispatch-coords');
      var dCard = document.getElementById('dispatch-map-card');
      var dStatusBox = document.getElementById('dispatch-pin-status');
      var dStatusHead = document.getElementById('dispatch-pin-heading');
      var dStatusSub = document.getElementById('dispatch-pin-summary');
      var dJumpBtn = document.getElementById('dispatch-jump-map');
      var dClear = document.getElementById('dispatch-clear');
      var dRecenter = document.getElementById('dispatch-recenter');
      var dShowClosed = document.getElementById('dispatch-show-closed');
      var dPin = null;
      var dLastAutoPlace = '';
      var dRevGeo = createReverseGeocoder();

      var activeGroup = L.layerGroup().addTo(dMap);
      var closedGroup = L.layerGroup();

      try {
        var existingPts = JSON.parse(dispatchEl.dataset.points || '[]');
        existingPts.forEach(function (p) {
          var pIcon = L.divIcon({
            className: 'pin pin-' + p.key,
            html: '<span></span>',
            iconSize: [20, 20], iconAnchor: [10, 20], popupAnchor: [0, -20]
          });
          var box = document.createElement('div');
          var link = document.createElement('a');
          var st = document.createElement('div');
          link.href = p.url;
          link.textContent = p.ref + ' ' + p.title;
          st.textContent = p.status;
          box.appendChild(link);
          box.appendChild(st);
          var m = L.marker([p.lat, p.lng], { icon: pIcon }).bindPopup(box);
          if (p.key === 'resolved' || p.key === 'rejected') closedGroup.addLayer(m);
          else activeGroup.addLayer(m);
        });
      } catch (err) {}

      if (dShowClosed) {
        dShowClosed.addEventListener('change', function () {
          if (dShowClosed.checked) closedGroup.addTo(dMap);
          else dMap.removeLayer(closedGroup);
        });
      }

      var dispatchIcon = L.divIcon({
        className: 'pin pin-dispatch',
        html: '<span></span>',
        iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -30]
      });

      function applyDispatchPlace(label, lat, lng) {
        if (!label) return;
        if (dLocIn) {
          var cur = dLocIn.value.trim();
          if (!cur || cur === dLastAutoPlace) {
            dLocIn.value = label;
            dLastAutoPlace = label;
          }
        }
        if (dCoords && dLatIn && dLatIn.value) {
          dCoords.textContent = 'Pinned: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' \u00b7 ' + label;
        }
        if (dStatusSub && dLatIn && dLatIn.value) {
          dStatusSub.textContent = label + ' (' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ')';
        }
      }

      function syncPinStatus(lat, lng) {
        var pinned = typeof lat === 'number' && typeof lng === 'number';
        var coordStr = pinned ? lat.toFixed(5) + ', ' + lng.toFixed(5) : '';
        if (dCoords) {
          dCoords.textContent = pinned ? ('Pinned: ' + coordStr) : 'No pin placed yet \u2014 click the map';
          dCoords.classList.toggle('is-pinned', pinned);
        }
        if (dClear) dClear.hidden = !pinned;
        if (dCard && pinned) dCard.classList.remove('needs-pin');
        if (dStatusBox) {
          dStatusBox.classList.toggle('is-pinned', pinned);
          if (pinned) dStatusBox.classList.remove('is-error');
        }
        if (dStatusHead) dStatusHead.textContent = pinned ? 'Dispatch pin ready' : 'Location pin required';
        if (dStatusSub) dStatusSub.textContent = pinned ? coordStr : 'Click or search on the map to drop a pin';
        if (dJumpBtn) dJumpBtn.textContent = pinned ? 'Adjust pin' : 'Select on map';
      }

      function updateDispatchPin(lat, lng, pan, resolvePlace) {
        if (!dPin) {
          dPin = L.marker([lat, lng], { icon: dispatchIcon, draggable: true, zIndexOffset: 1000 }).addTo(dMap);
          dPin.bindPopup('Dispatch target (drag to adjust)');
          dPin.on('dragend', function () {
            var pos = dPin.getLatLng();
            updateDispatchPin(pos.lat, pos.lng, false, true);
          });
        } else {
          dPin.setLatLng([lat, lng]);
        }
        if (dLatIn) dLatIn.value = lat.toFixed(7);
        if (dLngIn) dLngIn.value = lng.toFixed(7);
        syncPinStatus(lat, lng);
        if (pan) dMap.setView([lat, lng], Math.max(dMap.getZoom(), 17));
        if (resolvePlace !== false) {
          dRevGeo.lookup(lat, lng, function (label) { applyDispatchPlace(label, lat, lng); });
        }
      }

      if (dispatchEl.dataset.pin === '1') {
        var initCoords = centerOf(dispatchEl);
        updateDispatchPin(initCoords[0], initCoords[1], false, false);
      }

      dMap.on('click', function (ev) {
        updateDispatchPin(ev.latlng.lat, ev.latlng.lng, false, true);
      });

      attachMapSearch(dispatchEl, dMap, function (lat, lng, placeLabel) {
        dRevGeo.cancel();
        updateDispatchPin(lat, lng, true, !placeLabel);
        if (placeLabel) applyDispatchPlace(placeLabel, lat, lng);
      });

      var dLocate = document.getElementById('dispatch-locate');
      if (dLocate) {
        dLocate.addEventListener('click', function () {
          requestCurrentLocation(dLocate, function (lat, lng, meta) {
            updateDispatchPin(lat, lng, false, true);
            dMap.setView([lat, lng], (meta && meta.approximate) ? 14 : Math.max(dMap.getZoom(), 17));
          });
        });
      }

      if (dRecenter) {
        dRecenter.addEventListener('click', function () {
          var cLat = parseFloat(dispatchEl.dataset.centerLat || '');
          var cLng = parseFloat(dispatchEl.dataset.centerLng || '');
          var targetCenter = (!isNaN(cLat) && !isNaN(cLng)) ? [cLat, cLng] : centerOf(dispatchEl);
          dMap.setView(targetCenter, 14);
        });
      }

      if (dJumpBtn && dCard) {
        dJumpBtn.addEventListener('click', function () {
          dCard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        });
      }

      if (dClear) {
        dClear.addEventListener('click', function () {
          dRevGeo.cancel();
          if (dPin) { dMap.removeLayer(dPin); dPin = null; }
          if (dLatIn) dLatIn.value = '';
          if (dLngIn) dLngIn.value = '';
          if (dLocIn && dLastAutoPlace && dLocIn.value.trim() === dLastAutoPlace) {
            dLocIn.value = '';
          }
          dLastAutoPlace = '';
          syncPinStatus(null, null);
        });
      }

      var dForm = document.getElementById('dispatch-form');
      if (dForm) {
        dForm.addEventListener('submit', function (ev) {
          if (!dLatIn || !dLngIn || !dLatIn.value || !dLngIn.value) {
            ev.preventDefault();
            if (dCard) {
              dCard.classList.add('needs-pin');
              dCard.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
            }
            if (dStatusBox) dStatusBox.classList.add('is-error');
            showToast('Click on the map to drop a pin before dispatching.', 'error');
          }
        });
      }
    }

    // 10e. Admin Settings: barangay center coordinate picker
    var settingsEl = document.getElementById('settings-map-picker');
    if (settingsEl && !settingsEl._leafletMap) {
      var sLatIn = document.getElementById('map_lat');
      var sLngIn = document.getElementById('map_lng');
      var sReadout = document.getElementById('settings-map-readout');
      var initCenter = centerOf(settingsEl);
      var sMap = L.map(settingsEl, mapOpts).setView(initCenter, 14);
      attachMapLifecycle(settingsEl, sMap, addTiles(sMap));

      var sPin = L.marker(initCenter, { draggable: true }).addTo(sMap);
      sPin.bindPopup('Default Barangay Center');

      function applySettingsCoords(lat, lng, pan) {
        if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
        sPin.setLatLng([lat, lng]);
        if (sLatIn) sLatIn.value = lat.toFixed(6);
        if (sLngIn) sLngIn.value = lng.toFixed(6);
        if (sReadout) sReadout.textContent = 'Center: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
        if (pan) sMap.setView([lat, lng], Math.max(sMap.getZoom(), 16));
      }

      sMap.on('click', function (ev) {
        applySettingsCoords(ev.latlng.lat, ev.latlng.lng, false);
      });
      sPin.on('dragend', function () {
        var pos = sPin.getLatLng();
        applySettingsCoords(pos.lat, pos.lng, false);
      });

      attachMapSearch(settingsEl, sMap, function (lat, lng) {
        applySettingsCoords(lat, lng, true);
      }, 'Search place or lat, lng...');

      var sLocate = document.getElementById('settings-locate');
      if (sLocate) {
        sLocate.addEventListener('click', function () {
          requestCurrentLocation(sLocate, function (lat, lng, meta) {
            applySettingsCoords(lat, lng, false);
            sMap.setView([lat, lng], (meta && meta.approximate) ? 13 : Math.max(sMap.getZoom(), 16));
          });
        });
      }

      [sLatIn, sLngIn].forEach(function (inp) {
        if (!inp) return;
        inp.addEventListener('change', function () {
          var la = parseFloat(sLatIn ? sLatIn.value : '');
          var ln = parseFloat(sLngIn ? sLngIn.value : '');
          if (!isNaN(la) && !isNaN(ln) && Math.abs(la) <= 90 && Math.abs(ln) <= 180) {
            sPin.setLatLng([la, ln]);
            sMap.setView([la, ln], Math.max(sMap.getZoom(), 14));
            if (sReadout) sReadout.textContent = 'Center: ' + la.toFixed(5) + ', ' + ln.toFixed(5);
          }
        });
      });
    }
  }


  /* 11. LIVE UPDATES & NOTIFICATION POPOVER ------------------------------ */
  function setCountBadge(host, n) {
    if (!host) return;
    var badge = $('.count', host);
    if (n > 0) {
      if (!badge) { badge = document.createElement('span'); badge.className = 'count'; host.appendChild(badge); }
      badge.textContent = n;
    } else if (badge && badge.parentNode) {
      badge.parentNode.removeChild(badge);
    }
  }

  function syncUnreadUi(n) {
    var bell = $('#notif-bell-btn') || $('.topbar .icon-btn');
    setCountBadge(bell, n);
    if (bell) {
      bell.setAttribute('aria-label', n > 0 ? ('Notifications (' + n + ' unread)') : 'Notifications');
    }
    var pill = $('#notif-new-pill');
    if (pill) {
      pill.textContent = n + ' New';
      pill.hidden = !(n > 0);
    }
  }

  function getCsrfToken() {
    var m = $('meta[name="csrf-token"]');
    return m ? (m.getAttribute('content') || '') : '';
  }

  function isSafeRelUrl(u) {
    return typeof u === 'string' && u.charAt(0) === '/' && u.charAt(1) !== '/';
  }

  function createSvgIcon(kind, size) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'icon');
    svg.setAttribute('width', String(size || 16));
    svg.setAttribute('height', String(size || 16));
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');

    var paths = {
      bell: ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
      siren: ['M7 18v-6a5 5 0 1 1 10 0v6', 'M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z', 'M12 12v6'],
      x: ['M18 6 6 18', 'm6 6 12 12'],
      arrow: ['M5 12h14', 'm12 5 7 7-7 7'],
      'chevron-down': ['m6 9 6 6 6-6'],
      check: ['M20 6 9 17l-5-5'],
      'check-circle': ['M22 11.08V12a10 10 0 1 1-5.93-9.14', 'm9 11 3 3L22 4'],
      clock: ['M12 6v6l4 2', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z'],
      'user-check': ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'm16 11 2 2 4-4'],
      'user-x': ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'm17 8 5 5', 'm22 8-5 5'],
      user: ['M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
      loader: ['M12 2v4', 'm16.2 7.8 2.9-2.9', 'M18 12h4', 'm16.2 16.2 2.9 2.9', 'M12 18v4', 'm4.9 19.1 2.9-2.9', 'M2 12h4', 'm4.9 4.9 2.9 2.9'],
      'x-circle': ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'm15 9-6 6', 'm9 9 6 6'],
      layers: ['m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z', 'm22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65', 'm22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65'],
      tag: ['M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z', 'M7 7h.01'],
      flame: ['M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z'],
      droplet: ['M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z'],
      'shield-alert': ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10', 'M12 8v4', 'M12 16h.01'],
      'alert-triangle': ['m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z', 'M12 9v4', 'M12 17h.01'],
      'heart-pulse': ['M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z', 'M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27'],
      'volume-2': ['M11 5 6 9H2v6h4l5 4V5Z', 'M15.54 8.46a5 5 0 0 1 0 7.07', 'M19.07 4.93a10 10 0 0 1 0 14.14'],
      trash: ['M3 6h18', 'M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6', 'M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2'],
      zap: ['M13 2 3 14h9l-1 8 10-12h-9l1-8z'],
      info: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 16v-4', 'M12 8h.01'],
      'arrow-up': ['M12 19V5', 'm5 12 7-7 7 7'],
      'arrow-down': ['M12 5v14', 'm19 12-7 7-7-7'],
      minus: ['M5 12h14'],
      shield: ['M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z', 'm9 12 2 2 4-4'],
      radar: ['M19.07 4.93A10 10 0 0 0 6.99 3.34', 'M2.29 9.62A10 10 0 1 0 21.31 8.35', 'M16.24 7.76A6 6 0 1 0 8.23 16.67', 'm13.41 10.59 5.66-5.66', 'M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z'],
      sparkles: ['m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z'],
      shapes: ['M8.3 10a.7.7 0 0 1-.626-1.079L11.4 3a.7.7 0 0 1 1.198-.043L16.3 8.9a.7.7 0 0 1-.572 1.1Z', 'M3 15a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z', 'M17.5 14a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z'],
      grid: ['M3 4a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z', 'M14 4a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1z', 'M14 15a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1z', 'M3 15a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z'],
      megaphone: ['m3 11 18-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6'],
      plus: ['M5 12h14', 'M12 5v14'],
      search: ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z', 'm21 21-4.3-4.3'],
      pin: ['M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z', 'M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
    };
    (paths[kind] || paths.bell).forEach(function (d) {
      var p = document.createElementNS(ns, 'path');
      p.setAttribute('d', d);
      svg.appendChild(p);
    });
    return svg;
  }

  var refreshNotifPopover = function () {};

  var closeNotifPopover = function () {};
  var closeSidebarPopover = function () {};

  function initNotificationPopover() {
    var bellBtn = $('#notif-bell-btn');
    var popover = $('#notif-popover');
    var listEl = $('#notif-list');
    if (!bellBtn || !popover || !listEl || !window.fetch) return;

    var markAllBtn = $('#notif-mark-all');
    var clearAllBtn = $('#notif-clear-all');
    var closeBtn = $('#notif-close');
    var tabsWrap = $('.notif-pop-tabs', popover);
    var tabButtons = $all('[data-notif-tab]', popover);
    var tabPill = null;
    var lastPillRect = null;
    var activeTab = 'all';
    var state = { items: [], unread: 0, total: 0, loaded: false };

    if (tabsWrap && tabButtons.length) {
      tabPill = $('.notif-tab-pill', tabsWrap);
      if (!tabPill) {
        tabPill = document.createElement('span');
        tabPill.className = 'notif-tab-pill';
        tabPill.setAttribute('aria-hidden', 'true');
        tabsWrap.insertBefore(tabPill, tabsWrap.firstChild);
      }
      tabsWrap.classList.add('has-pill');
    }

    function positionTabPill(animate) {
      if (!tabsWrap || !tabPill || popover.hidden) return;
      var activeBtn = $('.notif-tab.is-active', tabsWrap) || tabButtons[0];
      if (!activeBtn || !activeBtn.offsetWidth || !activeBtn.offsetHeight) {
        tabPill.classList.remove('is-ready');
        lastPillRect = null;
        return;
      }
      var x = activeBtn.offsetLeft;
      var y = activeBtn.offsetTop;
      var w = activeBtn.offsetWidth;
      var h = activeBtn.offsetHeight;

      if (lastPillRect && lastPillRect.x === x && lastPillRect.y === y && lastPillRect.w === w && lastPillRect.h === h && tabPill.classList.contains('is-ready')) {
        return;
      }
      lastPillRect = { x: x, y: y, w: w, h: h };

      if (!animate || reduceMotion || !tabPill.classList.contains('is-ready')) {
        var prevTr = tabPill.style.transition;
        tabPill.style.transition = 'none';
        tabPill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
        tabPill.style.width = w + 'px';
        tabPill.style.height = h + 'px';
        tabPill.offsetHeight;
        tabPill.style.transition = prevTr || '';
        tabPill.classList.add('is-ready');
      } else {
        tabPill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
        tabPill.style.width = w + 'px';
        tabPill.style.height = h + 'px';
      }
    }

    function setOpen(open) {
      popover.hidden = !open;
      bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        closeActiveSelect(false);
        closeSidebarPopover();
        $all('.dropdown.open').forEach(function (d) {
          d.classList.remove('open');
          var b = $('[data-dropdown]', d);
          if (b) b.setAttribute('aria-expanded', 'false');
        });
        requestAnimationFrame(function () { positionTabPill(false); });
        loadNotifications();
      }
    }
    closeNotifPopover = function () { setOpen(false); };

    function apiPost(endpoint, payload) {
      return fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'fetch',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify(payload || {}),
      }).then(function (r) {
        return r.ok ? r.json() : null;
      });
    }

    function filteredItems() {
      return state.items.filter(function (it) {
        if (activeTab === 'unread') return !it.is_read;
        if (activeTab === 'sos') return it.is_sos;
        if (activeTab === 'reports') return !it.is_sos;
        return true;
      });
    }

    function renderList(slideDir) {
      var allCount = state.items.length;
      var unreadCount = state.items.filter(function (it) { return !it.is_read; }).length;
      var allSpan = $('[data-notif-count="all"]', popover);
      var unreadSpan = $('[data-notif-count="unread"]', popover);
      if (allSpan) allSpan.textContent = '(' + allCount + ')';
      if (unreadSpan) unreadSpan.textContent = '(' + unreadCount + ')';
      if (markAllBtn) markAllBtn.disabled = unreadCount === 0;
      if (clearAllBtn) clearAllBtn.disabled = allCount === 0;
      if (!slideDir && !popover.hidden) {
        requestAnimationFrame(function () { positionTabPill(false); });
      }

      listEl.classList.remove('is-slide-right', 'is-slide-left');
      listEl.textContent = '';
      if (slideDir && !reduceMotion) {
        void listEl.offsetWidth;
        listEl.classList.add(slideDir === 'left' ? 'is-slide-left' : 'is-slide-right');
      }

      var visible = filteredItems();
      if (!visible.length) {
        var empty = document.createElement('div');
        empty.className = 'notif-pop-empty';
        empty.textContent = activeTab === 'unread'
          ? 'No unread notifications right now.'
          : activeTab === 'sos'
            ? 'No SOS alerts in your notifications.'
            : 'Nothing yet. Updates will appear here.';
        listEl.appendChild(empty);
        return;
      }

      visible.forEach(function (item) {
        var card = document.createElement('article');
        card.className = 'notif-item' + (item.is_read ? '' : ' unread') + (item.is_sos ? ' is-sos' : '');

        var icWrap = document.createElement('div');
        icWrap.className = 'notif-item-ic';
        icWrap.appendChild(createSvgIcon(item.is_sos ? 'siren' : 'bell', 18));

        var main = document.createElement('div');
        main.className = 'notif-item-main';

        var top = document.createElement('div');
        top.className = 'notif-item-top';

        var title = document.createElement('h4');
        title.className = 'notif-item-title';
        title.textContent = item.title || 'Notification';

        var meta = document.createElement('div');
        meta.className = 'notif-item-meta';
        if (!item.is_read) {
          var dot = document.createElement('span');
          dot.className = 'notif-dot';
          dot.setAttribute('aria-hidden', 'true');
          meta.appendChild(dot);
          var tag = document.createElement('span');
          tag.textContent = item.is_sos ? 'SOS' : 'New';
          meta.appendChild(tag);
        }

        var dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'notif-dismiss';
        dismiss.setAttribute('aria-label', 'Dismiss notification');
        dismiss.appendChild(createSvgIcon('x', 13));
        dismiss.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          state.items = state.items.filter(function (x) { return x.id !== item.id; });
          renderList();
          apiPost('/api/notifications/clear', { id: item.id }).then(function (res) {
            if (res && typeof res.unread === 'number') syncUnreadUi(res.unread);
          });
        });
        meta.appendChild(dismiss);

        top.appendChild(title);
        top.appendChild(meta);

        var msg = document.createElement('p');
        msg.className = 'notif-item-msg';
        msg.textContent = item.message || '';

        var foot = document.createElement('div');
        foot.className = 'notif-item-foot';

        var time = document.createElement('span');
        time.className = 'notif-item-time';
        time.textContent = item.created_fmt || '';
        foot.appendChild(time);

        if (isSafeRelUrl(item.url)) {
          var cta = document.createElement('a');
          cta.className = 'notif-cta';
          cta.href = item.url;
          cta.appendChild(document.createTextNode((item.cta || 'View details') + ' '));
          cta.appendChild(createSvgIcon('arrow', 13));
          cta.addEventListener('click', function () {
            if (!item.is_read) {
              item.is_read = true;
              var nextUnread = state.items.filter(function (x) { return !x.is_read; }).length;
              syncUnreadUi(nextUnread);
              apiPost('/api/notifications/read', { id: item.id });
            }
            setOpen(false);
          });
          foot.appendChild(cta);
        }

        main.appendChild(top);
        main.appendChild(msg);
        main.appendChild(foot);
        card.appendChild(icWrap);
        card.appendChild(main);
        listEl.appendChild(card);
      });
    }

    function renderNotifSkeleton() {
      listEl.textContent = '';
      for (var i = 0; i < 3; i++) {
        var item = document.createElement('div');
        item.className = 'sk-notif-item';
        var ic = document.createElement('div');
        ic.className = 'sk-icon-box sk-shimmer';
        var copy = document.createElement('div');
        copy.className = 'sk-notif-copy';
        var l1 = document.createElement('div');
        l1.className = 'sk-line sk-shimmer';
        var l2 = document.createElement('div');
        l2.className = 'sk-line sk-line-sm sk-shimmer';
        copy.appendChild(l1);
        copy.appendChild(l2);
        item.appendChild(ic);
        item.appendChild(copy);
        listEl.appendChild(item);
      }
    }

    function loadNotifications() {
      if (!state.loaded) renderNotifSkeleton();
      fetch('/api/notifications', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          state.items = Array.isArray(data.items) ? data.items : [];
          state.unread = typeof data.unread === 'number' ? data.unread : 0;
          state.total = typeof data.total === 'number' ? data.total : state.items.length;
          state.loaded = true;
          syncUnreadUi(state.unread);
          renderList();
        })
        .catch(function () {
          if (!state.loaded) listEl.textContent = 'Could not load notifications.';
        });
    }

    refreshNotifPopover = function () {
      if (!popover.hidden) loadNotifications();
    };

    bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(popover.hidden);
    });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });

    function activateTab(btn) {
      if (!btn) return;
      var nextTab = btn.getAttribute('data-notif-tab') || 'all';
      if (nextTab === activeTab) {
        positionTabPill(false);
        return;
      }
      var prevIdx = tabButtons.findIndex(function (b) { return b.getAttribute('data-notif-tab') === activeTab; });
      var nextIdx = tabButtons.indexOf(btn);
      var dir = prevIdx !== -1 && nextIdx < prevIdx ? 'left' : 'right';

      activeTab = nextTab;
      tabButtons.forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      positionTabPill(true);
      renderList(dir);
    }

    tabButtons.forEach(function (btn, idx) {
      btn.addEventListener('click', function () {
        activateTab(btn);
      });
      btn.addEventListener('keydown', function (e) {
        var targetIdx = -1;
        if (e.key === 'ArrowRight') targetIdx = (idx + 1) % tabButtons.length;
        else if (e.key === 'ArrowLeft') targetIdx = (idx - 1 + tabButtons.length) % tabButtons.length;
        else if (e.key === 'Home') targetIdx = 0;
        else if (e.key === 'End') targetIdx = tabButtons.length - 1;
        if (targetIdx !== -1) {
          e.preventDefault();
          var targetBtn = tabButtons[targetIdx];
          activateTab(targetBtn);
          targetBtn.focus();
        }
      });
    });

    if (tabsWrap && window.ResizeObserver) {
      var nTabRo = new ResizeObserver(function () { positionTabPill(false); });
      nTabRo.observe(tabsWrap);
    } else {
      window.addEventListener('resize', function () { positionTabPill(false); });
    }
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () { positionTabPill(false); });
    }

    if (markAllBtn) {
      markAllBtn.addEventListener('click', function () {
        state.items.forEach(function (it) { it.is_read = true; });
        syncUnreadUi(0);
        renderList();
        apiPost('/api/notifications/read', {}).then(function (res) {
          if (res && typeof res.unread === 'number') syncUnreadUi(res.unread);
        });
      });
    }

    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', function () {
        state.items = [];
        syncUnreadUi(0);
        renderList();
        apiPost('/api/notifications/clear', { mode: 'all' }).then(function (res) {
          if (res && typeof res.unread === 'number') syncUnreadUi(res.unread);
        });
      });
    }

    document.addEventListener('click', function (e) {
      if (!popover.hidden && !popover.contains(e.target) && !bellBtn.contains(e.target)) {
        setOpen(false);
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !popover.hidden) setOpen(false);
    });
  }

  function initLivePoll() {
    var url = document.body.getAttribute('data-poll');
    if (!url || !window.fetch) return;
    var lastSos = null;
    var pollTimer = null;

    function sosToast(d) {
      var body = createToast('error', 20000);
      var link = document.createElement('a');
      link.href = d.sos_url;
      link.textContent = 'New SOS alert: ' + d.sos_title;       // textContent = safe from HTML injection
      body.appendChild(link);
    }

    function scheduleNext() {
      clearTimeout(pollTimer);
      pollTimer = setTimeout(poll, 15000);
    }

    function poll() {
      if (document.hidden) { scheduleNext(); return; }             // do nothing while the tab is in the background
      var ctrl = window.AbortController ? new AbortController() : null;
      var abortTimer = ctrl ? setTimeout(function () { ctrl.abort(); }, 10000) : null;
      fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        signal: ctrl ? ctrl.signal : undefined
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          syncUnreadUi(d.unread);
          refreshNotifPopover();
          if (typeof d.sos_last === 'number') {
            if (lastSos !== null && d.sos_last > lastSos) sosToast(d);
            lastSos = d.sos_last;
          }
        })
        .catch(function () { /* offline or server busy: try again next time */ })
        .finally(function () {
          if (abortTimer) clearTimeout(abortTimer);
          scheduleNext();
        });
    }

    pollTimer = setTimeout(poll, 1400);
  }


  /* 12. SMOOTH IN-APP NAVIGATION (keeps sidebar/topbar persistent) -------- */
  var leafletQueue = [];
  var leafletReadyState = { css: false, js: false };
  function ensureLeaflet(cb) {
    var needsMap = $(MAP_SELECTOR);
    if (!needsMap) { cb(); return; }

    primeMapSkeletons();
    leafletQueue.push(cb);

    function flushIfReady() {
      if (!window.L || !leafletReadyState.css) return;
      var q = leafletQueue.splice(0, leafletQueue.length);
      q.forEach(function (fn) { try { fn(); } catch (err) { if (window.console && console.error) console.error(err); } });
    }

    var cssHref = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    var link = document.querySelector('link[href="' + cssHref + '"]');
    if (!link) {
      link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssHref;
      link.crossOrigin = 'anonymous';
      document.head.appendChild(link);
    }
    if (link.dataset.ready === '1' || Boolean(link.sheet)) {
      leafletReadyState.css = true;
    } else if (!link.dataset.watching) {
      link.dataset.watching = '1';
      var markCssDone = function () {
        link.dataset.ready = '1';
        leafletReadyState.css = true;
        flushIfReady();
      };
      link.addEventListener('load', markCssDone, { once: true });
      link.addEventListener('error', markCssDone, { once: true });
      setTimeout(markCssDone, 1600);
    }

    if (window.L) {
      leafletReadyState.js = true;
      flushIfReady();
      return;
    }

    var jsSrc = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    var script = document.querySelector('script[src="' + jsSrc + '"]');
    if (!script) {
      script = document.createElement('script');
      script.src = jsSrc;
      script.async = true;
      script.crossOrigin = 'anonymous';
      document.head.appendChild(script);
    }
    if (!script.dataset.watching) {
      script.dataset.watching = '1';
      script.addEventListener('load', function () {
        leafletReadyState.js = true;
        flushIfReady();
      }, { once: true });
    }
  }

  function runMainScripts(mainEl) {
    $all('script', mainEl).forEach(function (oldScript) {
      var src = oldScript.getAttribute('src') || '';
      if (src.indexOf('leaflet') !== -1) {
        oldScript.remove();
        return;
      }
      var s = document.createElement('script');
      if (src) s.src = src;
      else s.textContent = oldScript.textContent;
      oldScript.parentNode.replaceChild(s, oldScript);
    });
  }

  /* 11b. MODERN GLASS SELECT DROPDOWNS ----------------------------------- */
  var activeSelectCtx = null;

  function closeActiveSelect(returnFocus) {
    if (!activeSelectCtx) return;
    var ctx = activeSelectCtx;
    activeSelectCtx = null;
    ctx.wrap.classList.remove('is-open');
    ctx.trigger.setAttribute('aria-expanded', 'false');
    if (ctx.menu && ctx.menu.parentNode) ctx.menu.parentNode.removeChild(ctx.menu);
    if (returnFocus) ctx.trigger.focus();
  }

  function resolveSelectOptionMeta(select, opt) {
    var name = String(select.getAttribute('name') || select.id || '').toLowerCase();
    var val = String(opt ? opt.value : '').toLowerCase();
    var txt = String(opt ? opt.textContent : '').trim().toLowerCase();

    if (name === 'status') {
      if (val === 'active') return { icon: 'check-circle', tone: 'emerald' };
      if (val === 'inactive') return { icon: 'x-circle', tone: 'rose' };
      if (val === 'pending') return { icon: 'clock', tone: 'amber' };
      if (val === 'assigned') return { icon: 'user-check', tone: 'sky' };
      if (val === 'in_progress') return { icon: 'loader', tone: 'violet' };
      if (val === 'resolved') return { icon: 'check-circle', tone: 'emerald' };
      if (val === 'rejected') return { icon: 'x-circle', tone: 'rose' };
      return { icon: 'layers', tone: 'primary' };
    }
    if (name === 'role') {
      if (val === 'resident') return { icon: 'user', tone: 'sky' };
      if (val === 'volunteer') return { icon: 'shield', tone: 'emerald' };
      if (val === 'admin') return { icon: 'shield-alert', tone: 'amber' };
      return { icon: 'user', tone: 'primary' };
    }
    if (name === 'priority') {
      if (val === 'low') return { icon: 'arrow-down', tone: 'emerald' };
      if (val === 'medium') return { icon: 'minus', tone: 'amber' };
      if (val === 'high') return { icon: 'arrow-up', tone: 'rose' };
      return { icon: 'layers', tone: 'primary' };
    }
    if (name === 'level') {
      if (val === 'info') return { icon: 'info', tone: 'sky' };
      if (val === 'warning') return { icon: 'alert-triangle', tone: 'amber' };
      if (val === 'critical') return { icon: 'siren', tone: 'rose' };
      return { icon: 'info', tone: 'primary' };
    }
    if (name === 'volunteer_id') {
      if (!val || val === '0') return { icon: 'user-x', tone: 'muted' };
      return { icon: 'user-check', tone: 'primary' };
    }
    if (name === 'category' || name === 'category_id') {
      if (!val || val === '0') return { icon: 'layers', tone: 'primary' };
      if (/fire|blaze|burn/.test(txt)) return { icon: 'flame', tone: 'rose' };
      if (/flood|water|drain|rain|typhoon/.test(txt)) return { icon: 'droplet', tone: 'sky' };
      if (/theft|crime|rob|burglar|security/.test(txt)) return { icon: 'shield-alert', tone: 'amber' };
      if (/road|accident|traffic|tree|crash/.test(txt)) return { icon: 'alert-triangle', tone: 'amber' };
      if (/medical|health|injur|ambulan|emerg/.test(txt)) return { icon: 'heart-pulse', tone: 'rose' };
      if (/noise|disturb|loud|sound/.test(txt)) return { icon: 'volume-2', tone: 'violet' };
      if (/garbage|sanit|trash|waste|clean/.test(txt)) return { icon: 'trash', tone: 'emerald' };
      if (/light|electric|power|wire|lamp/.test(txt)) return { icon: 'zap', tone: 'amber' };
      return { icon: 'tag', tone: 'primary' };
    }
    return { icon: 'layers', tone: 'primary' };
  }

  var selectGlobalsBound = false;
  function bindSelectGlobals() {
    if (selectGlobalsBound) return;
    selectGlobalsBound = true;

    document.addEventListener('click', function (e) {
      if (!activeSelectCtx) return;
      if (activeSelectCtx.wrap.contains(e.target) || activeSelectCtx.menu.contains(e.target)) return;
      closeActiveSelect(false);
    }, true);
    document.addEventListener('keydown', function (e) {
      if (!activeSelectCtx) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        closeActiveSelect(true);
      }
    });
    window.addEventListener('resize', function () {
      if (activeSelectCtx) closeActiveSelect(false);
    });
    window.addEventListener('scroll', function (e) {
      if (!activeSelectCtx) return;
      if (e && e.target && activeSelectCtx.menu && activeSelectCtx.menu.contains(e.target)) return;
      closeActiveSelect(false);
    }, true);
  }

  function initModernSelects() {
    bindSelectGlobals();
    $all('select').forEach(function (select) {
      if (select.multiple || select.dataset.msInit === '1') return;
      select.dataset.msInit = '1';

      var wrap = document.createElement('div');
      wrap.className = 'm-select';
      select.parentNode.insertBefore(wrap, select);
      wrap.appendChild(select);
      select.classList.add('m-select-native');
      select.setAttribute('tabindex', '-1');
      select.setAttribute('aria-hidden', 'true');

      var trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'm-select-trigger';
      trigger.setAttribute('role', 'combobox');
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');
      if (select.getAttribute('aria-label')) {
        trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
      } else if (select.id) {
        var lbl = document.querySelector('label[for="' + select.id + '"]');
        if (lbl) trigger.setAttribute('aria-label', lbl.textContent.trim());
      }

      var icSpan = document.createElement('span');
      icSpan.className = 'm-select-ic';
      icSpan.setAttribute('aria-hidden', 'true');

      var valSpan = document.createElement('span');
      valSpan.className = 'm-select-val';

      var chevSpan = document.createElement('span');
      chevSpan.className = 'm-select-chev';
      chevSpan.setAttribute('aria-hidden', 'true');
      chevSpan.appendChild(createSvgIcon('chevron-down', 15));

      trigger.appendChild(icSpan);
      trigger.appendChild(valSpan);
      trigger.appendChild(chevSpan);
      wrap.appendChild(trigger);

      function syncTrigger() {
        var opt = select.options[select.selectedIndex] || select.options[0];
        var meta = resolveSelectOptionMeta(select, opt);
        icSpan.className = 'm-select-ic ms-tone-' + meta.tone;
        icSpan.textContent = '';
        icSpan.appendChild(createSvgIcon(meta.icon, 14));
        valSpan.textContent = opt ? opt.textContent.trim() : '';
        valSpan.classList.toggle('is-placeholder', !opt || (opt.value === '' && select.required));
      }
      syncTrigger();

      function buildMenu() {
        var menu = document.createElement('div');
        menu.className = 'm-select-menu';
        var list = document.createElement('div');
        list.className = 'm-select-list';
        list.setAttribute('role', 'listbox');
        var buttons = [];

        Array.prototype.forEach.call(select.options, function (opt, idx) {
          var meta = resolveSelectOptionMeta(select, opt);
          var isSelected = idx === select.selectedIndex;
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'm-select-opt' + (isSelected ? ' is-selected' : '');
          btn.setAttribute('role', 'option');
          btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
          btn.dataset.idx = String(idx);

          var optIc = document.createElement('span');
          optIc.className = 'm-select-ic ms-tone-' + meta.tone;
          optIc.setAttribute('aria-hidden', 'true');
          optIc.appendChild(createSvgIcon(meta.icon, 14));

          var optLbl = document.createElement('span');
          optLbl.className = 'm-select-opt-label';
          optLbl.textContent = opt.textContent.trim();

          var chk = document.createElement('span');
          chk.className = 'm-select-check';
          chk.setAttribute('aria-hidden', 'true');
          chk.appendChild(createSvgIcon('check', 14));

          btn.appendChild(optIc);
          btn.appendChild(optLbl);
          btn.appendChild(chk);

          btn.addEventListener('click', function () {
            var changed = select.selectedIndex !== idx;
            select.selectedIndex = idx;
            syncTrigger();
            closeActiveSelect(true);
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
          });
          list.appendChild(btn);
          buttons.push(btn);
        });
        menu.appendChild(list);
        return { menu: menu, list: list, buttons: buttons };
      }

      function positionMenu(menu, list) {
        var r = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var minW = Math.max(r.width, 196);
        var maxW = Math.min(vw - 24, Math.max(r.width + 52, 320));
        menu.style.minWidth = minW + 'px';
        menu.style.maxWidth = maxW + 'px';

        var spaceBelow = vh - r.bottom - 12;
        var spaceAbove = r.top - 12;
        menu.style.maxHeight = '300px';
        list.style.maxHeight = '284px';
        var naturalH = menu.scrollHeight || 220;
        var openUp = spaceBelow < Math.min(naturalH, 190) && spaceAbove > spaceBelow;
        var avail = Math.max(120, Math.min(300, openUp ? spaceAbove : spaceBelow));
        menu.style.maxHeight = avail + 'px';
        list.style.maxHeight = Math.max(96, avail - 16) + 'px';
        var needsScroll = list.scrollHeight > list.clientHeight + 1;
        list.classList.toggle('has-scroll', needsScroll);
        if (needsScroll) {
          menu.style.minWidth = Math.min(maxW, minW + 18) + 'px';
        }

        var renderedH = Math.min(naturalH, avail);
        var renderedW = menu.offsetWidth || minW;
        var left = Math.max(12, Math.min(r.left, vw - renderedW - 12));
        var top = openUp ? Math.max(12, r.top - renderedH - 6) : (r.bottom + 6);

        menu.classList.toggle('is-up', openUp);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
      }

      function openMenu(focusIdx) {
        if (activeSelectCtx && activeSelectCtx.wrap === wrap) {
          closeActiveSelect(false);
          return;
        }
        closeActiveSelect(false);

        // Close other floating UI popovers cleanly
        var spPop = $('#sidebar-profile-popover');
        if (spPop && !spPop.hidden && typeof closeSidebarPopover === 'function') closeSidebarPopover();
        var nPop = $('#notif-popover');
        var nBell = $('#notif-bell-btn');
        if (nPop && !nPop.hidden) {
          nPop.hidden = true;
          if (nBell) nBell.setAttribute('aria-expanded', 'false');
        }

        var built = buildMenu();
        document.body.appendChild(built.menu);
        positionMenu(built.menu, built.list);
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        activeSelectCtx = { wrap: wrap, trigger: trigger, menu: built.menu, list: built.list, buttons: built.buttons };

        var targetBtn = built.buttons[typeof focusIdx === 'number' ? focusIdx : Math.max(0, select.selectedIndex)];
        if (targetBtn) {
          targetBtn.classList.add('is-focused');
          targetBtn.scrollIntoView({ block: 'nearest' });
        }
      }

      trigger.addEventListener('click', function () {
        openMenu();
      });

      trigger.addEventListener('keydown', function (e) {
        var count = select.options.length;
        if (!count) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (!activeSelectCtx || activeSelectCtx.wrap !== wrap) {
            openMenu();
            return;
          }
          var cur = activeSelectCtx.buttons.findIndex(function (b) { return b.classList.contains('is-focused'); });
          if (cur === -1) cur = select.selectedIndex;
          var nextIdx = e.key === 'ArrowDown' ? (cur + 1) % count : (cur - 1 + count) % count;
          activeSelectCtx.buttons.forEach(function (b, i) { b.classList.toggle('is-focused', i === nextIdx); });
          activeSelectCtx.buttons[nextIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' || e.key === ' ') {
          if (activeSelectCtx && activeSelectCtx.wrap === wrap) {
            var focusedBtn = activeSelectCtx.buttons.find(function (b) { return b.classList.contains('is-focused'); });
            if (focusedBtn) {
              e.preventDefault();
              focusedBtn.click();
            }
          } else if (e.key === ' ') {
            e.preventDefault();
            openMenu();
          }
        }
      });

      select.addEventListener('change', syncTrigger);
      select.addEventListener('focus', function () { trigger.focus(); });
      select.addEventListener('invalid', function () { openMenu(); });
    });
  }

  function initPage(isSwap) {
    closeActiveSelect(false);
    initConfirmForms();
    initToasts();
    initCountUp();
    initPhotoPreview();
    initPrint();
    initModernSelects();
    initAdminWorkspace();
    portalPageModals();
    ensureLeaflet(initMaps);
    var autoModal = $('.modal[data-modal-autoopen]');
    if (autoModal) openModal(autoModal);
  }

  function skEl(cls) {
    var d = document.createElement('div');
    if (cls) d.className = cls;
    return d;
  }

  function buildPageSkeleton(pathname) {
    var p = String(pathname || '').toLowerCase();
    var root = skEl('sk-page');
    root.setAttribute('aria-hidden', 'true');

    var head = skEl('sk-head');
    var copy = skEl('sk-head-copy');
    copy.appendChild(skEl('sk-line sk-line-lg sk-shimmer'));
    copy.appendChild(skEl('sk-line sk-line-md sk-shimmer'));
    var actions = skEl('sk-head-actions');
    actions.appendChild(skEl('sk-btn sk-shimmer'));
    head.appendChild(copy);
    head.appendChild(actions);
    root.appendChild(head);

    if (/dispatch|analytics|report\.php|report_details|sos\.php/.test(p)) {
      var twoMap = skEl('sk-two');
      var leftMap = skEl('sk-card');
      leftMap.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
      leftMap.appendChild(skEl('sk-map-frame sk-shimmer'));
      var rightForm = skEl('sk-card');
      for (var rf = 0; rf < 5; rf++) {
        rightForm.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
        rightForm.appendChild(skEl('sk-input sk-shimmer'));
      }
      twoMap.appendChild(leftMap);
      twoMap.appendChild(rightForm);
      root.appendChild(twoMap);
      return root;
    }

    if (/settings\.php/.test(p)) {
      var twoSet = skEl('sk-two is-settings');
      var navCard = skEl('sk-card');
      for (var ns = 0; ns < 5; ns++) navCard.appendChild(skEl('sk-line sk-shimmer'));
      var bodyCard = skEl('sk-card');
      bodyCard.appendChild(skEl('sk-line sk-line-md sk-shimmer'));
      for (var bs = 0; bs < 4; bs++) {
        bodyCard.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
        bodyCard.appendChild(skEl('sk-input sk-shimmer'));
      }
      twoSet.appendChild(navCard);
      twoSet.appendChild(bodyCard);
      root.appendChild(twoSet);
      return root;
    }

    if (/reports|residents|volunteers|assigned_reports|announcements|categories/.test(p)) {
      var filters = skEl('sk-filters');
      filters.appendChild(skEl('sk-input is-wide sk-shimmer'));
      filters.appendChild(skEl('sk-input sk-shimmer'));
      filters.appendChild(skEl('sk-input sk-shimmer'));
      root.appendChild(filters);

      var tCard = skEl('sk-card');
      var rows = skEl('sk-table-rows');
      for (var r = 0; r < 6; r++) {
        var row = skEl('sk-table-row');
        var uCell = skEl('sk-user');
        uCell.appendChild(skEl('sk-avatar sk-shimmer'));
        uCell.appendChild(skEl('sk-line sk-shimmer'));
        row.appendChild(uCell);
        row.appendChild(skEl('sk-line sk-shimmer'));
        row.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
        row.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
        rows.appendChild(row);
      }
      tCard.appendChild(rows);
      root.appendChild(tCard);
      return root;
    }

    var stats = skEl('sk-stats');
    for (var s = 0; s < 4; s++) {
      var sc = skEl('sk-stat-card');
      var top = skEl('sk-stat-top');
      top.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
      top.appendChild(skEl('sk-icon-box sk-shimmer'));
      sc.appendChild(top);
      sc.appendChild(skEl('sk-num sk-shimmer'));
      sc.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
      stats.appendChild(sc);
    }
    root.appendChild(stats);

    var dashCard = skEl('sk-card');
    var dRows = skEl('sk-table-rows');
    for (var d = 0; d < 4; d++) {
      var dRow = skEl('sk-table-row');
      dRow.appendChild(skEl('sk-line sk-shimmer'));
      dRow.appendChild(skEl('sk-line sk-shimmer'));
      dRow.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
      dRow.appendChild(skEl('sk-line sk-line-sm sk-shimmer'));
      dRows.appendChild(dRow);
    }
    dashCard.appendChild(dRows);
    root.appendChild(dashCard);
    return root;
  }

  function initSpaNavigation() {
    if (!window.history || !window.history.pushState || !window.fetch || !window.DOMParser) return;
    if (!document.body.classList.contains('app')) return;

    var navSeq = 0;
    var lastSpaPathQuery = window.location.pathname + window.location.search;
    var mainWrap = $('.main');
    var progressBar = mainWrap ? $('.spa-progress', mainWrap) : null;
    if (mainWrap && !progressBar) {
      progressBar = document.createElement('div');
      progressBar.className = 'spa-progress';
      progressBar.setAttribute('aria-hidden', 'true');
      var barInner = document.createElement('div');
      barInner.className = 'spa-progress-bar';
      progressBar.appendChild(barInner);
      mainWrap.appendChild(progressBar);
    }

    function navigate(url, push) {
      var parsed;
      try { parsed = new URL(url, window.location.href); } catch (_) { window.location.href = url; return; }
      var seq = ++navSeq;
      var main = $('#main');
      if (progressBar) progressBar.classList.add('is-active');
      if (main) main.style.opacity = '0.72';

      var skTimer = setTimeout(function () {
        if (seq !== navSeq || !main) return;
        main.style.opacity = '';
        main.setAttribute('aria-busy', 'true');
        main.textContent = '';
        main.appendChild(buildPageSkeleton(parsed.pathname));
      }, 120);

      fetch(parsed.href, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      })
        .then(function (r) {
          if (!r.ok) { window.location.href = parsed.href; return null; }
          return r.text().then(function (html) { return { html: html, finalUrl: r.url || parsed.href }; });
        })
        .then(function (res) {
          clearTimeout(skTimer);
          if (!res || seq !== navSeq) return;
          var doc = new DOMParser().parseFromString(res.html, 'text/html');
          if (!doc.body || !doc.body.classList.contains('app')) {
            window.location.href = res.finalUrl;
            return;
          }

          var newMain = doc.getElementById('main');
          if (!main || !newMain) { window.location.href = res.finalUrl; return; }

          // Remove floating menus & modals left open before swapping #main
          closeActiveSelect(false);
          $all('.modal.is-open').forEach(function (m) { m.classList.remove('is-open', 'is-mounted'); });
          document.documentElement.classList.remove('has-modal');
          document.body.classList.remove('has-modal');
          $all('.modal[data-spa-modal]').forEach(function (m) { m.remove(); });
          var oldSos = document.getElementById('sos-modal');
          if (oldSos && oldSos.parentNode === document.body) oldSos.remove();

          // Strip Leaflet assets inside #main so stylesheets don't mount/unmount per route
          $all('link[href*="leaflet"], script[src*="leaflet"]', newMain).forEach(function (el) { el.remove(); });

          document.title = doc.title;
          main.removeAttribute('aria-busy');
          main.innerHTML = newMain.innerHTML;
          main.style.opacity = '';
          if (progressBar) progressBar.classList.remove('is-active');

          // Sync sidebar nav active link & unread badge in-place to animate active pill
          var newNav = $('.sidebar nav', doc);
          var destUrlPreview = new URL(res.finalUrl, window.location.href);
          if (parsed.hash && !destUrlPreview.hash) destUrlPreview.hash = parsed.hash;
          if (sidebarNavApi && newNav) {
            sidebarNavApi.syncFromDoc(newNav, destUrlPreview);
          } else {
            var oldNav = $('.sidebar nav');
            if (oldNav && newNav) oldNav.innerHTML = newNav.innerHTML;
          }

          // Sync breadcrumbs and topbar notification badge
          var oldCrumbs = $('.topbar .crumbs');
          var newCrumbs = $('.topbar .crumbs', doc);
          if (oldCrumbs && newCrumbs) oldCrumbs.innerHTML = newCrumbs.innerHTML;

          var oldBell = $('.top-actions .icon-btn');
          var newBell = $('.top-actions .icon-btn', doc);
          if (oldBell && newBell) oldBell.innerHTML = newBell.innerHTML;
          var newMetaCsrf = doc.querySelector('meta[name="csrf-token"]');
          var oldMetaCsrf = document.querySelector('meta[name="csrf-token"]');
          if (newMetaCsrf && oldMetaCsrf) oldMetaCsrf.setAttribute('content', newMetaCsrf.getAttribute('content') || '');
          refreshNotifPopover();

          // Sync resident SOS floating button visibility
          var oldFab = $('.sos-fab');
          var newFab = $('.sos-fab', doc);
          if (oldFab && !newFab) oldFab.remove();
          else if (!oldFab && newFab && main.parentNode) main.parentNode.insertBefore(newFab.cloneNode(true), main);

          var dest = new URL(res.finalUrl, window.location.href);
          if (parsed.hash && !dest.hash) dest.hash = parsed.hash;
          lastSpaPathQuery = dest.pathname + dest.search;
          if (push) history.pushState({ spa: true }, '', dest.pathname + dest.search + dest.hash);

          try { runMainScripts(main); } catch (err) { if (window.console && console.error) console.error(err); }
          try { initPage(true); } catch (err) { if (window.console && console.error) console.error(err); }

          if (dest.hash) {
            var target = document.getElementById(dest.hash.slice(1));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
          } else {
            var scroller = $('.main-scroll');
            if (scroller) scroller.scrollTop = 0;
            window.scrollTo(0, 0);
          }
        })
        .catch(function () {
          clearTimeout(skTimer);
          if (progressBar) progressBar.classList.remove('is-active');
          if (main) {
            main.removeAttribute('aria-busy');
            main.style.opacity = '';
          }
          window.location.href = parsed.href;
        });
    }

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest ? e.target.closest('a[href]') : null;
      if (!a) return;
      if (a.hasAttribute('download') || (a.getAttribute('target') && a.getAttribute('target') !== '_self')) return;

      var rawHref = a.getAttribute('href') || '';
      if (!rawHref || rawHref.charAt(0) === '#' || rawHref.indexOf('mailto:') === 0 || rawHref.indexOf('tel:') === 0) return;

      var url;
      try { url = new URL(a.href, window.location.href); } catch (_) { return; }
      if (url.origin !== window.location.origin) return;
      if (url.pathname.indexOf('/logout') !== -1 || url.search.indexOf('export=') !== -1) return;

      // Same path & query: avoid re-fetching or duplicating history; scroll smoothly instead
      if (url.pathname === window.location.pathname && url.search === window.location.search) {
        if (a.closest && a.closest('.sidebar nav') && sidebarNavApi) {
          sidebarNavApi.setActiveByUrl(url);
        }
        if (url.hash) {
          var hashTarget = document.getElementById(url.hash.slice(1));
          if (url.hash === window.location.hash) {
            e.preventDefault();
            if (hashTarget) hashTarget.scrollIntoView({ behavior: 'smooth' });
          }
          return;
        }
        e.preventDefault();
        if (window.location.hash) {
          history.replaceState(history.state, '', url.pathname + url.search);
        }
        var scroller = $('.main-scroll');
        if (scroller) scroller.scrollTo({ top: 0, behavior: 'smooth' });
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      if (a.closest && a.closest('.sidebar nav') && sidebarNavApi) {
        sidebarNavApi.setActiveByUrl(url);
      }

      e.preventDefault();
      navigate(url.href, true);
    });

    // Smooth GET filter/search forms
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
      if (form.hasAttribute('data-confirm')) return;
      var action = form.getAttribute('action') || window.location.pathname;
      var url = new URL(action, window.location.href);
      var params = new URLSearchParams(new FormData(form));
      url.search = params.toString();
      e.preventDefault();
      navigate(url.href, true);
    });

    window.addEventListener('popstate', function () {
      var curPathQuery = window.location.pathname + window.location.search;
      if (curPathQuery === lastSpaPathQuery) {
        if (sidebarNavApi) {
          try { sidebarNavApi.setActiveByUrl(new URL(window.location.href)); } catch (_) {}
        }
        if (window.location.hash) {
          var target = document.getElementById(window.location.hash.slice(1));
          if (target) target.scrollIntoView({ behavior: 'smooth' });
        } else {
          var scroller = $('.main-scroll');
          if (scroller) scroller.scrollTo({ top: 0, behavior: 'smooth' });
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        return;
      }
      navigate(window.location.href, false);
    });
  }


  /* 13. SIDEBAR PROFILE POPOVER & PROFILE MODAL DIALOG ------------------- */
  function initSidebarProfilePopover() {
    var btn = document.getElementById('sidebar-user-btn');
    var pop = document.getElementById('sidebar-profile-popover');
    if (!btn || !pop) return;

    function setOpen(open) {
      pop.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        closeActiveSelect(false);
        closeNotifPopover();
      }
    }

    closeSidebarPopover = function () { setOpen(false); };

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(pop.hidden);
    });

    $all('[data-sp-close]', pop).forEach(function (el) {
      el.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('click', function (e) {
      if (!pop.hidden && !pop.contains(e.target) && !btn.contains(e.target)) {
        setOpen(false);
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !pop.hidden) setOpen(false);
    });
  }

  function initProfileModal() {
    var modal = document.getElementById('profile-modal');
    var form = document.getElementById('profile-modal-form');
    if (!modal || !form) return;

    var errBox = document.getElementById('profile-modal-err');
    var saveBtn = document.getElementById('pm-save-btn');
    var nameInput = document.getElementById('pm-name');
    var phoneInput = document.getElementById('pm-phone');
    var addrInput = document.getElementById('pm-address');
    var curPwInput = document.getElementById('pm-cur-pw');
    var newPwInput = document.getElementById('pm-new-pw');
    var notifPop = document.getElementById('notif-popover');
    var bellBtn = document.getElementById('notif-bell-btn');
    var detailsEl = document.getElementById('sp-details-text');

    var tabSlider = document.getElementById('pm-tab-slider');
    var navBtns = $all('[data-pm-tab]', modal);
    var panes = $all('[data-pm-pane]', modal);
    var shapeBtns = $all('[data-pm-shape]', modal);
    var badgeChips = $all('[data-pm-badge]', modal);
    var specEl = document.getElementById('pm-avatar-spec');
    var modeEl = document.getElementById('pm-avatar-mode');
    var metaPhoneEl = document.getElementById('pm-meta-phone');
    var metaAddressEl = document.getElementById('pm-meta-address');
    var pwFill = document.getElementById('pm-pw-fill');
    var pwHint = document.getElementById('pm-pw-hint');
    var cropFab = document.getElementById('pm-crop-fab');
    var cropBtn = document.getElementById('pm-crop-btn');
    var uploadBtn = document.getElementById('pm-upload-btn');
    var fileInput = document.getElementById('pm-photo-file');
    var resetAvatarBtn = document.getElementById('pm-reset-avatar-btn');

    var camBtn = document.getElementById('pm-camera-btn');
    var camPanel = document.getElementById('pm-camera-panel');
    var camClose = document.getElementById('pm-camera-close');
    var camCancel = document.getElementById('pm-camera-cancel');
    var camSnap = document.getElementById('pm-camera-snap');
    var camVideo = document.getElementById('pm-cam-video');
    var camErr = document.getElementById('pm-cam-err');

    var cropPanel = document.getElementById('pm-crop-panel');
    var cropClose = document.getElementById('pm-crop-close');
    var cropCancel = document.getElementById('pm-crop-cancel');
    var cropApply = document.getElementById('pm-crop-apply');
    var cropCanvas = document.getElementById('pm-crop-canvas');
    var cropZoom = document.getElementById('pm-crop-zoom');
    var cropX = document.getElementById('pm-crop-x');
    var cropY = document.getElementById('pm-crop-y');

    var urlToggleBtn = document.getElementById('pm-url-toggle-btn');
    var urlPanel = document.getElementById('pm-url-panel');
    var urlClose = document.getElementById('pm-url-close');
    var urlInput = document.getElementById('pm-avatar-url-input');
    var urlApply = document.getElementById('pm-url-apply');

    var BADGE_IMAGES = {
      shield: '/assets/images/badges/badge_shield.jpg',
      responder: '/assets/images/badges/badge_responder.jpg',
      rings: '/assets/images/badges/badge_highlight.jpg',
      shapes: '/assets/images/badges/badge_shapes.jpg',
      grid: '/assets/images/badges/badge_grid.jpg',
    };
    var ALL_BADGE_CLASSES = [
      'av-style-initials', 'av-style-indigo', 'av-style-emerald', 'av-style-amber', 'av-style-rose',
      'av-style-shield', 'av-style-responder', 'av-style-rings', 'av-style-shapes', 'av-style-grid',
    ];

    var avatarState = {
      avatar_type: 'badge',
      avatar_style: 'initials',
      avatar_shape: 'circle',
      avatar_url: '',
      avatar_data: '',
    };
    var camStream = null;
    var cropImg = null;
    var currentStep = 1;
    var pillTimer = null;

    function computeInitials(name) {
      var words = String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
      var out = words.map(function (w) { return w.charAt(0).toUpperCase(); }).join('');
      return out || '?';
    }

    function setError(msg) {
      if (!errBox) return;
      errBox.textContent = msg || '';
      errBox.hidden = !msg;
    }

    function stopCamera() {
      if (camStream) {
        camStream.getTracks().forEach(function (t) { t.stop(); });
        camStream = null;
      }
      if (camVideo) camVideo.srcObject = null;
    }

    function closeSubpanels() {
      stopCamera();
      if (camPanel) camPanel.hidden = true;
      if (cropPanel) cropPanel.hidden = true;
      if (urlPanel) urlPanel.hidden = true;
    }

    function moveTabSlider(btn, animate) {
      if (!tabSlider || !btn || !btn.offsetWidth) return;
      if (!animate) tabSlider.style.transition = 'none';
      tabSlider.style.transform = 'translateX(' + btn.offsetLeft + 'px)';
      tabSlider.style.width = btn.offsetWidth + 'px';
      tabSlider.classList.add('is-ready');
      if (!animate) {
        void tabSlider.offsetWidth;
        tabSlider.style.transition = '';
      }
    }

    function updatePasswordMeter() {
      if (!pwFill || !pwHint) return;
      var val = newPwInput ? newPwInput.value : '';
      if (!val) {
        pwFill.style.width = '0%';
        pwFill.style.background = 'var(--primary)';
        pwHint.textContent = 'Optional \u2014 minimum 6 characters to change password';
        return;
      }
      var score = 0;
      if (val.length >= 6) score += 1;
      if (val.length >= 10) score += 1;
      if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 1;
      if (/[0-9]/.test(val)) score += 1;
      if (/[^A-Za-z0-9]/.test(val)) score += 1;

      if (val.length < 6) {
        pwFill.style.width = '20%';
        pwFill.style.background = '#dc2626';
        pwHint.textContent = 'Too short \u2014 add at least ' + (6 - val.length) + ' more character(s)';
      } else if (score <= 2) {
        pwFill.style.width = '48%';
        pwFill.style.background = '#d97706';
        pwHint.textContent = 'Fair strength \u2014 consider adding numbers or symbols';
      } else if (score <= 3) {
        pwFill.style.width = '76%';
        pwFill.style.background = '#047857';
        pwHint.textContent = 'Good password strength';
      } else {
        pwFill.style.width = '100%';
        pwFill.style.background = '#16a34a';
        pwHint.textContent = 'Strong password';
      }
    }

    function switchTab(tabName, animate) {
      var activeBtn = null;
      var nextStep = 1;
      navBtns.forEach(function (b) {
        var on = b.getAttribute('data-pm-tab') === tabName;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        if (on) {
          activeBtn = b;
          nextStep = parseInt(b.getAttribute('data-pm-step') || '1', 10) || 1;
        }
      });
      var dirClass = nextStep >= currentStep ? 'is-slide-right' : 'is-slide-left';
      var stepChanged = nextStep !== currentStep;
      currentStep = nextStep;

      panes.forEach(function (p) {
        var on = p.getAttribute('data-pm-pane') === tabName;
        p.classList.remove('is-slide-right', 'is-slide-left');
        p.classList.toggle('is-active', on);
        p.hidden = !on;
        if (on && animate !== false && stepChanged) {
          void p.offsetWidth;
          p.classList.add(dirClass);
        }
      });
      if (activeBtn) {
        requestAnimationFrame(function () {
          moveTabSlider(activeBtn, animate !== false);
        });
      }
    }

    function applyAvatarToElement(el, state, displayName) {
      if (!el) return;
      var statusNode = $('.avatar-status', el);
      var keepStatus = Boolean(statusNode);

      el.classList.remove('av-shape-circle', 'av-shape-badge', 'av-has-img');
      ALL_BADGE_CLASSES.forEach(function (c) { el.classList.remove(c); });
      el.classList.add(state.avatar_shape === 'badge' ? 'av-shape-badge' : 'av-shape-circle');

      var imgSrc = state.avatar_type === 'image' ? (state.avatar_data || state.avatar_url) : '';
      var styleId = state.avatar_style || 'initials';
      var badgeImgSrc = !imgSrc ? (BADGE_IMAGES[styleId] || '') : '';
      el.textContent = '';

      if (imgSrc || badgeImgSrc) {
        el.classList.add('av-has-img');
        var img = document.createElement('img');
        img.className = 'avatar-img';
        img.referrerPolicy = 'no-referrer';
        img.src = imgSrc || badgeImgSrc;
        img.alt = imgSrc ? (displayName || '') : (styleId + ' badge');
        el.appendChild(img);
      } else {
        el.classList.add('av-style-' + styleId);
        var span = document.createElement('span');
        span.setAttribute('data-user-initials', '');
        span.textContent = computeInitials(displayName);
        el.appendChild(span);
      }
      if (keepStatus) el.appendChild(statusNode);
    }

    function syncModalPreview() {
      var curName = (nameInput && nameInput.value.trim()) || 'User';
      $all('[data-pm-preview-name]', modal).forEach(function (el) { el.textContent = curName; });
      $all('.pm-style-tile [data-user-initials]', modal).forEach(function (el) {
        el.textContent = computeInitials(curName);
      });
      if (metaPhoneEl && phoneInput) {
        metaPhoneEl.textContent = phoneInput.value.trim() || 'Not set';
      }
      if (metaAddressEl && addrInput) {
        metaAddressEl.textContent = addrInput.value.trim() || 'Not set';
      }

      var modalAvatars = $all('[data-user-avatar]', modal);
      modalAvatars.forEach(function (av) { applyAvatarToElement(av, avatarState, curName); });

      shapeBtns.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-pm-shape') === avatarState.avatar_shape);
      });
      badgeChips.forEach(function (chip) {
        var isSel = avatarState.avatar_type !== 'image' && chip.getAttribute('data-pm-badge') === avatarState.avatar_style;
        chip.classList.toggle('is-selected', isSel);
      });
      if (specEl) {
        specEl.textContent = (avatarState.avatar_shape === 'badge' ? 'Badge' : 'Circle') + ' \u00b7 256\u00d7256px';
      }
      var hasImg = avatarState.avatar_type === 'image' && Boolean(avatarState.avatar_data || avatarState.avatar_url);
      if (modeEl) modeEl.textContent = hasImg ? 'Custom Photo' : 'Badge Mode';
      if (cropBtn) cropBtn.disabled = !hasImg;
      if (cropFab) cropFab.hidden = !hasImg;
    }

    function drawCropCanvas() {
      if (!cropCanvas || !cropImg) return;
      var ctx = cropCanvas.getContext('2d');
      if (!ctx) return;
      var w = cropCanvas.width;
      var h = cropCanvas.height;
      ctx.clearRect(0, 0, w, h);
      ctx.fillStyle = '#f1f5f9';
      ctx.fillRect(0, 0, w, h);

      var zoom = parseFloat(cropZoom ? cropZoom.value : '1') || 1;
      var ox = (parseFloat(cropX ? cropX.value : '0') || 0) / 100;
      var oy = (parseFloat(cropY ? cropY.value : '0') || 0) / 100;

      var baseScale = Math.max(w / cropImg.naturalWidth, h / cropImg.naturalHeight);
      var scale = baseScale * zoom;
      var dw = cropImg.naturalWidth * scale;
      var dh = cropImg.naturalHeight * scale;
      var maxShiftX = Math.max(0, (dw - w) / 2);
      var maxShiftY = Math.max(0, (dh - h) / 2);
      var dx = (w - dw) / 2 + ox * maxShiftX;
      var dy = (h - dh) / 2 + oy * maxShiftY;
      ctx.drawImage(cropImg, dx, dy, dw, dh);
    }

    function openCropper(src) {
      if (!src || !cropPanel) return;
      closeSubpanels();
      setError('');
      if (cropZoom) cropZoom.value = '1';
      if (cropX) cropX.value = '0';
      if (cropY) cropY.value = '0';
      var img = new Image();
      if (/^https?:\/\//i.test(src)) img.crossOrigin = 'anonymous';
      img.onload = function () {
        cropImg = img;
        cropPanel.hidden = false;
        drawCropCanvas();
      };
      img.onerror = function () {
        setError('Could not load image into cropper. External URLs may block cross-origin cropping.');
      };
      img.src = src;
    }

    navBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchTab(btn.getAttribute('data-pm-tab'));
      });
    });

    shapeBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        avatarState.avatar_shape = btn.getAttribute('data-pm-shape') === 'badge' ? 'badge' : 'circle';
        syncModalPreview();
      });
    });

    badgeChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        closeSubpanels();
        avatarState.avatar_type = 'badge';
        avatarState.avatar_style = chip.getAttribute('data-pm-badge') || 'initials';
        avatarState.avatar_data = '';
        avatarState.avatar_url = '';
        syncModalPreview();
      });
    });

    if (nameInput) {
      nameInput.addEventListener('input', syncModalPreview);
    }
    if (phoneInput) {
      phoneInput.addEventListener('input', syncModalPreview);
    }
    if (addrInput) {
      addrInput.addEventListener('input', syncModalPreview);
    }

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', function () {
        fileInput.value = '';
        fileInput.click();
      });
      fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        setError('');
        if (!/^image\/(png|jpeg|webp)$/i.test(file.type)) {
          setError('Please choose a PNG, JPG, or WebP image.');
          return;
        }
        if (file.size > 5 * 1024 * 1024) {
          setError('Selected image is too large (max 5 MB).');
          return;
        }
        var reader = new FileReader();
        reader.onload = function () {
          var rawUrl = String(reader.result || '');
          var probe = new Image();
          probe.onload = function () {
            var size = 256;
            var cv = document.createElement('canvas');
            cv.width = size;
            cv.height = size;
            var ctx = cv.getContext('2d');
            var sc = Math.max(size / probe.naturalWidth, size / probe.naturalHeight);
            var dw = probe.naturalWidth * sc;
            var dh = probe.naturalHeight * sc;
            ctx.drawImage(probe, (size - dw) / 2, (size - dh) / 2, dw, dh);
            avatarState.avatar_type = 'image';
            avatarState.avatar_data = cv.toDataURL('image/png');
            avatarState.avatar_url = '';
            syncModalPreview();
            openCropper(rawUrl);
          };
          probe.onerror = function () { setError('Could not read the selected image.'); };
          probe.src = rawUrl;
        };
        reader.readAsDataURL(file);
      });
    }

    if (camBtn && camPanel) {
      camBtn.addEventListener('click', function () {
        closeSubpanels();
        setError('');
        if (camErr) { camErr.hidden = true; camErr.textContent = ''; }
        camPanel.hidden = false;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          if (camErr) {
            camErr.textContent = 'Camera capture is not supported in this browser. Upload a photo instead.';
            camErr.hidden = false;
          }
          return;
        }
        navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
          audio: false,
        }).then(function (stream) {
          camStream = stream;
          if (camVideo) camVideo.srcObject = stream;
        }).catch(function () {
          if (camErr) {
            camErr.textContent = 'Camera unavailable or permission denied. Upload a photo or pick a badge instead.';
            camErr.hidden = false;
          }
        });
      });
    }
    if (camClose) camClose.addEventListener('click', closeSubpanels);
    if (camCancel) camCancel.addEventListener('click', closeSubpanels);
    if (camSnap && camVideo) {
      camSnap.addEventListener('click', function () {
        if (!camStream || !camVideo.videoWidth) {
          if (camErr) { camErr.textContent = 'Camera is not ready yet.'; camErr.hidden = false; }
          return;
        }
        var size = 256;
        var cv = document.createElement('canvas');
        cv.width = size;
        cv.height = size;
        var ctx = cv.getContext('2d');
        var vw = camVideo.videoWidth;
        var vh = camVideo.videoHeight;
        var side = Math.min(vw, vh);
        ctx.drawImage(camVideo, (vw - side) / 2, (vh - side) / 2, side, side, 0, 0, size, size);
        avatarState.avatar_type = 'image';
        avatarState.avatar_data = cv.toDataURL('image/png');
        avatarState.avatar_url = '';
        closeSubpanels();
        syncModalPreview();
      });
    }

    [cropBtn, cropFab].forEach(function (btn) {
      if (!btn) return;
      btn.addEventListener('click', function () {
        var src = avatarState.avatar_data || avatarState.avatar_url;
        if (src) openCropper(src);
      });
    });
    [cropZoom, cropX, cropY].forEach(function (rng) {
      if (rng) rng.addEventListener('input', drawCropCanvas);
    });
    if (cropClose) cropClose.addEventListener('click', closeSubpanels);
    if (cropCancel) cropCancel.addEventListener('click', closeSubpanels);
    if (cropApply && cropCanvas) {
      cropApply.addEventListener('click', function () {
        try {
          var out = document.createElement('canvas');
          out.width = 256;
          out.height = 256;
          var octx = out.getContext('2d');
          octx.drawImage(cropCanvas, 0, 0, 256, 256);
          avatarState.avatar_type = 'image';
          avatarState.avatar_data = out.toDataURL('image/png');
          avatarState.avatar_url = '';
          closeSubpanels();
          syncModalPreview();
        } catch (_) {
          setError('Unable to crop this external URL due to browser CORS restrictions. Please upload the file directly.');
          closeSubpanels();
        }
      });
    }

    if (urlToggleBtn && urlPanel) {
      urlToggleBtn.addEventListener('click', function () {
        var willOpen = urlPanel.hidden;
        closeSubpanels();
        urlPanel.hidden = !willOpen;
        if (willOpen && urlInput) urlInput.focus();
      });
    }
    if (urlClose) urlClose.addEventListener('click', closeSubpanels);
    if (urlApply && urlInput) {
      urlApply.addEventListener('click', function () {
        var val = urlInput.value.trim();
        if (!/^https?:\/\/.+/i.test(val)) {
          setError('Enter a valid http:// or https:// image URL.');
          return;
        }
        setError('');
        avatarState.avatar_type = 'image';
        avatarState.avatar_url = val;
        avatarState.avatar_data = '';
        closeSubpanels();
        syncModalPreview();
      });
    }

    if (resetAvatarBtn) {
      resetAvatarBtn.addEventListener('click', function () {
        closeSubpanels();
        avatarState.avatar_type = 'badge';
        avatarState.avatar_data = '';
        avatarState.avatar_url = '';
        if (urlInput) urlInput.value = '';
        syncModalPreview();
      });
    }

    $all('[data-pm-eye]', modal).forEach(function (eyeBtn) {
      eyeBtn.addEventListener('click', function () {
        var target = document.getElementById(eyeBtn.getAttribute('data-pm-eye'));
        if (!target) return;
        var show = target.type === 'password';
        target.type = show ? 'text' : 'password';
        eyeBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });

    if (newPwInput) {
      newPwInput.addEventListener('input', updatePasswordMeter);
    }

    window.addEventListener('resize', function () {
      if (!modal.classList.contains('is-open')) return;
      var activeBtn = $('.pm-tab-btn.is-active', modal);
      if (activeBtn) moveTabSlider(activeBtn, false);
    });

    new MutationObserver(function () {
      if (!modal.classList.contains('is-open')) closeSubpanels();
    }).observe(modal, { attributes: true, attributeFilter: ['class'] });

    function openProfile() {
      setError('');
      closeSubpanels();
      if (curPwInput) curPwInput.value = '';
      if (newPwInput) newPwInput.value = '';
      updatePasswordMeter();
      closeSidebarPopover();
      if (notifPop && !notifPop.hidden) {
        notifPop.hidden = true;
        if (bellBtn) bellBtn.setAttribute('aria-expanded', 'false');
      }
      openModal(modal);
      switchTab('identity', false);
      fetch('/api/profile', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          if (nameInput) nameInput.value = d.full_name || '';
          if (phoneInput) phoneInput.value = d.phone || '';
          if (addrInput) addrInput.value = d.address || '';
          avatarState.avatar_type = d.avatar_type === 'image' ? 'image' : 'badge';
          avatarState.avatar_style = d.avatar_style || 'initials';
          avatarState.avatar_shape = d.avatar_shape === 'badge' ? 'badge' : 'circle';
          avatarState.avatar_url = d.avatar_url || '';
          avatarState.avatar_data = '';
          if (urlInput) urlInput.value = avatarState.avatar_type === 'image' ? avatarState.avatar_url : '';
          syncModalPreview();
        })
        .catch(function () {});
    }

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest ? e.target.closest('[data-profile-open]') : null;
      if (!trigger) return;
      e.preventDefault();
      e.stopPropagation();
      openProfile();
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      setError('');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.classList.add('is-loading');
      }
      var payload = {
        full_name: nameInput ? nameInput.value : '',
        phone: phoneInput ? phoneInput.value : '',
        address: addrInput ? addrInput.value : '',
        current_password: curPwInput ? curPwInput.value : '',
        new_password: newPwInput ? newPwInput.value : '',
        avatar_type: avatarState.avatar_type,
        avatar_style: avatarState.avatar_style,
        avatar_shape: avatarState.avatar_shape,
        avatar_url: avatarState.avatar_url,
        avatar_data: avatarState.avatar_data,
      };

      fetch('/api/profile', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'fetch',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify(payload),
      })
        .then(function (r) {
          return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        })
        .then(function (res) {
          if (!res.ok) {
            var fld = res.data && res.data.field;
            if (fld === 'phone' || fld === 'address') switchTab('contact');
            else if (fld === 'password') switchTab('security');
            else switchTab('identity');
            setError((res.data && res.data.error) || 'Could not update profile.');
            return;
          }
          if (res.data && res.data.user) {
            var u = res.data.user;
            avatarState.avatar_type = u.avatar_type || 'badge';
            avatarState.avatar_style = u.avatar_style || 'initials';
            avatarState.avatar_shape = u.avatar_shape || 'circle';
            avatarState.avatar_url = u.avatar_url || '';
            avatarState.avatar_data = '';

            $all('[data-user-name]').forEach(function (el) { el.textContent = u.full_name; });
            $all('[data-user-initials]').forEach(function (el) { el.textContent = u.initials; });
            $all('[data-user-avatar]').forEach(function (el) { applyAvatarToElement(el, avatarState, u.full_name); });
            if (detailsEl) {
              var parts = [u.address, u.phone].filter(Boolean);
              detailsEl.textContent = parts.length ? parts.join(' · ') : 'No contact info yet';
            }
          }
          if (curPwInput) curPwInput.value = '';
          if (newPwInput) newPwInput.value = '';
          closeSubpanels();
          closeModal(modal);
          showToast('Profile saved.', 'success');
        })
        .catch(function () {
          setError('Network error. Please try again.');
        })
        .finally(function () {
          if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('is-loading');
          }
        });
    });
  }


  /* 14. ADMIN WORKSPACE ENHANCEMENTS (RUNS ON INITIAL LOAD & SPA SWAPS) - */
  function initAdminWorkspace() {
    var mainEl = document.getElementById('main');
    if (!mainEl) return;

    // 14a. Password eye toggles inside page cards/modals
    $all('[data-pm-eye]', mainEl).forEach(function (eyeBtn) {
      eyeBtn.addEventListener('click', function () {
        var target = document.getElementById(eyeBtn.getAttribute('data-pm-eye'));
        if (!target) return;
        var show = target.type === 'password';
        target.type = show ? 'text' : 'password';
        eyeBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });

    // 14b. Temporary password generator buttons
    $all('[data-pw-gen]', mainEl).forEach(function (genBtn) {
      genBtn.addEventListener('click', function () {
        var target = document.getElementById(genBtn.getAttribute('data-pw-gen'));
        if (!target) return;
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        var pw = '';
        for (var i = 0; i < 10; i++) {
          pw += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        target.type = 'text';
        target.value = pw;
        target.focus();
        showToast('Temporary password generated. Copy and share securely with the user.', 'success');
      });
    });

    // 14c. Resident, Volunteer & Admin role edit modal populators
    function bindRoleNote(selectEl, noteEl, msgEl, baseRole, copyMap) {
      if (!selectEl || !noteEl || !msgEl) return function () {};
      function sync() {
        var val = selectEl.value || baseRole;
        if (val === baseRole) {
          noteEl.hidden = true;
          noteEl.classList.remove('is-info');
          msgEl.textContent = '';
        } else {
          var entry = copyMap[val];
          noteEl.hidden = !entry;
          noteEl.classList.toggle('is-info', !!(entry && entry.info));
          msgEl.textContent = entry ? entry.text : '';
        }
      }
      selectEl.addEventListener('change', sync);
      return sync;
    }

    var syncResRoleNote = bindRoleNote(
      document.getElementById('res-edit-role'),
      document.getElementById('res-edit-role-note'),
      document.getElementById('res-edit-role-msg'),
      'resident',
      {
        volunteer: { info: true, text: 'Promoting to Volunteer moves this account to the Volunteers roster so they can receive field dispatches.' },
        admin: { info: false, text: 'Administrator grants full control over reports, users, announcements, and system settings.' }
      }
    );

    $all('[data-res-edit]', mainEl).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idEl = document.getElementById('res-edit-id');
        var nameEl = document.getElementById('res-edit-name');
        var emailEl = document.getElementById('res-edit-email');
        var phoneEl = document.getElementById('res-edit-phone');
        var addrEl = document.getElementById('res-edit-address');
        var roleEl = document.getElementById('res-edit-role');
        if (idEl) idEl.value = btn.getAttribute('data-id') || '';
        if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
        if (emailEl) emailEl.value = btn.getAttribute('data-email') || '';
        if (phoneEl) phoneEl.value = btn.getAttribute('data-phone') || '';
        if (addrEl) addrEl.value = btn.getAttribute('data-address') || '';
        if (roleEl) {
          roleEl.value = btn.getAttribute('data-role') || 'resident';
          roleEl.dispatchEvent(new Event('change'));
        }
        syncResRoleNote();
      });
    });

    var syncVolRoleNote = bindRoleNote(
      document.getElementById('vol-edit-role'),
      document.getElementById('vol-edit-role-note'),
      document.getElementById('vol-edit-role-msg'),
      'volunteer',
      {
        resident: { info: true, text: 'Moving back to Resident requires 0 active assigned dispatches. Past report history stays intact.' },
        admin: { info: false, text: 'Administrator grants full system access. Any active dispatches must be reassigned or closed first.' }
      }
    );

    $all('[data-vol-edit]', mainEl).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idEl = document.getElementById('vol-edit-id');
        var nameEl = document.getElementById('vol-edit-name');
        var emailEl = document.getElementById('vol-edit-email');
        var phoneEl = document.getElementById('vol-edit-phone');
        var addrEl = document.getElementById('vol-edit-address');
        var pwEl = document.getElementById('vol-edit-password');
        var roleEl = document.getElementById('vol-edit-role');
        if (idEl) idEl.value = btn.getAttribute('data-id') || '';
        if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
        if (emailEl) emailEl.value = btn.getAttribute('data-email') || '';
        if (phoneEl) phoneEl.value = btn.getAttribute('data-phone') || '';
        if (addrEl) addrEl.value = btn.getAttribute('data-address') || '';
        if (pwEl) pwEl.value = '';
        if (roleEl) {
          roleEl.value = btn.getAttribute('data-role') || 'volunteer';
          roleEl.dispatchEvent(new Event('change'));
        }
        syncVolRoleNote();
      });
    });

    var syncAdmRoleNote = bindRoleNote(
      document.getElementById('adm-role-select'),
      document.getElementById('adm-role-note'),
      document.getElementById('adm-role-msg'),
      'admin',
      {
        volunteer: { info: true, text: 'This account will lose Administrator access and move to the Volunteers roster.' },
        resident: { info: false, text: 'This account will lose Administrator access and move to the Residents directory.' }
      }
    );

    $all('[data-adm-edit]', mainEl).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idEl = document.getElementById('adm-role-id');
        var nameEl = document.getElementById('adm-role-name');
        var emailEl = document.getElementById('adm-role-email');
        var roleEl = document.getElementById('adm-role-select');
        var statusEl = document.getElementById('adm-role-status');
        if (idEl) idEl.value = btn.getAttribute('data-id') || '';
        if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
        if (emailEl) emailEl.value = btn.getAttribute('data-email') || '';
        if (roleEl) {
          roleEl.value = btn.getAttribute('data-role') || 'admin';
          roleEl.dispatchEvent(new Event('change'));
        }
        if (statusEl) {
          statusEl.value = btn.getAttribute('data-status') || 'active';
          statusEl.dispatchEvent(new Event('change'));
        }
        syncAdmRoleNote();
      });
    });

    // 14d. Announcements composer: counters, live resident preview, edit modal
    var annTitle = document.getElementById('ann-composer-title');
    var annBody = document.getElementById('ann-composer-body');
    var annLevel = document.getElementById('ann-composer-level');
    var annPreview = document.getElementById('ann-live-preview');
    var annTitleCnt = document.getElementById('ann-title-count');
    var annBodyCnt = document.getElementById('ann-body-count');

    function syncAnnComposer() {
      var tt = annTitle ? annTitle.value : '';
      var bd = annBody ? annBody.value : '';
      var lv = annLevel ? annLevel.value : 'info';
      if (annTitleCnt) {
        annTitleCnt.textContent = tt.length + ' / 150';
        annTitleCnt.classList.toggle('is-near', tt.length >= 120 && tt.length < 150);
        annTitleCnt.classList.toggle('is-limit', tt.length >= 150);
      }
      if (annBodyCnt) {
        annBodyCnt.textContent = bd.length + ' / 2000';
        annBodyCnt.classList.toggle('is-near', bd.length >= 1700 && bd.length < 2000);
        annBodyCnt.classList.toggle('is-limit', bd.length >= 2000);
      }
      if (annPreview) {
        annPreview.className = 'ann ann-' + (['info', 'warning', 'critical'].indexOf(lv) !== -1 ? lv : 'info');
        var pvTitle = document.getElementById('ann-pv-title');
        var pvBody = document.getElementById('ann-pv-body');
        var pvLevel = document.getElementById('ann-pv-level');
        var pvIc = document.getElementById('ann-pv-ic');
        if (pvTitle) pvTitle.textContent = tt.trim() || 'Your announcement headline will appear here';
        if (pvBody) pvBody.textContent = bd.trim() || 'Write a concise notice for residents and responders. Active alerts display at the top of dashboards.';
        if (pvLevel) pvLevel.textContent = lv.charAt(0).toUpperCase() + lv.slice(1);
        if (pvIc) {
          pvIc.textContent = '';
          pvIc.appendChild(createSvgIcon(lv === 'critical' ? 'siren' : 'megaphone', 20));
        }
      }
    }
    if (annTitle || annBody || annLevel) {
      [annTitle, annBody].forEach(function (el) { if (el) el.addEventListener('input', syncAnnComposer); });
      if (annLevel) annLevel.addEventListener('change', syncAnnComposer);
      syncAnnComposer();
    }

    $all('[data-ann-edit]', mainEl).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idEl = document.getElementById('ann-edit-id');
        var tEl = document.getElementById('ann-edit-title');
        var lEl = document.getElementById('ann-edit-level');
        var bEl = document.getElementById('ann-edit-body');
        if (idEl) idEl.value = btn.getAttribute('data-id') || '';
        if (tEl) tEl.value = btn.getAttribute('data-title') || '';
        if (bEl) bEl.value = btn.getAttribute('data-body') || '';
        if (lEl) {
          lEl.value = btn.getAttribute('data-level') || 'info';
          lEl.dispatchEvent(new Event('change'));
        }
      });
    });

    $all('form[data-ann-toggle]', mainEl).forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.fetch) return;
        e.preventDefault();
        if (form.dataset.busy === '1') return;

        var btn = $('button[type="submit"]', form);
        var card = form.closest ? form.closest('article.ann') : null;
        var tag = card ? $('.ann-state-tag', card) : null;
        var origText = btn ? btn.textContent : '';

        form.dataset.busy = '1';
        if (btn) {
          btn.disabled = true;
          btn.setAttribute('aria-busy', 'true');
        }

        fetch(form.getAttribute('action') || window.location.pathname, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'fetch',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: new URLSearchParams(new FormData(form))
        })
          .then(function (r) {
            return r.text().then(function (txt) {
              var data = null;
              try { data = JSON.parse(txt); } catch (_) {}
              return { ok: r.ok, status: r.status, data: data };
            });
          })
          .then(function (res) {
            if (!res.ok || !res.data || !res.data.ok) {
              var errMsg = (res.data && res.data.error) || 'Could not update announcement visibility.';
              showToast(errMsg, 'error');
              if (res.status === 419) setTimeout(function () { window.location.reload(); }, 900);
              return;
            }
            var isLive = !!res.data.is_active;
            if (card) card.classList.toggle('is-off', !isLive);
            if (tag) {
              tag.classList.toggle('is-hidden', !isLive);
              tag.textContent = isLive ? 'Live' : 'Hidden';
            }
            if (btn) btn.textContent = isLive ? 'Hide' : 'Show again';

            var statsEl = document.getElementById('ann-stats');
            if (statsEl && res.data.counts) {
              var cards = $all('.stat', statsEl);
              var map = [
                ['total', null],
                ['live', null],
                ['hidden', 'tone-warning', 'tone-muted'],
                ['critical', 'tone-danger', 'tone-muted']
              ];
              map.forEach(function (spec, idx) {
                var sc = cards[idx];
                if (!sc) return;
                var val = res.data.counts[spec[0]];
                if (typeof val === 'number') {
                  var b = $('b', sc);
                  if (b) {
                    b.dataset.staticLock = '1';
                    b.textContent = String(val);
                  }
                  if (spec[1] && spec[2]) {
                    sc.classList.toggle(spec[1], val > 0);
                    sc.classList.toggle(spec[2], val <= 0);
                  }
                }
              });
            }

            showToast(res.data.message || 'Announcement visibility updated.');
          })
          .catch(function () {
            if (btn && origText) btn.textContent = origText;
            showToast('Network error updating announcement visibility.', 'error');
          })
          .finally(function () {
            form.dataset.busy = '0';
            if (btn) {
              btn.disabled = false;
              btn.removeAttribute('aria-busy');
            }
          });
      });
    });

    // 14e. Categories: live auto-priority prediction & edit modal
    var HIGH_CATS = ['fire', 'medical emergency', 'flood', 'road accident', 'theft / crime'];
    var LOW_CATS = ['noise disturbance', 'garbage / sanitation', 'broken streetlight / road'];
    function bindCatPrioPreview(inputEl, badgeEl) {
      if (!inputEl || !badgeEl) return null;
      function sync() {
        var v = inputEl.value.trim().toLowerCase();
        var pr = HIGH_CATS.indexOf(v) !== -1 ? 'high' : (LOW_CATS.indexOf(v) !== -1 ? 'low' : 'medium');
        badgeEl.className = 'badge p-' + pr;
        badgeEl.textContent = pr.charAt(0).toUpperCase() + pr.slice(1);
      }
      inputEl.addEventListener('input', sync);
      sync();
      return sync;
    }
    bindCatPrioPreview(document.getElementById('cat-add-name'), document.getElementById('cat-prio-badge'));
    var syncEditCatPrio = bindCatPrioPreview(document.getElementById('cat-edit-name'), document.getElementById('cat-edit-prio-badge'));

    $all('[data-cat-edit]', mainEl).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idEl = document.getElementById('cat-edit-id');
        var nameEl = document.getElementById('cat-edit-name');
        var sosEl = document.getElementById('cat-edit-sos-note');
        if (idEl) idEl.value = btn.getAttribute('data-id') || '';
        if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
        if (sosEl) sosEl.hidden = btn.getAttribute('data-sos') !== '1';
        if (syncEditCatPrio) syncEditCatPrio();
      });
    });

    // 14f. Settings workspace tabs, animated nav pill, live hotline preview, SMS counter, password strength
    var settingsNav = $('.settings-nav', mainEl);
    var settingsNavBtns = $all('.settings-nav-btn[data-settings-tab]', mainEl);
    var settingsPanels = $all('[data-settings-panel]', mainEl);
    if (settingsNav && settingsNavBtns.length && settingsPanels.length) {
      var settingsPill = $('.settings-nav-pill', settingsNav);
      if (!settingsPill) {
        settingsPill = document.createElement('span');
        settingsPill.className = 'settings-nav-pill';
        settingsPill.setAttribute('aria-hidden', 'true');
        settingsNav.insertBefore(settingsPill, settingsNav.firstChild);
      }
      settingsNav.classList.add('has-pill');
      settingsNavBtns.forEach(function (b, idx) {
        b.style.setProperty('--snav-idx', String(idx));
      });

      var lastSettingsBtn = null;
      var pillMoveTimer = null;
      var activeInitBtn = $('.settings-nav-btn.is-active', settingsNav) || settingsNavBtns[0];
      var currentSettingsTab = activeInitBtn ? (activeInitBtn.getAttribute('data-settings-tab') || 'all') : 'all';

      function positionSettingsPill(animate) {
        var activeBtn = $('.settings-nav-btn.is-active', settingsNav) || settingsNavBtns[0];
        if (!activeBtn || activeBtn.offsetHeight === 0 || activeBtn.offsetWidth === 0) {
          settingsPill.classList.remove('is-ready');
          return;
        }
        var x = activeBtn.offsetLeft;
        var y = activeBtn.offsetTop;
        var w = activeBtn.offsetWidth;
        var h = activeBtn.offsetHeight;

        if (!animate || reduceMotion || !settingsPill.classList.contains('is-ready')) {
          var prevTr = settingsPill.style.transition;
          settingsPill.style.transition = 'none';
          settingsPill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
          settingsPill.style.width = w + 'px';
          settingsPill.style.height = h + 'px';
          settingsPill.offsetHeight;
          settingsPill.style.transition = prevTr || '';
          settingsPill.classList.add('is-ready');
        } else {
          if (lastSettingsBtn && lastSettingsBtn !== activeBtn) {
            settingsPill.classList.remove('is-moving');
            settingsPill.offsetWidth;
            settingsPill.classList.add('is-moving');
            clearTimeout(pillMoveTimer);
            pillMoveTimer = setTimeout(function () { settingsPill.classList.remove('is-moving'); }, 340);
          }
          settingsPill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
          settingsPill.style.width = w + 'px';
          settingsPill.style.height = h + 'px';
        }
        lastSettingsBtn = activeBtn;
      }

      function showSettingsTab(tab) {
        var nextTab = tab || 'all';
        if (nextTab === currentSettingsTab) return;
        currentSettingsTab = nextTab;

        settingsNavBtns.forEach(function (b) {
          var isOn = b.getAttribute('data-settings-tab') === nextTab;
          b.classList.toggle('is-active', isOn);
          b.setAttribute('aria-current', isOn ? 'true' : 'false');
        });
        positionSettingsPill(true);

        var visibleIdx = 0;
        settingsPanels.forEach(function (p) {
          var show = nextTab === 'all' || p.getAttribute('data-settings-panel') === nextTab;
          p.hidden = !show;
          p.classList.remove('is-entering');
          if (show && !reduceMotion) {
            p.style.setProperty('--spanel-idx', String(visibleIdx++));
            p.offsetWidth;
            p.classList.add('is-entering');
          }
        });

        var settingsShell = $('.settings-shell', mainEl);
        var scroller = $('.main-scroll');
        if (settingsShell) {
          if (scroller && window.innerWidth > 820) {
            var shellTop = settingsShell.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
            var anchorTop = Math.max(0, Math.round(shellTop - 20));
            if (scroller.scrollTop > anchorTop + 4) {
              scroller.scrollTo({ top: anchorTop, behavior: reduceMotion ? 'auto' : 'smooth' });
            }
          } else {
            var winTop = settingsShell.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0);
            var winAnchor = Math.max(0, Math.round(winTop - 72));
            var curWinY = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (curWinY > winAnchor + 4) {
              window.scrollTo({ top: winAnchor, behavior: reduceMotion ? 'auto' : 'smooth' });
            }
          }
        }

        var sMapEl = document.getElementById('settings-map-picker');
        if (sMapEl && sMapEl._leafletMap) {
          setTimeout(function () { sMapEl._leafletMap.invalidateSize(); }, 80);
        }
      }

      settingsNavBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          showSettingsTab(btn.getAttribute('data-settings-tab') || 'all');
        });
      });

      if (!reduceMotion) {
        settingsNav.classList.add('is-revealing');
        setTimeout(function () { settingsNav.classList.remove('is-revealing'); }, 450);
      }
      requestAnimationFrame(function () { positionSettingsPill(false); });
      if (window.ResizeObserver) {
        var sNavRo = new ResizeObserver(function () { positionSettingsPill(false); });
        sNavRo.observe(settingsNav);
      } else {
        window.addEventListener('resize', function () { positionSettingsPill(false); });
      }
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { positionSettingsPill(false); });
      }
    }

    var hotPreview = document.getElementById('hotline-live-preview');
    if (hotPreview) {
      var hotInputs = [
        ['Emergency', 'emergency_hotline', 'siren'],
        ['Fire', 'hotline_fire', 'flame'],
        ['Police', 'hotline_police', 'shield'],
        ['Medical', 'hotline_medical', 'plus'],
      ];
      function syncHotlinePreview() {
        hotPreview.textContent = '';
        var any = false;
        hotInputs.forEach(function (pair) {
          var inp = document.getElementById(pair[1]);
          var val = inp ? inp.value.trim() : '';
          if (!val) return;
          any = true;
          var item = document.createElement('div');
          item.className = 'hotline';
          item.appendChild(createSvgIcon(pair[2], 18));
          var span = document.createElement('span');
          var sm = document.createElement('small');
          var b = document.createElement('b');
          sm.textContent = pair[0];
          b.textContent = val;
          span.appendChild(sm);
          span.appendChild(b);
          item.appendChild(span);
          hotPreview.appendChild(item);
        });
        if (!any) {
          var empty = document.createElement('p');
          empty.className = 'muted';
          empty.style.margin = '0';
          empty.textContent = 'All hotline tiles are hidden when every field is left blank.';
          hotPreview.appendChild(empty);
        }
      }
      hotInputs.forEach(function (pair) {
        var inp = document.getElementById(pair[1]);
        if (inp) inp.addEventListener('input', syncHotlinePreview);
      });
      syncHotlinePreview();
    }

    var smsSender = document.getElementById('sms_sender');
    var smsCounter = document.getElementById('sms-sender-count');
    if (smsSender && smsCounter) {
      function syncSmsCount() {
        var len = smsSender.value.length;
        smsCounter.textContent = len + ' / 11 chars';
        smsCounter.classList.toggle('is-limit', len >= 11);
      }
      smsSender.addEventListener('input', syncSmsCount);
      syncSmsCount();
    }

    var secNewPw = document.getElementById('sec-pw-new');
    var secFill = document.getElementById('sec-pw-fill');
    var secHint = document.getElementById('sec-pw-hint');
    if (secNewPw && secFill && secHint) {
      function syncSecPw() {
        var val = secNewPw.value || '';
        if (!val) {
          secFill.style.width = '0%';
          secFill.style.background = 'var(--primary)';
          secHint.textContent = 'Minimum 6 characters; mix letters, numbers & symbols';
          return;
        }
        var score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        if (val.length < 6) {
          secFill.style.width = '20%';
          secFill.style.background = '#dc2626';
          secHint.textContent = 'Too short \u2014 add at least ' + (6 - val.length) + ' more character(s)';
        } else if (score <= 2) {
          secFill.style.width = '48%';
          secFill.style.background = '#d97706';
          secHint.textContent = 'Fair strength \u2014 add numbers or symbols';
        } else if (score <= 3) {
          secFill.style.width = '76%';
          secFill.style.background = '#047857';
          secHint.textContent = 'Good password strength';
        } else {
          secFill.style.width = '100%';
          secFill.style.background = '#16a34a';
          secHint.textContent = 'Strong password';
        }
      }
      secNewPw.addEventListener('input', syncSecPw);
      syncSecPw();
    }

    // 14g. Directory Table (desktop) / Card Grid (tablet, reduced desktop, mobile)
    $all('[data-dir-switch]', mainEl).forEach(function (sw) {
      var key = sw.getAttribute('data-dir-switch') || 'default';
      var target = $('[data-dir-view="' + key + '"]', mainEl);
      var btns = $all('[data-view-mode]', sw);
      if (!target || !btns.length) return;

      var pill = $('.view-switch-pill', sw);
      if (!pill) {
        pill = document.createElement('span');
        pill.className = 'view-switch-pill';
        pill.setAttribute('aria-hidden', 'true');
        sw.insertBefore(pill, sw.firstChild);
      }
      sw.classList.add('has-pill');

      var storageKey = 'cairs_dir_view_' + key;
      var desktopMode = 'table';
      try {
        var raw = localStorage.getItem(storageKey);
        if (raw === 'grid' || raw === 'table') desktopMode = raw;
      } catch (_) {}

      var lastRect = null;
      function positionPill(animate) {
        if (sw.hidden) return;
        var active = $('.view-switch-btn.is-active', sw) || btns[0];
        if (!active || !active.offsetWidth || !active.offsetHeight) {
          pill.classList.remove('is-ready');
          lastRect = null;
          return;
        }
        var x = active.offsetLeft;
        var y = active.offsetTop;
        var w = active.offsetWidth;
        var h = active.offsetHeight;
        if (lastRect && lastRect.x === x && lastRect.y === y && lastRect.w === w && lastRect.h === h && pill.classList.contains('is-ready')) {
          return;
        }
        lastRect = { x: x, y: y, w: w, h: h };
        if (!animate || reduceMotion || !pill.classList.contains('is-ready')) {
          var prevTr = pill.style.transition;
          pill.style.transition = 'none';
          pill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
          pill.style.width = w + 'px';
          pill.style.height = h + 'px';
          pill.offsetHeight;
          pill.style.transition = prevTr || '';
          pill.classList.add('is-ready');
        } else {
          pill.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
          pill.style.width = w + 'px';
          pill.style.height = h + 'px';
        }
      }

      function isCompactViewport() {
        var w = target.clientWidth || (mainEl ? mainEl.clientWidth : 0);
        return w > 0 ? w < 940 : window.innerWidth <= 1180;
      }

      var lastCompact = null;
      function syncView(animate, persistDesktop) {
        var compact = isCompactViewport();
        var activeMode = compact ? 'grid' : desktopMode;
        sw.hidden = compact;
        btns.forEach(function (b) {
          var on = b.getAttribute('data-view-mode') === activeMode;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        target.classList.toggle('is-card-grid', activeMode === 'grid');
        if (animate && !reduceMotion) {
          target.classList.remove('is-view-entering');
          void target.offsetWidth;
          target.classList.add('is-view-entering');
        }
        if (!compact) positionPill(animate || lastCompact === true);
        lastCompact = compact;
        if (persistDesktop && !compact) {
          try { localStorage.setItem(storageKey, desktopMode); } catch (_) {}
        }
      }

      btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (isCompactViewport()) return;
          var mode = btn.getAttribute('data-view-mode') || 'table';
          if (mode === desktopMode && btn.classList.contains('is-active')) {
            positionPill(false);
            return;
          }
          desktopMode = mode === 'grid' ? 'grid' : 'table';
          syncView(true, true);
        });
      });

      if (window.ResizeObserver) {
        var ro = new ResizeObserver(function () {
          syncView(false, false);
          positionPill(false);
        });
        ro.observe(target);
        ro.observe(sw);
      } else {
        window.addEventListener('resize', function () {
          syncView(false, false);
          positionPill(false);
        });
      }
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { positionPill(false); });
      }

      syncView(false, false);
      requestAnimationFrame(function () { positionPill(false); });
    });
  }


  /* 15. MOBILE AUTO-HIDING OVERLAY SCROLLBAR --------------------------- */
  function initMobileOverlayScrollbar() {
    if (!document.body) return;
    var thumb = $('.mobile-scroll-thumb');
    if (!thumb) {
      thumb = document.createElement('div');
      thumb.className = 'mobile-scroll-thumb';
      thumb.setAttribute('aria-hidden', 'true');
      document.body.appendChild(thumb);
    }
    var hideTimer = null;
    var rafPending = false;

    function syncThumb(show) {
      if (window.innerWidth > 820 || document.body.classList.contains('nav-open') || document.body.classList.contains('has-modal')) {
        thumb.classList.remove('is-visible');
        clearTimeout(hideTimer);
        return;
      }
      var docEl = document.documentElement;
      var vh = window.innerHeight || docEl.clientHeight || 0;
      var sh = Math.max(docEl.scrollHeight || 0, document.body.scrollHeight || 0);
      if (sh <= vh + 4) {
        thumb.classList.remove('is-visible');
        clearTimeout(hideTimer);
        return;
      }
      var topbar = $('.topbar');
      var topPad = 6;
      if (topbar) {
        var tbRect = topbar.getBoundingClientRect();
        if (tbRect.height > 0 && tbRect.bottom > 0) {
          topPad = Math.max(6, Math.round(tbRect.bottom + 4));
        }
      }
      var botPad = 6;
      var trackH = Math.max(40, vh - topPad - botPad);
      var thumbH = Math.min(trackH, Math.max(36, Math.round((vh / sh) * trackH)));
      var maxScroll = Math.max(1, sh - vh);
      var ratio = Math.min(1, Math.max(0, (window.scrollY || window.pageYOffset || 0) / maxScroll));
      var y = Math.round(topPad + ratio * (trackH - thumbH));

      thumb.style.height = thumbH + 'px';
      thumb.style.transform = 'translate3d(0,' + y + 'px,0)';
      if (show) {
        thumb.classList.add('is-visible');
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function () {
          thumb.classList.remove('is-visible');
        }, 950);
      }
    }

    function scheduleSync(show) {
      if (rafPending) return;
      rafPending = true;
      requestAnimationFrame(function () {
        rafPending = false;
        syncThumb(show);
      });
    }

    window.addEventListener('scroll', function () { scheduleSync(true); }, { passive: true });
    window.addEventListener('resize', function () { scheduleSync(false); }, { passive: true });
    document.addEventListener('touchmove', function () { scheduleSync(true); }, { passive: true });
  }


  /* START ---------------------------------------------------------------- */
  initSidebar();
  initDropdowns();
  initClock();
  initModals();
  initPage(false);
  initSidebarProfilePopover();
  initProfileModal();
  initNotificationPopover();
  initLivePoll();
  initSpaNavigation();
  initMobileOverlayScrollbar();
})();
