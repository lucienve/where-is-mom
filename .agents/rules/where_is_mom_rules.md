---
trigger: always_on
description: Core constraints, architectural directives, and coding standards for the Where is Mom project.
---

# Project Context & Directives

You are working on the 'Where is Mom' project. You must strictly adhere to these constraints and coding standards at all times to ensure project consistency.

## 1. Source control
- **Version Control:** When asked to generate commit messages, mandate the Conventional Commits format (e.g., `feat: [message]`, `fix: [message]`).

## 2. Infrastructure Target Versions
Ensure all code and syntax is explicitly compatible with the following versions:
- **PHP:** `8.3.6`
- **MySQL:** `8.4.8`
- **Python:** `3.11+` (Relaxed from 3.12 to support native Debian 12 developer environments while maintaining forward-compatibility with Ubuntu production servers).
- **Datadocked API:** The version described at https://docs.datadocked.com/api-reference/openapi.json

## 3. Backend Directives: Python Architecture
- **Database Rules:** Connect using `mysql-connector-python`. **Do not use an ORM**. You must use strictly parameterized queries / prepared statements for all database interactions. String interpolation or concatenation for SQL queries is strictly forbidden to prevent SQL injections.
- **Datadocked API:** 
  - Requests must use `GET https://datadocked.com/api/vessels_operations/get-vessel-location?imo_or_mmsi=...` (do NOT use `/v1/...` routes).
  - Authentication requires `x-api-key: <key>` and `accept: application/json` headers (do NOT use Bearer tokens).
  - The JSON response payload is a flat dictionary returned from the API.
  - For timestamps, prioritize parsing `"positionReceived"` or `"updateTime"` instead of `"timestamp"`. Note that they come formatted as `"Apr 16, 2026 23:42 UTC"` and thus require `strptime` with `"%b %d, %Y %H:%M UTC"`.

## 4. Backend Directives: PHP
- **Testing Paths:** **You MUST put PHP test files strictly within the `backend/tests/` directory**, and mirror the logical file structure of the classes they test. Do NOT create a `tests/` directory at the project root.
- **Dependency Separation:** Make sure to separate development and production requirements in Composer.
- **Database Rules:** Connect using the native MySQL driver (e.g. PDO). **Do not use an ORM**. You must use prepared statements for all database interactions. Raw string interpolation for SQL queries is strictly forbidden.
- **Session Rules:** Use basic PHP Server Sessions for simple password authentication.

## 5. Frontend & UI Directives
- **Stack Limitations:** Stick strictly to Vanilla JavaScript (ES6+), semantic HTML5, and vanilla CSS3. **Do not use React, Vue, Svelte, or any heavy frontend frameworks.**
- **Responsiveness:** Ensure layouts are highly responsive and accessible on both mobile and desktop. Refrain from importing heavy external JS/CSS dependencies unless explicitly approved by the user.
- **Automated Layout Testing:** After every substantial UI or structural change, you must automatically run the Playwright E2E suite to verify that the layouts correctly constrain to mathematical device viewports without overflowing.
- **Separation of Concerns:** HTML, CSS, and JS logic must be completely decoupled into separate files. Inline styles (`style="..."`) and inline event handlers (`onclick="..."`) are strictly prohibited in the markup.
- **Photo Upload Rules:** Display a clear warning if an uploaded picture does not contain EXIF GPS data. If missing, intelligently fall back to the most recent known location available in the database (whether from AIS or a previous photo).

## 6. E2E Testing Synchronization
- **Schema & Test Data Parity:** Whenever you modify `schema.sql`, you must systematically evaluate the constraints of the new schema elements against the E2E test database structure. You MUST update `e2e/setup-test-db.sh` to ensure any mock data seeded for Playwright respects the newly defined columns, foreign keys, or logic requirements without diverging.
- **Test execution limits:** Explicitly avoid running `reporter='html'` such that it opens up a web browser visually natively. The CI configuration or `playwright.config.js` should explicitly possess `reporter: [['html', { open: 'never' }]]` manually.