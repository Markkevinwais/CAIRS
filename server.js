import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import multer from 'multer';
import { sessionMiddleware } from './lib/session.js';
import {
  db,
  saveDb,
  setting,
  setSetting,
  fetchReport,
  enrichReport,
  logUpdate,
  notify,
  sendSms,
  loginLocked,
  loginFailed,
  loginClear,
  hashPw,
  verifyPassword,
} from './lib/store.js';
import * as helpers from './lib/helpers.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const UPLOAD_DIR = path.join(__dirname, 'public', 'assets', 'uploads');
fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const app = express();
const PORT = parseInt(process.env.PORT || '3000', 10);

app.set('trust proxy', 1);
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json({ limit: '5mb' }));
app.use(express.static(path.join(__dirname, 'public')));

app.use(sessionMiddleware);

// Redirect legacy /CAIRS/* paths to root /*
app.use((req, res, next) => {
  if (req.path === '/CAIRS' || req.path === '/CAIRS/') {
    return res.redirect(301, '/index.php');
  }
  if (req.path.startsWith('/CAIRS/')) {
    const target = req.originalUrl.slice('/CAIRS'.length) || '/';
    return res.redirect(301, target);
  }
  next();
});

// View locals + flash + CSRF helpers + active user sync
app.use((req, res, next) => {
  let user = req.session.user || null;
  let userProfile = null;
  if (user) {
    const liveUser = db.users.find((u) => u.id === user.id);
    if (!liveUser || liveUser.status !== 'active') {
      delete req.session.user;
      user = null;
    } else {
      if (
        liveUser.full_name !== user.full_name ||
        liveUser.email !== user.email ||
        liveUser.role !== user.role
      ) {
        req.session.user = {
          id: liveUser.id,
          full_name: liveUser.full_name,
          email: liveUser.email,
          role: liveUser.role,
        };
        user = req.session.user;
      }
      userProfile = {
        full_name: liveUser.full_name,
        email: liveUser.email,
        role: liveUser.role,
        phone: liveUser.phone || '',
        address: liveUser.address || '',
        created_at: liveUser.created_at || '',
        avatar_type: liveUser.avatar_type === 'image' ? 'image' : 'badge',
        avatar_style: liveUser.avatar_style || 'initials',
        avatar_shape: liveUser.avatar_shape === 'badge' ? 'badge' : 'circle',
        avatar_url: helpers.sanitizeAvatarUrl(liveUser.avatar_url),
      };
    }
  }

  let unread = 0;
  if (user) {
    unread = db.notifications.filter((n) => n.user_id === user.id && n.is_read === 0).length;
  }

  const flashMsg = req.session.flash || null;
  delete req.session.flash;

  if (!req.session.csrf) {
    req.session.csrf = crypto.randomBytes(16).toString('hex');
  }

  res.locals = {
    ...helpers,
    setting,
    user,
    userProfile,
    unread,
    flash: flashMsg,
    current: req.path.replace(/^\//, ''),
    csrfToken: req.session.csrf,
    csrfField: () => `<input type="hidden" name="csrf" value="${req.session.csrf}">`,
  };
  next();
});

function setFlash(req, msg, type = 'success') {
  req.session.flash = { m: msg, t: type };
}

function redirect(res, relPath) {
  return res.redirect('/' + String(relPath || '').replace(/^\/+/, ''));
}

function requireRole(role) {
  return (req, res, next) => {
    const u = req.session.user;
    if (!u) return redirect(res, 'login.php');
    if (u.role !== role) return redirect(res, helpers.homeFor(u.role));
    next();
  };
}

function csrfCheck(req, res, next) {
  const token = req.body?.csrf;
  const expected = req.session?.csrf;
  const valid =
    Boolean(token && expected && token.length === expected.length) &&
    crypto.timingSafeEqual(Buffer.from(token), Buffer.from(expected));

  if (!valid) {
    if (req.path === '/login' || req.path === '/login.php') {
      return res.status(419).render('login', {
        error: 'Session expired. Please try logging in again.',
        email: String(req.body?.email || '').trim(),
      });
    }
    if (req.path === '/register' || req.path === '/register.php') {
      return res.status(419).render('register', {
        errors: ['Session expired. Please submit the form again.'],
        old: {
          full_name: String(req.body?.full_name || '').trim(),
          email: String(req.body?.email || '').trim(),
          phone: String(req.body?.phone || '').trim(),
          address: String(req.body?.address || '').trim(),
        },
      });
    }
    return res.status(419).send('Session expired. Go back, refresh the page and try again.');
  }
  next();
}

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 3 * 1024 * 1024 },
});

function detectImageExt(buf) {
  if (!buf || buf.length < 12) return null;
  if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'jpg';
  if (buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47) return 'png';
  if (buf.subarray(0, 4).toString('ascii') === 'RIFF' && buf.subarray(8, 12).toString('ascii') === 'WEBP') return 'webp';
  return null;
}

/* ==================== PUBLIC & AUTH ROUTES ==================== */

app.get(['/', '/index.php'], (req, res) => {
  if (req.session.user) return redirect(res, helpers.homeFor(req.session.user.role));
  const resolved = db.reports.filter((r) => r.status === 'resolved').length;
  const total = db.reports.length;
  res.render('index', {
    site: setting('site_name', 'CAIRS'),
    barangay: setting('barangay_name', 'your barangay'),
    resolved,
    total,
  });
});

app.get(['/login', '/login.php'], (req, res) => {
  if (req.session.user) return redirect(res, helpers.homeFor(req.session.user.role));
  res.render('login', { error: '', email: '' });
});

app.post(['/login', '/login.php'], csrfCheck, (req, res) => {
  if (req.session.user) return redirect(res, helpers.homeFor(req.session.user.role));
  const email = String(req.body.email || '').trim();
  const password = String(req.body.password || '');
  const ip = req.ip || '';
  const user = db.users.find((u) => u.email.toLowerCase() === email.toLowerCase());

  let error = '';
  if (loginLocked(email, ip)) {
    error = 'Too many failed attempts. Please wait 10 minutes and try again.';
  } else if (!user || !verifyPassword(password, user.password)) {
    error = 'Email or password is incorrect.';
    loginFailed(email, ip);
  } else if (user.status !== 'active') {
    error = 'This account is inactive. Contact the barangay office.';
  } else {
    loginClear(email);
    const csrf = req.session.csrf;
    return req.session.regenerate((err) => {
      if (err) {
        return res.render('login', { error: 'Could not start session. Please try again.', email });
      }
      req.session.csrf = csrf || crypto.randomBytes(16).toString('hex');
      req.session.user = {
        id: user.id,
        full_name: user.full_name,
        email: user.email,
        role: user.role,
      };
      return req.session.save((saveErr) => {
        if (saveErr) {
          return res.render('login', { error: 'Could not save session. Please try again.', email });
        }
        return redirect(res, helpers.homeFor(user.role));
      });
    });
  }
  res.render('login', { error, email });
});

