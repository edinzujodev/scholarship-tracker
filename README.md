# Scholarship Tracker

A B2B partner portal for reviewing scholarship applicants. Partner organisations run scholarship campaigns; for each campaign they see summary metrics and a list of applicants ranked by an AI match score, and shortlist or reject them.

Built as a full-stack practical task for ScholarshipOwl: a Laravel JSON:API backend and a Nuxt frontend, both fully tested.

## Features

- **Campaign list**: each partner sees only their own campaigns, with application counts, eligible counts, per-status counts and the average AI score.
- **Applicant summary per campaign**: totals, eligible count, average score and top-tier highlights, plus a filtered, sorted, paginated list of applicants.
- **Review actions**: Shortlist, Reject or move back to Pending, with optimistic UI updates that roll back if the API refuses.
- **Filters and sorting**: minimum score slider, status, application date range, sort by score, date or name, page size.
- **Applicant cards** with name, colour-coded score, application date and AI-generated highlights; loading skeletons and empty states.
- **Tenant isolation**: one partner can never see or change another partner's data. Foreign ids return the same 404 as unknown ones.
- **Strict JSON:API**: validated input at every layer, JSON:API error objects, content negotiation (415/406).
- **Security**: partner token kept server-side behind a Nuxt backend-for-frontend, nonce-based Content-Security-Policy, applicant names and emails encrypted at rest (Google Cloud KMS), append-only audit log of review decisions.
- **Observability**: one `X-Request-Id` traced from the browser through Nuxt and Laravel to the database, with redacted JSON logs.

## Tech stack

```
backend/        Laravel 13 · PHP 8.2+ · Doctrine ORM 3 · PHPUnit
frontend/       Nuxt 4 · Vue 3 · Pinia · PrimeVue 5 · Tailwind 4 · TypeScript · Vitest · Playwright
TASK_REPORT.md  Design notes, architecture, AI tooling write-up and the incident/architecture task
```

## Quick start

```bash
# Backend: API on http://127.0.0.1:8000
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan doctrine:schema:create
php artisan db:seed
php artisan serve

# Frontend: portal on http://localhost:3000 (in a second terminal)
cd frontend
npm install
cp .env.example .env
NUXT_BACKEND_URL=http://127.0.0.1:8000 npm run dev
```

The frontend also runs on its own with `npm run dev`, using a built-in mock API. The full setup, test commands and API examples are below; the design decisions and architecture are in [TASK_REPORT.md](TASK_REPORT.md).

## Prerequisites

- PHP 8.2+ with `pdo_sqlite` and Composer 2
- Node 20+ (tested on 22) and npm

Tests use in-memory SQLite, and local runs use a SQLite file.

## Backend

```bash
cd backend
composer install
cp .env.example .env          # already uses sqlite + file cache
php artisan key:generate
php artisan test              # 166 tests: feature + unit
```

Run it locally with demo data:

```bash
touch database/database.sqlite            # PowerShell: New-Item database/database.sqlite
php artisan doctrine:schema:create
php artisan db:seed                       # 4 campaigns with fixed ids (see the seeder)
php artisan serve                         # http://127.0.0.1:8000
```

The backend is an API only. Opening its root URL in a browser redirects to the partner portal at `FRONTEND_URL` (default `http://localhost:3000`). The API is under `/api/v1`, and `/up` is the health check.


Token and authorisation curl 

```bash
# the partner's campaigns (only their own), each with a link to its applicant summary
curl -H "Authorization: Bearer demo-partner-token" http://127.0.0.1:8000/api/v1/campaigns

curl -g -H "Authorization: Bearer demo-partner-token" \
  "http://127.0.0.1:8000/api/v1/campaigns/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01/applicant-summary?min_score=80&page[size]=5"

# filters + sort (JSON:API style)
curl -g -H "Authorization: Bearer demo-partner-token" \
  "http://127.0.0.1:8000/api/v1/campaigns/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01/applicant-summary?filter[status]=pending&filter[applied_from]=2026-09-01&filter[applied_to]=2026-09-15&sort=-appliedAt"

# empty campaign → zeroed summary
curl -H "Authorization: Bearer demo-partner-token" \
  http://127.0.0.1:8000/api/v1/campaigns/3c6e2b8a-1f4d-4e5a-8b7c-2d9e0f1a2b3c/applicant-summary

```

