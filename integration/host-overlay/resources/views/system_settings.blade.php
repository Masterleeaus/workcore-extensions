@extends('layouts.app')

@section('title', 'System Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endpush

@section('content')
    <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto">

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
                    <p class="text-gray-600 mt-2">Manage application configuration and preferences</p>
                </div>
                <a href="{{ route('chat') }}" class="p-2 text-gray-500 hover:text-gray-700 transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </a>
            </div>

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center text-green-700">
                    <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data"
                      class="p-6 space-y-8">
                    @csrf

                    <!-- General Info Section -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-2 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">General Info</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">App Name</label>
                                <input type="text" name="app_name" value="{{ setting('app_name') }}"
                                       class="input-field">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" value="{{ setting('email') }}" class="input-field">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" name="phone" value="{{ setting('phone') }}" class="input-field">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <input type="text" name="address" value="{{ setting('address') }}" class="input-field">
                            </div>
                        </div>
                    </div>

                    <!-- System Section -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-2 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">System</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                                <select name="timezone" class="input-field">
                                    <option value="UTC" {{ setting('timezone') == 'UTC' ? 'selected' : '' }}>UTC
                                    </option>
                                    <option
                                        value="Asia/Dhaka" {{ setting('timezone') == 'Asia/Dhaka' ? 'selected' : '' }}>
                                        Asia/Dhaka
                                    </option>
                                    <option
                                        value="America/New_York" {{ setting('timezone') == 'America/New_York' ? 'selected' : '' }}>
                                        America/New_York
                                    </option>
                                    <option
                                        value="Europe/London" {{ setting('timezone') == 'Europe/London' ? 'selected' : '' }}>
                                        Europe/London
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date Format</label>
                                <select name="date_format" class="input-field">
                                    <option value="Y-m-d" {{ setting('date_format') == 'Y-m-d' ? 'selected' : '' }}>
                                        YYYY-MM-DD
                                    </option>
                                    <option value="d-m-Y" {{ setting('date_format') == 'd-m-Y' ? 'selected' : '' }}>
                                        DD-MM-YYYY
                                    </option>
                                    <option value="m/d/Y" {{ setting('date_format') == 'm/d/Y' ? 'selected' : '' }}>
                                        MM/DD/YYYY
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Appearance Section -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-2 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Appearance</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="theme_primary_color" value="#724FF9"
                                           class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                    <span class="text-sm text-gray-500">#724FF9</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                                <div class="flex items-center space-x-3">
                                    <input type="color" name="theme_secondary_color" value="#724FF9"
                                           class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                    <span class="text-sm text-gray-500">#724FF9</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branding Section -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-2 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Branding</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                                <div class="flex items-center space-x-4">
                                    @if(setting('logo'))
                                        <img src="{{ getImageUrl(setting('logo')) }}" alt="Logo"
                                             class="h-16 w-auto object-contain bg-gray-50 rounded p-2 border border-gray-200">
                                    @endif
                                    <input type="file" name="logo" accept="image/*"
                                           class="input-field file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Favicon</label>
                                <div class="flex items-center space-x-4">
                                    @if(setting('favicon'))
                                        <img src="{{ getImageUrl(setting('favicon')) }}" alt="Favicon"
                                             class="h-10 w-10 object-contain bg-gray-50 rounded p-1 border border-gray-200">
                                    @endif
                                    <input type="file" name="favicon" accept="image/*"
                                           class="input-field file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Assistant Settings -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-2 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">AI Assistant Settings</h2>
                            <p class="text-sm text-gray-500 mt-1">Configure AI provider and API credentials for chat
                                features</p>
                        </div>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">AI Provider</label>
                                <select name="ai_provider" class="input-field">
                                    <option value="">-- Use .env Configuration --</option>
                                    <option value="gemini" {{ setting('ai_provider') == 'gemini' ? 'selected' : '' }}>
                                        Google Gemini (Recommended)
                                    </option>
                                    <option
                                        value="openrouter" {{ setting('ai_provider') == 'openrouter' ? 'selected' : '' }}>
                                        OpenRouter
                                    </option>
                                    <option value="openai" {{ setting('ai_provider') == 'openai' ? 'selected' : '' }}>
                                        OpenAI
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Select which AI service to use for chat summary
                                    and reply suggestions</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Key</label>
                                <input type="password" name="ai_api_key"
                                       value="{{ setting('ai_api_key') }}"
                                       class="input-field font-mono text-sm"
                                       placeholder="sk-or-v1-... or leave empty to use .env">
                                <p class="text-xs text-gray-500 mt-1">Your AI provider API key (stored encrypted). Leave
                                    empty to use .env configuration.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                <input type="text" name="ai_model"
                                       value="{{ setting('ai_model') }}"
                                       class="input-field font-mono text-sm"
                                       placeholder="openai/gpt-3.5-turbo">
                                <p class="text-xs text-gray-500 mt-1">
                                    Model to use. Examples:
                                    <code class="text-xs bg-gray-100 px-1 rounded">openai/gpt-3.5-turbo</code>,
                                    <code class="text-xs bg-gray-100 px-1 rounded">openai/gpt-4</code>,
                                    <code class="text-xs bg-gray-100 px-1 rounded">anthropic/claude-3-opus</code>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 flex justify-end sticky bottom-0 bg-white p-4 -mx-6 -mb-6">
                        <button type="submit"
                                class="px-6 py-2 bg-primary text-white font-semibold rounded-lg hover:opacity-90 transition shadow-sm">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
