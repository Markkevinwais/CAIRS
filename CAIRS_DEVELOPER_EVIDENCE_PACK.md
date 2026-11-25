# CAIRS Developer Documentation & Testing Evidence Pack

**System:** Community Assistance and Incident Reporting System (CAIRS)  
**Source Stack:** PHP 8.1+ (PDO, strict types) + MySQL/MariaDB + Plain CSS & Vanilla JS (no build step required in Apache/Laragon/XAMPP)

> **Important integrity rule:** Technical confirmations in **Sections 5 and 6** below are verified directly from the CAIRS PHP/SQL source code. Screenshot captures (**Section 1**) and runtime test results (**Sections 2–4**) must be executed on your local running **Apache + PHP + MySQL** environment (e.g., Laragon or XAMPP) using the test/demo accounts before filling in **Actual Result**, **Pass/Fail**, **Date Tested**, and screenshot filenames.

---

## 1. Actual PHP Application Screenshots Checklist

Run CAIRS on local Apache + MySQL (`http://localhost/...` or `http://cairs.test`), sign in with the seeded demonstration accounts below (no real resident PII), and capture full-page or clean 1280×800+ desktop views (plus optional mobile checks) without cropping UI controls:

- **Demo Admin:** `admin@cairs.local` / `admin123`
- **Demo Volunteer:** `volunteer@cairs.local` / `volunteer123`
- **Demo Resident:** `resident@cairs.local` / `resident123`

| No. | Screenshot Needed | Exact PHP Route / State to Capture | Suggested Filename | Status |
| --- | --- | --- | --- | --- |
| 1 | Login page | `/login.php` (logged out) | `01-login-page.png` | `[ ] Pending local capture` |
| 2 | Administrator dashboard | `/admin/dashboard.php` (logged in as Admin; include Active SOS board if an SOS exists, KPI cards, pending queue, recent activity) | `02-admin-dashboard.png` | `[ ] Pending local capture` |
| 3 | Resident dashboard | `/resident/dashboard.php` (logged in as Resident; showing active announcements, KPI cards, recent reports, hotlines) | `03-resident-dashboard.png` | `[ ] Pending local capture` |
| 4 | Volunteer dashboard | `/volunteer/dashboard.php` (logged in as Volunteer; showing assignment KPIs, priority task queue, recent activity) | `04-volunteer-dashboard.png` | `[ ] Pending local capture` |
| 5 | Incident reporting page | `/resident/report.php` (category selector, description, Leaflet map pin picker, file upload input) | `05-incident-reporting.png` | `[ ] Pending local capture` |
| 6 | Incident tracking page | `/resident/my_reports.php` and/or `/resident/report_details.php?id=<id>` (showing status badge, assignee, and chronological timeline history) | `06-incident-tracking.png` | `[ ] Pending local capture` |
| 7 | Dispatch and assignment page | `/admin/dispatch.php` (Pin & Dispatch map + form) and `/admin/report_details.php?id=<id>` (Manage panel assigning volunteer & priority) | `07-dispatch-assignment.png` | `[ ] Pending local capture` |
| 8 | Analytics dashboard | `/admin/analytics.php` (summary metrics, category/status bars, 6-month column chart) | `08-analytics-dashboard.png` | `[ ] Pending local capture` |
| 9 | Map interface | `/admin/analytics.php#incident-map` (all pinned incident markers with status legend & popups) | `09-incident-map.png` | `[ ] Pending local capture` |
| 10 | Notifications and announcements | Open topbar notification dropdown (`api/notifications.php`) + `/admin/announcements.php` (live composer & announcement list) | `10-notifications-announcements.png` | `[ ] Pending local capture` |

---

## 2. Functional Testing Evidence Workbook (Features 1–12)

Execute each test case against the running PHP/MySQL instance and fill in the bracketed fields (`[Date]`, `[Actual Result]`, `[Pass/Fail]`, `[Screenshot]`, `[Defects/Retest]`).

