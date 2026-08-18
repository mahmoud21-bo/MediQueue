# MediQueue Authentication API

Base URL: `/api/v1`

All request and response bodies use JSON. Send protected requests with:

```http
Authorization: Bearer <plain-text-token>
Accept: application/json
```

Tokens are returned only by registration and login. Sanctum token expiration is disabled, so mobile tokens remain valid until explicitly revoked. Each login creates a separate device token. Emails are stored in lowercase, and phone numbers are reduced to digits with an optional leading `+`. Passwords must be at least eight characters and contain uppercase and lowercase letters, a number, and a symbol.

Clients cannot submit a role. The patient and doctor registration services assign their corresponding Spatie role inside a database transaction.

## Response shape

Registration and login return:

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "first_name": "Mona",
      "last_name": "Ali",
      "email": "mona@example.com",
      "phone": "+15551234567",
      "is_active": true
    },
    "profile": {},
    "role": "patient",
    "verification_status": null,
    "token": "1|plain-text-sanctum-token",
    "token_type": "Bearer"
  }
}
```

Authenticated profile responses have the same identity fields but omit `token` and `token_type`. Doctor responses set `verification_status` to `pending`, `approved`, or `rejected`.

Validation failures return HTTP `422`, missing/invalid tokens return `401`, role violations return `403`, and throttled registration/login requests return `429`.

## Patient endpoints

### Register

`POST /api/v1/patient/auth/register` — limited to 5 requests per minute per IP.

```json
{
  "first_name": "Mona",
  "last_name": "Ali",
  "email": "mona@example.com",
  "phone": "+1 (555) 123-4567",
  "password": "Valid#Pass1",
  "password_confirmation": "Valid#Pass1",
  "date_of_birth": "1995-05-12",
  "gender": "female",
  "device_name": "Mona iPhone"
}
```

`gender` accepts `male`, `female`, or `other`. Successful registration returns HTTP `201`, creates the user and patient profile, assigns `patient`, and returns a Sanctum token.

### Login

`POST /api/v1/patient/auth/login` — limited to 10 requests per minute per normalized email/IP pair.

```json
{
  "email": "mona@example.com",
  "password": "Valid#Pass1",
  "device_name": "Mona iPhone"
}
```

Only active, non-deleted users with the `patient` role can log in here. `device_name` is accepted and defaults to `Patient Device` when omitted. A successful login updates `last_login_at` and creates a new token without revoking other devices.

### Current patient

`GET /api/v1/patient/auth/me` — requires `auth:sanctum` and `patient`.

### Update patient profile

`PATCH /api/v1/patient/profile` — requires `auth:sanctum` and `patient`.

All fields are optional, but supplied fields must be valid. Accepted fields are `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, and `gender`. The server always updates the token owner; submitted identity IDs are ignored.

### Logout

- `POST /api/v1/patient/auth/logout` revokes only the bearer token used for the request.
- `POST /api/v1/patient/auth/logout-all` revokes all tokens belonging to the authenticated patient.

Both require `auth:sanctum` and `patient`.

## Doctor endpoints

### Register

`POST /api/v1/doctor/auth/register` — limited to 5 requests per minute per IP.

```json
{
  "first_name": "Omar",
  "last_name": "Hassan",
  "email": "omar@example.com",
  "phone": "+15550001111",
  "password": "Valid#Pass1",
  "password_confirmation": "Valid#Pass1",
  "specialty_id": 1,
  "medical_license_number": "LIC-1000",
  "years_of_experience": 7,
  "bio": "Board-certified physician.",
  "device_name": "Omar Android"
}
```

`specialty_id` must reference an existing specialty. `bio` is nullable. Successful registration returns HTTP `201`, creates the user and doctor profile, assigns `doctor`, sets `verification_status` to `pending`, and returns a Sanctum token.

### Login

`POST /api/v1/doctor/auth/login` — limited to 10 requests per minute per normalized email/IP pair.

```json
{
  "email": "omar@example.com",
  "password": "Valid#Pass1",
  "device_name": "Omar Android"
}
```

Only active, non-deleted users with the `doctor` role can log in here. `device_name` is accepted and defaults to `Doctor Device` when omitted. Pending and rejected doctors may log in.

### Current doctor

`GET /api/v1/doctor/auth/me` — requires `auth:sanctum` and `doctor`.

### Verification status

`GET /api/v1/doctor/auth/verification-status` — requires `auth:sanctum`, `active`, and `doctor`.

Returns `verification_status`, `reviewed_at`, `approved_at`, and `rejection_reason`. Pending and rejected doctors can access this endpoint.

### Update doctor profile

`PATCH /api/v1/doctor/profile` — requires `auth:sanctum` and `doctor`.

Accepted fields are `first_name`, `last_name`, `email`, `phone`, `specialty_id`, `medical_license_number`, `years_of_experience`, and `bio`. Verification fields cannot be changed through this route, and the server always updates the token owner.

### Logout

- `POST /api/v1/doctor/auth/logout` revokes only the current bearer token.
- `POST /api/v1/doctor/auth/logout-all` revokes all tokens belonging to the authenticated doctor.

Both require `auth:sanctum` and `doctor` and remain available regardless of verification status.

## Mobile device sessions

These shared endpoints require `auth:sanctum`, an active account, and either the `patient` or `doctor` role:

- `GET /api/v1/auth/sessions` lists only the authenticated user's device tokens. It returns token IDs, device names, current-session flags, usage timestamps, and expiration metadata—never token hashes or plain-text tokens.
- `DELETE /api/v1/auth/sessions/{tokenId}` revokes one device token owned by the authenticated user. Another user's token ID returns `404`.

Logout revokes only the current bearer token. Logout-all revokes every token belonging to the user. Deactivating an account or changing its password revokes every token. The `active` middleware also blocks an inactive account whose token survived an out-of-band database update and removes its remaining tokens.

## Doctor approval guard

Future doctor schedules, appointment management, medical-record creation, and access to patient medical information must use all three middleware:

```php
['auth:sanctum', 'role:doctor', 'doctor.approved']
```

`doctor.approved` maps to `EnsureDoctorIsApproved` and returns HTTP `403` unless the profile status is `approved`. No doctor dashboard, schedule, appointment-management, or medical-record-creation endpoint is included in this implementation.
