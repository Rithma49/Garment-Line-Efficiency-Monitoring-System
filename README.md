# GLEMS — Garment Line Efficiency Monitoring System

Hey! This is a simple web app I built for garment factories. It helps you keep track of everything that happens on the factory floor — production, workers, machines, attendance, and more — all in one place.

It's made with PHP and MySQL, and it runs on XAMPP. No internet needed, so you can run it on any computer in the office.

---

## Why I made this

Garment factories deal with a lot of daily data. How many pieces did each line produce? Who came to work today? Which machine broke down and for how long? Doing all this on paper is slow, and mistakes happen all the time.

So I made GLEMS to fix that. Now everything goes into one system, and the numbers come out automatically. Managers can just log in and see what's going on.

---

## What you can do with it

You can manage production lines, add workers and machines, and enter daily production numbers. The system will calculate efficiency for you automatically — no more manual math.

There's also attendance tracking (present, absent, late), machine downtime records, and a place to log quality defects. You can add buyers and orders too, so you know what's coming and when it's due.

The dashboard gives you a quick look at today's output and efficiency with charts. The reports page lets you filter by date and export everything as a CSV file.

If you're an admin, you can also manage users, change settings, and back up or restore the whole database.

---

## What you need

- XAMPP (Apache + MySQL + PHP) — download it from https://www.apachefriends.org
- PHP 7.4 or newer
- Any modern browser (Chrome, Firefox, Edge — anything works)

No extra libraries or frameworks to install.

---

## How to set it up

First, drop the project folder into XAMPP's htdocs folder:

```
C:\xampp\htdocs\project\Garment Line Efficiency Monitoring System\
```

Then open XAMPP Control Panel and start Apache and MySQL. Both need to be running.

Next, open http://localhost/phpmyadmin in your browser. Click the Import tab, choose the file `database/glems_schema.sql`, and hit Go. That's it — the database gets created with all the tables.

Finally, open this link in your browser:

```
http://localhost/project/Garment%20Line%20Efficiency%20Monitoring%20System/
```

You'll see the login page.

---

## Logging in

The default admin account is:

- Username: admin
- Password: admin123

Please change the password right after your first login. It's just a default to get you started.

---

## User roles

There are four roles in the system. Admins get full access to everything. Managers can handle production data. Supervisors manage lines, operators, and daily production. Operators get basic access.

Only admins can touch users, settings, and backups — that keeps things safe.

---

## How efficiency is calculated

The formula is simple:

```
Efficiency % = (Quantity Produced ÷ (Working Hours × 60)) × 100
```

So if a line produced 480 pieces in 8 hours, that's (480 ÷ (8 × 60)) × 100 = 100%. Easy.

---

## Daily work

The normal flow goes like this. Log in, then go to Production Entry and add today's numbers. The system handles the rest.

After that, mark attendance for the workers. If a machine stopped working, record it under Machine Downtime. If you found defects, log them under Quality Defects.

Check the Dashboard anytime for a quick summary. And when you need a report, head to the Reports page — you can filter by date and download a CSV.

---

## Backup and restore

If you're an admin, go to the Backup page to download a full .sql file of the database. Keep it safe.

To restore, go to the Restore page, pick your .sql file, and click Restore. Just a heads up — this will wipe everything currently in the database and replace it with the backup. So be careful.

---

## What it's built with

Backend is PHP with PDO for the database. The database is MySQL. The frontend is plain HTML, CSS, and JavaScript, with Bootstrap 5 for the styling. Charts are made with Chart.js. Icons come from Bootstrap Icons.

Nothing fancy — just stuff that works.

---

## Security

Passwords are hashed, so they're never stored in plain text. Every form has a CSRF token. Database queries use prepared statements to stop SQL injection. Logins are session-based, and users only see what their role allows. Every important action also gets written to an activity log, so you always know who did what.

---

## If something goes wrong

If you see "Database connection failed," make sure MySQL is actually running in XAMPP. Also check the settings in config/database.php.

If you get a 404, check the folder name in the URL — it has to match exactly.

If you see "Invalid CSRF token," just clear your browser cookies and log in again. That usually fixes it.

If images aren't uploading, make sure the folders uploads/machines/ and uploads/operators/ exist.

And if login doesn't work at all, re-import the SQL file. The default admin should be there.

---

## A couple of notes

Time zone is set to Asia/Colombo, currency is LKR, and dates are in YYYY-MM-DD format. The database tables use InnoDB so foreign keys work properly.

---

## Version

Version 1.0.0. Free to use for learning or in a real factory. Do whatever you want with it.

---

That's pretty much it. Hope it helps someone out there. 