| ID | Feature | Preconditions | Steps Performed | Expected Result (Implemented in PHP) | Actual Result | Pass/Fail | Date | Evidence | Defects / Retest |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **FT-01A** | User authentication (Valid login & logout) | Demo resident account exists and is `active`. | 1. Open `/login.php`.<br>2. Enter `resident@cairs.local` / `resident123` and submit.<br>3. Click Logout (`/logout.php`). | Redirects to `/resident/dashboard.php`, regenerates session ID; logout clears `$_SESSION['user']` and redirects to `/login.php` with flash `"You have been logged out."` | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-01a-auth.png` | `[None / Note defect]` |
| **FT-01B** | User authentication (Invalid password & lockout) | Account `resident@cairs.local` active; fewer than 5 failed attempts in last 10 min. | 1. Submit wrong password 5 times.<br>2. Submit valid or invalid password a 6th time within 10 min. | First 5 attempts log into `login_attempts` and display `"Email or password is incorrect."`; 6th triggers `loginLocked()` displaying `"Too many failed attempts. Please wait 10 minutes and try again."` | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-01b-lockout.png` | `[None / Note defect]` |
| **FT-02** | Role-based access | Logged in separately as Resident, Volunteer, and Admin (and tested unauthenticated). | 1. While logged out, open `/admin/dashboard.php`.<br>2. While logged in as Resident, navigate directly to `/admin/dashboard.php` and `/volunteer/dashboard.php`.<br>3. Repeat with Volunteer -> `/admin/settings.php`. | Unauthenticated user redirects to `/login.php`; authenticated cross-role access redirects immediately via `requireRole()` to `homeFor($role)`. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-02-rbac.png` | `[None / Note defect]` |
| **FT-03A** | Incident reporting (Valid submission) | Logged in as Resident; categories seeded; `assets/uploads/` writable. | 1. Open `/resident/report.php`.<br>2. Choose category, enter title, >=10-char description, location, click map to pin coords, attach JPG/PNG/WEBP <=3 MB.<br>3. Submit. | Inserts row in `reports` (`status='pending'`, `is_sos=0`, category priority via `defaultPriority()`), inserts `"Report submitted by the resident."` in `report_updates`, notifies active admins, redirects to `report_details.php`. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-03a-report-ok.png` | `[None / Note defect]` |
| **FT-03B** | Incident reporting (Invalid input / file upload) | Logged in as Resident on `/resident/report.php`. | 1. Submit description `< 10` chars or empty title.<br>2. Submit non-image file (e.g. `.txt`/`.php`/`.pdf`) or image `> 3 MB`. | Validation blocks insert; shows exact flash/inline errors (`"Describe the incident in at least 10 characters."`, `"Photo must be a JPG, PNG or WEBP image."`, `"Photo must be 3 MB or smaller."`). | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-03b-report-invalid.png` | `[None / Note defect]` |
| **FT-04** | Incident tracking | Resident has filed report; Admin/Volunteer updates status. | 1. Open `/resident/my_reports.php`, filter by status.<br>2. Click `View` -> `/resident/report_details.php?id=<id>`.<br>3. After Admin/Volunteer update, refresh page. | Displays matching filtered rows; detail view shows status badge, priority, assigned volunteer, pinned map, and full reverse-chronological `report_updates` history (`renderTimeline()`). | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-04-tracking.png` | `[None / Note defect]` |
| **FT-05A** | Dispatch (Assign & reassign existing report) | Logged in as Admin; active Volunteer exists; a `pending` report exists. | 1. Open `/admin/report_details.php?id=<id>`.<br>2. Select active Volunteer and Priority, submit.<br>3. Reassign to another volunteer or update status (`in_progress` / `resolved`). | When volunteer chosen on `pending` report, status updates to `assigned`; logs `"Assigned to <name>..."` in `report_updates`; notifies Resident and newly assigned Volunteer. Marking `assigned`/`in_progress` without a volunteer fails validation. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-05a-assign.png` | `[None / Note defect]` |
| **FT-05B** | Dispatch (Direct Pin & Dispatch) | Logged in as Admin on `/admin/dispatch.php`. | 1. Choose active Volunteer, category, title, priority.<br>2. Click map to place pin.<br>3. Submit `Dispatch volunteer`. | Creates `reports` record directly with `status='assigned'`, `volunteer_id` set, `report_updates` entry, and volunteer notification. Missing pin displays `"Click on the map to place a pin for the volunteer."` | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-05b-pin-dispatch.png` | `[None / Note defect]` |
| **FT-06** | Report rejection & required remarks | Logged in as Admin on `/admin/report_details.php?id=<id>`. | 1. Set status to `Rejected` with blank `remark` and submit.<br>2. Set status to `Rejected` with non-empty reason and submit. | Empty remark is rejected with `"Give a reason when rejecting a report."` Non-empty remark updates status to `rejected`, records reason in `report_updates`, and notifies Resident. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-06-reject.png` | `[None / Note defect]` |
| **FT-07A** | SOS (Send emergency alert) | Logged in as Resident on `/resident/sos.php` (categories exist). | 1. Click an emergency tile (e.g., `Fire`).<br>2. Confirm via `Send now` (or wait 5-second countdown). | Creates `reports` row with `is_sos=1`, `priority='high'`, `status='pending'`, GPS coordinates (or fallback home address if geolocation blocked), logs `"SOS alert sent by the resident."`, notifies all active Admins & Volunteers. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-07a-sos.png` | `[None / Note defect]` |
| **FT-07B** | SOS (Cancel countdown & duplicate guard) | Same Resident on `/resident/sos.php`. | 1. Click a tile and click `Cancel` before 5s finishes.<br>2. Trigger one SOS, then attempt another SOS within 2 minutes. | Cancelling stops the timer with no report inserted. Sending a second SOS within 2 minutes triggers `INTERVAL 2 MINUTE` duplicate check and redirects to the existing SOS report with `"Your SOS was already sent a moment ago. Help is on the way."` | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-07b-sos-guard.png` | `[None / Note defect]` |
| **FT-08** | Notifications | Logged in as Resident/Admin/Volunteer with unread notifications. | 1. Open topbar notification bell.<br>2. Click an item / `Mark all read`.<br>3. Click `Clear read` / `Clear all`. | Calls `api/notifications.php`, `api/notifications_read.php`, `api/notifications_clear.php`; unread badge updates dynamically (`api/poll.php` polls unread count & active SOS alerts for Admins). | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-08-notifications.png` | `[None / Note defect]` |
| **FT-09** | Announcements | Logged in as Admin on `/admin/announcements.php`. | 1. Create announcement (`title`, `body`, `level`: info/warning/critical).<br>2. Check `/index.php`, `/resident/dashboard.php`, `/volunteer/dashboard.php`.<br>3. Toggle visibility (`is_active`) and delete. | Active announcements appear on landing page (up to 2) and resident/volunteer dashboards; toggling `is_active` hides/shows immediately; deletion removes from DB. | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-09-announcements.png` | `[None / Note defect]` |
| **FT-10** | Analytics calculations | Known counts of reports across statuses/categories/months in DB; Admin on `/admin/analytics.php`. | 1. Record exact SQL counts from `reports`.<br>2. Compare against `Total reports`, `Resolution rate`, `Avg. hours to resolve`, category/status bars, and 6-month chart. | `Resolution rate = round((resolved / total) * 100)%`; `Avg. hours to resolve = mean((updated_at - created_at) / 3600)` over resolved rows (`-` when 0 resolved); bars & 6-month buckets (`Asia/Manila`) match DB totals. | `[Record SQL counts vs UI values]` | `[ ]` | `[YYYY-MM-DD]` | `ft-10-analytics.png` | `[None / Note defect]` |
| **FT-11** | CSV export | Reports exist (optionally including one starting with `=`, `+`, `-`, or `@` in title to verify `csvSafe`). | 1. On `/admin/reports.php`, optionally apply a filter.<br>2. Click `Download Excel (CSV)`.<br>3. Inspect downloaded `cairs-reports-YYYY-MM-DD.csv`. | Downloads UTF-8 BOM CSV with 12 columns (`Reference`, `Title`, `Category`, `Resident`, `Volunteer`, `Location`, `Latitude`, `Longitude`, `Priority`, `Status`, `Filed`, `Last updated`); values starting with `= + - @ \t \r` are prefixed with `'` (`csvSafe()`). | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-11-csv-export.png` | `[None / Note defect]` |
| **FT-12** | Map interface | Leaflet CDN reachable; report with pinned lat/lng exists. | 1. Pin location on `/resident/report.php` and save.<br>2. Open `/admin/analytics.php#incident-map`.<br>3. Update map center in `/admin/settings.php` (verify `-90..90`, `-180..180` bounds). | Map picker records numeric coordinates; invalid/partial coords rejected; `Incident map` renders status-colored markers linking to `report_details.php`; Settings rejects out-of-range coords (`abs(lat)>90` or `abs(lng)>180`). | `[Record local observation]` | `[ ]` | `[YYYY-MM-DD]` | `ft-12-map.png` | `[None / Note defect]` |

