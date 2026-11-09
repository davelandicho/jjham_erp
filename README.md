# JJHAM Portal

Staff panel (Filament) and Applicant Portal for JJHAM recruitment processing.

**Stack:** PHP 8.5 · Laravel 13 · Filament v5 · MySQL · Livewire

## First-time setup (Mac)

Needs: PHP 8.5, Composer, Node, MySQL, and Tesseract (`brew install tesseract`, for passport scan-to-read).

```bash
git clone https://github.com/davelandicho/jjham_erp.git jjham_portal
cd jjham_portal
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE jjham_portal;"
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan make:filament-user
php artisan shield:super-admin
php artisan storage:link
```

Check `TESSERACT_PATH` in `.env` with `which tesseract`.

## Running it (2 terminal tabs)

| Tab | Command | Why |
|---|---|---|
| 1 | `php artisan serve` | the website: http://127.0.0.1:8000/staff |
| 2 | `php artisan queue:work` | sends emails, including 2FA login codes |

Restart `queue:work` after changing code or `.env`.

## Logging in (2FA is required)

Locally, emails are not really sent (`MAIL_MAILER=log`). After you type your password, get the code from the log:

```bash
grep "sign-in code" storage/logs/laravel.log | tail -1
```

You can also set up an authenticator app in **Profile**.

## Rules

- **Fake test data only.** Never commit `.env`, real applicant data, or database dumps.
- Pull before starting work: `git pull`
- Portal work goes on its own branch (e.g. `git switch -c portal-yourname`), not `main`.
- After pulling, run `composer install`, `npm install` and `php artisan migrate` in case something changed.