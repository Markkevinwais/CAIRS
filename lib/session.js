import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DATA_DIR = process.env.CAIRS_DATA_DIR
  ? path.resolve(process.env.CAIRS_DATA_DIR)
  : path.resolve(__dirname, '..', '.data');
const SESSIONS_FILE = path.join(DATA_DIR, 'sessions.json');
const COOKIE_NAME = 'cairs_sid';
const SESSION_TTL_MS = 30 * 24 * 60 * 60 * 1000; // 30 days sliding expiration

fs.mkdirSync(DATA_DIR, { recursive: true });

function loadSessions() {
  if (!fs.existsSync(SESSIONS_FILE)) return new Map();
  try {
    const raw = JSON.parse(fs.readFileSync(SESSIONS_FILE, 'utf8'));
    const now = Date.now();
    const map = new Map();
    for (const [sid, entry] of Object.entries(raw || {})) {
      if (entry && entry.expiresAt > now) {
        map.set(sid, entry);
      }
    }
    return map;
  } catch {
    return new Map();
  }
}

const sessions = loadSessions();

function persistSessions() {
  const now = Date.now();
  const obj = {};
  for (const [sid, entry] of sessions.entries()) {
    if (entry.expiresAt > now) {
      obj[sid] = entry;
    } else {
      sessions.delete(sid);
    }
  }
  const tmpFile = SESSIONS_FILE + '.tmp';
  fs.writeFileSync(tmpFile, JSON.stringify(obj), 'utf8');
  fs.renameSync(tmpFile, SESSIONS_FILE);
}

function extractSessionCookieCandidates(header) {
  const out = [];
  if (!header) return out;
  for (const part of String(header).split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    const k = part.slice(0, idx).trim();
    if (k !== COOKIE_NAME) continue;
    const v = part.slice(idx + 1).trim();
    try {
      out.push(decodeURIComponent(v));
    } catch {
      out.push(v);
    }
  }
  return out;
}

function isLocalHttpHost(host) {
  const clean = String(host || '').toLowerCase().split(':')[0];
  return clean === '127.0.0.1' || clean === 'localhost' || clean === '[::1]';
}

function detectHttps(req, entry) {
  if (entry && entry.cookieSecure) return true;
  const fwdProto = String(req.headers['x-forwarded-proto'] || '').split(',')[0].trim().toLowerCase();
  const host = String(req.headers['x-forwarded-host'] || req.headers.host || '').toLowerCase();
  const origin = String(req.headers.origin || req.headers.referer || '').toLowerCase();
  const secSite = String(req.headers['sec-fetch-site'] || '').toLowerCase();
  const secDest = String(req.headers['sec-fetch-dest'] || '').toLowerCase();

  return (
    Boolean(req.secure) ||
    fwdProto === 'https' ||
    origin.startsWith('https://') ||
    host.endsWith('.run.app') ||
    secSite === 'cross-site' ||
    secDest === 'iframe' ||
    (host !== '' && !isLocalHttpHost(host))
  );
}

function serializeSessionCookie(sid, isHttps, maxAgeMs = SESSION_TTL_MS) {
  const parts = [
    `${COOKIE_NAME}=${encodeURIComponent(sid)}`,
    'Path=/',
    'HttpOnly',
    `Max-Age=${Math.floor(maxAgeMs / 1000)}`,
  ];
  if (isHttps) {
    parts.push('Secure', 'SameSite=None', 'Partitioned');
  } else {
    parts.push('SameSite=Lax');
  }
  return parts.join('; ');
}

function createNewSessionData(isHttps) {
  return {
    csrf: crypto.randomBytes(16).toString('hex'),
    expiresAt: Date.now() + SESSION_TTL_MS,
    cookieSecure: Boolean(isHttps),
  };
}

