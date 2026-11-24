/* ==========================================================================
   CAIRS - assets/js/script.js
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
        if (!navigator.geolocation) { alert('Your browser does not support location.'); return; }
        navigator.geolocation.getCurrentPosition(function (p) {
          setPin(p.coords.latitude, p.coords.longitude);
          pickMap.setView([p.coords.latitude, p.coords.longitude], 17);
        }, function () { alert('Could not get your location. Click the map instead.'); });
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
    var spNotifBtn = $('#sp-open-notifs');
    if (spNotifBtn) setCountBadge(spNotifBtn, n);
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
      return { icon: 'layers', tone: 'cyan' };
    }
    if (name === 'priority') {
      if (val === 'low') return { icon: 'arrow-down', tone: 'emerald' };
      if (val === 'medium') return { icon: 'minus', tone: 'amber' };
      if (val === 'high') return { icon: 'arrow-up', tone: 'rose' };
      return { icon: 'layers', tone: 'cyan' };
    }
    if (name === 'level') {
      if (val === 'info') return { icon: 'info', tone: 'sky' };
      if (val === 'warning') return { icon: 'alert-triangle', tone: 'amber' };
      if (val === 'critical') return { icon: 'siren', tone: 'rose' };
      return { icon: 'info', tone: 'cyan' };
    }
    if (name === 'volunteer_id') {
      if (!val || val === '0') return { icon: 'user-x', tone: 'muted' };
      return { icon: 'user-check', tone: 'cyan' };
    }
    if (name === 'category' || name === 'category_id') {
      if (!val || val === '0') return { icon: 'layers', tone: 'cyan' };
      if (/fire|blaze|burn/.test(txt)) return { icon: 'flame', tone: 'rose' };
      if (/flood|water|drain|rain|typhoon/.test(txt)) return { icon: 'droplet', tone: 'sky' };
      if (/theft|crime|rob|burglar|security/.test(txt)) return { icon: 'shield-alert', tone: 'amber' };
      if (/road|accident|traffic|tree|crash/.test(txt)) return { icon: 'alert-triangle', tone: 'amber' };
      if (/medical|health|injur|ambulan|emerg/.test(txt)) return { icon: 'heart-pulse', tone: 'rose' };
      if (/noise|disturb|loud|sound/.test(txt)) return { icon: 'volume-2', tone: 'violet' };
      if (/garbage|sanit|trash|waste|clean/.test(txt)) return { icon: 'trash', tone: 'emerald' };
      if (/light|electric|power|wire|lamp/.test(txt)) return { icon: 'zap', tone: 'amber' };
      return { icon: 'tag', tone: 'cyan' };
    }
    return { icon: 'layers', tone: 'cyan' };
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

    var spNotifs = document.getElementById('sp-open-notifs');
    if (spNotifs) {
      spNotifs.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(false);
        var bell = document.getElementById('notif-bell-btn');
        var notifPop = document.getElementById('notif-popover');
        if (bell && notifPop && notifPop.hidden) bell.click();
      });
    }

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

    function setError(msg) {
      if (!errBox) return;
      errBox.textContent = msg || '';
      errBox.hidden = !msg;
    }

    function openProfile() {
      setError('');
      if (curPwInput) curPwInput.value = '';
      if (newPwInput) newPwInput.value = '';
      closeSidebarPopover();
      if (notifPop && !notifPop.hidden) {
        notifPop.hidden = true;
        if (bellBtn) bellBtn.setAttribute('aria-expanded', 'false');
      }
      openModal(modal);
      fetch('/api/profile', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          if (nameInput) nameInput.value = d.full_name || '';
          if (phoneInput) phoneInput.value = d.phone || '';
          if (addrInput) addrInput.value = d.address || '';
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
            setError((res.data && res.data.error) || 'Could not update profile.');
            return;
          }
          if (res.data && res.data.user) {
            $all('[data-user-name]').forEach(function (el) { el.textContent = res.data.user.full_name; });
            $all('[data-user-initials]').forEach(function (el) { el.textContent = res.data.user.initials; });
            if (detailsEl) {
              var parts = [res.data.user.address, res.data.user.phone].filter(Boolean);
              detailsEl.textContent = parts.length ? parts.join(' · ') : 'No contact info yet';
            }
          }
          if (curPwInput) curPwInput.value = '';
          if (newPwInput) newPwInput.value = '';
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
