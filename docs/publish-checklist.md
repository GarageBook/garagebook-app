# Publish Checklist

Run this before every publish or deploy.

1. Run `php artisan test --filter=AdminManagementAccessTest`.
2. Run `php artisan test`.
3. Confirm `willemvanveelen@icloud.com` is still the only admin account.
4. Confirm a normal user does not see beheer/admin navigation or widgets in Filament.
5. Confirm a normal user gets `403` on admin pages and resources.
6. Confirm `willemvanveelen@icloud.com` can still open admin pages and resources.
7. Run `php artisan optimize:clear` on the target environment if admin/navigation changes were deployed.
8. Do not deploy if any admin access test fails.

If any result is unexpected, stop the publish and fix the authorization issue first.
