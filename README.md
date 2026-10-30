# MNHS portal

PHP/Apache portal with a Vite/React frontend and Moodle integration.

## Docker deployment

Requirements: Docker with Compose and a configured Moodle instance. The optional local database requires a compatible MariaDB dump at `functions/maboqraj_portals.sql`. The dump contains school data and is intentionally excluded from Git; obtain it securely and place it there before starting the local database profile. Without it, use an existing database with the required schema instead. No general migration system is included.

1. Copy `.env.example` to `.env`, restrict access to the file, and fill in the database credentials and Moodle URL. For the optional containerized database, set `DB_HOST=db` and choose strong, different `DB_PASS` and `DB_ROOT_PASSWORD` values. For an existing external database, set its reachable host and credentials instead. `host.docker.internal` works with Docker Desktop when a host database accepts connections from containers. Use a dedicated least-privilege database account.
2. Set `MOODLE_WSTOKEN` if shared Moodle web-service calls are required. Enable the required Moodle mobile/web-service functions and verify permissions with a test account. `ADMIN_USERNAMES`, if used, grants those Moodle usernames full portal admin access.
3. For a new local MariaDB database, run `docker compose --profile local-db up -d --build`. The SQL dump is imported **only on the first initialization of an empty `portal_db` volume**; initialization can take time. Check `docker compose --profile local-db logs db` for completion or errors. Never re-import into an existing database without a backup and a deliberate recovery plan. To use an external database instead, run `docker compose up -d --build` without the profile. Open `http://localhost:8080` for local testing. Check `docker compose ps` and `docker compose logs app` for errors. `GET /health.php` checks only that Apache/PHP is running, not database or Moodle connectivity.
4. For deployment, put a trusted TLS reverse proxy in front of the loopback-bound application, configure the public hostname and certificates, and prevent direct public access to the app port. The proxy must overwrite (not append untrusted values to) `X-Forwarded-Proto`. Do not expose Redis or MariaDB publicly. Arrange regular database backups and test restores; a Docker volume is not a backup.

The image builds the frontend from the lockfile and installs PHP MySQL, cURL and Redis extensions. Redis is used for caching; sessions currently use local files. Run only one app replica and expect sessions to be lost when the container is replaced. Cache is disposable. Configuration changes require recreating the container (`docker compose up -d`). Never commit `.env` or a database dump.

## Before production

This setup is a starting point, not a production certification. Validate the database schema and imported data, test all student and admin workflows against the real Moodle service, and arrange monitoring, backups and recovery tests. The login lockout is session-based and can be bypassed by creating a new session; enforce robust rate limiting at the reverse proxy and Moodle. Review authorization, CSRF protection and security across all endpoints before exposing the portal to users. Keep base images and dependencies updated and scan them regularly. TLS termination, secrets management, availability and deployment policies remain the operator's responsibility.

For frontend-only development, run `npm ci` and `npm run build` (or `npm run dev` for watch mode).

## Teacher portal

Teachers can open `/teacher/` from the portal navigation after signing in with Moodle. Access requires a matching `teachers.moodle_id` in the portal database; an administrator must sync the Moodle teacher account and assign schedules and students to sections first. Teachers can open their assigned classes, see the students assigned to each section and save quarter grades from 0 to 100. Blank grades are not recorded. These grades are stored in the portal database, not Moodle, and are not yet displayed in the student portal.

The separate teacher schedule is available at `/teacher/schedule.php` and links to each assigned class.

At sign-in, Moodle course roles `editingteacher` and `teacher` route a user to the teacher portal (unless an explicit destination was requested; administrators default to the admin portal). Moodle must expose `core_enrol_get_users_courses` and `core_enrol_get_enrolled_users` and return the user's roles to the login service. A verified Moodle teacher is added to or updated in the local `teachers` table at sign-in. A matching local teacher record also routes to the portal if the Moodle role check is unavailable. Access to classes and grades still requires an administrator to assign schedules to that teacher.

Fresh local database volumes import `migrations/001_teacher_grades.sql` automatically. For an existing local database, back it up first, then apply the migration once from PowerShell:

```powershell
Get-Content -Raw migrations/001_teacher_grades.sql | docker compose --profile local-db exec -T db sh -c 'exec mariadb -u "$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
```

For an external database, have its administrator apply that SQL file using a migration account with CREATE and REFERENCES privileges. The current schema has no academic-year or term field on schedules or grades: grades for a student, subject and section are shared across schedules and cannot be separated by school year. Do not use this for successive school years until term-aware records and a migration are implemented. Evaluation reports and Moodle grade synchronization are not included.
