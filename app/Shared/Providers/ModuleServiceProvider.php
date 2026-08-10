<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base untuk ServiceProvider tiap modul.
 *
 * Setiap modul memuat route-nya sendiri dari routes/modules/{nama}.php dan
 * mendaftarkan Policy-nya sendiri. Dengan begitu penambahan modul baru tidak
 * menyentuh berkas terpusat mana pun selain bootstrap/providers.php.
 *
 * Lihat ADR-02 pada docs/architecture/02-architecture-blueprint.md.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Nama modul dalam huruf kecil — dipakai untuk menemukan berkas route.
     */
    abstract protected function name(): string;

    /**
     * Peta Model => Policy milik modul ini.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * Middleware yang membungkus seluruh route modul.
     *
     * Default-nya mensyaratkan login. Modul yang butuh route publik
     * (mis. halaman auth) menimpa method ini.
     *
     * @return list<string>
     */
    protected function routeMiddleware(): array
    {
        return ['web', 'auth'];
    }

    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootPolicies();
    }

    private function bootRoutes(): void
    {
        $file = base_path("routes/modules/{$this->name()}.php");

        // Tidak semua modul punya route — Approval misalnya hanya menyediakan
        // service yang dipanggil modul lain.
        if (! is_file($file)) {
            return;
        }

        Route::middleware($this->routeMiddleware())->group($file);
    }

    private function bootPolicies(): void
    {
        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