function attachSessionMethods(req, res, entry, isHttps) {
  Object.defineProperties(entry, {
    regenerate: {
      configurable: true,
      writable: true,
      enumerable: false,
      value: (cb) => {
        const secureFlag = Boolean(entry.cookieSecure || isHttps);
        sessions.delete(req.sessionID);
        const newSid = crypto.randomBytes(32).toString('hex');
        const fresh = createNewSessionData(secureFlag);
        fresh.lastPersistedAt = Date.now();
        fresh.lastCookieAt = Date.now();
        sessions.set(newSid, fresh);
        req.sessionID = newSid;
        req.session = fresh;
        attachSessionMethods(req, res, fresh, secureFlag);
        res.setHeader('Set-Cookie', serializeSessionCookie(newSid, secureFlag));
        persistSessions();
        if (cb) cb(null);
      },
    },
    save: {
      configurable: true,
      writable: true,
      enumerable: false,
      value: (cb) => {
        const secureFlag = Boolean(req.session.cookieSecure || isHttps);
        req.session.cookieSecure = secureFlag;
        req.session.expiresAt = Date.now() + SESSION_TTL_MS;
        req.session.lastPersistedAt = Date.now();
        req.session.lastCookieAt = Date.now();
        sessions.set(req.sessionID, req.session);
        res.setHeader('Set-Cookie', serializeSessionCookie(req.sessionID, secureFlag));
        persistSessions();
        if (cb) cb(null);
      },
    },
    destroy: {
      configurable: true,
      writable: true,
      enumerable: false,
      value: (cb) => {
        const secureFlag = Boolean(req.session.cookieSecure || isHttps);
        sessions.delete(req.sessionID);
        res.setHeader('Set-Cookie', serializeSessionCookie('', secureFlag, 0));
        persistSessions();
        if (cb) cb(null);
      },
    },
  });
}

function sessionDataSnapshot(entry) {
  if (!entry) return '';
  return JSON.stringify({
    csrf: entry.csrf || null,
    user: entry.user || null,
    flash: entry.flash || null,
    cookieSecure: Boolean(entry.cookieSecure),
  });
}

export function sessionMiddleware(req, res, next) {
  const candidates = extractSessionCookieCandidates(req.headers.cookie);
  const now = Date.now();
  let sid = null;
  let entry = null;

  for (const cand of candidates) {
    if (!/^[a-f0-9]{64}$/.test(cand)) continue;
    const found = sessions.get(cand);
    if (found && found.expiresAt > now) {
      if (!entry || (!entry.user && found.user)) {
        sid = cand;
        entry = found;
      }
    }
  }

  const isHttps = detectHttps(req, entry);
  let isNew = false;

  if (!entry) {
    sid = crypto.randomBytes(32).toString('hex');
    entry = createNewSessionData(isHttps);
    entry.lastPersistedAt = 0;
    entry.lastCookieAt = now;
    sessions.set(sid, entry);
    isNew = true;
  } else {
    entry.expiresAt = now + SESSION_TTL_MS;
    if (isHttps) entry.cookieSecure = true;
  }

  const beforeSnap = sessionDataSnapshot(entry);
  req.sessionID = sid;
  req.session = entry;
  attachSessionMethods(req, res, entry, isHttps);

  // Set cookie when newly issued or refresh sliding cookie max-age hourly
  if (isNew || now - (entry.lastCookieAt || 0) > 60 * 60 * 1000) {
    entry.lastCookieAt = now;
    res.setHeader('Set-Cookie', serializeSessionCookie(req.sessionID, isHttps));
  }

  const origEnd = res.end.bind(res);
  res.end = function (...args) {
    if (req.session && sessions.has(req.sessionID)) {
      const endNow = Date.now();
      const afterSnap = sessionDataSnapshot(req.session);
      const needsRefresh = endNow - (req.session.lastPersistedAt || 0) > 60 * 1000;
      if (isNew || afterSnap !== beforeSnap || needsRefresh) {
        req.session.lastPersistedAt = endNow;
        persistSessions();
      }
    }
    return origEnd(...args);
  };

  next();
}
