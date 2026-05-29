# Japan Trip Planner

A web application that automatically builds a good-quality travel route visiting a list of places while minimising the total distance travelled. Built for the **Final Certification Box 2025/2026 — Bachelor in Digital Sciences** (subject: _Travel Planning_).

A user signs up, adds cities by name, and the app computes an optimised tour (returning to the start) using a two-phase strategy: geographic **clustering** followed by **Ant Colony Optimization (ACO)**. Trips can be saved and shared publicly or privately.

---

## Features

- **Authentication** — sign up / log in / log out, hashed passwords, session-based access control.
- **Place management** — add cities by name; coordinates are retrieved automatically via the GeoNames API.
- **Route optimisation** — clustering of nearby cities into "hotel cities" + ACO over the hotel cities, minimising total distance.
- **Trips** — create, edit and delete multiple trips; the optimised order and total distance are stored.
- **Sharing** — share a trip with a **public** link (no login) or **private** link (login + targeted user only).
- **Account isolation** — each user only sees and edits their own data.

---

## Architecture

A three-tier architecture with a stand-alone optimisation service:

```
Browser ──HTTP──> PHP web app ──JSON──> Python optimisation service (FastAPI)
                       │                          (clustering + ACO)
                       ├──SQL (PDO)──> MySQL database
                       └──HTTP──> GeoNames API (geocoding)
```

| Layer                | Role                                         | Technology          |
| -------------------- | -------------------------------------------- | ------------------- |
| Web application      | UI, auth, trips, sharing, orchestration      | PHP 8 + Bootstrap 5 |
| Optimisation service | Computes the optimal tour, exposed over HTTP | Python 3 + FastAPI  |
| Database             | Persists users, places, trips, shares        | MySQL (PDO)         |
| Geocoding            | City name → coordinates                      | GeoNames API        |

---

## Repository structure

```
algo/                   # Optimisation service (Python)
  place.py              # Place value object (name + coordinates)
  distance.py           # Great-circle distance (subject's formula)
  cluster.py            # Grouping of nearby cities (clustering)
  aco.py                # Ant Colony Optimization (TSP)
  api.py                # FastAPI server (/optimize endpoint)
  main.py               # Command-line demonstration
  test.py               # Unit tests for the algorithm
web/                    # Web application (PHP)
  config/database.php   # PDO connection
  auth/                 # signUp.php, logIn.php, logOut.php
  trip/                 # index.php (CRUD), editTrip.php
  share.php             # Viewing a shared trip
  shareLink.php         # Managing shares (public / private)
  functions/            # loginCheck.php (access guard)
  header.php, index.php, needLogin.php, style/, assets/
database/db.sql         # Database schema
tests/                  # Slots for additional tests
```

---

## Prerequisites

- **PHP 8** with the cURL extension (and a web server, or the built-in `php -S`)
- **MySQL / MariaDB**
- **Python 3.10+** with `pip`
- A **GeoNames** account (username) for geocoding — register at https://www.geonames.org

---

## Installation & setup

### 1. Database

```bash
mysql -u root -p -e "CREATE DATABASE boxdb;"
mysql -u root -p boxdb < database/db.sql
```

Then set your credentials in `web/config/database.php`:

```php
$host     = '127.0.0.1';
$dbname   = 'boxdb';
$username = 'boxdbuser';
$password = 'boxdbpassword';
```

### 2. Optimisation service (Python)

```bash
cd algo
pip install fastapi uvicorn pydantic
uvicorn api:app --host 127.0.0.1 --port 8000
```

The service listens on `http://127.0.0.1:8000`; interactive docs are at `/docs`.

### 3. Geocoding

In `web/trip/editTrip.php`, replace the `username` parameter of the GeoNames URL with your own GeoNames account name. Without it, city search will fail.

### 4. Web application (PHP)

```bash
cd web
php -S localhost:8080
```

Open `http://localhost:8080/index.php`.

---

## Usage

1. **Sign up**, then **log in**.
2. Go to **Trip**, create a trip, and open it.
3. **Add cities** by name (e.g. Tokyo, Kyoto, Osaka).
4. Click **"Optimise the route"** (the Python service must be running).
5. The optimised itinerary and total distance are displayed and saved.
6. Use **Share a trip** to generate a public or private link.

---

## Optimisation API

The Python service exposes a single endpoint.

**`POST /optimize`**

Request:

```json
{
  "places": [
    { "name": "Tokyo", "latitude": 35.682, "longitude": 139.7622 },
    { "name": "Kyoto", "latitude": 34.9946, "longitude": 135.7344 }
  ],
  "maxRadius": 150
}
```

Response:

```json
{
  "route": ["Tokyo", "Kyoto"],
  "clusters": [
    { "hotel": "Tokyo", "dayTrips": [] },
    { "hotel": "Kyoto", "dayTrips": ["Osaka"] }
  ],
  "interHotelDistance": 367.0,
  "dayTripDistance": 86.4,
  "totalDistance": 453.4
}
```

At least 2 cities are required, otherwise the service returns HTTP 400.

---

## How the algorithm works

The planner runs in two phases. The optimised criterion is the **total distance**, computed with the great-circle distance imposed by the subject (`D = R · arccos(…)`, `R = 6378.197 km`).

1. **Clustering** — nearby cities (within `maxRadius`, default 150 km) are grouped around a _hotel city_ and visited as day trips. This limits hotel changes and reduces the size of the optimisation problem. A cluster's day-trip distance is counted as a round trip (×2) from the hotel.
2. **ACO (Ant System)** — the order of the hotel cities is a Travelling Salesman Problem solved with ant colony optimisation. Ants build tours probabilistically (pheromone × proximity heuristic); shorter tours deposit more pheromone, gradually steering the colony towards short edges.

Default hyper-parameters: `nAnts=20`, `nIterations=100`, `alpha=1.0`, `beta=3.0`, `evaporation=0.5`, `q=100.0`. Complexity of the ACO core: `O(nIterations × nAnts × n²)`.

Run the algorithm on its own as a demo:

```bash
cd algo && python3 main.py
```

---

## Testing

Automated unit tests cover the algorithm:

```bash
cd algo && python3 test.py
```

Expected output:

```
Place ......... OK   Distance ...... OK   Matrix ........ OK
Tour length ... OK   Build tour .... OK   Choose next ... OK
Run ........... OK
All tests passed!
```

Web features (authentication, sharing, access control) are covered by the manual **Test Protocol** document.

---

## Security

- **SQL injection** — all queries use PDO prepared statements with bound parameters.
- **Passwords** — hashed with `password_hash()` (bcrypt); verified with `password_verify()`.
- **XSS** — dynamic output escaped with `htmlspecialchars()`.
- **Access control** — PHP sessions, a `checkLogin()` guard on protected pages, and an ownership check on every trip/share operation.

---

## Known limitations & future work

- Some pages include `config/dataBase.php` while others use `config/database.php`; unify the name (`database.php`) for reliable deployment on case-sensitive (Linux) file systems.
- `loginCheck.php` redirects without `exit()` after `header()`; add `exit()` to stop the page from continuing.
- The `optimize` action in `share.php` is reachable by any visitor of a public link; restrict it to the owner.
- Move DB credentials, the Python service URL and the GeoNames key into environment variables.

---

Github Link: https://github.com/Leorc9/box-certif

---

## Team

Léo Roussel--Cousin - Marc Maalouf - Lucien Paul-Constant

_Final Certification Box 2025/2026 - Bachelor in Digital Sciences._
