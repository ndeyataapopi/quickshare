# QuickShare

QuickShare is a Namibian peer-to-peer lending platform built with Laravel 12. It connects verified borrowers with individual lenders and supports the full loan lifecycle from KYC through repayment.

## Status

Private beta with real users and real financial transactions. The current payment workflow is manual; automated payment-provider execution is planned but not enabled.

## Technology Stack

- **PHP:** `^8.2`
- **Laravel:** `^12.0`
- **Frontend:** Vite, TailwindCSS, Alpine.js, Bootstrap
- **Database:** MySQL (production), SQLite (tests)
- **Cache/Queue/Session:** Redis
- **Auth:** Laravel Breeze, Sanctum, Spatie Permission

## Quick Start

```bash
cp .env.example .env
# Edit .env with your database and Redis credentials
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For queue workers, see `docs/queue-deployment.md`.

## Documentation

The authoritative platform documentation is in `docs/platform/master.md` and is also viewable inside the admin panel by users with the `view_documentation` permission.

## Important Operational Principles

- QuickShare records the **business meaning** of payments; the movement of money is initiated outside the platform and confirmed by an administrator.
- Automated payment execution is optional and must not replace the manual workflow unless explicitly enabled.
- No production credentials, API keys, KYC documents or bank account numbers should be committed to the repository.

## License

All rights reserved. QuickShare is proprietary software of the operating entity.
