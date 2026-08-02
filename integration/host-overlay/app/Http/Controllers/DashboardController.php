<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $count = static fn (string $table): int => Schema::hasTable($table) ? DB::table($table)->count() : 0;

        return view('dashboard', [
            'totalUsers' => User::count(),
            'totalCompanies' => $count('tz_companies'),
            'totalCustomers' => $count('tz_customers'),
            'totalWorkOrders' => $count('tz_work_orders'),
            'recentUsers' => User::latest()->take(5)->get(),
        ]);
    }
}
