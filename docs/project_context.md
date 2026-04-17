# Where is Mom - Project Context

## Overview
"Where is Mom" is a tracking application designed to monitor a cruise ship's location (via Datadocked Maritime API) and plot it against geolocated photo uploads to a Google Maps interface, tailored for family viewing.

## Architectural Decisions
1. **Backend Integration (Python):** 
   - Uses `cron_fetch.py` as an automated polling service.
   - Fetches flat JSON from Datadocked using `x-api-key`.
   - Records current server UTC timestamp + last known AIS coordinate payload.
2. **Database (MySQL 8.4+):** 
   - Employs strict parameterized statements to mitigate SQL injection.
   - Utilizes Spatial columns (`ST_SRID(Point(lat, lng), 4326)`) for bounding-box (`MBRContains`) optimizations. Note that OGC standard SRID 4326 enforces `X = Latitude` and `Y = Longitude`.
3. **Backend Service (PHP 8.3+):** 
   - Built on PSR-12 and PSR-4 specs, bypassing heavy ORMs.
   - Utilizes native `parse_ini_file()` matching Python's native `configparser` against a unified, dependency-free `config.ini` file.
   - Photos uploaded via `api.php` extract EXIF GPS via intermediate processing and proxy binary returns via `image.php`.
4. **Cloud Storage (Google Cloud Platform):**
   - Stores photos in a private GCP Bucket, loaded securely to the client via PHP proxy streaming to bypass public ACL requirements. 
   - Automatically utilizes `GOOGLE_APPLICATION_CREDENTIALS` passed via `putenv`.
5. **Frontend (Vanilla HTML/CSS/JS):**
   - Strictly Vanilla ES6+ stack (no heavyweight frameworks).
   - Utilizes modern `google.maps.marker.AdvancedMarkerElement` and `PinElement` architectures, unlocking native DOM integrations over legacy markers.

## Completed Tasks
- **Phase 1:** Google Cloud GCP Bucket & Application Credential Initialization.
- **Phase 2:** MySQL Schema spatial indexing & strict type conversions. 
- **Phase 3:** Datadocked API debugging (converting nested JSON expectations to Flat properties, resolving string-typing bugs for DECIMAL). 
- **Phase 4:** Strict Style Enforcement. Migrated Python scripts to 10/10 pylint/pytest standards. Refactored PHP files to utilize PSR-4 autoloading (`backend/src/`), resolved all PSR-12 formatting issues, and enforced static analysis via `phpstan` at Level 5.