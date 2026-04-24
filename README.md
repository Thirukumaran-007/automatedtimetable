# College Timetable Management System (CTMS)

PHP + MySQL (XAMPP) mini-project that demonstrates DBMS concepts (PK/FK/UNIQUE, constraints, triggers) to manage:
- Faculty profiles
- Student details
- Courses and Class offerings
- Faculty assignment to classes
- Student enrollment to classes
- Room bookings (timetable slots) with **conflict prevention**
- Reports for faculty workload + room utilization

## 1) Setup on XAMPP

1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Open phpMyAdmin: `http://localhost/phpmyadmin`
3. Import the SQL file:
   - Create DB automatically by importing: `sql/timetable.sql`
   - In phpMyAdmin: **Import** → choose `timetable/sql/timetable.sql` → **Go**

Alternative:
- Run the setup helper: `http://localhost/timetable/public/setup.php`

## Year/Semester (Cohort) flow

1. Apply migration: `sql/migrations/2026_03_26_year_semester_cohorts.sql`
2. Ensure your courses have `year_level` and `semester_no` filled (sample updates included in `sql/seed_sample_data.sql`).
3. Create a cohort: `http://localhost/timetable/public/cohorts.php`
4. Select subjects + assign faculty: `http://localhost/timetable/public/cohort_subjects.php`
5. Generate Mon–Fri timetable: `http://localhost/timetable/public/cohort_timetable.php`

## 2) Configure DB credentials (if needed)

Edit `app/config.php` if your MySQL user/password is not the XAMPP default.

## 3) Run the app

Open:
- `http://localhost/timetable/public/index.php`

## Deploy on Vercel (PHP runtime)

This project can also run on Vercel using the community PHP runtime (`vercel-php`).

### 1) Push this repo to GitHub

Vercel imports from Git providers, so push your latest code first.

### 2) Create a Vercel project

1. Go to Vercel Dashboard -> **Add New...** -> **Project**
2. Import your GitHub repository
3. Keep defaults (framework can remain `Other`)

### 3) Add environment variables in Vercel

Set these in **Project Settings -> Environment Variables**:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_NAME` (optional)
- `ADMIN_USER` (optional, default: `admin`)
- `ADMIN_PASS` (optional, default: `admin123`)

Important:
- Vercel does not provide MySQL by default. Use an external MySQL database (for example Railway, Neon MySQL, PlanetScale, etc).
- This repo includes `vercel.json` and `api/index.php`, so all existing `public/*.php` pages route correctly.

### 4) Deploy

Trigger deployment from Vercel dashboard (or push a new commit to your connected branch).

### 5) First run

Open:
- `https://<your-project>.vercel.app/setup.php`

Then login at:
- `https://<your-project>.vercel.app/login.php`

## Deploy (free) using GitHub + Render (recommended)

This project can be deployed for free as a Docker web service on Render.

### A) Prepare a MySQL database (required)

You need a MySQL-compatible database (host, db name, user, password, port).
Free DB providers often have limits/trials, but any hosted MySQL works.

Import schema from your computer into the hosted DB:

- For local XAMPP/phpMyAdmin (creates DB `ctms`): `sql/timetable.sql`
- For hosted DBs that already come with a fixed database name (Railway, etc): `sql/timetable_schema.sql`
- Cohorts migration (optional): `sql/migrations/2026_03_26_year_semester_cohorts.sql`

Example import command (hosted DB):
`mysql -h <HOST> -P <PORT> -u <USER> -p <DB_NAME> < sql/timetable_schema.sql`

### B) Deploy on Render

1. Go to Render → New → **Web Service**
2. Connect your GitHub repo
3. Choose **Docker** (Render will use the `Dockerfile` in this repo)
4. Add these Environment Variables in Render:
   - `DB_HOST`
   - `DB_PORT` (usually 3306)
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

Optional:
- `APP_BASE_PATH`:
  - Leave empty if deploying at domain root (recommended)
  - Set to `/timetable/public` if you host the app under that subpath

5. Deploy. When it’s live, open the Render URL.

Notes:
- This repo includes a `Dockerfile` that serves the app from the `public/` folder.
- The app auto-detects its base path, so it works both locally (`/timetable/public`) and at a deployed root URL.

## 4) What to do in the UI

- Add Faculty / Students / Courses / Rooms
- Create **Classes** (course + section + term)
- Assign faculty to a class and enroll students
- Add timetable **Schedule slots**
  - Conflicts are blocked at the database level (room/faculty/class overlap triggers)

## Notes

- Working hours enforced: 08:00 to 18:00.
- Day of week: 1=Mon ... 7=Sun.

## Optional sample data

- Local XAMPP seed (uses `USE ctms;`): `sql/seed_sample_data.sql`
- Hosted DB seed (no hardcoded DB): `sql/seed_sample_data_schema.sql`
- Local cohort flow sample (uses `USE ctms;`): `sql/seed_cohort_flow_sample.sql`
- Hosted cohort flow sample (no hardcoded DB): `sql/seed_cohort_flow_sample_schema.sql`
