@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="flex-1 overflow-y-auto p-8">
    <div class="max-w-2xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Create New User</h1>
            <p class="text-gray-600 mt-2">Add a new user to the system</p>
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

        <!-- Create Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Full Name *
                    </label>
                    <input type="text" id="name" name="name" required 
                           value="{{ old('name') }}"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address *
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="{{ old('email') }}"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password *
                    </label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary transition">
                    <p class="mt-1 text-xs text-gray-500">Minimum 6 characters</p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password *
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required 
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
                        Create User
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
