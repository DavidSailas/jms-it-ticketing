<?php

namespace App\Http\Controllers;

use App\Support\DashboardCharts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live numbers for the admin dashboard charts (the page refreshes them without a reload). */
class DashboardChartController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()
            ->json(DashboardCharts::payload(DashboardCharts::range($request->query('range'))))
            ->header('Cache-Control', 'no-store');
    }
}
