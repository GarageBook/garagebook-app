<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PublicGarageService;
use App\Services\Seo\SeoHealthService;
use App\Support\PublicSeoUrl;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DeploymentSmokeTestCommand extends Command
{
    protected $signature = 'garagebook:deployment-smoke-test
        {--garage-slug= : Optional known public garage slug to check}
        {--admin-email= : Optional admin email to use for authenticated admin routes}';

    protected $description = 'Run a post-deploy smoke test for core public and admin routes.';

    /** @var list<array{label:string,path:string,status:int}> */
    private array $failures = [];

    public function handle(PublicGarageService $publicGarageService): int
    {
        $this->failures = [];

        $this->line('Deployment smoke test');

        $garageSlug = $this->resolveGarageSlug($publicGarageService);
        $admin = $this->resolveAdminUser();

        if ($admin instanceof User) {
            $this->assertRouteIsOk('admin dashboard', '/admin', $admin);
            $this->assertSeoHealthDashboardIsOk($admin);
        }

        Auth::forgetGuards();

        $this->assertRouteIsOk('public home', '/', publicHost: true);
        if ($garageSlug !== null) {
            $this->assertRouteIsOk('public garage page', '/garage/'.$garageSlug);
        }

        $this->assertRouteIsOk('sitemap garages', '/sitemap-garages.xml');

        $failed = $this->failures !== [];

        $this->newLine();
        $this->line($failed ? 'FAILED' : 'PASS');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function resolveAdminUser(): ?User
    {
        $email = trim((string) ($this->option('admin-email') ?: config('services.deployment_smoke_test.admin_email', User::ADMIN_EMAIL)));
        $admin = User::query()->where('email', $email)->first();

        if (! $admin instanceof User) {
            $this->recordFailure('admin dashboard', '/admin', 404, 'Admin user not found: '.$email);

            return null;
        }

        return $admin;
    }

    private function resolveGarageSlug(PublicGarageService $publicGarageService): ?string
    {
        $slug = trim((string) ($this->option('garage-slug') ?: config('services.deployment_smoke_test.public_garage_slug')));

        if ($slug !== '') {
            return $slug;
        }

        $vehicle = $publicGarageService->indexableVehicles()->first();

        if ($vehicle && filled($vehicle->public_slug)) {
            return (string) $vehicle->public_slug;
        }

        $this->recordFailure(
            'public garage page',
            '/garage/{slug}',
            404,
            'No indexable public garage page found via PublicGarageService::indexableVehicles().'
        );

        return null;
    }

    private function assertRouteIsOk(string $label, string $path, ?User $user = null, bool $publicHost = false): void
    {
        $response = $this->dispatchPath($path, $user, $publicHost);

        if ($response->getStatusCode() !== 200) {
            $this->recordFailure($label, $path, $response->getStatusCode(), sprintf('%s returned %d', $path, $response->getStatusCode()));

            return;
        }

        $this->line('✓ '.$label.': '.$path);
    }

    private function assertSeoHealthDashboardIsOk(User $user): void
    {
        $guardName = (string) config('auth.defaults.guard', 'web');

        Auth::forgetGuards();
        Auth::shouldUse($guardName);
        Auth::guard($guardName)->setUser($user);

        try {
            if (! ($user->isAdmin())) {
                $this->recordFailure('seo health dashboard', '/admin/seo-health-dashboard', 403);

                return;
            }

            app(SeoHealthService::class)->report();
        } catch (Throwable $exception) {
            Log::error('deployment_smoke_test_exception', [
                'path' => '/admin/seo-health-dashboard',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->recordFailure('seo health dashboard', '/admin/seo-health-dashboard', 500);

            return;
        } finally {
            Auth::forgetGuards();
        }

        $this->line('✓ seo health dashboard: /admin/seo-health-dashboard');
    }

    private function dispatchPath(string $path, ?User $user = null, bool $publicHost = false): Response
    {
        $appUrl = (string) config('app.url');
        $host = $publicHost ? PublicSeoUrl::HOST : (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');
        $scheme = $publicHost ? 'https' : (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
        $guardName = (string) config('auth.defaults.guard', 'web');

        $request = Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => $host,
            'HTTPS' => $scheme === 'https' ? 'on' : 'off',
        ]);

        $request->setUserResolver(fn () => $user);

        Auth::forgetGuards();
        Auth::shouldUse($guardName);
        if ($user instanceof User) {
            Auth::guard($guardName)->setUser($user);
        }

        try {
            return app()->handle($request);
        } catch (Throwable $exception) {
            Log::error('deployment_smoke_test_exception', [
                'path' => $path,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return new Response('', 500);
        } finally {
            Auth::forgetGuards();
        }
    }

    private function recordFailure(string $label, string $path, int $status, ?string $message = null): void
    {
        $details = $message ?: sprintf('%s returned %d', $path, $status);

        $this->failures[] = [
            'label' => $label,
            'path' => $path,
            'status' => $status,
        ];

        $this->error('✗ '.$label.': '.$details);

        Log::error('deployment_smoke_test_failed', [
            'label' => $label,
            'path' => $path,
            'status' => $status,
            'message' => $details,
        ]);
    }
}