### Partner tokens (mock authentication)

There is no login screen. There are two mock tokens, one for each of two pretend partner organisations. The second partner exists to prove that partners can't see each other's data. Both tokens are defined in [backend/config/partners.php](backend/config/partners.php) and work out of the box: **nothing needs to be set in any `.env` file**.

| Token | Partner | Owns |
|---|---|---|
| `demo-partner-token` | `partner-demo` | Global Scholars, Engineering Fellowship, Spring Pilot |
| `other-partner-token` | `partner-other` | Northbridge Bursary 2026 |

**Switching partner in the portal: one change, in `frontend/.env` only.** The backend already accepts both tokens, so the portal only has to choose which one to send:

```dotenv
# frontend/.env
NUXT_PARTNER_TOKEN=other-partner-token
```

Restart `npm run dev` after changing it. Without this line, the portal acts as `partner-demo`. With curl, choose the partner by the token in the `Authorization` header, as in the examples above. For the cookie and header alternatives, see "Campaign page access checks" below.

**Changing a token's value: two changes, rarely needed.** Do this only to replace a token string, for example with a secret of your own. The backend must learn the new value and the portal must send it:

```dotenv
# backend/.env
PARTNER_OTHER_TOKEN=my-secret-123

# frontend/.env
NUXT_PARTNER_TOKEN=my-secret-123
```

The old value then returns 401. An empty token, or two partners with the same token, stops the backend at startup with a clear message.

### Strict validation

Every input is validated strictly, at three layers. Nothing is silently ignored, trimmed or coerced.

**1. HTTP layer.** Every endpoint, enforced by the form requests and the `EnsureJsonApiMediaType` middleware.

| Input | Rule | Failure |
|---|---|---|
| Query parameter names | Each endpoint lists the parameters it supports; anything else is rejected, including JSON:API families we don't implement (`include`, `fields`). Summary: `min_score`, `filter`, `sort`, `page`. Campaign list: `page`. Single campaign and PATCH: none. | **400** `unsupported_parameter`, one error per unknown name |
| Keys inside `filter[…]` / `page[…]` | Only `filter[status\|applied_from\|applied_to]` and `page[size\|number]` | 422 |
| `min_score`, `page[size]`, `page[number]` | Canonical non-negative integers: digits only. Rejects `+80`, ` 80`, `80.0`, `8e1`, `080`, an empty value, and arrays. Ranges: `min_score` 0 to 100, `page[size]` 1 to 100, `page[number]` 1 to 100000. | 422 |
| `filter[status]` | Exactly `pending`, `shortlisted` or `rejected` (case-sensitive, not empty) | 422 |
| `filter[applied_from]` / `filter[applied_to]` | A real calendar date in `YYYY-MM-DD` (rejects `2026-02-30` and times); `to` on or after `from` | 422 |
| `sort` | One allowed value (`-aiScore`, `aiScore`, `-appliedAt`, `appliedAt`, `name`, `-name`); no lists, no arrays | **400**, as JSON:API requires |
| Ids in URLs | UUID (`campaignId`, `applicationId`) | 422 |
| Request body media type | `application/vnd.api+json` only, with no parameters other than `ext`/`profile` (plain `application/json` is rejected) | **415** |
| Request body syntax | A well-formed JSON object: not empty, not truncated, not an array or scalar | **400** `malformed_body` |
| PATCH document members | Top level: `data`, `meta`, `jsonapi`. `data`: `type`, `id`, `attributes`, `meta`. `data.attributes`: `status` only. Anything else, such as `relationships` or a read-only `aiScore`, is rejected and nothing is written. | 422 with a JSON pointer, e.g. `/data/attributes/aiScore` |
| PATCH `data.type` / `data.id` | String / UUID; must match the URL | 422 for the wrong type or format; **409** when they disagree with the URL |
| `Accept` | JSON:API entries must not all carry unsupported parameters | **406** |
| Authorization | `Bearer` token compared in constant time | **401** |

