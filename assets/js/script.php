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

  function openModal(modal) {
    lastFocus = document.activeElement;
    document.body.classList.add('has-modal');
    modal.classList.add('is-mounted');
    void modal.offsetWidth;                         // force a reflow so the CSS transition plays
    modal.classList.add('is-open');
    var focusable = $('[data-autofocus]', modal) || $('button, a[href], input:not([disabled]), select, textarea', modal);
    if (focusable) focusable.focus();
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    if (!$('.modal.is-open')) document.body.classList.remove('has-modal');
    setTimeout(function () { modal.classList.remove('is-mounted'); }, reduceMotion ? 0 : 260);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
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
  function bindToastDismiss(toast) {
    function dismiss() {
      toast.classList.add('hide');
      setTimeout(function () { toast.style.display = 'none'; }, 450);
    }
    var close = $('.toast-close', toast);
    if (close) close.addEventListener('click', dismiss);
    setTimeout(dismiss, toast.classList.contains('success') ? 5000 : 9000);
  }

  function showToast(msg, type) {
    var main = $('#main');
    if (!main) return;
    var toast = document.createElement('div');
    toast.className = 'flash ' + (type || 'success') + ' toast';
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var span = document.createElement('span');
    span.textContent = msg;
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.setAttribute('aria-label', 'Dismiss');
    close.innerHTML = '&times;';
    toast.appendChild(span);
    toast.appendChild(close);
    main.insertBefore(toast, main.firstChild);
    bindToastDismiss(toast);
  }

  function initToasts() {
    $all('.toast').forEach(bindToastDismiss);
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
        if (!start) start = time;
        var progress = Math.min((time - start) / 900, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = (end * eased).toFixed(decimals) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      }
      setTimeout(function () { requestAnimationFrame(step); }, 250);
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
  function initMaps() {
    if (!window.L) return;

    function addTiles(map) {
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
    }
    function centerOf(el) { return [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)]; }

    // 10a. Report form: click the map (or use GPS) to drop a pin
    var picker = document.getElementById('map-picker');
    if (picker) {
      var pickMap = L.map(picker).setView(centerOf(picker), picker.dataset.pin === '1' ? 17 : 14);
      var pin = null;
      var latInput = document.getElementById('latitude');
      var lngInput = document.getElementById('longitude');
      addTiles(pickMap);

      var setPin = function (lat, lng) {
        if (pin) pin.setLatLng([lat, lng]); else pin = L.marker([lat, lng]).addTo(pickMap);
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
      };
      if (picker.dataset.pin === '1') setPin.apply(null, centerOf(picker));
      pickMap.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });

      var locate = document.getElementById('locate');
      if (locate) locate.addEventListener('click', function () {
        if (!navigator.geolocation) { showToast('Your browser does not support location.', 'error'); return; }
        navigator.geolocation.getCurrentPosition(function (p) {
          setPin(p.coords.latitude, p.coords.longitude);
          pickMap.setView([p.coords.latitude, p.coords.longitude], 17);
        }, function () { showToast('Could not get your location. Click the map instead.', 'error'); });
      });
    }

    // 10b. Report details: show the saved pin
    var view = document.getElementById('map-view');
    if (view) {
      var viewMap = L.map(view, { scrollWheelZoom: false }).setView(centerOf(view), 17);
      addTiles(viewMap);
      L.marker(centerOf(view)).addTo(viewMap);
    }

    // 10c. Admin: every pinned report, colored by status
    var all = document.getElementById('map-all');
    if (all) {
      var allMap = L.map(all).setView(centerOf(all), 13);
      var points = JSON.parse(all.dataset.points);
      var markers = [];
      addTiles(allMap);

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
    };
    (paths[kind] || paths.bell).forEach(function (d) {
      var p = document.createElementNS(ns, 'path');
      p.setAttribute('d', d);
      svg.appendChild(p);
    });
    return svg;
  }

  var refreshNotifPopover = function () {};

  function initNotificationPopover() {
    var bellBtn = $('#notif-bell-btn');
    var popover = $('#notif-popover');
    var listEl = $('#notif-list');
    if (!bellBtn || !popover || !listEl || !window.fetch) return;

    var markAllBtn = $('#notif-mark-all');
    var clearAllBtn = $('#notif-clear-all');
    var closeBtn = $('#notif-close');
    var tabButtons = $all('[data-notif-tab]', popover);
    var activeTab = 'all';
    var state = { items: [], unread: 0, total: 0, loaded: false };

    function setOpen(open) {
      popover.hidden = !open;
      bellBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        $all('.dropdown.open').forEach(function (d) {
          d.classList.remove('open');
          var b = $('[data-dropdown]', d);
          if (b) b.setAttribute('aria-expanded', 'false');
        });
        loadNotifications();
      }
    }

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

    function renderList() {
      var allCount = state.items.length;
      var unreadCount = state.items.filter(function (it) { return !it.is_read; }).length;
      var allSpan = $('[data-notif-count="all"]', popover);
      var unreadSpan = $('[data-notif-count="unread"]', popover);
      if (allSpan) allSpan.textContent = '(' + allCount + ')';
      if (unreadSpan) unreadSpan.textContent = '(' + unreadCount + ')';
      if (markAllBtn) markAllBtn.disabled = unreadCount === 0;
      if (clearAllBtn) clearAllBtn.disabled = allCount === 0;

      listEl.textContent = '';
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

    function loadNotifications() {
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

    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeTab = btn.getAttribute('data-notif-tab') || 'all';
        tabButtons.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderList();
      });
    });

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

    function sosToast(d) {
      var main = $('#main');
      if (!main) return;
      var toast = document.createElement('div');
      toast.className = 'flash error toast';
      toast.setAttribute('role', 'alert');
      var text = document.createElement('span');
      var link = document.createElement('a');
      link.href = d.sos_url;
      link.textContent = 'New SOS alert: ' + d.sos_title;       // textContent = safe from HTML injection
      text.appendChild(link);
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'toast-close';
      close.setAttribute('aria-label', 'Dismiss');
      close.innerHTML = '&times;';
      close.addEventListener('click', function () { toast.style.display = 'none'; });
      toast.appendChild(text);
      toast.appendChild(close);
      main.insertBefore(toast, main.firstChild);
      setTimeout(function () { toast.style.display = 'none'; }, 20000);
    }

    function poll() {
      if (document.hidden) return;                                 // do nothing while the tab is in the background
      fetch(url, { credentials: 'same-origin', cache: 'no-store' })
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
        .catch(function () { /* offline or server busy: try again next time */ });
    }
    poll();
    setInterval(poll, 15000);
  }


  /* 12. SMOOTH IN-APP NAVIGATION (keeps sidebar/topbar persistent) -------- */
  function ensureLeaflet(cb) {
    if (window.L) { cb(); return; }
    var needsMap = $('#map-picker') || $('#map-view') || $('#map-all');
    if (!needsMap) { cb(); return; }

    var cssHref = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    if (!document.querySelector('link[href="' + cssHref + '"]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssHref;
      document.head.appendChild(link);
    }

    var jsSrc = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    var existing = document.querySelector('script[src="' + jsSrc + '"]');
    if (existing) {
      existing.addEventListener('load', cb, { once: true });
      return;
    }
    var script = document.createElement('script');
    script.src = jsSrc;
    script.onload = cb;
    document.head.appendChild(script);
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
      if (val === 'pending') return { icon: 'clock', tone: 'amber' };
      if (val === 'assigned') return { icon: 'user-check', tone: 'sky' };
      if (val === 'in_progress') return { icon: 'loader', tone: 'violet' };
      if (val === 'resolved') return { icon: 'check-circle', tone: 'emerald' };
      if (val === 'rejected') return { icon: 'x-circle', tone: 'rose' };
      return { icon: 'layers', tone: 'primary' };
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
    });
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
        menu.setAttribute('role', 'listbox');
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
          menu.appendChild(btn);
          buttons.push(btn);
        });
        return { menu: menu, buttons: buttons };
      }

      function positionMenu(menu) {
        var r = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var minW = Math.max(r.width, 196);
        var maxW = Math.min(vw - 24, Math.max(r.width + 36, 300));
        menu.style.minWidth = minW + 'px';
        menu.style.maxWidth = maxW + 'px';

        var spaceBelow = vh - r.bottom - 12;
        var spaceAbove = r.top - 12;
        menu.style.maxHeight = '300px';
        var naturalH = menu.scrollHeight || 220;
        var openUp = spaceBelow < Math.min(naturalH, 190) && spaceAbove > spaceBelow;
        var avail = Math.max(120, Math.min(300, openUp ? spaceAbove : spaceBelow));
        menu.style.maxHeight = avail + 'px';

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
        positionMenu(built.menu);
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        activeSelectCtx = { wrap: wrap, trigger: trigger, menu: built.menu, buttons: built.buttons };

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
    if (isSwap) {
      ensureLeaflet(initMaps);
    } else {
      initMaps();
    }
  }

  function initSpaNavigation() {
    if (!window.history || !window.history.pushState || !window.fetch || !window.DOMParser) return;
    if (!document.body.classList.contains('app')) return;

    var navSeq = 0;

    function navigate(url, push) {
      var parsed;
      try { parsed = new URL(url, window.location.href); } catch (_) { window.location.href = url; return; }
      var seq = ++navSeq;
      var main = $('#main');
      if (main) main.style.opacity = '0.65';

      fetch(parsed.href, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      })
        .then(function (r) {
          if (!r.ok) { window.location.href = parsed.href; return null; }
          return r.text().then(function (html) { return { html: html, finalUrl: r.url || parsed.href }; });
        })
        .then(function (res) {
          if (!res || seq !== navSeq) return;
          var doc = new DOMParser().parseFromString(res.html, 'text/html');
          if (!doc.body || !doc.body.classList.contains('app')) {
            window.location.href = res.finalUrl;
            return;
          }

          var newMain = doc.getElementById('main');
          if (!main || !newMain) { window.location.href = res.finalUrl; return; }

          // Remove floating menus & modals moved onto document.body by previous page scripts
          closeActiveSelect(false);
          var oldSos = document.getElementById('sos-modal');
          if (oldSos && oldSos.parentNode === document.body) oldSos.remove();

          document.title = doc.title;
          main.innerHTML = newMain.innerHTML;
          main.style.opacity = '';

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
          if (push) history.pushState({ spa: true }, '', dest.pathname + dest.search + dest.hash);

          runMainScripts(main);
          initPage(true);

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

      // Same path & query with only a hash jump: let the browser scroll directly & glide pill
      if (url.pathname === window.location.pathname && url.search === window.location.search) {
        if (a.closest && a.closest('.sidebar nav') && sidebarNavApi) {
          sidebarNavApi.setActiveByUrl(url);
        }
        if (url.hash) return;
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
      navigate(window.location.href, false);
    });
  }


  /* 13. SIDEBAR PROFILE POPOVER & PROFILE MODAL DIALOG ------------------- */
  var closeSidebarPopover = function () {};

  function initSidebarProfilePopover() {
    var btn = document.getElementById('sidebar-user-btn');
    var pop = document.getElementById('sidebar-profile-popover');
    if (!btn || !pop) return;

    function setOpen(open) {
      pop.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
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

    var BADGE_ICONS = {
      shield: 'shield',
      responder: 'radar',
      rings: 'sparkles',
      shapes: 'shapes',
      grid: 'grid',
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
      el.textContent = '';

      if (imgSrc) {
        el.classList.add('av-has-img');
        var img = document.createElement('img');
        img.className = 'avatar-img';
        img.src = imgSrc;
        img.alt = displayName || '';
        el.appendChild(img);
      } else {
        var styleId = state.avatar_style || 'initials';
        el.classList.add('av-style-' + styleId);
        if (BADGE_ICONS[styleId]) {
          var glyph = document.createElement('span');
          glyph.className = 'avatar-glyph';
          glyph.setAttribute('aria-hidden', 'true');
          var sz = el.classList.contains('avatar-xl') ? 28 : el.classList.contains('avatar-lg') ? 22 : 17;
          glyph.appendChild(createSvgIcon(BADGE_ICONS[styleId], sz));
          el.appendChild(glyph);
        } else {
          var span = document.createElement('span');
          span.setAttribute('data-user-initials', '');
          span.textContent = computeInitials(displayName);
          el.appendChild(span);
        }
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
})();
