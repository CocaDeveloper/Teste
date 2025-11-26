<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\MonitoringService;

class MonitoringController extends Controller
{
    public function index()
    {
        return view('admin.monitoring.index', ['metrics' => []]); // Placeholder
    }
}