app.get(['/register', '/register.php'], (req, res) => {
  if (req.session.user) return redirect(res, helpers.homeFor(req.session.user.role));
  res.render('register', {
    errors: [],
    old: { full_name: '', email: '', phone: '', address: '' },
  });
});

app.post(['/register', '/register.php'], csrfCheck, (req, res) => {
  if (req.session.user) return redirect(res, helpers.homeFor(req.session.user.role));
  const old = {
    full_name: String(req.body.full_name || '').trim(),
    email: String(req.body.email || '').trim(),
    phone: String(req.body.phone || '').trim(),
    address: String(req.body.address || '').trim(),
  };
  const pass = String(req.body.password || '');
  const confirm = String(req.body.confirm || '');
  const errors = [];

  if (!old.full_name) errors.push('Enter your full name.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(old.email)) errors.push('Enter a valid email address.');
  if (!old.address) errors.push('Enter your address or purok.');
  if (pass.length < 6) errors.push('Password must be at least 6 characters.');
  if (pass !== confirm) errors.push('Passwords do not match.');

  if (!errors.length && db.users.some((u) => u.email.toLowerCase() === old.email.toLowerCase())) {
    errors.push('That email is already registered.');
  }

  if (!errors.length) {
    db.users.push({
      id: db.nextIds.users++,
      full_name: old.full_name,
      email: old.email,
      password: hashPw(pass),
      role: 'resident',
      phone: old.phone || null,
      address: old.address,
      status: 'active',
      created_at: new Date().toISOString(),
    });
    saveDb();
    setFlash(req, 'Account created. You can log in now.');
    return redirect(res, 'login.php');
  }

  res.render('register', { errors, old });
});

app.get(['/logout', '/logout.php'], (req, res) => {
  req.session.regenerate(() => {
    setFlash(req, 'You have been logged out.');
    redirect(res, 'login.php');
  });
});

/* ==================== API POLLING & NOTIFICATIONS ==================== */

function apiCsrfCheck(req, res, next) {
  if (!req.session?.user) {
    return res.status(401).json({ error: 'login' });
  }
  const token = String(req.get('X-CSRF-Token') || req.body?.csrf || '');
  const expected = String(req.session?.csrf || '');
  const valid =
    Boolean(token && expected && token.length === expected.length) &&
    crypto.timingSafeEqual(Buffer.from(token), Buffer.from(expected));
  if (!valid || req.get('X-Requested-With') !== 'fetch') {
    return res.status(419).json({ error: 'Invalid or expired security token.' });
  }
  next();
}

function formatNotificationItem(n, role) {
  const report = n.report_id ? db.reports.find((r) => r.id === n.report_id) : null;
  const isSos = Boolean(report?.is_sos === 1 || /^SOS\b/i.test(n.message));
  const ref = n.report_id ? helpers.refNo(n.report_id) : '';
  let title = 'Incident Update';
  if (isSos) {
    title = 'Emergency SOS Alert';
  } else if (report) {
    title = `${ref} · ${report.title}`;
  } else if (ref) {
    title = `${ref} Update`;
  }
  return {
    id: n.id,
    report_id: n.report_id,
    title,
    message: n.message,
    is_read: n.is_read === 1,
    is_sos: isSos,
    ref,
    created_at: n.created_at,
    created_fmt: helpers.fmtDate(n.created_at),
    url: n.report_id ? `/${role}/report_details.php?id=${n.report_id}` : '',
    cta: n.report_id ? `View ${ref}` : '',
  };
}

app.get('/api/poll.php', (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const u = req.session.user;
  if (!u) return res.status(401).json({ error: 'login' });

  const unread = db.notifications.filter((n) => n.user_id === u.id && n.is_read === 0).length;
  const out = { unread };

  if (u.role === 'admin') {
    const activeSos = db.reports
      .filter((r) => r.is_sos === 1 && ['pending', 'assigned', 'in_progress'].includes(r.status))
      .sort((a, b) => b.id - a.id);
    out.sos = activeSos.length;
    const last = activeSos[0] || null;
    out.sos_last = last ? last.id : 0;
    out.sos_title = last ? `${helpers.refNo(last.id)} ${last.title}` : '';
    out.sos_url = last ? `/admin/report_details.php?id=${last.id}` : '';
  }

  res.json(out);
});

app.get('/api/notifications', (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const u = req.session.user;
  if (!u) return res.status(401).json({ error: 'login' });

  const allUser = db.notifications
    .filter((n) => n.user_id === u.id)
    .sort((a, b) => b.id - a.id);
  const unread = allUser.filter((n) => n.is_read === 0).length;
  const items = allUser.slice(0, 30).map((n) => formatNotificationItem(n, u.role));

  res.json({
    unread,
    total: allUser.length,
    items,
  });
});

app.post('/api/notifications/read', apiCsrfCheck, (req, res) => {
  const uid = req.session.user.id;
  const rawId = req.body?.id;
  if (rawId !== undefined && rawId !== null && rawId !== '') {
    const nid = Number(rawId);
    if (!Number.isInteger(nid) || nid <= 0) {
      return res.status(400).json({ error: 'Invalid notification ID.' });
    }
    const target = db.notifications.find((n) => n.id === nid && n.user_id === uid);
    if (!target) {
      return res.status(404).json({ error: 'Notification not found.' });
    }
    target.is_read = 1;
  } else {
    for (const n of db.notifications) {
      if (n.user_id === uid) n.is_read = 1;
    }
  }
  saveDb();
  const unread = db.notifications.filter((n) => n.user_id === uid && n.is_read === 0).length;
  res.json({ ok: true, unread });
});

app.post('/api/notifications/clear', apiCsrfCheck, (req, res) => {
  const uid = req.session.user.id;
  const rawId = req.body?.id;
  const mode = String(req.body?.mode || 'all');

  if (rawId !== undefined && rawId !== null && rawId !== '') {
    const nid = Number(rawId);
    if (!Number.isInteger(nid) || nid <= 0) {
      return res.status(400).json({ error: 'Invalid notification ID.' });
    }
    const before = db.notifications.length;
    db.notifications = db.notifications.filter((n) => !(n.id === nid && n.user_id === uid));
    if (db.notifications.length === before) {
      return res.status(404).json({ error: 'Notification not found.' });
    }
  } else if (mode === 'read') {
    db.notifications = db.notifications.filter((n) => !(n.user_id === uid && n.is_read === 1));
  } else {
    db.notifications = db.notifications.filter((n) => n.user_id !== uid);
  }
  saveDb();
  const unread = db.notifications.filter((n) => n.user_id === uid && n.is_read === 0).length;
  res.json({ ok: true, unread });
});

app.get('/api/profile', (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  const u = req.session.user;
  if (!u) return res.status(401).json({ error: 'login' });
  const me = db.users.find((usr) => usr.id === u.id);
  if (!me) return res.status(404).json({ error: 'User not found.' });
  res.json({
    full_name: me.full_name,
    email: me.email,
    phone: me.phone || '',
    address: me.address || '',
    role: me.role,
    created_at: me.created_at,
    created_fmt: helpers.fmtDate(me.created_at),
    initials: helpers.initials(me.full_name),
    avatar_type: me.avatar_type === 'image' ? 'image' : 'badge',
    avatar_style: me.avatar_style || 'initials',
    avatar_shape: me.avatar_shape === 'badge' ? 'badge' : 'circle',
    avatar_url: helpers.sanitizeAvatarUrl(me.avatar_url),
  });
});

function removeOldUserAvatars(uid, keepFilename = '') {
  try {
    const prefix = `avatar_${uid}_`;
    for (const f of fs.readdirSync(UPLOAD_DIR)) {
      if (f.startsWith(prefix) && f !== keepFilename) {
        fs.unlinkSync(path.join(UPLOAD_DIR, f));
      }
    }
  } catch {}
}

app.post('/api/profile', apiCsrfCheck, (req, res) => {
  const uid = req.session.user.id;
  const me = db.users.find((usr) => usr.id === uid);
  if (!me) return res.status(404).json({ error: 'User not found.' });

  const name = String(req.body?.full_name || '').trim();
  const phone = String(req.body?.phone || '').trim();
  const addr = String(req.body?.address || '').trim();
  const curPass = String(req.body?.current_password || '');
  const newPass = String(req.body?.new_password || '');

  const avatarType = req.body?.avatar_type === 'image' ? 'image' : 'badge';
  const validStyleIds = new Set(helpers.AVATAR_BADGE_STYLES.map((s) => s.id));
  const avatarStyle = validStyleIds.has(req.body?.avatar_style) ? req.body.avatar_style : 'initials';
  const avatarShape = req.body?.avatar_shape === 'badge' ? 'badge' : 'circle';
  const avatarData = typeof req.body?.avatar_data === 'string' ? req.body.avatar_data.trim() : '';
  const rawAvatarUrl = typeof req.body?.avatar_url === 'string' ? req.body.avatar_url.trim() : '';

  if (!name) return res.status(400).json({ error: 'Enter your full name.', field: 'name' });
  if (name.length > 100) return res.status(400).json({ error: 'Name is too long.', field: 'name' });
  if (phone.length > 40) return res.status(400).json({ error: 'Phone number is too long.', field: 'phone' });
  if (addr.length > 200) return res.status(400).json({ error: 'Address is too long.', field: 'address' });
  if (me.role === 'resident' && !addr) {
    return res.status(400).json({ error: 'Enter your address or purok.', field: 'address' });
  }
  if (newPass !== '') {
    if (!verifyPassword(curPass, me.password)) {
      return res.status(400).json({ error: 'Current password is incorrect.', field: 'password' });
    }
    if (newPass.length < 6) {
      return res.status(400).json({ error: 'New password must be at least 6 characters.', field: 'password' });
    }
  }

  let nextAvatarUrl = '';
  let nextAvatarType = avatarType;
  if (avatarType === 'image') {
    if (avatarData) {
      const m = avatarData.match(/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$/);
      if (!m) {
        return res.status(400).json({ error: 'Unsupported image format. Use PNG, JPG, or WebP.', field: 'avatar' });
      }
      const buf = Buffer.from(m[2], 'base64');
      if (buf.length === 0 || buf.length > 3 * 1024 * 1024) {
        return res.status(400).json({ error: 'Profile photo must be under 3 MB.', field: 'avatar' });
      }
      const ext = m[1] === 'jpeg' ? 'jpg' : m[1];
      const fname = `avatar_${uid}_${Date.now()}.${ext}`;
      fs.writeFileSync(path.join(UPLOAD_DIR, fname), buf);
      removeOldUserAvatars(uid, fname);
      nextAvatarUrl = `/assets/uploads/${fname}`;
    } else {
      const safeUrl = helpers.sanitizeAvatarUrl(rawAvatarUrl);
      if (!safeUrl) {
        return res.status(400).json({ error: 'Please provide a valid http(s) image URL or upload a photo.', field: 'avatar' });
      }
      if (!safeUrl.startsWith('/assets/uploads/')) {
        removeOldUserAvatars(uid);
      }
      nextAvatarUrl = safeUrl;
    }
  } else {
    removeOldUserAvatars(uid);
    nextAvatarType = 'badge';
    nextAvatarUrl = '';
  }

  me.full_name = name;
  me.phone = phone || null;
  me.address = addr || null;
  me.avatar_type = nextAvatarType;
  me.avatar_style = avatarStyle;
  me.avatar_shape = avatarShape;
  me.avatar_url = nextAvatarUrl || null;
  if (newPass !== '') me.password = hashPw(newPass);
  saveDb();

  req.session.user.full_name = name;
  res.json({
    ok: true,
    user: {
      full_name: me.full_name,
      email: me.email,
      phone: me.phone || '',
      address: me.address || '',
      role: me.role,
      initials: helpers.initials(me.full_name),
      avatar_type: me.avatar_type,
      avatar_style: me.avatar_style,
      avatar_shape: me.avatar_shape,
      avatar_url: me.avatar_url || '',
    },
  });
});

/* ==================== RESIDENT ROUTES ==================== */

app.get('/resident/dashboard.php', requireRole('resident'), (req, res) => {
  const uid = req.session.user.id;
  const myReports = db.reports.filter((r) => r.resident_id === uid);
  const c = {};
  for (const r of myReports) c[r.status] = (c[r.status] || 0) + 1;
  const total = myReports.length;
  const open = (c.assigned || 0) + (c.in_progress || 0);

  const recent = [...myReports]
    .sort((a, b) => b.id - a.id)
    .slice(0, 6)
    .map(enrichReport);

  const myReportIds = new Set(myReports.map((r) => r.id));
  const activity = db.report_updates
    .filter((ru) => myReportIds.has(ru.report_id))
    .sort((a, b) => b.id - a.id)
    .slice(0, 6)
    .map((ru) => {
      const r = db.reports.find((rep) => rep.id === ru.report_id);
      const u = db.users.find((usr) => usr.id === ru.user_id);
      return { ...ru, title: r ? r.title : '', full_name: u ? u.full_name : 'System' };
    });

  res.render('resident/dashboard', { c, total, open, recent, activity });
});

app.get('/resident/report.php', requireRole('resident'), (req, res) => {
  const cats = [...db.categories].sort((a, b) => a.name.localeCompare(b.name));
  res.render('resident/report', {
    cats,
    errors: [],
    old: { category_id: '', title: '', description: '', location: '', latitude: '', longitude: '' },
  });
});

app.post(
  '/resident/report.php',
  requireRole('resident'),
  (req, res, next) => {
    upload.single('photo')(req, res, (err) => {
      if (err) {
        req.uploadError = err.code === 'LIMIT_FILE_SIZE' ? 'Photo must be 3 MB or smaller.' : 'Photo upload failed.';
      }
      next();
    });
  },
  csrfCheck,
  async (req, res) => {
    const uid = req.session.user.id;
    const cats = [...db.categories].sort((a, b) => a.name.localeCompare(b.name));
    const old = {
      category_id: String(req.body.category_id || '').trim(),
      title: String(req.body.title || '').trim(),
      description: String(req.body.description || '').trim(),
      location: String(req.body.location || '').trim(),
      latitude: String(req.body.latitude || '').trim(),
      longitude: String(req.body.longitude || '').trim(),
    };
    const errors = [];
    if (req.uploadError) errors.push(req.uploadError);

    const cat = cats.find((c) => String(c.id) === old.category_id);
    if (!cat) errors.push('Choose a category.');
    if (!old.title) errors.push('Enter a short title.');
    if (old.description.length < 10) errors.push('Describe the incident in at least 10 characters.');
    if (!old.location) errors.push('Enter where it happened.');

    const hasLat = old.latitude !== '' && !Number.isNaN(Number(old.latitude));
    const hasLng = old.longitude !== '' && !Number.isNaN(Number(old.longitude));
    const lat = hasLat ? Number(old.latitude) : null;
    const lng = hasLng ? Number(old.longitude) : null;
    if ((lat === null) !== (lng === null) || (lat !== null && (Math.abs(lat) > 90 || Math.abs(lng) > 180))) {
      errors.push('The map pin is not valid. Click the map to place it again.');
    }

    let photoFilename = null;
    if (req.file && !req.uploadError) {
      const ext = detectImageExt(req.file.buffer);
      if (!ext) {
        errors.push('Photo must be a JPG, PNG or WEBP image.');
      } else if (req.file.size > 3 * 1024 * 1024) {
        errors.push('Photo must be 3 MB or smaller.');
      } else {
        photoFilename = `${crypto.randomBytes(8).toString('hex')}.${ext}`;
      }
    }

    if (!errors.length) {
      if (photoFilename && req.file) {
        fs.writeFileSync(path.join(UPLOAD_DIR, photoFilename), req.file.buffer);
      }
      const rid = db.nextIds.reports++;
      const nowIso = new Date().toISOString();
      db.reports.push({
        id: rid,
        resident_id: uid,
        category_id: cat.id,
        volunteer_id: null,
        title: old.title,
        description: old.description,
        location: old.location,
        latitude: lat,
        longitude: lng,
        photo: photoFilename,
        priority: helpers.defaultPriority(cat.name),
        status: 'pending',
        is_sos: 0,
        created_at: nowIso,
        updated_at: nowIso,
      });
      logUpdate(rid, uid, 'pending', 'Report submitted.');
      const admins = db.users.filter((u) => u.role === 'admin' && u.status === 'active');
      for (const a of admins) {
        await notify(a.id, rid, `New report ${helpers.refNo(rid)}: ${old.title}`);
      }
      setFlash(req, `Report submitted as ${helpers.refNo(rid)}. We will notify you of any update.`);
      return redirect(res, `resident/report_details.php?id=${rid}`);
    }

    res.render('resident/report', { cats, errors, old });
  }
);

const SOS_TYPES = {
  fire: ['Fire', 'Fire', 'flame'],
  medical: ['Medical emergency', 'Medical emergency', 'plus'],
  crime: ['Crime / Police', 'Theft / Crime', 'shield'],
  flood: ['Flood', 'Flood', 'radar'],
  accident: ['Road accident', 'Road accident', 'siren'],
};

function sosCategoryId(cats, name) {
  const exact = cats.find((c) => c.name.toLowerCase() === name.toLowerCase());
  if (exact) return exact.id;
  const other = cats.find((c) => c.name.toLowerCase() === 'other');
  if (other) return other.id;
  return cats.length ? cats[0].id : 0;
}

app.get('/resident/sos.php', requireRole('resident'), (req, res) => {
  res.render('resident/sos', { types: SOS_TYPES });
});

app.post('/resident/sos.php', requireRole('resident'), csrfCheck, async (req, res) => {
  const uid = req.session.user.id;
  const key = String(req.body.type || '');
  if (!SOS_TYPES[key]) {
    setFlash(req, 'Choose the kind of emergency.', 'error');
    return redirect(res, 'resident/sos.php');
  }
  if (!db.categories.length) {
    setFlash(req, 'No categories are set up yet. Please call the barangay hotline.', 'error');
    return redirect(res, 'resident/sos.php');
  }

  const twoMinAgo = Date.now() - 2 * 60 * 1000;
  const dupe = [...db.reports]
    .sort((a, b) => b.id - a.id)
    .find((r) => r.resident_id === uid && r.is_sos === 1 && new Date(r.created_at).getTime() > twoMinAgo);
  if (dupe) {
    setFlash(req, 'Your SOS was already sent a moment ago. Help is on the way.');
    return redirect(res, `resident/report_details.php?id=${dupe.id}`);
  }

  const me = db.users.find((u) => u.id === uid);
  const rawLat = req.body.latitude ?? '';
  const rawLng = req.body.longitude ?? '';
  let lat = rawLat !== '' && !Number.isNaN(Number(rawLat)) ? Number(rawLat) : null;
  let lng = rawLng !== '' && !Number.isNaN(Number(rawLng)) ? Number(rawLng) : null;
  if ((lat === null) !== (lng === null) || (lat !== null && (Math.abs(lat) > 90 || Math.abs(lng) > 180))) {
    lat = null;
    lng = null;
  }

  const [label, catName] = SOS_TYPES[key];
  const home = me?.address ? me.address : 'not provided';
  const location = (lat !== null ? `Sent from live GPS location (home: ${home})` : `No GPS. Home address: ${home}`).slice(0, 255);
  const title = `SOS: ${label}`;
  const desc = `EMERGENCY SOS sent by ${me?.full_name || 'Resident'}. Type: ${label}. Contact number: ${me?.phone || 'not provided'}.`;

  const rid = db.nextIds.reports++;
  const nowIso = new Date().toISOString();
  db.reports.push({
    id: rid,
    resident_id: uid,
    category_id: sosCategoryId(db.categories, catName),
    volunteer_id: null,
    title,
    description: desc,
    location,
    latitude: lat,
    longitude: lng,
    photo: null,
    priority: 'high',
    status: 'pending',
    is_sos: 1,
    created_at: nowIso,
    updated_at: nowIso,
  });

  logUpdate(rid, uid, 'pending', 'SOS alert sent by the resident.');
  const msg = `SOS ${helpers.refNo(rid)} (${label}) from ${me?.full_name || 'Resident'}. Respond now.`;
  const team = db.users.filter((u) => (u.role === 'admin' || u.role === 'volunteer') && u.status === 'active');
  for (const t of team) {
    await notify(t.id, rid, msg);
  }

  setFlash(req, `SOS sent as ${helpers.refNo(rid)}. The barangay has been alerted. Stay safe.`);
  redirect(res, `resident/report_details.php?id=${rid}`);
});

app.get('/resident/my_reports.php', requireRole('resident'), (req, res) => {
  const uid = req.session.user.id;
  const status = String(req.query.status || '');
  const rows = db.reports
    .filter((r) => r.resident_id === uid && (status === '' || r.status === status))
    .sort((a, b) => b.id - a.id)
    .map(enrichReport);
  res.render('resident/my_reports', { status, rows });
});

app.get('/resident/report_details.php', requireRole('resident'), (req, res) => {
  const r = fetchReport(parseInt(req.query.id || '0', 10));
  if (!r || r.resident_id !== req.session.user.id) {
    setFlash(req, 'Report not found.', 'error');
    return redirect(res, 'resident/my_reports.php');
  }
  res.render('resident/report_details', { r });
});

app.get('/resident/notifications.php', requireRole('resident'), (req, res) => {
  return redirect(res, 'resident/dashboard.php');
});

app.get('/resident/profile.php', requireRole('resident'), (req, res) => {
  return redirect(res, 'resident/dashboard.php');
});

/* ==================== VOLUNTEER ROUTES ==================== */

app.get('/volunteer/dashboard.php', requireRole('volunteer'), (req, res) => {
  const uid = req.session.user.id;
  const assigned = db.reports.filter((r) => r.volunteer_id === uid);
  const c = {};
  for (const r of assigned) c[r.status] = (c[r.status] || 0) + 1;

  const prioRank = { high: 1, medium: 2, low: 3 };
  const todo = assigned
    .filter((r) => r.status === 'assigned' || r.status === 'in_progress')
    .sort((a, b) => {
      const diff = (prioRank[a.priority] || 9) - (prioRank[b.priority] || 9);
      return diff !== 0 ? diff : new Date(a.created_at) - new Date(b.created_at);
    })
    .slice(0, 6)
    .map(enrichReport);

  const ids = new Set(assigned.map((r) => r.id));
  const activity = db.report_updates
    .filter((ru) => ids.has(ru.report_id))
    .sort((a, b) => b.id - a.id)
    .slice(0, 6)
    .map((ru) => {
      const r = db.reports.find((rep) => rep.id === ru.report_id);
      const u = db.users.find((usr) => usr.id === ru.user_id);
      return { ...ru, title: r ? r.title : '', full_name: u ? u.full_name : 'System' };
    });

  res.render('volunteer/dashboard', { c, todo, activity });
});

app.get('/volunteer/assigned_reports.php', requireRole('volunteer'), (req, res) => {
  const uid = req.session.user.id;
  const status = String(req.query.status || '');
  const rows = db.reports
    .filter((r) => r.volunteer_id === uid && (status === '' || r.status === status))
    .sort((a, b) => b.id - a.id)
    .map(enrichReport);
  res.render('volunteer/assigned_reports', { status, rows });
});

app.get('/volunteer/report_details.php', requireRole('volunteer'), (req, res) => {
  const uid = req.session.user.id;
  const id = parseInt(req.query.id || '0', 10);
  const r = fetchReport(id);
  if (!r || r.volunteer_id !== uid) {
    setFlash(req, 'Report not found.', 'error');
    return redirect(res, 'volunteer/assigned_reports.php');
  }
  const open = r.status === 'assigned' || r.status === 'in_progress';
  res.render('volunteer/report_details', { r, open, errors: [] });
});

app.post('/volunteer/report_details.php', requireRole('volunteer'), csrfCheck, async (req, res) => {
  const uid = req.session.user.id;
  const id = parseInt(req.query.id || '0', 10);
  const report = db.reports.find((item) => item.id === id);
  if (!report || report.volunteer_id !== uid) {
    setFlash(req, 'Report not found.', 'error');
    return redirect(res, 'volunteer/assigned_reports.php');
  }
  const open = report.status === 'assigned' || report.status === 'in_progress';
  const errors = [];

  if (open) {
    const status = String(req.body.status || '');
    const remark = String(req.body.remark || '').trim();
    if (status !== 'in_progress' && status !== 'resolved') {
      errors.push('Choose In progress or Resolved.');
    }
    if (status === 'resolved' && remark === '') {
      errors.push('Describe what was done before marking it resolved.');
    }
    if (!errors.length) {
      report.status = status;
      report.updated_at = new Date().toISOString();
      logUpdate(id, uid, status, remark);
      await notify(report.resident_id, id, `${helpers.refNo(id)} is now ${helpers.statusLabel(status)}.`);
      const admins = db.users.filter((u) => u.role === 'admin' && u.status === 'active');
      for (const a of admins) {
        await notify(a.id, id, `${req.session.user.full_name} set ${helpers.refNo(id)} to ${helpers.statusLabel(status)}.`);
      }
      setFlash(req, 'Report updated.');
      return redirect(res, `volunteer/report_details.php?id=${id}`);
    }
  }

  res.render('volunteer/report_details', { r: fetchReport(id), open, errors });
});

/* ==================== ADMIN ROUTES ==================== */

app.get('/admin/dashboard.php', requireRole('admin'), (req, res) => {
  const c = {};
  const prioCounts = { high: 0, medium: 0, low: 0 };
  for (const r of db.reports) {
    c[r.status] = (c[r.status] || 0) + 1;
    if (prioCounts[r.priority] !== undefined) prioCounts[r.priority]++;
  }
  const total = db.reports.length;
  const residents = db.users.filter((u) => u.role === 'resident').length;
  const volunteers = db.users.filter((u) => u.role === 'volunteer' && u.status === 'active').length;

  const nowDate = new Date();
  const startCur = new Date(nowDate.getFullYear(), nowDate.getMonth(), 1);
  const startPrev = new Date(nowDate.getFullYear(), nowDate.getMonth() - 1, 1);
  let cur = 0;
  let prev = 0;
  for (const r of db.reports) {
    const dt = new Date(r.created_at);
    if (dt >= startCur) cur++;
    else if (dt >= startPrev && dt < startCur) prev++;
  }
  let trend;
  if (prev > 0) {
    const diff = Math.round(((cur - prev) / prev) * 100);
    trend = [diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat', `${diff > 0 ? '+' : ''}${diff}% this month`];
  } else if (cur > 0) {
    trend = ['up', `${cur} new this month`];
  } else {
    trend = ['flat', 'No new reports this month'];
  }

  const recent = [...db.reports].sort((a, b) => b.id - a.id).slice(0, 6).map(enrichReport);
  const pending = db.reports
    .filter((r) => r.status === 'pending')
    .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    .slice(0, 8)
    .map(enrichReport);
  const activity = [...db.report_updates]
    .sort((a, b) => b.id - a.id)
    .slice(0, 8)
    .map((ru) => {
      const r = db.reports.find((rep) => rep.id === ru.report_id);
      const u = db.users.find((usr) => usr.id === ru.user_id);
      return { ...ru, title: r ? r.title : '', full_name: u ? u.full_name : 'System' };
    });
  const sos = db.reports
    .filter((r) => r.is_sos === 1 && ['pending', 'assigned', 'in_progress'].includes(r.status))
    .sort((a, b) => b.id - a.id)
    .slice(0, 6)
    .map(enrichReport);

  res.render('admin/dashboard', {
    c,
    total,
    residents,
    volunteers,
    prioCounts,
    trend,
    recent,
    pending,
    activity,
    sos,
  });
});

app.get('/admin/reports.php', requireRole('admin'), (req, res) => {
  const status = String(req.query.status || '');
  const cat = parseInt(req.query.category || '0', 10) || 0;
  const q = String(req.query.q || '').trim();
  const qLower = q.toLowerCase();
  const exporting = req.query.export !== undefined;

  let rows = db.reports.map(enrichReport).filter((r) => {
    if (status && r.status !== status) return false;
    if (cat && r.category_id !== cat) return false;
    if (
      qLower &&
      !r.title.toLowerCase().includes(qLower) &&
      !r.location.toLowerCase().includes(qLower) &&
      !r.resident_name.toLowerCase().includes(qLower)
    ) {
      return false;
    }
    return true;
  });

  rows.sort((a, b) => b.id - a.id);
  if (!exporting) rows = rows.slice(0, 200);

  if (exporting) {
    const today = new Date().toISOString().slice(0, 10);
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', `attachment; filename="cairs-reports-${today}.csv"`);
    const csvCell = (val) => {
      const safe = helpers.csvSafe(val);
      return `"${String(safe).replace(/"/g, '""')}"`;
    };
    const header = ['Reference', 'Title', 'Category', 'Resident', 'Volunteer', 'Location', 'Latitude', 'Longitude', 'Priority', 'Status', 'Filed', 'Last updated'];
    const lines = [header.map(csvCell).join(',')];
    for (const r of rows) {
      lines.push(
        [
          helpers.refNo(r.id),
          r.title,
          r.category,
          r.resident_name,
          r.volunteer_name || '',
          r.location,
          r.latitude ?? '',
          r.longitude ?? '',
          r.priority.charAt(0).toUpperCase() + r.priority.slice(1),
          helpers.statusLabel(r.status),
          r.created_at,
          r.updated_at,
        ]
          .map(csvCell)
          .join(',')
      );
    }
    return res.send('\uFEFF' + lines.join('\r\n'));
  }

  const cats = [...db.categories].sort((a, b) => a.name.localeCompare(b.name));
  const params = new URLSearchParams();
  if (q) params.set('q', q);
  if (status) params.set('status', status);
  if (cat) params.set('category', String(cat));
  params.set('export', '1');

  res.render('admin/reports', {
    exportQuery: params.toString(),
    q,
    status,
    cat,
    cats,
    rows,
  });
});

app.get('/admin/report_details.php', requireRole('admin'), (req, res) => {
  const id = parseInt(req.query.id || '0', 10);
  const r = fetchReport(id);
  if (!r) {
    setFlash(req, 'Report not found.', 'error');
    return redirect(res, 'admin/reports.php');
  }
  const vols = db.users
    .filter((u) => u.role === 'volunteer' && u.status === 'active')
    .sort((a, b) => a.full_name.localeCompare(b.full_name));
  res.render('admin/report_details', {
    r,
    vols,
    statuses: ['pending', 'assigned', 'in_progress', 'resolved', 'rejected'],
    errors: [],
  });
});

app.post('/admin/report_details.php', requireRole('admin'), csrfCheck, async (req, res) => {
  const id = parseInt(req.query.id || '0', 10);
  const report = db.reports.find((item) => item.id === id);
  if (!report) {
    setFlash(req, 'Report not found.', 'error');
    return redirect(res, 'admin/reports.php');
  }
  const vols = db.users
    .filter((u) => u.role === 'volunteer' && u.status === 'active')
    .sort((a, b) => a.full_name.localeCompare(b.full_name));
  const statuses = ['pending', 'assigned', 'in_progress', 'resolved', 'rejected'];
  const errors = [];

  let status = String(req.body.status || '');
  const priority = String(req.body.priority || '');
  const vol = parseInt(req.body.volunteer_id || '0', 10) || null;
  const remark = String(req.body.remark || '').trim();

  if (!statuses.includes(status)) errors.push('Choose a valid status.');
  if (!['low', 'medium', 'high'].includes(priority)) errors.push('Choose a valid priority.');
  const volObj = vol ? vols.find((v) => v.id === vol) : null;
  if (vol && !volObj) errors.push('Choose an active volunteer.');
  if (vol && status === 'pending') status = 'assigned';
  if ((status === 'assigned' || status === 'in_progress') && !vol) {
    errors.push(`Assign a volunteer before marking it ${helpers.statusLabel(status)}.`);
  }
  if (status === 'rejected' && remark === '') {
    errors.push('Give a reason when rejecting a report.');
  }

  if (!errors.length) {
    const prevStatus = report.status;
    const prevVol = report.volunteer_id;
    report.status = status;
    report.priority = priority;
    report.volunteer_id = vol;
    report.updated_at = new Date().toISOString();

    let note = remark;
    const newVol = Boolean(vol && vol !== prevVol);
    if (newVol && volObj) {
      note = `Assigned to ${volObj.full_name}. ${remark}`.trim();
    }
    if (status !== prevStatus || note !== '') {
      logUpdate(id, req.session.user.id, status, note);
    }
    if (status !== prevStatus || newVol) {
      await notify(report.resident_id, id, `${helpers.refNo(id)} is now ${helpers.statusLabel(status)}.`);
    }
    if (newVol) {
      await notify(vol, id, `You were assigned to ${helpers.refNo(id)}: ${report.title}`);
    }
    saveDb();
    setFlash(req, 'Report updated.');
    return redirect(res, `admin/report_details.php?id=${id}`);
  }

  res.render('admin/report_details', { r: fetchReport(id), vols, statuses, errors });
});

app.get('/admin/residents.php', requireRole('admin'), (req, res) => {
  const rows = db.users
    .filter((u) => u.role === 'resident')
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    .map((u) => ({
      ...u,
      total: db.reports.filter((r) => r.resident_id === u.id).length,
    }));
  res.render('admin/residents', { rows });
});

app.post('/admin/residents.php', requireRole('admin'), csrfCheck, (req, res) => {
  const id = parseInt(req.body.id || '0', 10);
  const u = db.users.find((usr) => usr.id === id && usr.role === 'resident');
  if (u) {
    u.status = u.status === 'active' ? 'inactive' : 'active';
    saveDb();
  }
  setFlash(req, 'Resident status changed.');
  redirect(res, 'admin/residents.php');
});

app.get('/admin/volunteers.php', requireRole('admin'), (req, res) => {
  const rows = db.users
    .filter((u) => u.role === 'volunteer')
    .sort((a, b) => a.full_name.localeCompare(b.full_name))
    .map((u) => ({
      ...u,
      active_jobs: db.reports.filter((r) => r.volunteer_id === u.id && ['assigned', 'in_progress'].includes(r.status)).length,
      done: db.reports.filter((r) => r.volunteer_id === u.id && r.status === 'resolved').length,
    }));
  res.render('admin/volunteers', { rows, errors: [], old: { full_name: '', email: '', phone: '' } });
});

app.post('/admin/volunteers.php', requireRole('admin'), csrfCheck, (req, res) => {
  if (req.body.action === 'toggle') {
    const id = parseInt(req.body.id || '0', 10);
    const u = db.users.find((usr) => usr.id === id && usr.role === 'volunteer');
    if (u) {
      u.status = u.status === 'active' ? 'inactive' : 'active';
      saveDb();
    }
    setFlash(req, 'Volunteer status changed.');
    return redirect(res, 'admin/volunteers.php');
  }

  const old = {
    full_name: String(req.body.full_name || '').trim(),
    email: String(req.body.email || '').trim(),
    phone: String(req.body.phone || '').trim(),
  };
  const pass = String(req.body.password || '');
  const errors = [];

  if (!old.full_name) errors.push("Enter the volunteer's name.");
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(old.email)) errors.push('Enter a valid email address.');
  if (pass.length < 6) errors.push('Password must be at least 6 characters.');
  if (!errors.length && db.users.some((u) => u.email.toLowerCase() === old.email.toLowerCase())) {
    errors.push('That email is already registered.');
  }

  if (!errors.length) {
    db.users.push({
      id: db.nextIds.users++,
      full_name: old.full_name,
      email: old.email,
      password: hashPw(pass),
      role: 'volunteer',
      phone: old.phone || null,
      address: null,
      status: 'active',
      created_at: new Date().toISOString(),
    });
    saveDb();
    setFlash(req, 'Volunteer account created.');
    return redirect(res, 'admin/volunteers.php');
  }

  const rows = db.users
    .filter((u) => u.role === 'volunteer')
    .sort((a, b) => a.full_name.localeCompare(b.full_name))
    .map((u) => ({
      ...u,
      active_jobs: db.reports.filter((r) => r.volunteer_id === u.id && ['assigned', 'in_progress'].includes(r.status)).length,
      done: db.reports.filter((r) => r.volunteer_id === u.id && r.status === 'resolved').length,
    }));
  res.render('admin/volunteers', { rows, errors, old });
});

app.get('/admin/announcements.php', requireRole('admin'), (req, res) => {
  const rows = [...db.announcements]
    .sort((a, b) => b.id - a.id)
    .map((a) => {
      const u = db.users.find((usr) => usr.id === a.user_id);
      return { ...a, full_name: u ? u.full_name : 'Admin' };
    });
  res.render('admin/announcements', {
    rows,
    errors: [],
    old: { title: '', body: '', level: 'info' },
    levels: ['info', 'warning', 'critical'],
  });
});

app.post('/admin/announcements.php', requireRole('admin'), csrfCheck, (req, res) => {
  const action = String(req.body.action || '');
  const levels = ['info', 'warning', 'critical'];

  if (action === 'toggle') {
    const id = parseInt(req.body.id || '0', 10);
    const ann = db.announcements.find((a) => a.id === id);
    if (ann) {
      ann.is_active = 1 - ann.is_active;
      saveDb();
    }
    setFlash(req, 'Announcement updated.');
    return redirect(res, 'admin/announcements.php');
  }
  if (action === 'delete') {
    const id = parseInt(req.body.id || '0', 10);
    db.announcements = db.announcements.filter((a) => a.id !== id);
    saveDb();
    setFlash(req, 'Announcement deleted.');
    return redirect(res, 'admin/announcements.php');
  }

  const old = {
    title: String(req.body.title || '').trim(),
    body: String(req.body.body || '').trim(),
    level: String(req.body.level || '').trim(),
  };
  const errors = [];
  if (!old.title) errors.push('Enter a title.');
  if (old.title.length > 150) errors.push('The title is too long (150 characters at most).');
  if (!old.body) errors.push('Write the message.');
  if (old.body.length > 2000) errors.push('The message is too long (2000 characters at most).');
  if (!levels.includes(old.level)) errors.push('Choose a level.');

  if (!errors.length) {
    db.announcements.push({
      id: db.nextIds.announcements++,
      user_id: req.session.user.id,
      title: old.title,
      body: old.body,
      level: old.level,
      is_active: 1,
      created_at: new Date().toISOString(),
    });
    saveDb();
    setFlash(req, 'Announcement posted.');
    return redirect(res, 'admin/announcements.php');
  }

  const rows = [...db.announcements]
    .sort((a, b) => b.id - a.id)
    .map((a) => {
      const u = db.users.find((usr) => usr.id === a.user_id);
      return { ...a, full_name: u ? u.full_name : 'Admin' };
    });
  res.render('admin/announcements', { rows, errors, old, levels });
});

function categoryNameError(name, except = 0) {
  if (!name) return 'Enter a category name.';
  if (name.length > 60) return 'The name is too long (60 characters at most).';
  const dupe = db.categories.find((c) => c.name.toLowerCase() === name.toLowerCase() && c.id !== except);
  return dupe ? 'That category already exists.' : '';
}

app.get('/admin/categories.php', requireRole('admin'), (req, res) => {
  const rows = [...db.categories]
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((c) => ({
      ...c,
      total: db.reports.filter((r) => r.category_id === c.id).length,
    }));
  res.render('admin/categories', { rows });
});

app.post('/admin/categories.php', requireRole('admin'), csrfCheck, (req, res) => {
  const action = String(req.body.action || '');
  const id = parseInt(req.body.id || '0', 10);
  const name = String(req.body.name || '').trim();

  if (action === 'add') {
    const err = categoryNameError(name);
    if (err) setFlash(req, err, 'error');
    else {
      db.categories.push({ id: db.nextIds.categories++, name });
      saveDb();
      setFlash(req, 'Category added.');
    }
  } else if (action === 'rename') {
    const err = categoryNameError(name, id);
    if (err) setFlash(req, err, 'error');
    else {
      const cat = db.categories.find((c) => c.id === id);
      if (cat) {
        cat.name = name;
        saveDb();
      }
      setFlash(req, 'Category renamed.');
    }
  } else if (action === 'delete') {
    const used = db.reports.some((r) => r.category_id === id);
    if (used) setFlash(req, 'This category is used by reports, so it cannot be deleted.', 'error');
    else {
      db.categories = db.categories.filter((c) => c.id !== id);
      saveDb();
      setFlash(req, 'Category deleted.');
    }
  }
  redirect(res, 'admin/categories.php');
});

app.get('/admin/analytics.php', requireRole('admin'), (req, res) => {
  const total = db.reports.length;
  const resolvedRows = db.reports.filter((r) => r.status === 'resolved');
  const resolved = resolvedRows.length;
  let avgHours = null;
  if (resolved > 0) {
    const sumHours = resolvedRows.reduce((acc, r) => {
      return acc + (new Date(r.updated_at).getTime() - new Date(r.created_at).getTime()) / 3600000;
    }, 0);
    avgHours = sumHours / resolved;
  }

  const byStatus = {
    pending: 0,
    assigned: 0,
    in_progress: 0,
    resolved: 0,
    rejected: 0,
  };
  for (const r of db.reports) {
    byStatus[r.status] = (byStatus[r.status] || 0) + 1;
  }

  const catEntries = db.categories
    .map((c) => [c.name, db.reports.filter((r) => r.category_id === c.id).length])
    .sort((a, b) => b[1] - a[1]);
  const byCat = Object.fromEntries(catEntries);

  const nowManilaParts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(new Date());
  const curYear = Number(nowManilaParts.find((p) => p.type === 'year')?.value || new Date().getFullYear());
  const curMonth = Number(nowManilaParts.find((p) => p.type === 'month')?.value || new Date().getMonth() + 1);

  const monthBuckets = new Map();
  for (let offset = 5; offset >= 0; offset--) {
    const d = new Date(Date.UTC(curYear, curMonth - 1 - offset, 15));
    const ym = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: '2-digit',
    }).format(d);
    const shortMon = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Manila',
      month: 'short',
    }).format(d);
    const yr = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
    }).format(d);
    monthBuckets.set(ym, {
      label: shortMon,
      subLabel: yr,
      ym: ym.replace('-', '/'),
      fullLabel: `${shortMon} ${yr}`,
      value: 0,
      resolved: 0,
    });
  }

  for (const r of db.reports) {
    const dt = new Date(r.created_at);
    if (Number.isNaN(dt.getTime())) continue;
    const ym = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: '2-digit',
    }).format(dt);
    const bucket = monthBuckets.get(ym);
    if (bucket) {
      bucket.value += 1;
      if (r.status === 'resolved') bucket.resolved += 1;
    }
  }

  const byMonth = Array.from(monthBuckets.values());

  res.render('admin/analytics', { total, resolved, avgHours, byStatus, byCat, byMonth });
});