Laravel's global string trimming is switched off for `/api/*`, so the API validates exactly what the client sent: `" 80"` is an error, not `80`.

**2. Domain layer** (`App\Support\Guard`, used by every entity). Entities refuse invalid state wherever it comes from (API, seeder, a future import):
- `Campaign`: partner id 1 to 64 characters, name 1 to 255, trimmed.
- `Applicant`: first and last name 1 to 100 characters, optional middle name up to 100 (blank becomes null), a valid email of at most 255 characters stored in lower case.
- `Application`: AI score 0 to 100; highlights a list of at most 20 non-empty strings, each at most 500 characters.
- Every entity: any id passed in must be a UUID, and is stored in lower case.

These limits match the database column sizes, so the database never truncates or rejects what the entity accepted.

**3. Frontend.**
- The campaign page checks the URL id before rendering (see "Campaign page access checks").
- The proxy rejects non-JSON:API or malformed PATCH bodies with the same 415 and 400 as the API.
- The in-repo mock enforces every rule above, so the frontend tests can't pass against a looser fake.
- The UI only produces valid input: a 0 to 100 slider in steps of 5, a date-range picker, fixed status, sort and page-size options.

All PHP files declare `strict_types=1`, and the TypeScript build runs in `strict` mode.

Tests: [StrictValidationTest](backend/tests/Feature/Api/StrictValidationTest.php) (backend, 39 cases), [DomainInvariantsTest](backend/tests/Unit/DomainInvariantsTest.php) (17 cases), and [strictValidation.spec.ts](frontend/tests/unit/strictValidation.spec.ts) for the mock.

### Manual API checks: auth, validation, isolation, content negotiation

Run these with the backend serving on port 8000. They use bash syntax, so run them in Git Bash; in PowerShell, type `curl.exe` instead of `curl`. Add `-i` to any command to see the status line and headers.

```bash
C=9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01       # partner-demo's campaign
O=7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d       # partner-other's campaign
A=00000000-0000-4000-8000-000000000002       # an application in $C
API=http://127.0.0.1:8000/api/v1

# 401: no token, then an unknown token (both answer with WWW-Authenticate: Bearer)
curl -i $API/campaigns/$C/applicant-summary
curl -i -H "Authorization: Bearer wrong-token" $API/campaigns/$C/applicant-summary

# 422: campaign id is not a UUID → errors[0].source.parameter = "campaignId"
curl -i -H "Authorization: Bearer demo-partner-token" $API/campaigns/not-a-uuid/applicant-summary

# 404: a well-formed id that does not exist ...
curl -i -H "Authorization: Bearer demo-partner-token" $API/campaigns/11111111-2222-4333-8444-555555555555/applicant-summary
# ... and another partner's campaign: the same 404, so ids cannot be probed
curl -i -H "Authorization: Bearer demo-partner-token" $API/campaigns/$O/applicant-summary
# the owner gets 200 for the very same URL
curl -i -H "Authorization: Bearer other-partner-token" $API/campaigns/$O/applicant-summary

# 415: a JSON:API body whose media type has an unsupported parameter (version=2)
curl -i -X PATCH $API/applications/$A \
  -H "Authorization: Bearer demo-partner-token" \
  -H "Content-Type: application/vnd.api+json; version=2" \
  -d "{\"data\":{\"type\":\"applications\",\"id\":\"$A\",\"attributes\":{\"status\":\"rejected\"}}}"

# 406: every JSON:API entry in Accept carries an unsupported parameter
curl -i -H "Authorization: Bearer demo-partner-token" \
  -H "Accept: application/vnd.api+json; charset=utf-8" $API/campaigns/$C/applicant-summary

# 200: the same PATCH with a plain JSON:API content type succeeds
curl -i -X PATCH $API/applications/$A \
  -H "Authorization: Bearer demo-partner-token" \
  -H "Content-Type: application/vnd.api+json" \
  -d "{\"data\":{\"type\":\"applications\",\"id\":\"$A\",\"attributes\":{\"status\":\"pending\"}}}"
```

The same cases run automatically in the test suite:

