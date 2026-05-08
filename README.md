# IPTV Backend API

A slim PHP 8.4 REST API that handles auth, user profiles, Xtream IPTV credential management, watch progress, favorites, and regional trending via TMDB. Built on RoadRunner persistent workers with no full framework.

---

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.4 + RoadRunner (persistent workers) |
| Router | `symfony/routing` |
| HTTP | `symfony/http-foundation` + `nyholm/psr7` |
| ORM / DB | `doctrine/orm` + `doctrine/dbal` |
| Auth | `symfony/security-core` (Argon2id hashing) + `firebase/php-jwt` |
| Cache | `predis/predis` → Redis |
| Config | `vlucas/phpdotenv` |
| Database | PostgreSQL |

---

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

Copy `.env` and fill in your values:

```bash
cp .env .env.example
```

| Variable | Description |
|---|---|
| `DB_HOST` | PostgreSQL host |
| `DB_PORT` | PostgreSQL port (default `5432`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASSWORD` | Database password |
| `REDIS_HOST` | Redis host |
| `REDIS_PORT` | Redis port (default `6379`) |
| `JWT_SECRET` | Long random string for signing JWTs |
| `APP_KEY` | Base64-encoded 32-byte key for AES-256-GCM encryption of Xtream credentials |
| `APP_URL` | Backend base URL (used in email links) |
| `APP_FRONTEND_URL` | Frontend base URL (used in password reset email) |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port |
| `MAIL_FROM_ADDRESS` | Sender address |
| `MAIL_FROM_NAME` | Sender display name |
| `TMDB_API_KEY` | TMDB v3 API key (from themoviedb.org/settings/api) |

### 3. Configure the test environment

`.env.test` is already committed and contains safe defaults for a local test setup (separate DB `iptv_test`, Redis on port `6380`, dummy JWT/APP keys). You should not need to touch it.

For tests that call real external services, create `.env.test.local` (gitignored — never committed):

```bash
cp .env.test.local.example .env.test.local   # if the example file exists, otherwise create it manually
```

Add your real credentials to `.env.test.local`:

```ini
# Real Xtream credentials — used by SelectProfile integration tests
XTREAM_TEST_URL=http://your-provider.example.com/
XTREAM_TEST_USERNAME=your_username
XTREAM_TEST_PASSWORD=your_password

# TMDB v3 API key — used by Trending integration tests
TMDB_API_KEY=your_tmdb_api_key
```

Tests that require these values call `$this->markTestSkipped()` automatically when the file is absent, so the suite still passes without it.

**Test infrastructure required:**
- A PostgreSQL database named `iptv_test` accessible with the credentials in `.env.test`
- A Redis instance on port `6380` (separate from the dev Redis on `6379`)

### 4. Run migrations

```bash
php bin/migrations.php diff
php bin/migrations.php migrate
```

### 5 Run integration tests

```bash
composer test
```

### 6. Start the server

```bash
composer start
```

The API listens on `http://localhost:8080` by default.

---

## Authentication Model

### Access token

A short-lived JWT (15 minutes) signed with `JWT_SECRET`. Passed as a Bearer token on every protected request:

```
Authorization: Bearer <access_token>
```

**Payload claims:**

| Claim | Type | Description |
|---|---|---|
| `sub` | string (UUID) | User ID |
| `profile_id` | string (UUID) \| null | Active profile ID (set after profile selection) |
| `exp` | int | Unix timestamp expiry |

### Refresh token

A random 64-byte hex string stored in Redis under `refresh:{user_id}` with a 7-day TTL. Sent in the request body to `POST /api/auth/refresh` to obtain a new token pair.

When a profile is selected the refresh key stores `userId|profileId` (pipe-delimited) so refreshed tokens retain the profile context.

---

## Error Format

All error responses follow one of two shapes:

```json
{ "error": "Human-readable message" }
```

```json
{ "errors": { "field": "Validation message", ... } }
```

Common HTTP status codes used throughout the API:

| Code | Meaning |
|---|---|
| `200` | Success |
| `201` | Created |
| `400` | Malformed JSON body |
| `401` | Missing or invalid JWT |
| `403` | Forbidden (e.g. profile belongs to another user) |
| `404` | Resource not found |
| `409` | Conflict (e.g. duplicate email) |
| `422` | Validation / business rule failure |
| `502` | Upstream service error (TMDB) |

---

## Endpoints

### Auth

#### `POST /api/auth/register`

Register a new account. Sends a verification email.

**Request body:**

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

Validation rules:
- `email` — must be a valid email address
- `password` — minimum 8 characters

**Responses:**

`201 Created`
```json
{ "message": "Registration successful. Please check your email to verify your account." }
```

`409 Conflict` — email already registered
```json
{ "error": "Email already registered" }
```

`422 Unprocessable` — validation failures
```json
{ "errors": { "email": "A valid email address is required", "password": "Password must be at least 8 characters" } }
```

---

#### `GET /api/auth/verify-email?token=<token>`

Verifies the email address using the token sent in the registration email.

**Query parameter:** `token` — the signed verification token from the email link

**Responses:**

`200 OK`
```json
{ "message": "Email verified successfully." }
```

`422 Unprocessable` — token missing, invalid, or already used
```json
{ "error": "Invalid or expired verification token" }
```

---

#### `POST /api/auth/login`

Authenticate and receive a JWT token pair.

**Request body:**

```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

**Responses:**

`200 OK`
```json
{
  "access_token": "<jwt>",
  "refresh_token": "<hex_string>",
  "token_type": "Bearer",
  "expires_in": 900
}
```

`401 Unauthorized`
```json
{ "error": "Invalid credentials" }
```

`403 Forbidden` — account email not yet verified
```json
{ "error": "Email not verified" }
```

---

#### `POST /api/auth/refresh`

Exchange a valid refresh token for a new access + refresh token pair. The old refresh token is invalidated.

**Request body:**

```json
{
  "refresh_token": "<hex_string>"
}
```

**Responses:**

`200 OK` — same shape as login response

`401 Unauthorized`
```json
{ "error": "Invalid or expired refresh token" }
```

---

#### `POST /api/auth/logout`

Revoke the current refresh token.

**Request body:**

```json
{
  "refresh_token": "<hex_string>"
}
```

**Responses:**

`200 OK`
```json
{ "message": "Logged out successfully" }
```

---

#### `POST /api/auth/forgot-password`

Send a password reset email.

**Request body:**

```json
{
  "email": "user@example.com"
}
```

**Responses:**

`200 OK` — always returns success regardless of whether the email exists (prevents enumeration)
```json
{ "message": "If that email is registered you will receive a reset link shortly." }
```

---

#### `POST /api/auth/reset-password`

Reset the password using the token from the reset email.

**Request body:**

```json
{
  "token": "<reset_token>",
  "password": "newpassword123"
}
```

Validation: `password` must be at least 8 characters.

**Responses:**

`200 OK`
```json
{ "message": "Password reset successfully." }
```

`422 Unprocessable` — invalid/expired token or password too short
```json
{ "error": "Invalid or expired reset token" }
```

---

### Profiles

All profile endpoints require `Authorization: Bearer <access_token>`.

#### `GET /api/profiles`

List all profiles for the authenticated user.

**Response `200 OK`:**

```json
[
  {
    "id": "uuid",
    "name": "Main",
    "country_code": "NL",
    "has_credentials": true
  }
]
```

---

#### `POST /api/profiles`

Create a new profile.

**Request body:**

```json
{
  "name": "Kids",
  "country_code": "NL"
}
```

- `name` — required, must be unique per user account
- `country_code` — optional, ISO 3166-1 alpha-2 (e.g. `NL`, `US`, `DE`). Used for regional trending.

**Responses:**

`201 Created`
```json
{
  "id": "uuid",
  "name": "Kids",
  "country_code": "NL",
  "has_credentials": false
}
```

`409 Conflict`
```json
{ "error": "A profile with that name already exists" }
```

---

#### `PATCH /api/profiles/{id}`

Update a profile's name and/or country code.

**Request body** (all fields optional):

```json
{
  "name": "Main HD",
  "country_code": "DE"
}
```

Pass `"country_code": ""` to clear the country code.

**Responses:**

`200 OK` — returns the updated profile shape (same as GET)

`404 Not Found`
```json
{ "error": "Profile not found" }
```

`409 Conflict`
```json
{ "error": "A profile with that name already exists" }
```

---

#### `DELETE /api/profiles/{id}`

Delete a profile and all its associated data.

**Responses:**

`200 OK`
```json
{ "message": "Profile deleted" }
```

`404 Not Found`
```json
{ "error": "Profile not found" }
```

---

#### `POST /api/profiles/{id}/credentials`

Save (or replace) Xtream IPTV credentials for a profile. Credentials are AES-256-GCM encrypted at rest.

**Request body:**

```json
{
  "url": "http://provider.example.com",
  "username": "myuser",
  "password": "mypass"
}
```

All three fields are required.

**Responses:**

`200 OK`
```json
{ "message": "Credentials saved" }
```

`404 Not Found`
```json
{ "error": "Profile not found" }
```

---

#### `POST /api/profiles/{id}/select`

Select a profile for the current session. This endpoint:
1. Decrypts the profile's Xtream credentials
2. Tests them against the Xtream provider (`player_api.php`)
3. Issues a new JWT pair with `profile_id` embedded in the access token

Requires a base JWT (from login) without a `profile_id` claim, or an existing profile-scoped JWT.

**Responses:**

`200 OK` — same shape as login response, access token now carries `profile_id`
```json
{
  "access_token": "<jwt_with_profile_id>",
  "refresh_token": "<hex_string>",
  "token_type": "Bearer",
  "expires_in": 900
}
```

`404 Not Found` — profile does not exist or belongs to another user
```json
{ "error": "Profile not found" }
```

`422 Unprocessable` — profile has no credentials saved
```json
{ "error": "No credentials found for this profile" }
```

`401 Unauthorized` — Xtream credentials are invalid
```json
{ "error": "Invalid IPTV credentials" }
```

---

### IPTV Credentials

Requires a **profile-scoped JWT** (obtained via `POST /api/profiles/{id}/select`).

#### `GET /api/credentials`

Returns the decrypted Xtream credentials for the active profile. The client should use these to call the Xtream API directly and cache the channel/VOD/series lists in IndexedDB.

**Response `200 OK`:**

```json
{
  "url": "http://provider.example.com",
  "username": "myuser",
  "password": "mypass"
}
```

`403 Forbidden` — active profile has no credentials
```json
{ "error": "No credentials found for this profile" }
```

---

### Watch Progress

Requires a **profile-scoped JWT**.

#### `GET /api/progress`

Return all watch progress entries for the active profile, ordered by most recently updated.

**Response `200 OK`:**

```json
[
  {
    "stream_id": "12345",
    "stream_type": "movie",
    "timestamp_seconds": 3720,
    "updated_at": "2026-05-08T14:22:00+00:00"
  }
]
```

---

#### `GET /api/progress/{stream_id}`

Get the watch progress for a single stream.

**Response `200 OK`:**

```json
{
  "stream_id": "12345",
  "stream_type": "movie",
  "timestamp_seconds": 3720,
  "updated_at": "2026-05-08T14:22:00+00:00"
}
```

`404 Not Found`
```json
{ "error": "No progress found for this stream" }
```

---

#### `POST /api/progress/{stream_id}`

Create or update watch progress for a stream (UPSERT). Call this periodically while playing (e.g. every 15 seconds) and on pause/close.

**Request body:**

```json
{
  "stream_type": "movie",
  "timestamp_seconds": 3720
}
```

| Field | Type | Allowed values |
|---|---|---|
| `stream_type` | string | `live`, `movie`, `series_episode` |
| `timestamp_seconds` | int | >= 0 |

**Responses:**

`200 OK`
```json
{ "message": "Progress saved" }
```

`422 Unprocessable`
```json
{ "error": "timestamp_seconds is required" }
{ "error": "timestamp_seconds must be non-negative" }
{ "error": "stream_type must be one of: live, movie, series_episode" }
```

---

### Favorites

Requires a **profile-scoped JWT**.

#### `GET /api/favorites`

Return all favorites for the active profile, ordered by most recently added.

**Response `200 OK`:**

```json
[
  {
    "stream_id": "99",
    "stream_type": "movie",
    "created_at": "2026-05-08T10:00:00+00:00"
  }
]
```

---

#### `POST /api/favorites`

Add a stream to favorites.

**Request body:**

```json
{
  "stream_id": "99",
  "stream_type": "movie"
}
```

| Field | Type | Allowed values |
|---|---|---|
| `stream_id` | string | Any Xtream stream ID |
| `stream_type` | string | `live`, `movie`, `series` |

**Responses:**

`201 Created`
```json
{ "message": "Added to favorites" }
```

`409 Conflict` — already in favorites
```json
{ "error": "Already in favorites" }
```

`422 Unprocessable`
```json
{ "error": "stream_type must be one of: live, movie, series" }
```

---

#### `DELETE /api/favorites/{stream_id}`

Remove a stream from favorites.

**Responses:**

`200 OK`
```json
{ "message": "Removed from favorites" }
```

`404 Not Found`
```json
{ "error": "Favorite not found" }
```

---

### Trending

Requires a **profile-scoped JWT**. The active profile must have a `country_code` set.

#### `GET /api/regional/trending`

Returns this week's trending movies and TV shows from TMDB for the profile's region. Results are cached in Redis for 12 hours per region (`trending:{region_code}`).

**Response `200 OK`:**

```json
{
  "region": "NL",
  "movies": [
    {
      "tmdb_id": 123456,
      "title": "Some Movie",
      "year": "2026",
      "overview": "A brief plot summary.",
      "poster_path": "/abc123.jpg",
      "vote_average": 7.4,
      "media_type": "movie"
    }
  ],
  "tv": [
    {
      "tmdb_id": 654321,
      "title": "Some Show",
      "year": "2026",
      "overview": "A brief plot summary.",
      "poster_path": "/xyz789.jpg",
      "vote_average": 8.1,
      "media_type": "tv"
    }
  ]
}
```

Up to 20 movies and 20 TV shows are returned. `poster_path` is a relative path — prepend `https://image.tmdb.org/t/p/w500` to build the full image URL.

`422 Unprocessable` — profile has no country code
```json
{ "error": "Profile has no country code configured" }
```

`502 Bad Gateway` — TMDB API unavailable
```json
{ "error": "Failed to fetch trending data from TMDB" }
```

---

## Redis Key Conventions

| Key | TTL | Contents |
|---|---|---|
| `refresh:{user_id}` | 7 days | Refresh token or `userId\|profileId` pipe-delimited string |
| `trending:{region_code}` | 12 hours | JSON-encoded TMDB trending response |
| `email_verify:{token}` | 24 hours | User ID for email verification |
| `password_reset:{token}` | 1 hour | User ID for password reset |

---

## Design Notes

**Credentials-only sync model** — The backend does not proxy Xtream API calls. `GET /api/credentials` returns decrypted credentials; the client calls the Xtream provider directly and caches the channel/VOD/series lists in IndexedDB. This keeps PHP worker memory flat regardless of concurrent users.

**Profile-scoped JWTs** — After calling `POST /api/profiles/{id}/select`, the access token carries a `profile_id` claim. Routes that require profile context (credentials, progress, favorites, trending) read this claim directly — no extra database lookup per request.

**AES-256-GCM encryption** — Xtream credentials are encrypted at rest using `APP_KEY`. Each credential set has its own random IV stored alongside the ciphertext.

**Watch progress UPSERT** — Progress saves use raw `INSERT ... ON CONFLICT DO UPDATE` SQL to avoid race conditions when the client fires rapid updates.

**Trending cache** — Trending results are cached per region, not per user. 20 million users in `NL` share one Redis key. The cache is refreshed at most once every 12 hours per region.
