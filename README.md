# Campus Lost & Found System

A clean base project foundation for the Campus Lost & Found System.

## Stack

* PHP 8.3 (Apache)
* MariaDB 11
* MySQLi
* Tailwind CSS v4
* Bun
* Vanilla JavaScript
* Docker & Docker Compose

## Requirements

* Docker and Docker Compose
* Bun (v1.0+)

## Installation

```bash
git clone https://github.com/jeerawatwongkhamchan-ops/Campus-Lost-Found-System.git
cd Campus-Lost-Found-System

cp .env.example .env

bun install

docker compose up -d --build

bun run dev
```

Open in your browser:

```text
http://localhost:8080
```

## Docker Commands

Start containers:
```bash
docker compose up -d
```

Build and start containers:
```bash
docker compose up -d --build
```

Stop containers:
```bash
docker compose down
```

Stop containers and delete local database volume (Database reset):
```bash
docker compose down -v
```

View container logs:
```bash
docker compose logs -f
```

## Bun Commands

Install frontend dependencies:
```bash
bun install
```

Watch and compile Tailwind CSS for development:
```bash
bun run dev
```

Build minified Tailwind CSS for production:
```bash
bun run build
```

Run automated security and quality test suite:
```bash
bun run test
```

## Security & Architectural Features

* **HTTP Defense-in-Depth Headers:** Automated dispatch of `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, and `Permissions-Policy`.
* **Immutable Audit Trail:** All security events are logged to `security_logs` with tamper-proof, append-only retention (failed attempts reset naturally via login timestamp without deleting history).
* **Smart Matching Recommender:** Automatically analyzes categories, campus locations, timestamps, and keywords to suggest matching lost/found items.
* **Proof of Ownership Verification:** Claimants can securely upload image evidence (receipts, proof photos) with strict MIME validation.
* **Private Challenge Notes:** Reporters can save private notes (hidden from public view) used by staff/reporters to verify claimants.
* **In-App Real-time Notifications:** Bell notification badge and history for claim submissions, approvals, and rejections.
* **Soft Delete Architecture:** Reports are soft-deleted (`deleted_at`), preventing accidental cascade data loss and preserving audit history.
* **Admin CSV Export:** 1-click export of Reports and Security Logs with UTF-8 BOM encoding for Excel compatibility.

## Database

### Initialization
On first startup (`docker compose up -d`), MariaDB automatically executes:
* `database/schema.sql`: creates tables (`users`, `categories`, `item_reports`, `claims`, `security_logs`) with proper keys, constraints, and indexes.
* `database/seed.sql`: seeds static category data (`Electronics`, `Documents`, `Bags`, `Accessories`, `Clothing`, `Keys`, `Other`).

### Reset
To wipe the database and re-initialize from scratch, destroy the persistent Docker volume:
```bash
docker compose down -v
docker compose up -d --build
```

## Environment Configuration

Configuration variables are defined in `.env`:

| Variable | Description | Default |
| --- | --- | --- |
| `APP_ENV` | Application environment (`development` or `production`) | `development` |
| `APP_PORT` | Host port mapped to Apache | `8080` |
| `DB_HOST` | Database host name | `db` |
| `DB_PORT` | Database port | `3306` |
| `DB_NAME` | Database name | `campus_lost_found` |
| `DB_USER` | Application database user | `campus_lost_found_app` |
| `DB_PASSWORD` | Application database password | `change_me` |
| `DB_ROOT_PASSWORD` | MariaDB root password | `change_root_password` |

## Project Structure

```text
Campus-Lost-Found-System/
├── app/
│   ├── config/
│   │   └── database.php
│   ├── helpers/
│   │   └── functions.php
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── header.php
│   │   │   └── footer.php
│   │   └── components/
│   │       └── navbar.php
│   └── bootstrap.php
│
├── public/
│   ├── index.php
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   ├── reports/
│   │   ├── index.php
│   │   └── create.php
│   ├── claims/
│   │   └── index.php
│   ├── admin/
│   │   └── index.php
│   └── assets/
│       ├── css/
│       │   └── app.css
│       └── js/
│           └── app.js
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── docker/
│   └── php/
│       └── Dockerfile
│
├── src/
│   └── input.css
│
├── compose.yaml
├── .env.example
├── .gitignore
├── package.json
├── bun.lock
└── README.md
```

## Public Portfolio Copy

This group project was imported from a shared source archive. The original repository was Bxmluvuu/Campus-Lost-Found-System. Four screenshots containing personal identifiers have been omitted from this public copy. Database seed accounts and configuration passwords are local development examples; change them before deploying.
