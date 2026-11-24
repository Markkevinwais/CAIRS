import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import bcrypt from 'bcryptjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DATA_DIR = process.env.CAIRS_DATA_DIR
  ? path.resolve(process.env.CAIRS_DATA_DIR)
  : path.resolve(__dirname, '..', '.data');
const DB_FILE = path.join(DATA_DIR, 'db.json');

fs.mkdirSync(DATA_DIR, { recursive: true });

function hashPw(plain) {
  return bcrypt.hashSync(plain, 10);
}

export function verifyPassword(plain, hashed) {
  if (!plain || !hashed) return false;
  const normalized = hashed.startsWith('$2y$') ? '$2a$' + hashed.slice(4) : hashed;
  return bcrypt.compareSync(plain, normalized);
}

const now = Date.now();
const hoursAgo = (h) => new Date(now - h * 3600 * 1000).toISOString();
const daysAgo = (d) => new Date(now - d * 86400 * 1000).toISOString();

function createInitialData() {
  return {
    nextIds: {
      users: 4,
      categories: 10,
      reports: 5,
      report_updates: 10,
      notifications: 5,
      announcements: 2,
      login_attempts: 1,
    },

    users: [
      {
        id: 1,
        full_name: 'System Admin',
        email: 'admin@cairs.local',
        password: hashPw('admin123'),
        role: 'admin',
        phone: '0900-000-0001',
        address: 'Barangay Hall',
        status: 'active',
        created_at: daysAgo(30),
      },
      {
        id: 2,
        full_name: 'Vince Volunteer',
        email: 'volunteer@cairs.local',
        password: hashPw('volunteer123'),
        role: 'volunteer',
        phone: '0900-000-0002',
        address: 'Purok 2',
        status: 'active',
        created_at: daysAgo(25),
      },
      {
        id: 3,
        full_name: 'Rita Resident',
        email: 'resident@cairs.local',
        password: hashPw('resident123'),
        role: 'resident',
        phone: '0900-000-0003',
        address: 'Purok 5',
        status: 'active',
        created_at: daysAgo(20),
      },
    ],

    categories: [
      { id: 1, name: 'Fire' },
      { id: 2, name: 'Flood' },
      { id: 3, name: 'Theft / Crime' },
      { id: 4, name: 'Road accident' },
      { id: 5, name: 'Medical emergency' },
      { id: 6, name: 'Noise disturbance' },
      { id: 7, name: 'Garbage / Sanitation' },
      { id: 8, name: 'Broken streetlight / Road' },
      { id: 9, name: 'Other' },
    ],

    settingsEntries: [
      ['site_name', 'CAIRS'],
      ['barangay_name', 'Barangay Sample'],
      ['contact_phone', '0912-345-6789'],
      ['map_lat', '8.4542'],
      ['map_lng', '124.6319'],
      ['sms_enabled', '0'],
      ['sms_api_key', ''],
      ['sms_sender', ''],
      ['emergency_hotline', '911'],
      ['hotline_fire', ''],
      ['hotline_police', ''],
      ['hotline_medical', ''],
    ],

    reports: [
      {
        id: 1,
        resident_id: 3,
        category_id: 8,
        volunteer_id: 2,
        title: 'Broken streetlight on Rizal St.',
        description: 'The streetlamp near the corner store has been flickering and completely dark for two nights, making the intersection unsafe for pedestrians.',
        location: 'Rizal St., Purok 5',
        latitude: 8.4551,
        longitude: 124.6332,
        photo: null,
        priority: 'low',
        status: 'resolved',
        is_sos: 0,
        created_at: daysAgo(5),
        updated_at: daysAgo(4),
      },
      {
        id: 2,
        resident_id: 3,
        category_id: 2,
        volunteer_id: 2,
        title: 'Drainage overflow near covered court',
        description: 'Heavy rain caused water to pool along the sidewalk by the covered court entrance. Drain grates appear clogged with debris.',
        location: 'Barangay Covered Court, Purok 2',
        latitude: 8.4533,
        longitude: 124.6305,
        photo: null,
        priority: 'high',
        status: 'in_progress',
        is_sos: 0,
        created_at: daysAgo(2),
        updated_at: hoursAgo(6),
      },
      {
        id: 3,
        resident_id: 3,
        category_id: 7,
        volunteer_id: 2,
        title: 'Missed garbage collection at Purok 5',
        description: 'Waste bags at the Purok 5 collection point were not picked up during this morning schedule.',
        location: 'Purok 5 Collection Point',
        latitude: 8.4562,
        longitude: 124.6298,
        photo: null,
        priority: 'low',
        status: 'assigned',
        is_sos: 0,
        created_at: hoursAgo(18),
        updated_at: hoursAgo(12),
      },
      {
        id: 4,
        resident_id: 3,
        category_id: 4,
        volunteer_id: null,
        title: 'Fallen tree branch blocking lane',
        description: 'A large acacia branch fell across the northbound lane after last night gusts. Vehicles are forced to swerve.',
        location: 'Mabini Ave., near Elementary School',
        latitude: 8.4521,
        longitude: 124.6346,
        photo: null,
        priority: 'high',
        status: 'pending',
        is_sos: 0,
        created_at: hoursAgo(3),
        updated_at: hoursAgo(3),
      },
    ],

    report_updates: [
      { id: 1, report_id: 1, user_id: 3, status: 'pending', remark: 'Report submitted.', created_at: daysAgo(5) },
      { id: 2, report_id: 1, user_id: 1, status: 'assigned', remark: 'Assigned to Vince Volunteer. Please inspect ballast and wiring.', created_at: daysAgo(5) },
      { id: 3, report_id: 1, user_id: 2, status: 'resolved', remark: 'Replaced faulty LED driver and tested lamp at dusk.', created_at: daysAgo(4) },
      { id: 4, report_id: 2, user_id: 3, status: 'pending', remark: 'Report submitted.', created_at: daysAgo(2) },
      { id: 5, report_id: 2, user_id: 1, status: 'assigned', remark: 'Assigned to Vince Volunteer.', created_at: daysAgo(1) },
      { id: 6, report_id: 2, user_id: 2, status: 'in_progress', remark: 'Clearing blocked storm drain with barangay maintenance crew.', created_at: hoursAgo(6) },
      { id: 7, report_id: 3, user_id: 3, status: 'pending', remark: 'Report submitted.', created_at: hoursAgo(18) },
      { id: 8, report_id: 3, user_id: 1, status: 'assigned', remark: 'Assigned to Vince Volunteer.', created_at: hoursAgo(12) },
      { id: 9, report_id: 4, user_id: 3, status: 'pending', remark: 'Report submitted.', created_at: hoursAgo(3) },
    ],

    notifications: [
      { id: 1, user_id: 3, report_id: 2, message: 'CAIRS-00002 is now In Progress.', is_read: 0, created_at: hoursAgo(6) },
      { id: 2, user_id: 3, report_id: 3, message: 'CAIRS-00003 is now Assigned.', is_read: 0, created_at: hoursAgo(12) },
      { id: 3, user_id: 2, report_id: 3, message: 'You were assigned to CAIRS-00003: Missed garbage collection at Purok 5', is_read: 0, created_at: hoursAgo(12) },
      { id: 4, user_id: 1, report_id: 4, message: 'New report CAIRS-00004: Fallen tree branch blocking lane', is_read: 0, created_at: hoursAgo(3) },
    ],

    announcements: [
      {
        id: 1,
        user_id: 1,
        title: 'Barangay Clean-Up & Drainage Clearing',
        body: 'Scheduled clearing of main storm drains this Saturday from 7:00 AM to 11:00 AM. Please keep roadside gutters clear of parked vehicles.',
        level: 'info',
        is_active: 1,
        created_at: daysAgo(2),
      },
    ],
  };
}

