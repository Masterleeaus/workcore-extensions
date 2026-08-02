@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="flex-1 overflow-y-auto p-8">
    <div class="max-w-2xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Edit User</h1>
            <p class="text-gray-600 mt-2">Update user information</p>
        </div>

        <!-- Error Messages -->
        @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start space-x-2">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 mt-0.5"></i>
                <div class="text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Edit Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Name *
                    </label>
                    <input type="text" id="name" name="name" required 
                           value="{{ old('name', $user->name) }}"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address *
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="{{ old('email', $user->email) }}"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                </div>

                <!-- Password (Optional) -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        New Password (Leave blank to keep current)
                    </label>
                    <input type="password" id="password" name="password" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                    <p class="mt-1 text-xs text-gray-500">Minimum 6 characters if changing</p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm New Password
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                    <a href="{{ route('users.index') }}"
                        class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition shadow-lg hover:shadow-xl">
                        Update User
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
