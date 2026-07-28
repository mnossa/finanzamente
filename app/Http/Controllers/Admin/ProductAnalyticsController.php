<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductAnalytics\ProductAnalyticsDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProductAnalyticsController extends Controller
{
    public function index(Request $request, ProductAnalyticsDashboardService $dashboard): Response
    {
        $days = max(1, min(90, (int) $request->integer('days', 30)));
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        Log::channel('security')->info('product_analytics.dashboard_viewed', [
            'admin_email_hash' => hash('sha256', strtolower((string) $request->user()?->email).config('app.key')),
            'days' => $days,
        ]);

        return Inertia::render('Admin/ProductAnalytics/Index', [
            'analytics' => $dashboard->build($from, $to),
            'days' => $days,
            'tools' => [
                'pulse_url' => url('/pulse'),
                'pulse_enabled' => (bool) config('pulse.enabled'),
                'telescope_enabled' => (bool) config('telescope.enabled')
                    && app()->environment(['local', 'staging']),
                'telescope_url' => url('/telescope'),
            ],
        ]);
    }
}