---

## 3. Integration & Security Testing Evidence Workbook

Verify these 8 specific scenarios on your running local PHP + MySQL stack and fill in actual observed HTTP responses/DB state:

| Area | What Code Implements (Verified in Source) | Verification Steps to Execute Locally | Observed Result (`[Fill after local test]`) | Status |
| --- | --- | --- | --- | --- |
| 1. Reporting ↔ Dispatch communication | `resident/report.php` & `resident/sos.php` write to `reports`; `admin/dashboard.php`, `admin/reports.php`, `admin/report_details.php`, and `admin/dispatch.php` read/update the same `reports` row (`volunteer_id`, `status`, `priority`). | Submit report as Resident -> verify appears on Admin `Pending` queue -> assign Volunteer -> verify appears in Volunteer `Assigned reports` (`volunteer/assigned_reports.php`). | `[Record report ID, assigned volunteer, and status transitions observed]` | `[ ]` |
| 2. Status-history updates (`report_updates`) | `logUpdate($reportId, $userId, $status, $remark)` inserts rows on submission, admin assignment/status/remark, volunteer progress/resolve, and dispatch; rendered via `renderTimeline()`. | Track one report from `pending` -> `assigned` -> `in_progress` -> `resolved` (and another -> `rejected`). Inspect `report_updates` rows & UI timeline order. | `[Record number of timeline entries, actors, timestamps, and remarks]` | `[ ]` |
| 3. Role restrictions & unauthorized access | `requireRole('admin'|'resident'|'volunteer')` calls `syncActiveUser()` (verifies live DB user & `status === 'active'`), redirects logged-out requests to `login.php` and wrong roles to `homeFor($role)`. `volunteer/report_details.php` also checks `(int) $r['volunteer_id'] === $uid`, and `resident/report_details.php` checks `(int) $r['resident_id'] === $uid`. | 1. Access admin URLs as Resident/Volunteer.<br>2. Access another resident's `?id=` as Resident, or unassigned `?id=` as Volunteer.<br>3. Set user `status = 'inactive'` and refresh an active session. | `[Record HTTP redirects / flash error messages / forced logout]` | `[ ]` |
| 4. Session & authentication handling | `config/database.php` enables `httponly => true`, `samesite => 'Lax'` (or `'None'` + `secure` on HTTPS); `login.php` runs `session_regenerate_id(true)` on login; `logout.php` unsets user & regenerates ID; `loginLocked()` throttles 5 failed logins per email or IP for 10 min. | Inspect `PHPSESSID` cookie flags in Browser DevTools; confirm `PHPSESSID` changes after login and logout; confirm inactive accounts cannot log in (`"This account is inactive..."`). | `[Record cookie attributes and regenerated PHPSESSID confirmation]` | `[ ]` |
| 5. CSRF protection on forms & APIs | All state-changing PHP forms call `csrfCheckOrDie()` validating `$_POST['csrf']` via `hash_equals()` (returning HTTP `419` on mismatch). JSON APIs (`api/notifications_read.php`, `api/notifications_clear.php`, `api/profile.php` POST) call `apiCsrfCheck()` verifying `X-CSRF-Token` and `X-Requested-With: fetch`. | Tamper with hidden `csrf` input in browser DevTools and submit a form (e.g., `admin/settings.php` or `resident/report.php`); send POST to `api/notifications_read.php` without `X-CSRF-Token`. | `[Record HTTP 419 response and zero DB mutation]` | `[ ]` |
| 6. Input validation & file-upload restrictions | PDO prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`) used for queries; output escaped via `e()` (`htmlspecialchars`); upload validates `<= 3 MB`, checks real MIME via `finfo(FILEINFO_MIME_TYPE)` allowing only `jpg/png/webp`, generates random hex filename via `random_bytes(8)`. | Attempt SQL injection payload in search/title/login; attempt `<script>` XSS payload in title/description; upload renamed `.php` or `>3 MB` file to `/resident/report.php`. | `[Record sanitized HTML output, prepared-statement safety, and upload rejection message]` | `[ ]` |
| 7. Duplicate or repeated SOS handling | `resident/sos.php` queries `reports` for `resident_id = ? AND is_sos = 1 AND created_at > (NOW() - INTERVAL 2 MINUTE)` and redirects to existing report without inserting a second row. | Trigger an SOS report, immediately navigate back to `/resident/sos.php`, trigger a second SOS within 2 minutes, and inspect `reports` table count. | `[Record redirect to existing report ID and DB row count unchanged]` | `[ ]` |
| 8. Notification generation & retrieval | `notify()` inserts per-user rows into `notifications` (`is_read = 0`); `api/poll.php` returns unread count (+ active SOS alerts for Admins); `api/notifications.php`, `_read.php`, `_clear.php` filter strictly by authenticated `$_SESSION['user']['id']`. | Trigger SOS / assignment / status change -> inspect `api/poll.php` & `api/notifications.php` JSON -> mark single/all read -> attempt `notifications_read.php` with another user's notification `id`. | `[Record unread count transitions and 404/ownership enforcement]` | `[ ]` |

---

## 4. Performance Testing Evidence Template (Optional — Only Include if Run Locally)

> Do **not** include performance claims in the thesis unless you actually execute these measurements on your local test machine.

- **Test Environment Specs:** `[CPU, RAM, OS, Storage]`
- **Software Versions:** PHP `[run: php -v]`, MySQL/MariaDB `[run: mysql --version]`, Web Server `[Laragon Apache / XAMPP Apache version]`
- **Dataset Size at Test Time:** `[e.g., X users, Y reports, Z notifications]`
- **Measurement Procedure & Repetitions:** `[e.g., Chrome DevTools Network tab (Disable Cache) / PowerShell Measure-Command / ApacheBench, N = 5 or 10 repetitions per endpoint]`

| Endpoint / Action | Run 1 (ms) | Run 2 (ms) | Run 3 (ms) | Run 4 (ms) | Run 5 (ms) | Mean (ms) | Calculation Formula |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `/login.php` (GET) | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `(R1+R2+R3+R4+R5)/5` |
| `/admin/dashboard.php` (GET) | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `(R1+R2+R3+R4+R5)/5` |
| `/resident/report.php` (POST) | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `(R1+R2+R3+R4+R5)/5` |
| `/admin/analytics.php` (GET) | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `(R1+R2+R3+R4+R5)/5` |
| `/api/poll.php` (GET JSON) | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `[ ]` | `(R1+R2+R3+R4+R5)/5` |

---

## 5. Technical Information & Confirmation (Developer Summary)

1. **PHP Version Used:** Requires **PHP 8.1+** (`declare(strict_types=1)`, `str_starts_with`, `str_contains`, `never` return type). Local exact version used during testing: `[run php -v in Laragon/XAMPP]`.
2. **MySQL / MariaDB Version Used:** Designed for MySQL 8+ / MariaDB 10.4+ (`utf8mb4_unicode_ci`, `InnoDB`). Local exact version: `[run mysql --version]`.
3. **Web Server & Deployment Environment:** Standard Apache HTTP Server (Laragon or XAMPP on Windows, or Apache/PHP hosting with mod_php/PHP-FPM). Auto-detects `BASE_URL` from `DOCUMENT_ROOT`; auto-provisions `cairs` database, tables, defaults, and demo accounts via `config/database.php` on first load.
4. **Database Name & Final SQL Schema:** Database **`cairs`** (`database/cairs.sql`), containing 8 tables:
   - `users` (`id`, `full_name`, `email`, `password`, `role`, `phone`, `address`, `status`, `avatar_type`, `avatar_style`, `avatar_shape`, `avatar_url`, `created_at`)
   - `categories` (`id`, `name`)
   - `settings` (**`k`** `VARCHAR(64) PRIMARY KEY`, **`v`** `TEXT NOT NULL`) — *Confirmed: the thesis ERD should be updated to use `settings.k` and `settings.v`.*
   - `reports` (`id`, `resident_id`, `category_id`, `volunteer_id`, `title`, `description`, `location`, `latitude`, `longitude`, `photo`, `priority`, `status`, `is_sos`, `created_at`, `updated_at`)
   - `report_updates` (`id`, `report_id`, `user_id`, `status`, `remark`, `created_at`)
   - `notifications` (`id`, `user_id`, `report_id`, `message`, `is_read`, `created_at`)
   - `announcements` (`id`, `user_id`, `title`, `body`, `level`, `is_active`, `created_at`)
   - `login_attempts` (`id`, `email`, `ip`, `created_at`)
5. **Final List of Implemented User Roles:** `resident`, `volunteer`, `admin`.
6. **Final List of Implemented Modules:**
   - **Public / Auth:** Landing page (`index.php`), Login (`login.php`), Resident Registration (`register.php`), Logout (`logout.php`)
   - **Resident Portal:** Dashboard (`resident/dashboard.php`), Submit Incident Report (`resident/report.php`), Emergency SOS (`resident/sos.php`), My Reports (`resident/my_reports.php`), Incident Details & Timeline (`resident/report_details.php`)
   - **Volunteer Portal:** Dashboard (`volunteer/dashboard.php`), Assigned Reports (`volunteer/assigned_reports.php`), Incident Details & Status Update (`volunteer/report_details.php`)
   - **Administrator Portal:** Dashboard + Active SOS Board (`admin/dashboard.php`), Reports Directory & CSV Export (`admin/reports.php`), Report Review/Assignment/Rejection (`admin/report_details.php`), Pin & Dispatch (`admin/dispatch.php`), Residents Directory (`admin/residents.php`), Volunteers Directory (`admin/volunteers.php`), Announcements Manager (`admin/announcements.php`), Categories Manager (`admin/categories.php`), Analytics & Incident Map (`admin/analytics.php`), System Settings & Admin Roles (`admin/settings.php`)
   - **Shared / JSON APIs:** Notifications Poll (`api/poll.php`), Notifications Feed (`api/notifications.php`), Mark Read (`api/notifications_read.php`), Clear (`api/notifications_clear.php`), Profile & Avatar Studio (`api/profile.php`)
7. **Actual Third-Party Integrations:**
   - **Leaflet 1.9.4** (CDN via `cdnjs.cloudflare.com`) + **OpenStreetMap** tile server + **Nominatim** address search/reverse-geocoding API
   - **Google Fonts** (`Plus Jakarta Sans`) and outbound **Google Maps** external link (`https://www.google.com/maps?q=lat,lng`)
   - **Semaphore SMS API v4** (`https://api.semaphore.co/api/v4/messages` via PHP `cURL` — optional)
