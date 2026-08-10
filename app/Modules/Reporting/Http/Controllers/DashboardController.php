<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\DashboardService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard monitoring — fitur 5 blueprint ("Pelaporan/Monitoring").
 *
 * Halaman depan seluruh pengguna. Payload disesuaikan kewenangan (DashboardService)
 * agar requester biasa tidak melihat statistik yang bukan haknya.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard/Index', [
            'data' => $user !== null ? $this->dashboard->forUser($user) : null,
        ]);
    }
}
