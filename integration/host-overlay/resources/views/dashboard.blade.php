@extends('layouts.app')

@section('title', 'WorkCore Dashboard')

@section('content')
<div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">WorkCore Dashboard</h1>
            <p class="text-gray-600 mt-2">Operational system status for {{ auth()->user()->name }}.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            @foreach([
                ['Users', $totalUsers, 'users'],
                ['Companies', $totalCompanies, 'building-2'],
                ['Customers', $totalCustomers, 'contact'],
                ['Work Orders', $totalWorkOrders, 'clipboard-list'],
            ] as [$label, $value, $icon])
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $value }}</p>
                        </div>
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="{{ $icon }}" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Users</h2>
            <div class="space-y-3">
                @forelse($recentUsers as $user)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </div>
                        <span class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No users yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
