# DoubtQueue Architecture

This guide explains how the backend, Flutter app, database, and tests fit together.

## Project Structure

### Backend

The backend is a PHP application that exposes a JSON API.

- `backend/public/index.php`: Entry point. Connects the database, creates services and controllers, registers routes, and handles exceptions.
- `backend/src/Config.php`: Reads backend configuration.
- `backend/src/Database.php`: Creates PDO database connections.
- `backend/src/DatabaseSetup.php`: Creates database tables and inserts sample data when needed.
- `backend/src/Router.php`: Matches HTTP methods and URL paths to controller actions.
- `backend/src/Http/Request.php`: Reads the incoming HTTP request, including its method, path, query parameters, and JSON body.
- `backend/src/Http/Response.php`: Sends JSON responses and HTTP status codes.
- `backend/src/Controller/`: Receives API requests and calls services.
- `backend/src/Service/`: Contains application rules and coordinates repository operations.
- `backend/src/Repository/`: Reads and updates database records using PDO.
- `backend/src/Model/`: Contains domain status definitions, such as doubt and session statuses.
- `backend/src/Exception/`: Contains exceptions used to report expected errors.

### Flutter app

The Flutter app provides the screens used by mentors and students.

- `app/lib/main.dart`: Creates the app, configures its theme, and provides the API service.
- `app/lib/config.dart`: Holds app configuration, including the API base URL.
- `app/lib/screens/`: Contains full app screens, such as the session list, mentor dashboard, and student queue screens.
- `app/lib/widgets/`: Contains reusable UI components used by screens.
- `app/lib/models/`: Converts API JSON data into Dart objects, including `Session` and `Doubt`.
- `app/lib/services/api_service.dart`: Sends HTTP requests to the backend and converts responses into models.
- `app/lib/theme/`: Contains the app theme and layout styling.
- `app/lib/utils/`: Contains shared helpers for session filtering and time formatting.

## Backend Request Flow

A typical request follows this path:

1. `backend/public/index.php` receives the request through PHP's web server.
2. `backend/src/Router.php` matches the HTTP method and URL to a controller action.
3. `backend/src/Controller/` handles the request and calls the relevant service.
4. `backend/src/Service/` checks application rules and calls repositories.
5. `backend/src/Repository/` uses PDO to query or update the database.
6. The result returns through the controller and is sent as JSON by `backend/src/Http/Response.php`.

For example, when a mentor marks a doubt as solved, `PATCH /api/doubts/{id}/solved` follows this flow:

1. `backend/public/index.php` registers the route.
2. `backend/src/Router.php` matches the route and extracts the doubt ID.
3. `backend/src/Controller/DoubtController.php` calls the doubt service.
4. `backend/src/Service/DoubtService.php` checks the doubt and asks the repository to mark it solved.
5. `backend/src/Repository/DoubtRepository.php` updates the doubt's status and finish time.
6. The updated doubt is returned as JSON.

The service only allows a doubt that is currently in progress to be marked solved or skipped.

## Error Handling

`backend/public/index.php` converts exceptions into JSON error responses:

| Exception | HTTP status | Meaning |
| --- | --- | --- |
| `ValidationException` | 400 | The request or requested operation is invalid. |
| `NotFoundException` | 404 | The requested resource or route was not found. |
| `SessionClosedException` | 409 | An operation cannot continue because the session is closed. |
| Other `Throwable` errors | 500 | An unexpected server error occurred. |

Error responses contain a `status` and a `message`. Unexpected errors are logged, while the client receives a generic message.

## Database

The project supports SQLite by default and MySQL as an optional database.

- `backend/database/schema-sqlite.sql`: Defines the SQLite `sessions` and `doubts` tables and an index for session and doubt status.
- `backend/database/schema-mysql.sql`: Defines the corresponding MySQL tables and index.
- `backend/src/DatabaseSetup.php`: Selects the schema for the active PDO driver, creates the tables, and adds sample data when the sessions table is empty.
- `backend/src/Database.php`: Configures PDO connections and enables SQLite foreign-key checks.

The `doubts.session_id` column references `sessions.id`. A session can therefore have multiple doubts.

## Flutter State and API

The app uses Flutter's built-in state management. Screens that manage changing data use `StatefulWidget` and `setState()` to update the UI.

`ApiService` is passed into screens through their constructors. This makes API calls explicit and allows tests to provide a fake backend instead of making real network requests.

The API service is in `app/lib/services/api_service.dart`. It handles HTTP requests, decodes JSON responses, and throws `ApiException` when a request fails.

## Tests

### Backend tests

Backend tests live under `backend/tests/`:

- `backend/tests/Repository/`: Tests repository database operations.
- `backend/tests/Service/`: Tests service rules and behavior.
- `backend/phpunit.xml`: PHPUnit configuration.

The backend tests use a database connection and `DatabaseSetup` to create the test tables.

### Flutter tests

Flutter tests live under `app/test/`:

- `app/test/api_service_test.dart`: Tests API service behavior.
- `app/test/fake_backend.dart`: Provides an in-memory fake backend for tests.
- `app/test/models_test.dart`: Tests model behavior.
- `app/test/`: Also contains tests for screens, session filtering, time formatting, and responsive layouts.

Run the backend and Flutter tests before submitting changes. See `CONTRIBUTING.md` for the commands.

## Adding an API Endpoint

1. Add or update the route in `backend/public/index.php`.
2. Add the controller action in `backend/src/Controller/`.
3. Put application rules in the relevant class under `backend/src/Service/`.
4. Add database operations to the relevant class under `backend/src/Repository/` if needed.
5. Add or update tests under `backend/tests/`.
6. Update the API endpoint list in `README.md` if the public API changes.

## Adding a Flutter Screen

1. Create the screen under `app/lib/screens/`.
2. Reuse existing components from `app/lib/widgets/` where appropriate.
3. Use models from `app/lib/models/` for API data.
4. Call the backend through the injected `ApiService`.
5. Use `StatefulWidget` and `setState()` when the screen needs local changing state.
6. Add or update widget tests under `app/test/`.

## Further Reading

- [Project setup and API endpoints](../README.md)
- [Contribution guidelines](../CONTRIBUTING.md)