function loadStore() {
  const seed = createInitialData();
  let parsed = null;
  if (fs.existsSync(DB_FILE)) {
    try {
      parsed = JSON.parse(fs.readFileSync(DB_FILE, 'utf8'));
    } catch {
      parsed = null;
    }
  }
  const src = parsed && Array.isArray(parsed.users) ? parsed : seed;
  return {
    nextIds: src.nextIds || seed.nextIds,
    users: src.users || seed.users,
    categories: src.categories || seed.categories,
    settings: new Map(Array.isArray(src.settingsEntries) ? src.settingsEntries : seed.settingsEntries),
    reports: src.reports || seed.reports,
    report_updates: src.report_updates || seed.report_updates,
    notifications: src.notifications || seed.notifications,
    announcements: src.announcements || seed.announcements,
    login_attempts: [],
  };
}

export const db = loadStore();

export function saveDb() {
  const payload = {
    nextIds: db.nextIds,
    users: db.users,
    categories: db.categories,
    settingsEntries: Array.from(db.settings.entries()),
    reports: db.reports,
    report_updates: db.report_updates,
    notifications: db.notifications,
    announcements: db.announcements,
  };
  const tmpFile = DB_FILE + '.tmp';
  fs.writeFileSync(tmpFile, JSON.stringify(payload, null, 2), 'utf8');
  fs.renameSync(tmpFile, DB_FILE);
}