```bash
php artisan test --filter=ApplicantSummaryTest              # 401, 422 (8 cases), 404 unknown and foreign
php artisan test --filter=ApplicationStatusTest             # PATCH: status per application, cross-partner 404
php artisan test --filter=JsonApiContentNegotiationTest     # 415 and 406
php artisan test --filter=CampaignIndexTest                 # campaign list is partner-scoped
```

## Frontend

```bash
cd frontend
npm install
cp .env.example .env               # includes the free PrimeUI community key (PrimeVue 5 needs one)
npm test                              # Vitest: 94 component/store tests
npx playwright install chromium       # first time only
npm run test:e2e                      # Playwright: builds, serves and runs 18 E2E tests (mock API)
npm run typecheck
```

Run the portal:

```bash
npm run dev                           # http://localhost:3000, uses the built-in mock API
```

To run the portal against the real Laravel API, start the backend as above first. Then:

```bash
NUXT_BACKEND_URL=http://127.0.0.1:8000 npm run dev        # PowerShell: $env:NUXT_BACKEND_URL="http://127.0.0.1:8000"; npm run dev
```

The browser only talks to the Nuxt server at `/api/portal/*`. Nuxt forwards requests to Laravel with the partner token (`NUXT_PARTNER_TOKEN`, default `demo-partner-token`), so the token never reaches the browser. You can replace it with `other-partner-token` to test things out

The portal opens on the campaign list at `/`. Click a campaign to open its applicants at `/campaigns/<campaignId>`, and use "All campaigns" to go back.

### Campaign page access checks

Before a campaign page renders, a route middleware ([middleware/campaign-access.ts](frontend/middleware/campaign-access.ts)) checks three things. It runs on the server for a direct page load and in the browser for in-app navigation, so the page component never starts for an inaccessible campaign.

1. **Format.** The campaign id must be a UUID. This is checked locally, with no API call.
2. **Existence and ownership.** The middleware calls `GET /api/v1/campaigns/{campaignId}` through the Nuxt proxy. An unknown campaign and another partner's campaign get the same answer.
3. **Authorization.** The proxy authenticates as the caller (see mock auth below). Laravel, or the mock, rejects an unknown token.

If any check fails, Nuxt renders the dedicated **"Page not accessible"** page ([error.vue](frontend/error.vue)) with the matching HTTP status, instead of an error inside the normal page:

| Situation | HTTP status | Page says |
|---|---|---|
| Campaign id is not a UUID | **400** | This link is not valid |
| Missing or invalid partner token | **401** | You don't have access |
| Campaign doesn't exist, or belongs to another partner | **404** | Campaign not found |
| Campaign service unreachable | 503 | Campaign service unavailable |

**Mock auth.** The Nuxt server picks the partner token for each request from, in order:
1. an `Authorization: Bearer <token>` header on the page request,
2. a `portal_session` cookie,
3. `NUXT_PARTNER_TOKEN` (default `demo-partner-token`). Set it to an empty string to require an explicit header or cookie.

The same token is used for the page's data calls, so the whole page acts as one partner. Valid tokens are `demo-partner-token` and `other-partner-token`, as in `backend/config/partners.php`.

Try it with the portal running on port 3000. Send `Accept: text/html` as a browser does; without it, Nuxt answers errors as JSON.

```bash
P=http://localhost:3000/campaigns
curl -i -H "Accept: text/html" $P/not-a-uuid                                     # 400
curl -i -H "Accept: text/html" $P/11111111-2222-4333-8444-555555555555           # 404 unknown
curl -i -H "Accept: text/html" $P/7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d           # 404 another partner's
curl -i -H "Accept: text/html" -H "Authorization: Bearer wrong-token" \
  $P/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01                                         # 401 bad header
curl -i -H "Accept: text/html" -H "Cookie: portal_session=wrong-token" \
  $P/9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01                                         # 401 bad cookie
curl -i -H "Accept: text/html" -H "Cookie: portal_session=other-partner-token" \
  $P/7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d                                         # 200: the other partner's own campaign
```

In a browser, open `/campaigns/not-a-uuid`, or set a `portal_session` cookie in the dev tools and reload. The automated versions are in [tests/e2e/access.spec.ts](frontend/tests/e2e/access.spec.ts), which also covers in-app navigation and the "Back to campaigns" button.
