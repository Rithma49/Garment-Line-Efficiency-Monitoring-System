# GLEMS — Garment Line Efficiency Monitoring System

A simple web system to manage garment factory production, workers, machines, and reports — all in one place.

Built with **PHP + MySQL**. Runs on **XAMPP**. No internet needed.

---

## About the System

GLEMS is a web-based management system built for garment factories. It helps factory managers, supervisors, and admins keep track of everything that happens on the production floor.

Instead of using papers and spreadsheets, everything goes into one system. Production numbers, worker attendance, machine breakdowns, quality defects, buyers, and orders — all stored safely in a database.

The best part is **efficiency is calculated automatically**. When you enter daily production data, the system calculates the efficiency percentage right away. No manual math, no mistakes.

The dashboard shows daily output and efficiency with simple charts, so managers can quickly see how the factory is doing. The reports page lets you filter by date and export data as a CSV file.

The system also supports **four user roles** — Admin, Manager, Supervisor, and Operator — so each person sees only what they need. Every important action is recorded in an activity log, so you always know who did what and when.

Since it runs on XAMPP, you can use it on any local computer or office network. No monthly fees, no cloud subscriptions, and your data stays with you.

---

## Features

### Production Management
- **Production Lines** — Add, edit, and delete production lines
- **Production Entry** — Enter daily production numbers, hours, and defects
- **Production Targets** — Set daily targets for each line
- **Production Planning** — View a 7-day plan across all lines
- **Auto Efficiency** — Efficiency percentage calculated automatically

### People Management
- **Operators** — Manage workers with skill levels (beginner, intermediate, expert)
- **Attendance** — Mark present, absent, late, half-day, or leave
- **Overtime** — Track overtime hours per worker
- **Operator Performance** — See which operator is best and who needs help

### Machine Management
- **Machines** — Full machine registry with image upload
- **Machine Downtime** — Record when machines stop, why, and for how long

### Business Management
- **Buyers** — Store buyer and customer details
- **Orders** — Track orders with style numbers and delivery dates
- **Quality Defects** — Record defects per order and per line

### Insights & Reports
- **Dashboard** — Live summary with efficiency and output charts
- **Reports** — Filter production data by date range
- **CSV Export** — Download reports as CSV files
- **Activity Log** — Full audit trail of all user actions

### System Management
- **Users** — Role-based accounts (Admin, Manager, Supervisor, Operator)
- **Settings** — Change company name, currency, timezone, etc.
- **Backup** — Download a full database backup as a .sql file
- **Restore** — Load a backup file to restore the database
- **Notifications** — System alerts for important events
- **Profile** — Update your own details and change password

---

## Tech Stack

### Backend
| Technology | Purpose |
|-----------|---------|
| **PHP 7.4+** | Server-side scripting |
| **PDO** | Database connection (secure, prepared statements) |
| **MySQL 5.7+** | Relational database (InnoDB engine) |

### Frontend
| Technology | Purpose |
|-----------|---------|
| **HTML5** | Page structure |
| **CSS3** | Custom styling |
| **JavaScript (ES6)** | Interactivity and AJAX |
| **Bootstrap 5** | Responsive UI framework |
| **Bootstrap Icons** | Icon set |
| **Chart.js** | Interactive charts on dashboard |

### Server / Environment
| Technology | Purpose |
|-----------|---------|
| **XAMPP** | Local development server (Apache + MySQL + PHP) |
| **Apache** | Web server |

### Security
| Feature | How it works |  
|---------|--------------|
| **Password Hashing** | Passwords stored with `password_hash()` |
| **CSRF Protection** | Token verification on all POST forms |
| **SQL Injection Prevention** | All queries use prepared statements |
| **Session Auth** | Session-based login system |
| **Role-Based Access** | Users only see what their role allows |
| **Activity Logging** | Every important action is recorded |

### Architecture
| Aspect | Detail |
|--------|--------|
| **Pattern** | Simple MVC-like structure (config, includes, pages) |
| **Database** | MySQL with foreign key relationships |
| **File Uploads** | Images stored in `uploads/` folder |
| **AJAX** | Small JSON endpoints in `ajax/` folder |
| **Routing** | Direct file access (no framework) |

---