8. **Operational Conditions & Incomplete/External-Dependent Features:**
   - Browser Geolocation ("Use my current location" and live GPS on SOS) requires **`localhost` or HTTPS**; on plain HTTP LAN IPs, SOS falls back to the resident's saved home address.
   - Incident map & address search require internet access to load Leaflet/OpenStreetMap/Nominatim tiles.
   - `assets/uploads/` must be writable by Apache/PHP for incident photo and avatar image uploads.
   - Optional SMS alerts depend on an external paid Semaphore API key (disabled by default).
9. **Source-to-Thesis Sync Confirmation:** Confirm prior to final binding that your deployed PHP/MySQL folder matches the exact git commit referenced in the thesis manuscript.

---

## 6. SMS Clarification (Confirmed)

- **Does optional Semaphore integration remain in the PHP source?** **Yes** (`sendSms()` and `notify()` in `includes/helpers.php`, and the Semaphore configuration card in `admin/settings.php`).
- **Is it disabled by default?** **Yes** (`sms_enabled = '0'`, `sms_api_key = ''`, and `sms_sender = ''` in `database/cairs.sql`).
- **Is any SMS-related feature incomplete or unavailable?** Without an active Semaphore API key (or `SEMAPHORE_API_KEY` env var), PHP `cURL`, and outbound HTTPS, SMS delivery is inactive (`"No API key saved in Settings."`); additionally, `notify()` only attempts SMS for `resident` and `volunteer` recipients who have a phone number stored (Admin SMS is only invoked manually via "Send test SMS"). Per checklist instructions, no SMS delivery test evidence is included in the thesis evaluation.
