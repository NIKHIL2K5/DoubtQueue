# Contributing to DoubtQueue

Thank you for helping with DoubtQueue. Please follow these steps.

1. Fork the repository on GitHub.
2. Clone your fork:

   ```
   git clone https://github.com/<your-username>/DoubtQueue.git
   cd DoubtQueue
   ```

3. Create a branch for your change:

   ```
   git checkout -b fix/issue-12-description
   ```

4. Comment on the GitHub issue you want to work on before you start, so nobody else works on the same thing.
5. Make your change. Keep it small and focused on the issue.
6. Add or update tests for the behavior you changed.
7. Run the tests:

   ```
   cd backend
   composer test

   cd ../app
   flutter analyze
   flutter test
   ```

8. Push your branch and create a pull request against `main`. Fill in the pull request template.
9. Mention the issue number in the pull request description, for example:

   ```
   Fixes #12
   ```

## Project Architecture

New contributors can read the [Architecture Overview](docs/architecture.md) to understand the project structure, request flow, database, and tests before making changes.

## Code style

- PHP follows PSR-12 and uses `declare(strict_types=1);`.
- All SQL queries use prepared statements.
- Dart code must pass `flutter analyze` with no issues.
- Keep the project simple. Do not add frameworks or state-management packages.

## Scope

DoubtQueue is a small project. Login, chat, push notifications and similar features are out of scope.
