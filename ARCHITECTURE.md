# IPTV App — Backend Architecture Notes

> This document captures the full backend design agreed in the planning session.
> Attach this file (and the diagrams below) to your Claude context in the IDE so it understands the architecture.

---

## Stack

| Layer | Technology |
|---|---|
| PHP runtime | RoadRunner (persistent workers, no framework bootstrap overhead) |
| PHP style | Standalone Symfony components — no full framework |
| Router | `symfony/routing` |
| HTTP | `symfony/http-foundation` + `nyholm/psr7` |
| ORM / DB | `doctrine/orm` + `doctrine/dbal` |
| Auth primitives | `symfony/security-core` (password hashing) |
| JWT | `firebase/php-jwt` |
| Cache / queue | `predis/predis` → Redis |
| Config | `vlucas/phpdotenv` |
| Database | PostgreSQL |

---

## Core Principles

- **PHP never touches video bytes.** Stream URLs are returned to the client and played directly from the provider's CDN.
- **Xtream Codes API** is the IPTV provider protocol. Credentials are stored AES-256 encrypted per profile.
- **Everything is profile-scoped.** A user account can have multiple profiles (like Netflix). The JWT encodes both `user_id` and `profile_id` after profile selection.
- **Redis is the performance layer.** Channel list cache, refresh tokens, and regional trending all live in Redis with appropriate TTLs.

---

## Feature Set

### 1. Auth
- Users register with email + password (argon2id hashed)
- Email verification via signed token
- Login returns a **JWT access token (15 min TTL)** and a **refresh token (7 days, stored in Redis)**
- JWT middleware validates signature + expiry on every protected route
- `user_id` + `profile_id` injected into request context — no extra DB lookup per request

### 2. Profiles & IPTV Credentials
- Each user account has 1–N profiles
- Each profile stores Xtream credentials (provider URL, username, password) — **AES-256 encrypted at rest**
- Credentials are decrypted server-side only when making Xtream API calls

### 3. IPTV Data Sync (`GET /api/sync`)
- PHP decrypts credentials and fires all Xtream endpoints **in parallel**:
  - `get_live_categories` + `get_live_streams`
  - `get_vod_categories` + `get_vod_streams`
  - `get_series_categories` + `get_series`
- Full payload is **cached in Redis** keyed by `sync:{cred_hash}` with a **6h TTL**
- Client stores the full response in **IndexedDB** for instant offline browsing
- On app open: if last sync > 6h ago, re-fetch in background

### 4. Watch Progress (`/api/progress`)
- Client sends `POST /api/progress/{stream_id}` every ~15 seconds while playing, and on pause/close
- DB uses `UPSERT` (`INSERT ... ON CONFLICT DO UPDATE`) — no race conditions
- `GET /api/progress` returns all progress for the active profile, ordered by `updated_at`
- `GET /api/progress/{stream_id}` returns a single item's timestamp

**DB table: `watch_progress`**
```
profile_id         uuid   FK
stream_id          string (Xtream stream ID)
stream_type        enum   live | movie | series_episode
timestamp_seconds  int
updated_at         timestamp
PRIMARY KEY (profile_id, stream_id)
```

### 5. Favorites (`/api/favorites`)
- `POST /api/favorites` — add `{ stream_id, stream_type }`
- `DELETE /api/favorites/{stream_id}` — remove
- `GET /api/favorites` — all favorites for active profile

**DB table: `favorites`**
```
profile_id   uuid   FK
stream_id    string
stream_type  enum   live | movie | series
created_at   timestamp
PRIMARY KEY (profile_id, stream_id)
```

### 6. Regional Trending (`GET /api/regional/trending`)
- User's `country_code` stored on their profile (e.g. `NL`)
- PHP calls **TMDB API** `/trending/movie/week?region={code}` and `/trending/tv/week?region={code}`
- TMDB titles are cross-referenced against the user's available streams by normalised title + release year
- Result cached in Redis as `trending:{region_code}` with **12h TTL**
- Returns an array of matching `stream_id`s the client can look up in its local IndexedDB cache

---

## Full API Surface

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/refresh
POST   /api/auth/logout
GET    /api/auth/verify            ?token=...

GET    /api/profiles
POST   /api/profiles
DELETE /api/profiles/{id}
POST   /api/profiles/{id}/credentials

GET    /api/sync                   full IPTV payload (Redis cached 6h)

GET    /api/progress               all progress for active profile
GET    /api/progress/{stream_id}   single item
POST   /api/progress/{stream_id}   { timestamp_seconds }

GET    /api/favorites
POST   /api/favorites              { stream_id, stream_type }
DELETE /api/favorites/{stream_id}

GET    /api/regional/trending      popular in user's region (Redis cached 12h)
```

---

## Redis Key Conventions

```
sync:{cred_hash}         TTL 6h    Full Xtream payload per credential set
trending:{region_code}   TTL 12h   TMDB-matched stream_ids per region
refresh:{user_id}        TTL 7d    Refresh token for JWT rotation
```

---

## Diagrams

The following diagrams were produced during planning. Reference them for the auth, sync, and data flows.

- **Registration flow** — email + password signup, password hashing, email verification
- **Login & JWT flow** — credential check, JWT pair issuance, Redis refresh token, JWT middleware on protected routes
- **IPTV sync flow** — Redis cache check, parallel Xtream fetch, client IndexedDB storage
- **Watch progress & favorites flow** — periodic progress saves, UPSERT pattern, favorites add/remove
- **Regional trending flow** — TMDB lookup, title matching, Redis caching

> **Tip for Claude Code:** attach this file with `@ARCHITECTURE.md` in your prompt.
> To also share the diagrams, screenshot them from the chat and drag them into your Claude Code context,
> or describe the flow you are implementing and reference the section above by name.

---

## Key Implementation Notes

1. **Never skip argon2id** — use `sodium_crypto_pwhash_str()` or Symfony's `NativePasswordHasher`
2. **Encrypt Xtream creds** with `openssl_encrypt()` using AES-256-GCM; store IV alongside ciphertext
3. **UPSERT watch progress** — `INSERT INTO watch_progress ... ON CONFLICT (profile_id, stream_id) DO UPDATE SET timestamp_seconds = $1, updated_at = NOW()`
4. **TMDB title matching** — normalise both sides: `strtolower(preg_replace('/[^a-z0-9]/i', '', $title))` + match release year as tiebreaker
5. **RoadRunner workers reuse DB connections** — use Doctrine's `EntityManager` reset pattern on each request to avoid stale state between workers