if (!fs.existsSync(DB_FILE)) {
  saveDb();
}

export function setting(key, fallback = '') {
  if (key === 'sms_api_key' && process.env.SEMAPHORE_API_KEY) {
    return process.env.SEMAPHORE_API_KEY;
  }
  return db.settings.has(key) ? db.settings.get(key) : fallback;
}

export function setSetting(key, value) {
  db.settings.set(key, String(value ?? ''));
  saveDb();
}

export function fetchReport(id) {
  const r = db.reports.find((item) => item.id === Number(id));
  if (!r) return null;
  const cat = db.categories.find((c) => c.id === r.category_id);
  const resident = db.users.find((u) => u.id === r.resident_id);
  const volunteer = r.volunteer_id ? db.users.find((u) => u.id === r.volunteer_id) : null;
  return {
    ...r,
    category: cat ? cat.name : 'Other',
    resident_name: resident ? resident.full_name : 'Unknown',
    resident_phone: resident ? resident.phone || '' : '',
    volunteer_name: volunteer ? volunteer.full_name : null,
  };
}

export function enrichReport(r) {
  const cat = db.categories.find((c) => c.id === r.category_id);
  const resident = db.users.find((u) => u.id === r.resident_id);
  const volunteer = r.volunteer_id ? db.users.find((u) => u.id === r.volunteer_id) : null;
  return {
    ...r,
    category: cat ? cat.name : 'Other',
    resident_name: resident ? resident.full_name : 'Unknown',
    resident_phone: resident ? resident.phone || '' : '',
    volunteer_name: volunteer ? volunteer.full_name : null,
  };
}

export function logUpdate(reportId, userId, status, remark) {
  db.report_updates.push({
    id: db.nextIds.report_updates++,
    report_id: Number(reportId),
    user_id: Number(userId),
    status,
    remark: remark || null,
    created_at: new Date().toISOString(),
  });
  saveDb();
}

export async function sendSms(phone, message) {
  const key = setting('sms_api_key', '');
  if (!key) return [false, 'No API key saved in Settings.'];
  const number = String(phone || '').replace(/[^0-9+]/g, '');
  if (number.length < 10) return [false, 'Invalid phone number.'];

  const params = new URLSearchParams({ apikey: key, number, message });
  const sender = setting('sms_sender', '');
  if (sender) params.set('sendername', sender);

  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 6000);
    const resp = await fetch('https://api.semaphore.co/api/v4/messages', {
      method: 'POST',
      body: params,
      signal: controller.signal,
    });
    clearTimeout(timeout);
    const text = await resp.text();
    if (resp.ok) return [true, 'Sent.'];
    return [false, `SMS service replied ${resp.status}: ${text.slice(0, 150)}`];
  } catch (err) {
    return [false, 'Could not reach the SMS service: ' + (err?.message || String(err))];
  }
}

export async function notify(userId, reportId, message) {
  db.notifications.push({
    id: db.nextIds.notifications++,
    user_id: Number(userId),
    report_id: reportId ? Number(reportId) : null,
    message,
    is_read: 0,
    created_at: new Date().toISOString(),
  });
  saveDb();

  if (setting('sms_enabled', '0') === '1') {
    const user = db.users.find((u) => u.id === Number(userId));
    if (user && (user.role === 'resident' || user.role === 'volunteer') && user.phone) {
      await sendSms(user.phone, `[${setting('site_name', 'CAIRS')}] ${message}`);
    }
  }
}

export function loginLocked(email, ip) {
  const cutoff = Date.now() - 10 * 60 * 1000;
  const normalized = String(email || '').toLowerCase();
  const count = db.login_attempts.filter(
    (a) => a.created_at > cutoff && (a.email === normalized || (ip && a.ip === ip))
  ).length;
  return count >= 5;
}

export function loginFailed(email, ip) {
  const nowMs = Date.now();
  const oneDayAgo = nowMs - 86400 * 1000;
  db.login_attempts = db.login_attempts.filter((a) => a.created_at >= oneDayAgo);
  db.login_attempts.push({
    id: db.nextIds.login_attempts++,
    email: String(email || '').toLowerCase(),
    ip: ip || '',
    created_at: nowMs,
  });
}

export function loginClear(email) {
  const normalized = String(email || '').toLowerCase();
  db.login_attempts = db.login_attempts.filter((a) => a.email !== normalized);
}

export { hashPw };
