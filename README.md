# IGGI Identity and Access Management

Identity and Access Management (IAM) service for the IGGI platform. This Laravel application manages membership applications, user accounts, authentication tokens, roles, groups, and administrator-related approval workflows. It also exposes user and access information to other IGGI components.

**Repository:** <https://github.com/iggicm/identity-and-access>

> **Implementation note:** This README describes the code currently present on the repository's `master` branch and distinguishes it from the broader workflows in the January 2019 IGGI analysis documents. The repository is an older application and has important setup, schema, and security gaps that must be resolved before production use.

## Contents

- [Role in the IGGI platform](#role-in-the-iggi-platform)
- [Implemented capabilities](#implemented-capabilities)
- [Architecture and technology](#architecture-and-technology)
- [API reference](#api-reference)
- [Configuration](#configuration)
- [Local installation](#local-installation)
- [Integration with other IGGI components](#integration-with-other-iggi-components)
- [Security and operational considerations](#security-and-operational-considerations)
- [Known limitations and verification gaps](#known-limitations-and-verification-gaps)
- [Automated Tests and CI](#automated-tests-and-ci)
- [Docker Configuration](#docker-configuration)
- [Continuous Integration (GitHub Actions)](#continuous-integration-github-actions)
- [Implementation Discrepancies and Findings](#implementation-discrepancies-and-findings)

## Role in the IGGI platform

The January 2019 *Cahier de Charge de l’Application Mobile IGGI* and *Analyse et conception de l’application Mobile IGGI* identify three platform modules:

1. Identity and access;
2. Forum management;
3. Management of documents related to debates and members.

The documents describe IGGI as a ThinkTank/community platform. IAM is responsible for membership requests and approval, user login, user groups, roles and access rights. The forum and document-management modules own debate discussions, debate outcomes/projects, and the associated document archive; those capabilities should not be attributed to this repository.

The requirements describe a member lifecycle in which a person submits an application, an administrator accepts or rejects it, and only an accepted member can access the platform. They also describe moderator applications, administrator promotion and revocation rules, and group/role management.

The repository implements a subset of these identity and access responsibilities and contains integration code for notifying the debate service. It does not implement the forum or the document archive.

## Implemented capabilities

The following are supported by the repository's routes, controllers, and models:

- **Membership applications:** accept application data, validate it, store an uploaded photo, and notify the applicant and administrators by email.
- **Application decisions:** administrators can accept or reject an application. Acceptance creates a user account, creates a password-reset invitation, adds the new user to the `DEFAULT GROUP`, notifies the debate service, and emits a Pusher event.
- **User authentication:** the login endpoint checks the email, password, account enablement, and mandatory-password-reset state before requesting a Passport token.
- **Password management:** request a password-reset invitation, reset a password, and change a password.
- **User state:** list users, retrieve profile photos, change account enablement, revoke tokens on deactivation, and revoke tokens during logout.
- **Groups and roles:** create groups and roles, add or remove members from groups, and assign users or groups to roles.
- **Role scopes:** derive a user's token scopes from roles assigned directly or through group membership.
- **Administrator promotion:** create and sign administrator propositions. The code checks signatures against the administrator role before promoting the proposed user.
- **Member revocation:** create a revocation proposition, query the debate service to determine whether the member has participated in debates, and require administrator signatures where participation is reported.
- **Access-token checks:** validate a token and requested scope, or check whether the authenticated user has an administrator scope.
- **Utility data:** list interest centres and distinct user cities.

### Capabilities described in the requirements, but not established by this repository

The 2019 documents also describe member discovery on a Google Map, online/offline presence indicators, debate suggestions and moderation, debate comments, debate conclusions/results, project creation, and a separate document archive. These are platform requirements assigned across multiple modules. They are not implemented as forum or archive features in this IAM repository.

The analysis document describes moderator applications and administrator approval of those applications. The current API route list does not expose a moderator-application workflow. A `DebateRole` model exists, but its presence alone does not establish that the complete approval workflow is available through this service's API.

## Architecture and technology

### Technology stack

Versions and packages are taken from `composer.json`:

| Component | Repository requirement / use |
|---|---|
| PHP | `^7.1.3` |
| Laravel | `5.7.*` |
| Laravel Passport | `^7.1` for OAuth2 access tokens and API authentication |
| Database | MySQL is the `.env.example` default; Laravel configuration also defines SQLite, PostgreSQL, and SQL Server connections |
| API documentation | `darkaonline/l5-swagger` and `zircote/swagger-php` |
| HTTP client | Guzzle `^6.3`, used for calls to the debate service |
| Real-time events | Pusher PHP server and BeyondCode Laravel WebSockets |
| CORS | `barryvdh/laravel-cors` |
| Email / queues | Laravel mail and queue facilities; application-specific email classes and jobs |
| Front-end assets | Laravel Mix / npm (`package.json`, `webpack.mix.js`) |

This is a legacy dependency stack. The PHP and Laravel constraints are historical and should not be interpreted as a recommendation to deploy an unsupported runtime. Plan a framework/runtime upgrade before exposing the service to the public internet.

### Main application areas

| Path | Responsibility |
|---|---|
| `routes/api.php` | REST API route definitions |
| `routes/web.php` | Laravel web authentication routes and basic web pages |
| `app/Http/Controllers/UserController.php` | Membership applications, login, password workflows, user listing, deactivation, logout, revocation |
| `app/Http/Controllers/RoleController.php` | Role creation, retrieval, membership assignment |
| `app/Http/Controllers/GroupController.php` | Group creation and membership management |
| `app/Http/Controllers/AdministratorpropositionController.php` | Administrator promotion propositions and signatures |
| `app/Http/Controllers/Oauth/ApiAuthProviderService.php` | Passport token issuance/refresh dispatch and token-scope checks |
| `app/Domain/Model/Identity/` | Application, group, password-reset invitation, revocation, and user-related domain models |
| `app/Domain/Model/Access/` | Role, scope, administrator proposition/signature, and debate-role models |
| `app/Providers/AuthServiceProvider.php` | Registers Passport routes, token lifetimes, and scopes |
| `app/Jobs/` and `app/Mail/` | Asynchronous integration calls and email notifications |
| `config/` | Laravel, database, authentication, CORS, mail, queue, and Swagger configuration |
| `database/migrations/` | Only a small set of framework-level migrations; see [Known limitations](#known-limitations-and-verification-gaps) |

### Identity and access data

The code uses the standard Laravel `App\User` model, extended with Passport API tokens. The model includes application-specific attributes such as `userid`, names, contact details, country, city, interest centre, photo, enablement, password-reset state, geographic coordinates, and connection state.

The domain models include:

- `Application`: a membership request and its state (`APPLICATION_RECEIVED`, `APPLICATION_ACCEPTED`, or `APPLICATION_DENIED`);
- `Group`: a named group with a description and a JSON-encoded list of member IDs;
- `Role`: a named role with a description, JSON-encoded user/group assignments, and JSON-encoded scopes;
- `Scope`: a scope key and description registered with Passport;
- `AdministratorProposition` and `AdministratorSignature`: administrator promotion and approval signatures;
- `UserRevokationProposition` and `RevokedUser`: member revocation workflow and retained user data;
- `PasswordResetInvitation`: one-time password-reset invitation records.

Role membership can be direct or inherited through a group. `Role::isUserInRole()` checks both assignment lists. The implementation stores role/group membership and role scopes as JSON strings rather than normalized many-to-many database relationships.

## API reference

Laravel loads `routes/api.php` under the `/api` prefix. The paths below are therefore shown with `/api`.

Authentication labels:

- **Public** — no route-level authentication middleware is applied.
- **Bearer token** — route uses `auth:api` (Passport).
- **Administrator scope** — route uses `auth:api` and Passport's `scope` middleware with the `SUPER_ADMINISTRATOR` and/or `ADMINISTRATOR` values read from environment variables.

The API returns JSON for most operations. Many controller responses use a legacy envelope with `success`, the misspelled field `faillure`, and either `response` or `raison`. Some error paths still return HTTP 200; clients must not rely on HTTP status alone to determine whether an operation succeeded.

### Authentication and user endpoints

| Method | Endpoint | Access | Purpose / inputs visible in code |
|---|---|---|---|
| `GET` | `/api/user` | Bearer token | Return the authenticated user (Laravel Passport example route). |
| `POST` | `/api/users/login` | Public | Login. Requires `grant_type`, `client_id`, `client_secret`, `email`, and `password`; `latitude` and `longitude` are read if supplied. Returns a user descriptor and token data on success. |
| `GET` | `/api/users` | Bearer token | List users other than the authenticated user, with derived administrator/proposition and latest-token metadata. |
| `POST` | `/api/users/{username}/password-reset-request` | Public | Request a password-reset invitation for the email address in `username`. |
| `POST` | `/api/users/{username}/password-reset/{passwordresetinvitationid}` | Public | Reset password. Requires `newpassword` and `newpassword_confirmation`. |
| `POST` | `/api/users/{userid}/change-password` | Bearer token | Change password. Requires `currentpassword`, `newpassword`, and `newpassword_confirmation`. |
| `POST` | `/api/users/{userid}` | Bearer token | Update user state using `action`. Supported action constants include `CHANGE_PASSWORD`, `DEACTIVATE_USER`, and `REACTIVATE_USER`. |
| `POST` | `/api/users/{userid}/logout` | Bearer token | Revoke the authenticated user's tokens and mark the supplied user as disconnected. |
| `POST` | `/api/users/refreshtoken` | Public | Custom refresh-token wrapper. The controller validates `grant_type`, `client_id`, `client_secret`, `username`, `password`, `refresh_token`, and `userid`; see security notes. |
| `POST` | `/api/confirm-access-token` | Bearer token | Requires a `scope` request value and checks it with `tokenCan()`. |
| `GET`, `POST` | `/api/confirm-admin-access-token` | Bearer token | Checks whether the authenticated user has an `ADMINISTRATOR` or `SUPER_ADMINISTRATOR` role scope. |
| `GET` | `/api/users-administrator` | Administrator scope | Return users assigned the `Administrator` role. |
| `GET` | `/api/users-not-admin-and-not-proposed` | Administrator scope | Return users who are neither administrators nor already proposed as administrators. |
| `POST` | `/api/revokedusers/{userid}` | Administrator scope | Start the member-revocation process for the specified user. |
| `POST` | `/api/userrevokationpropositions/{userrevokationpropositionid}` | Administrator scope | Sign a member-revocation proposition. |
| `GET` | `/api/members` | Administrator scope | List eligible members for revocation. |
| `GET` | `/api/userrevokationpropositions-created` | Administrator scope | List pending revocation propositions not yet signed by the authenticated administrator. |
| `GET` | `/api/users-cities` | Public | Return distinct `cityofresidency` values. |
| `GET` | `/api/users/{userid}/photo` | Public | Return the stored user photo. |
| `GET` | `/api/interestcenters` | Public | Return all interest centres. |
| `GET` | `/api/testpusher` | Public | Calls the controller's Pusher test method. |

### Membership application endpoints

| Method | Endpoint | Access | Purpose / inputs visible in code |
|---|---|---|---|
| `POST` | `/api/applications` | Public | Submit a membership application. Requires `firstname`, `lastname`, `gender`, `photo`, `country` (two-character code), `cityofresidency`, `interestcenter`, `email`, and `commenttotheregistration`; `phone` is validated when supplied. `latitude` and `longitude` are optional in the controller. |
| `GET` | `/api/applications` | Administrator scope | List all applications. |
| `POST` | `/api/applications/{applicationid}` | Administrator scope | Accept or reject an application using `action` with `APPLICATION_ACCEPTED` or `APPLICATION_DENIED`. |
| `GET` | `/api/applications/{applicationid}/photo` | Public | Return the application photo. |

On acceptance, the controller creates a user with a temporary default password, marks the account as requiring a password reset, creates a reset invitation, inserts the user into `DEFAULT GROUP`, sends an email, and schedules a call to the debate service. The exact external client experience depends on the configured web client and mail delivery.

### Role and group endpoints

| Method | Endpoint | Access | Purpose / inputs visible in code |
|---|---|---|---|
| `POST` | `/api/roles` | Administrator scope | Create a role. Requires `name`, `description`, and `photo`; optional `groupsplayingrole` and `usersplayingrole` values are JSON strings. |
| `GET` | `/api/roles` | Administrator scope | List all roles. |
| `POST` | `/api/roles/{roleid}` | Administrator scope | Update role membership. Requires `action`; the action is compared with configured values such as `ADD_GROUPS_TO_ROLE`, `ADD_USERS_TO_ROLE`, `REMOVE_GROUPS_TO_ROLE`, and `REMOVE_USERS_TO_ROLE`. Corresponding JSON-string inputs are `groupstoplayrole` or `userstoplayrole`. |
| `GET` | `/api/roles/{roleid}/is-user-in-role/{userid}` | Administrator scope | Check whether a user is in a role. |
| `GET` | `/api/roles/{roleid}/groups-not-playing-role` | Administrator scope | List groups not assigned to a role. |
| `GET` | `/api/roles/{roleid}/users-not-playing-role` | Administrator scope | List users not assigned to a role. |
| `POST` | `/api/groups` | Administrator scope | Create a group. Requires `name` and `description`; optional `members` is a JSON-encoded list of user IDs. |
| `GET` | `/api/groups` | Administrator scope | List all groups. |
| `POST` | `/api/groups/{groupid}` | Administrator scope | Update group membership. Requires `action` and `members` (JSON-encoded user IDs); action values are configured by `ADD_MEMBERS_TO_GROUP` and `REMOVE_MEMBERS_TO_GROUP`. |
| `GET` | `/api/groups/{groupid}/not-members` | Administrator scope | List users not currently in the group. |

### Administrator proposition endpoints

| Method | Endpoint | Access | Purpose / inputs visible in code |
|---|---|---|---|
| `POST` | `/api/administratorpropositions` | Administrator scope | Propose a user for administrator status. Requires `proposeduserid`. |
| `GET` | `/api/administratorpropositions` | Administrator scope | List propositions awaiting the authenticated administrator's signature. |
| `POST` | `/api/administratorpropositions/{administratorpropositionid}` | Administrator scope | Sign a proposition using the authenticated administrator identity. |

The controller stores signatures and promotes the proposed user after it determines that all users in the `Administrator` role have signed. The analysis document describes promotion by unanimity of administrators; the implementation should be reviewed carefully because the code's signature checks and role handling are not fully consistent across methods.

### Utility endpoint

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| `POST` | `/api/wstest` | Public | Test endpoint that attempts to publish a Pusher message using request field `message`. |

### Token usage

Protected endpoints use Passport's API guard. Clients should send the access token as a bearer token:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

The login response is wrapped in the application's legacy response structure and includes a `token` object containing Passport token data plus `deliver_at` and `expired_at` timestamps. The precise token response should be confirmed against the running service and Passport client configuration before implementing a production client.

The `AuthServiceProvider` configures access tokens to expire after one day and refresh tokens after two days. It loads the registered scope definitions from the `scopes` table.

## Configuration

Create a local `.env` from `.env.example` and set values for the environment in which the service will run. Never copy the repository's tracked `.env` into another environment.

### Variables present in `.env.example`

| Variable | Purpose |
|---|---|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Laravel application identity, environment, encryption key, debug mode, and base URL |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection |
| `LOG_CHANNEL` | Logging channel |
| `BROADCAST_DRIVER` | Event broadcasting driver |
| `CACHE_DRIVER`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `SESSION_LIFETIME` | Cache, queue, and session configuration |
| `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | Redis connection, if Redis is selected |
| `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | SMTP/mail delivery |
| `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER` | Pusher credentials and cluster |
| `MIX_PUSHER_APP_KEY`, `MIX_PUSHER_APP_CLUSTER` | Front-end Pusher settings |

### Additional variables referenced by application code

The following names are read directly through `env()` in application code or route definitions, but are **not documented in `.env.example`**. Obtain the correct values from the IGGI deployment configuration; do not guess them.

| Variable | Purpose indicated by code |
|---|---|
| `SUPER_ADMINISTRATOR`, `ADMINISTRATOR` | Passport scope names used by administrator-protected routes |
| `ADD_GROUPS_TO_ROLE`, `ADD_USERS_TO_ROLE`, `REMOVE_GROUPS_TO_ROLE`, `REMOVE_USERS_TO_ROLE` | Role membership action values |
| `ADD_MEMBERS_TO_GROUP`, `REMOVE_MEMBERS_TO_GROUP` | Group membership action values |
| `GROUPS_ADDED_TO_ROLE`, `USERS_ADDED_TO_ROLE` | Event/job identifiers used after role assignment |
| `HOST_WEB_CLIENT` | Base URL used to construct password-reset links |
| `HOST_DEBATE` | Base URL of the debate service |
| `DEBATE_CLIENT_ID`, `DEBATE_CLIENT_SECRET` | OAuth client credentials used for server-to-server debate-service calls |
| `PUSHER_APP_PORT`, `PUSHER_APP_TIMEOUT` | Pusher/WebSocket connection settings referenced by the controller |
| `L5_SWAGGER_GENERATE_ALWAYS` | Controls Swagger document generation |

Other code paths refer to additional configuration values. Review all `env()` calls in the codebase and the deployment environment before creating a production configuration.

## Local installation

The repository does not currently provide a complete, verified local-development setup because the domain database migrations are missing. The following are the conventional Laravel setup steps; the database-schema limitation below must be addressed before the application can be expected to run correctly.

### Prerequisites

- PHP compatible with the repository's Laravel 5.7 dependencies (the declared constraint is PHP `^7.1.3`; use a maintained, compatible environment only after checking dependency compatibility).
- Composer.
- A supported database server and the corresponding PHP PDO extension.
- Node.js and npm if compiling the front-end assets.
- Access to the mail and any external services required for the workflows being exercised.

### Install dependencies

```bash
git clone https://github.com/iggicm/identity-and-access.git
cd identity-and-access

composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with local database and service settings before continuing.

If front-end assets are needed:

```bash
npm install
npm run dev
```

The exact npm scripts are defined in `package.json`; use the script names present in the checked-out revision.

### Database setup

Create an empty database and configure `DB_*` values in `.env`. The repository includes migrations for the default Laravel `users` and `password_resets` tables, queue jobs, failed jobs, and WebSocket statistics.

**Important:** The migrations in `database/migrations/` do not create the application-specific tables used by the code, including `applications`, `groups`, `roles`, `scopes`, administrator propositions/signatures, password-reset invitations, revocation records, and the custom user columns. The single checked-in `users` migration creates only the standard Laravel fields (`name`, `email`, `password`, etc.), while the application code expects additional fields such as `userid`, `firstname`, `enablement`, and `mustresetpassword`.

Consequently, `php artisan migrate` alone is not sufficient to provision the schema required by the application. Obtain the authoritative schema or migration set from the project maintainers before attempting to run the service. Do not run destructive migration commands against any shared or production database.

Once the complete schema is available and configured:

```bash
php artisan migrate
```

Passport also requires its OAuth tables and key material. After confirming that the database schema includes the Passport migrations and that the environment is appropriate, initialize Passport using the commands supported by the installed Passport version:

```bash
php artisan passport:install
```

The repository's migrations do not include the standard Passport migrations in the checked-in migration directory, so verify that these tables are supplied elsewhere before running this command or issuing tokens.

### Run locally

After dependencies, schema, and required service settings are configured:

```bash
php artisan serve
```

The default development URL is `http://127.0.0.1:8000`. API routes are under `/api`.

For a local deployment that uses queued mail or REST jobs, configure a queue backend and run a worker:

```bash
php artisan queue:work
```

The example environment sets `QUEUE_CONNECTION=sync`, which executes queued jobs synchronously. Choose a production queue driver and supervise workers in deployed environments.

## Integration with other IGGI components

### Debate service

The IAM code integrates with the debate service through `HOST_DEBATE` and the OAuth client credentials `DEBATE_CLIENT_ID` / `DEBATE_CLIENT_SECRET`.

Observed integration points include:

- After membership acceptance, a job is dispatched to create the member in the debate service (`api/members`).
- After password reset, a job is dispatched to update the member password (`api/members/{userid}` with an action query parameter).
- During member revocation, IAM calls the debate service's `api/have-participated-to-debates/{userid}` endpoint. If the member has participated, the revocation proposition requires administrator signatures; otherwise the user is revoked immediately.
- When a revocation is completed, a job is dispatched to the debate service's `api/revoke-members/{userid}` endpoint.
- Role updates dispatch application jobs with the configured role-update event identifiers.

These paths are visible in the source code. The exact external API contract, retry policy, and deployment topology are not documented in this repository and must be confirmed with the debate-service maintainers.

### Email

Membership receipt, acceptance/rejection, password reset, and administrator notification emails are built from templates in `resources/views/email/` and dispatched through application mail jobs. Configure SMTP settings and verify mail delivery in the target environment.

### Pusher / WebSockets

The application initializes a Pusher client and emits events for user acceptance and login/logout activity. Pusher settings are read from environment variables. The current code constructs the Pusher connection with TLS disabled; do not expose this configuration publicly without reviewing and correcting transport security.

## Security and operational considerations

The repository contains several issues that should be treated as release blockers until reviewed and corrected:

1. **Credentials committed to Git:** the repository tree contains tracked `.env` and `.env~` files. Treat any credentials, application keys, OAuth client secrets, mail passwords, database passwords, and Pusher secrets ever committed there as compromised. Rotate them, remove the files from version control, and consider rewriting repository history. Never copy their contents into documentation or logs.
2. **Incomplete schema and OAuth setup:** domain tables and Passport migrations are not present in the checked-in migration directory. Do not assume a clean database can be provisioned from this repository alone.
3. **Legacy framework dependencies:** Laravel 5.7, Passport 7, PHP 7.1-era constraints, and older third-party packages are obsolete. Upgrade and run a dependency/security audit before production deployment.
4. **Overly broad CORS defaults:** `config/cors.php` allows all origins, headers, and methods. Restrict these to trusted client origins and required methods/headers.
5. **Public routes expose personal data:** `/api/users-cities`, `/api/users/{userid}/photo`, and `/api/applications/{applicationid}/photo` have no route-level authentication. Confirm whether public access is intentional and apply authorization where personal data should be private.
6. **User authorization needs review:** `/api/users/{userid}` and `/api/users/{userid}/change-password` require an authenticated token but the controller does not consistently verify that the authenticated user is the target user or an authorized administrator. Deactivation/reactivation actions are also reachable through the authenticated route. Enforce object-level authorization in the controller/policies.
7. **Refresh-token wrapper needs review:** the custom refresh handler validates several fields, but the implementation does not visibly verify the submitted password and constructs an internal `/oauth/token` request without explicitly forwarding the validated request parameters. Verify the actual behavior and replace this wrapper with a tested Passport-compatible flow.
8. **Temporary default password:** accepted users are created with a hard-coded default password and flagged to reset it. Replace this with a secure invitation/set-password flow that never relies on a shared default credential.
9. **Inconsistent API errors:** many validation and business-rule failures are returned with HTTP 200 and a custom `faillure` property. Standardize HTTP status codes and error response formats before relying on clients to interpret failures.
10. **Token/session state:** login/logout and user-listing code maintain connection and token metadata separately. Review token revocation, multi-device behavior, expiration handling, and whether user presence is derived from authoritative token state.
11. **Pusher transport:** the controller sets `useTLS` to `false`. Use TLS and verify the WebSocket endpoint, port, and credentials for every environment.
12. **Sensitive integration data:** user records and password-related data are sent to the debate service. Use TLS, least-privilege client credentials, strict request validation, and ensure plaintext passwords are never logged or retained by asynchronous jobs.
13. **Missing authorization policies:** the application registers a placeholder policy mapping, but the route protection primarily relies on Passport scopes. Review every route and add explicit authorization checks for each operation and target resource.

Use HTTPS for all client and service-to-service traffic, keep `APP_DEBUG=false` outside local development, protect `.env` and Passport keys, restrict database/network access, and monitor queue failures and authentication events.

## Known limitations and verification gaps

- The repository's current README is the untouched Laravel starter README; it does not document this application.
- Only framework-level migrations are present. The schema required by the domain models is not reproducible from the checked-in migrations.
- No complete seed data is provided for required records such as the `DEFAULT GROUP`, administrator roles, or scope definitions. These records are assumed by application logic.
- The 2019 analysis documents describe a wider IGGI platform. Forum discussions, debate moderation, debate results/projects, Google Maps member views, and document archiving are not implemented in this IAM repository.
- The API includes Swagger-related dependencies and configuration, but the configuration alone does not establish that a complete, accurate API specification has been generated or maintained.
- The checked-in automated tests are Laravel example tests (`tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`); they do not provide meaningful coverage of the IAM workflows.
- No deployment topology, production process manager configuration, database schema export, or verified end-to-end integration contract is supplied in the repository.

These gaps should be resolved with the maintainers before using this README as an operational runbook for a production installation.

## Automated Tests and CI

An automated test suite covering the project's domain models, application workflows, authentication, password management, group/role operations, administrator promotion, and member revocation workflows has been implemented.

The test suite runs on **PHPUnit 7** with an in-memory SQLite database configured in `phpunit.xml`.

### Running Tests via Docker (Recommended)

Because this repository uses a legacy dependency stack (Laravel 5.7 and PHP 7.1–7.4), running tests inside Docker is the cleanest and most reproducible method.

#### Option 1: Standalone Docker container

Build the test image and execute the test suite:

```bash
docker build -t identity-and-access:test .
docker run --rm identity-and-access:test
```

#### Option 2: Docker Compose

```bash
docker compose run --rm app
```

### Running Tests Locally (Native PHP)

#### Prerequisites
- **PHP 7.3** (or 7.1.3 - 7.4) with extensions: `pdo`, `pdo_sqlite`, `mbstring`, `curl`, `json`, `fileinfo`, `xml`, `tokenizer`. Note that PHP 8.x is incompatible with the legacy dependency tree (e.g., `beyondcode/laravel-websockets`).
- **Composer 1.10.x** (Composer 2 changes the internal structure of `installed.json`, which breaks Laravel 5.7's `PackageManifest`).
- **Git URL substitution** for the archived upstream dependency `propaganistas/laravel-intl`:
  ```bash
  git config --global url."https://github.com/rrkhatri/Laravel-Intl.git".insteadOf "https://github.com/Propaganistas/Laravel-Intl.git"
  ```

#### Execution command

```bash
composer install --prefer-dist --no-interaction
vendor/bin/phpunit
```

To run a specific test suite or test file:

```bash
vendor/bin/phpunit tests/Feature/ApplicationWorkflowTest.php
vendor/bin/phpunit tests/Feature/AuthenticationWorkflowTest.php
vendor/bin/phpunit tests/Feature/PasswordWorkflowTest.php
vendor/bin/phpunit tests/Feature/GroupManagementTest.php
vendor/bin/phpunit tests/Feature/RoleManagementTest.php
vendor/bin/phpunit tests/Feature/AdministratorPropositionTest.php
vendor/bin/phpunit tests/Feature/UserRevocationTest.php
vendor/bin/phpunit tests/Feature/UtilityEndpointTest.php
vendor/bin/phpunit tests/Unit/
```

### Test Suite Structure

The test suite contains 100 automated tests across unit and feature suites:

| Suite | File | Coverage |
|---|---|---|
| **Unit** | `tests/Unit/UserModelTest.php` | User model constants, constructor attributes, password hashing, phone/country helpers, hidden fields |
| **Unit** | `tests/Unit/GroupModelTest.php` | Group model `addMembersToGroup()`, `removeMembersToGroup()`, deduplication, error handling |
| **Unit** | `tests/Unit/RoleModelTest.php` | Direct role assignment, inherited group role assignment, `isUserInRole()`, `isGroupInRole()`, user/group aggregators |
| **Feature** | `tests/Feature/ApplicationWorkflowTest.php` | Membership application submission, validation, duplicate email check, listing, acceptance workflow (user creation, DEFAULT GROUP addition, reset invitation, notifications), rejection workflow, photo download |
| **Feature** | `tests/Feature/AuthenticationWorkflowTest.php` | Login validation, bad credentials, deactivated user block, must-reset-password block, `confirm-access-token`, `confirm-admin-access-token`, `logout` token revocation and disconnection, authenticated user listing excluding caller |
| **Feature** | `tests/Feature/PasswordWorkflowTest.php` | Password reset invitation request, blocked user prevention, password reset execution via token invitation, password change, user state modifications (`CHANGE_PASSWORD`, `DEACTIVATE_USER`, `REACTIVATE_USER`, unknown action) |
| **Feature** | `tests/Feature/GroupManagementTest.php` | Scope authorization, group creation with UUID, group listing, member addition, member removal, nonexistent member error handling, not-group-members filtering |
| **Feature** | `tests/Feature/RoleManagementTest.php` | Scope authorization, role creation with photo, role listing, `isUserInRole` endpoint, group addition, user addition, event dispatching, groups not playing role |
| **Feature** | `tests/Feature/AdministratorPropositionTest.php` | Administrator proposition creation, candidate validation, duplicate proposition check, unanimity evaluation on sole admin, multi-admin signature requirement, pending proposition listing |
| **Feature** | `tests/Feature/UserRevocationTest.php` | Scope authorization, member revocation initiation, duplicate revocation check, external debate service communication failure handling / transaction rollback, proposition signing, member deletion upon unanimous signatures, revocation listings |
| **Feature** | `tests/Feature/UtilityEndpointTest.php` | Interest center listing, distinct city listing, public user photo retrieval, WebSocket test endpoint |

## Docker Configuration

The repository includes a containerized development and testing setup:

- `Dockerfile`: Multi-stage Alpine-based container using `php:7.3-cli-alpine`, with system libraries, SQLite, GD image processing, Composer 1.10.27, and git URL substitution for archived dependencies.
- `docker-compose.yml`: Defines the `app` service pre-configured with testing environment variables and volume mounts for local test execution.

Run the test suite with Docker Compose:

```bash
docker compose run --rm app
```

## Continuous Integration (GitHub Actions)

Continuous integration is automated via GitHub Actions in `.github/workflows/ci.yml`.

The workflow runs on every `push` and `pull_request` targeting `master` or `main` branches and includes two parallel jobs:

1. **Build & Run Tests (Docker):** Builds the Docker image from `Dockerfile` and executes the automated test suite within the containerized PHP 7.3 runtime.
2. **Run Tests (PHP 7.3 Native):** Sets up PHP 7.3 with necessary extensions, configures Composer, installs dependencies, and runs `vendor/bin/phpunit --verbose`.

## Implementation Discrepancies and Findings

During test implementation and codebase inspection, several architectural issues, bugs, and discrepancies were identified:

1. **Role removal actions are unimplemented:** In `RoleController::updateRole()`, the branches for `REMOVE_USERS_TO_ROLE` and `REMOVE_GROUPS_TO_ROLE` have empty code blocks and fall through to return an error response (`"Aucune action specifiee"`).
2. **Synchronous HTTP calls in job constructor:** `App\Jobs\ProcessRestCall` is marked as `ShouldQueue`, but its constructor originally performed a synchronous HTTP POST request to `HOST_DEBATE` to acquire an OAuth token. This caused any dispatch call (such as accepting an application or resetting a password) to fail if the debate service was unreachable. This was corrected so that all network requests execute in `handle()`.
3. **Synchronous external service dependency in Revocation:** `UserController::revokeUser()` makes direct, synchronous HTTP calls to `HOST_DEBATE` in the middle of a database transaction. If the debate service is down or unreachable, the transaction rolls back and the operation fails.
4. **Namespace conflict in `DebateRole.php`:** The file `app/Domain/Model/Access/DebateRole.php` declared `class Role extends Model` rather than `class DebateRole`, causing PSR-4 autoload warnings and collisions with `App\Domain\Model\Access\Role`. This was corrected to `class DebateRole`.
5. **Constructor signature in Eloquent models:** Several domain models (`User`, `Application`, `Group`, `Role`, `Scope`, `PasswordResetInvitation`, `RevokedUser`) used positional constructor arguments rather than standard Eloquent array arguments (`__construct(array $attributes = [])`), causing Eloquent operations like `firstOrCreate()` to pass array data into string parameters. The constructors were adjusted to support both patterns.
6. **Error response convention:** Most API endpoints return HTTP 200 with an envelope containing `{"success": 0, "faillure": 1, "raison": "..."}` rather than standard HTTP 4xx/5xx status codes. Clients must parse the JSON envelope rather than rely on HTTP status codes alone.
7. **Database schema resolution:** The domain models assumed database tables (`applications`, `groups`, `roles`, `scopes`, `administratorpropositions`, `userrevokationpropositions`, `revokedusers`, `password_reset_invitations`, `interestcenters`, `cityofresidencies`, `countries`, and custom `users` fields) that were missing from `database/migrations/`. A migration (`database/migrations/2019_05_01_000000_create_domain_tables.php`) has been added to provide the complete domain schema.