app.get('/admin/settings.php', requireRole('admin'), (req, res) => {
  res.render('admin/settings', { errors: [] });
});

app.post('/admin/settings.php', requireRole('admin'), csrfCheck, async (req, res) => {
  const action = String(req.body.action || '');
  const errors = [];

  if (action === 'site') {
    for (const k of ['site_name', 'barangay_name', 'contact_phone', 'map_lat', 'map_lng']) {
      const v = String(req.body[k] ?? '').trim();
      if ((k === 'map_lat' || k === 'map_lng') && (v === '' || Number.isNaN(Number(v)))) continue;
      setSetting(k, v);
    }
    setFlash(req, 'Settings saved.');
    return redirect(res, 'admin/settings.php');
  }

  if (action === 'sms') {
    setSetting('sms_enabled', req.body.sms_enabled ? '1' : '0');
    setSetting('sms_sender', String(req.body.sms_sender || '').trim());
    const apiKey = String(req.body.sms_api_key || '').trim();
    if (apiKey !== '') setSetting('sms_api_key', apiKey);
    setFlash(req, 'SMS settings saved.');
    return redirect(res, 'admin/settings.php');
  }

  if (action === 'sms_test') {
    const me = db.users.find((u) => u.id === req.session.user.id);
    const [ok, msg] = await sendSms(me?.phone || '', `Test message from ${setting('site_name', 'CAIRS')}.`);
    setFlash(req, ok ? 'Test SMS sent to your admin phone number.' : `Test failed: ${msg}`, ok ? 'success' : 'error');
    return redirect(res, 'admin/settings.php');
  }

  if (action === 'hotlines') {
    for (const k of ['emergency_hotline', 'hotline_fire', 'hotline_police', 'hotline_medical']) {
      setSetting(k, String(req.body[k] || '').trim().slice(0, 50));
    }
    setFlash(req, 'Hotlines saved.');
    return redirect(res, 'admin/settings.php');
  }

  if (action === 'password') {
    const me = db.users.find((u) => u.id === req.session.user.id);
    const currentPw = String(req.body.current || '');
    const newPw = String(req.body.new || '');
    if (!verifyPassword(currentPw, me.password)) {
      errors.push('Current password is incorrect.');
    } else if (newPw.length < 6) {
      errors.push('New password must be at least 6 characters.');
    } else {
      me.password = hashPw(newPw);
      saveDb();
      setFlash(req, 'Password changed.');
      return redirect(res, 'admin/settings.php');
    }
  }

  res.render('admin/settings', { errors });
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`CAIRS server listening on http://0.0.0.0:${PORT}`);
});
