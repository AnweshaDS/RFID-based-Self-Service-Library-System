# RFID-Based Self-Service Library System

An intelligent, self-service library kiosk application built with **Laravel 12** and integrated with the **Koha Integrated Library System (ILS) REST API**. 

The system provides a touch-friendly interface for library patrons to scan their RFID membership cards, view active loans, borrow books, attempt returns, and view their activity history seamlessly.

---

## 🚀 Key Features

* **RFID Member Authentication**: Maps physical or simulated RFID UIDs directly to Koha patron cardnumbers (`RfidCard`).
* **Koha REST API Integration**:
  * OAuth2 authentication & token management (`KohaService`).
  * Real-time patron lookup & loan status retrieval.
  * Item metadata lookup & checkouts.
  * Automatic local **mock fallback mode** for offline development when Koha is unavailable.
* **Kiosk Interface**:
  * Sleek, high-contrast dark theme designed for kiosk screens (`/kiosk`).
  * Patron Dashboard with real-time loan counts, due-soon indicators, and detailed book metadata (Title, Author, Barcode, Due Date).
  * Interactive **Borrow Book** workflow with lookup & confirmation steps.
  * Interactive **Return Book** workflow with isolated transport logic.
* **Persistent Activity Logging**:
  * Audit logging (`ActivityLog`) capturing scans, borrows, and returns.
  * Safe logging without storing OAuth tokens, client secrets, or raw API error traces.
  * Displays patron-specific recent activity logs on the dashboard.
* **Roles & Departments Foundation**:
  * Department management (`Department` model: CSE, EEE, ME).
  * Multi-role user assignments (`Role` model: Student, Librarian, Admin).
  * `TaskDelegation` system for assigning library tasks between staff users.
  * `EnsureUserHasRole` authorization middleware (`role:...`).
* **Automated Test Suite**:
  * Comprehensive feature & unit test coverage (76+ passing tests).

---

## 🛠️ Tech Stack

* **Framework**: Laravel 12 (PHP 8.2+)
* **Database**: SQLite / MySQL / PostgreSQL
* **Integration**: Koha ILS REST API (OAuth2)
* **Frontend**: Blade + Vanilla CSS / TailwindCSS

---

## ⚙️ Prerequisites & Installation

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/AnweshaDS/RFID-based-Self-Service-Library-System.git
cd RFID-based-Self-Service-Library-System
composer install
npm install && npm run build
```

### 2. Environment Configuration
Copy the `.env.example` file to `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Configure your Koha REST API credentials in `.env` (optional for local simulation):
```env
KOHA_BASE_URL=http://localhost:8080
KOHA_CLIENT_ID=your-client-id
KOHA_CLIENT_SECRET=your-client-secret
KOHA_MOCK_MODE=false
```
*Note: If Koha credentials are left blank or `KOHA_MOCK_MODE=true`, the application automatically uses local mock data for testing.*

### 3. Database Migration & Seeding
Run migrations and seed pre-configured test data (RFID cards, roles, departments):
```bash
php artisan migrate:fresh --seed
```

---

## 🧪 Testing Seed Data

The database seeder provisions test mappings for simulated scanning:

| RFID UID | Koha Cardnumber | Patron Name | Default Role |
| :--- | :--- | :--- | :--- |
| `04:B2:11:8A:92:31` | `STU001` | Arif Hasan | Student |
| `04:A3:91:7B:22:18` | `STU002` | Rahim Uddin | Student |

---

## 🏃 Running the Application

Start the Laravel local development server:
```bash
php artisan serve
```

Access the kiosk interface at:
**[http://127.0.0.1:8000](http://127.0.0.1:8000)** (or **[http://127.0.0.1:8000/kiosk](http://127.0.0.1:8000/kiosk)**)

---

## 📋 Kiosk Workflow & Demonstration

1. **Start Screen**: Open `http://127.0.0.1:8000`.
2. **Scan Card**: Type or select simulated UID `04:B2:11:8A:92:31` and click **Simulate scan**.
3. **Dashboard**: View current loans (with title, author, barcode, due dates) and recent activity logs.
4. **Borrow Book**: Click **Borrow a book**, enter barcode `BOOK001`, and click **Confirm Borrow**.
5. **Return Book**: Click **Return a book**, select a borrowed item, and confirm the return.
6. **Logout**: End the session and return to the main kiosk welcome screen.

---

## 🧪 Running Automated Tests

Execute the full PHPUnit/Pest test suite:
```bash
php artisan test
```

Tests cover:
* `KioskScanTest`: RFID card resolution & session setup.
* `KioskBorrowTest`: Item lookup, availability checks, & checkout.
* `KioskReturnTest`: Return validation & transport safety.
* `KohaServiceTest`: API OAuth authentication, item/patron queries, & fallback enrichment.
* `ActivityLogTest`: Activity logging persistence & patron activity scoping.
* `RoleDepartmentTest`: Department/Role Eloquent relations, task delegations, & middleware authorization.

---

## 📄 License

This project is open-sourced under the [MIT license](LICENSE).
