# CAIRS - Community Assistance and Incident Reporting System

A PHP + MySQL web app for a barangay: residents report incidents (with a map pin and photo) or send an
emergency **SOS**, admins assign volunteers and track progress, and everyone gets notifications.

## Roles
- **Resident** - report an incident, send an SOS, follow progress, notifications, profile.
- **Volunteer** - sees assigned reports, posts updates, marks them resolved.
- **Admin** - dashboard with live SOS board, reports (filter, CSV export, print), residents, volunteers,
  announcements, categories, analytics + incident map, settings (hotlines, SMS, map center).

## Setup (Laragon or XAMPP)
1. Install [Laragon](https://laragon.org) or XAMPP (PHP 8+, MySQL/MariaDB). Start Apache and MySQL.
2. Put this folder in the web root (e.g. `C:\laragon\www\CAIRS-main` or `C:\xampp\htdocs\CAIRS-main`).
   The app detects its own base URL, so the folder name does not matter.
3. Just open the site. The `cairs` database, all tables, categories, settings and demo accounts are
   created automatically on the first page load from `database/cairs.sql` - no manual import needed.
4. Database login is `root` with an empty password. Change it in `config/database.php` if yours differs.
5. Open the site, e.g. <http://cairs-main.test> (Laragon) or <http://localhost/CAIRS-main>.

## Demo accounts
| Role      | Email                  | Password     |
|-----------|------------------------|--------------|
| Admin     | admin@cairs.local      | admin123     |
| Volunteer | volunteer@cairs.local  | volunteer123 |
| Resident  | resident@cairs.local   | resident123  |

Change these before using the system for real.

## Notes
- The SOS page and "Use my current location" need GPS permission. Browsers only allow that on
  `localhost` or HTTPS; on plain `http://192.168.x.x` it falls back to the resident's home address.
- `assets/uploads/` must be writable by the web server. Uploaded photos are not committed to git.
- SMS alerts are optional (Semaphore API key, set in Admin > Settings).
- Five wrong logins from the same email or IP lock logging in for 10 minutes.
