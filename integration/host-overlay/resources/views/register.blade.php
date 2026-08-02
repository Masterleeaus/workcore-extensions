@extends('layouts.guest')
@section('title', 'Create Account')
@section('content')
    <!-- Register Card -->
    <div class="login-card w-full max-w-md">

        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16  rounded-2xl mb-4">
                <img src="{{ getImageUrl(setting('logo')) }}" alt="logo">
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Account</h1>
            <p class="text-gray-600">Sign up to start chatting</p>
        </div>

        <!-- Error Message -->
        @if ($errors->any())
            <div id="error-message" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center space-x-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    <div class="text-sm text-red-600">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Register Form -->
        <form method="POST" action="{{ route('register') }}" id="register-form" class="space-y-5">
            @csrf

            <!-- Name Field -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    Full Name
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="text" id="name" name="name" placeholder="John Doe" value="{{ old('name') }}"
                        class="input-field pl-10">
                </div>
                @error('name')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email Field -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email Address
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="email" id="email" name="email" placeholder="you@example.com"
                        value="{{ old('email') }}" class="input-field pl-10">
                </div>
                @error('email')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    Password
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" placeholder="••••••••"
                        class="input-field pl-10 pr-10">
                    <button type="button" id="toggle-password"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>
                </div>
                @error('password')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confirm Password Field -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                    Confirm Password
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••"
                        class="input-field pl-10">
                </div>
                @error('password_confirmation')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" id="register-btn"
                class="w-full py-3 px-4 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition shadow-lg hover:shadow-xl transform hover:scale-[1.02] active:scale-[0.98]">
                <span id="register-btn-text">Create Account</span>
            </button>
        </form>

        <!-- Sign In Link -->
        <p class="mt-6 text-center text-sm text-gray-600">
            Already have an account?
            <a href="{{ route('login') }}" class="text-primary hover:text-secondary font-semibold">
                Sign in
            </a>
        </p>

    </div>
@endsection

@push('scripts')
    <script>
        // Toggle password visibility
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;

            const icon = this.querySelector('i');
            icon.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
            lucide.createIcons();
        });
    </script>
@endpush
