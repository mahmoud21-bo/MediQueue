# Platform Admin Doctor Verification API

Base URL: `/api/v1`

All admin endpoints require a Sanctum bearer token belonging to a user with the `platform_admin` role. There is no public admin-registration endpoint.

## Create the platform admin

Configure all five variables locally. Do not commit their values:

```dotenv
ADMIN_FIRST_NAME=
ADMIN_LAST_NAME=
ADMIN_EMAIL=
ADMIN_PHONE=
ADMIN_PASSWORD=
```

`ADMIN_PASSWORD` must contain at least 12 characters with uppercase and lowercase letters, a number, and a symbol. The seeder also validates the email and normalized phone number. If every value is empty, `AdminSeeder` safely skips admin creation. If only some values are configured, seeding fails rather than creating an incomplete account.

```bash
php artisan db:seed --class=AdminSeeder
```

## Admin authentication

### Login

`POST /api/v1/admin/auth/login`

```json
{
  "email": "admin@example.test",
  "password": "a-locally-configured-password"
}
```

Only active, non-deleted `platform_admin` users can log in. A successful response includes a plain-text Sanctum token and updates `last_login_at`.

### Current admin

`GET /api/v1/admin/auth/me`

Requires `auth:sanctum` and `role:platform_admin`.

### Logout

`POST /api/v1/admin/auth/logout`

Revokes the bearer token used for the request.

## Application statistics

`GET /api/v1/admin/doctor-applications/statistics`

```json
{
  "success": true,
  "message": "Doctor application statistics retrieved successfully",
  "data": {
    "total": 100,
    "pending": 20,
    "approved": 70,
    "rejected": 10
  }
}
```

## List applications

`GET /api/v1/admin/doctor-applications`

Supported query parameters:

| Parameter | Rules | Description |
| --- | --- | --- |
| `search` | String, maximum 255 characters | Searches full name, email, phone, and medical-license number. |
| `verification_status` | `pending`, `approved`, or `rejected` | Filters by current state. |
| `specialty_id` | Existing specialty ID | Filters by specialty. |
| `application_date` | `YYYY-MM-DD` | Filters by submission date. |
| `sort` | `newest` or `oldest` | Defaults to `newest`. |
| `per_page` | Integer from 1 to 100 | Defaults to 15. |
| `page` | Positive integer | Selects the result page. |

The paginated resource includes doctor identity, specialty, license, experience, status, submission/review dates, and rejection reason. `doctor_id` is the user ID used by the detail and review routes.

## Application details

`GET /api/v1/admin/doctor-applications/{doctor}`

Returns account information, professional profile, specialty, biography, current verification status, reviewer information, audit history, clinic information when a clinic module is available, and verification-document metadata.

No storage disk or path is returned. Every document provides only an authorized API endpoint.

## Private verification documents

`GET /api/v1/admin/doctor-applications/{doctor}/documents/{document}`

The default response streams the document inline. Add `?download=1` for an attachment. The endpoint verifies both the admin role and that the document belongs to the doctor in the URL; a mismatched doctor/document pair returns `404`.

Documents use the private `local` disk rooted at `storage/app/private`. No public storage URL is generated.

## Approve an application

`PATCH /api/v1/admin/doctor-applications/{doctor}/approve`

No request body is required. The operation:

1. Locks the doctor-profile row inside a database transaction.
2. Requires the current state to be `pending`.
3. Sets `verification_status`, `reviewed_by`, `reviewed_at`, and `approved_at`.
4. Clears `rejection_reason`.
5. Writes a `doctor_verification_audits` record.
6. Sends a database notification to the doctor.

An approved doctor immediately passes the `doctor.approved` middleware.

## Reject an application

`PATCH /api/v1/admin/doctor-applications/{doctor}/reject`

```json
{
  "rejection_reason": "The medical license document is unclear."
}
```

The reason is required and must contain 10–1000 characters. The transaction locks the row, changes the state to `rejected`, records the admin and review time, clears `approved_at`, writes an audit, and sends a database notification. The doctor can read the reason from `GET /api/v1/doctor/auth/verification-status`.

## State conflicts and errors

The only permitted transitions are:

- `pending` → `approved`
- `pending` → `rejected`

Reviewing an application that is no longer pending returns HTTP `409 Conflict`. Validation failures return `422`, absent/invalid tokens return `401`, role or policy failures return `403`, and unknown or mismatched resources return `404`.

## Postman

Import these files:

- `docs/postman/MediQueue.postman_collection.json`
- `docs/postman/MediQueue.local.postman_environment.json`

Select the `MediQueue Local` environment. Set `base_url` if the API is not running at `http://localhost:8000`. Patient, doctor, and admin login requests automatically save their returned tokens into `patient_token`, `doctor_token`, and `admin_token` respectively.

The exported environment contains no credentials or tokens. Request passwords in the collection are explicit non-secret examples and must be replaced with local test-account credentials.
