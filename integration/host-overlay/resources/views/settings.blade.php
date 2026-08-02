@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto">

            <div class="flex mb-8">
                <!-- Header -->
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your account settings and preferences</p>
                </div>
                <a href="{{ route('chat') }}"><i data-lucide="x"></i></a>
            </div>

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <p class="text-sm text-green-600">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">

                <!-- Sidebar Navigation -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <nav class="space-y-1">
                            <button onclick="showTab('profile')" id="tab-profile"
                                class="settings-tab active w-full flex items-center space-x-3 px-4 py-3 text-sm font-medium rounded-lg transition">
                                <i data-lucide="user" class="w-5 h-5"></i>
                                <span>Profile Information</span>
                            </button>
                            <button onclick="showTab('password')" id="tab-password"
                                class="settings-tab w-full flex items-center space-x-3 px-4 py-3 text-sm font-medium rounded-lg transition">
                                <i data-lucide="lock" class="w-5 h-5"></i>
                                <span>Change Password</span>
                            </button>
                            <button onclick="showTab('notifications')" id="tab-notifications"
                                class="settings-tab w-full flex items-center space-x-3 px-4 py-3 text-sm font-medium rounded-lg transition">
                                <i data-lucide="bell" class="w-5 h-5"></i>
                                <span>Notifications</span>
                            </button>
                            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center space-x-3 px-4 py-3 text-sm font-medium rounded-lg transition text-red-600 hover:bg-red-50">
                                    <i data-lucide="log-out" class="w-5 h-5"></i>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </nav>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="lg:col-span-2">

                    <!-- Profile Information Tab -->
                    <div id="content-profile" class="settings-content">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Profile Information</h2>

                            <form method="POST" action="{{ route('settings.update-profile') }}"
                                enctype="multipart/form-data" class="space-y-5">
                                @csrf
                                @method('PUT')

                                <!-- Avatar Upload -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Profile Picture
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <div class="relative">
                                            <img id="avatar-preview"
                                                src="{{ auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=724FF9&color=fff' }}"
                                                alt="Avatar"
                                                class="w-20 h-20 rounded-full object-cover ring-2 ring-secondary">
                                        </div>
                                        <div class="flex-1">
                                            <input type="file" id="avatar-input" name="avatar" accept="image/*"
                                                class="hidden">
                                            <button type="button" onclick="document.getElementById('avatar-input').click()"
                                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                                                Change Photo
                                            </button>
                                            <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF. Max size 2MB</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Full Name
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="text" id="name" name="name" required
                                            value="{{ old('name', auth()->user()->name) }}" class="input-field pl-10">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="email" id="email" name="email" required
                                            value="{{ old('email', auth()->user()->email) }}" class="input-field pl-10">
                                    </div>
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone Number
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="phone" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="tel" id="phone" name="phone"
                                            value="{{ old('phone', auth()->user()->phone) }}"
                                            placeholder="+1 (555) 123-4567" class="input-field pl-10">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="flex justify-end">
                                    <button type="submit"
                                        class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition shadow-lg hover:shadow-xl">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Tab -->
                    <div id="content-password" class="settings-content hidden">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Change Password</h2>

                            <form method="POST" action="{{ route('settings.update-password') }}" class="space-y-5">
                                @csrf
                                @method('PUT')

                                <!-- Current Password -->
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Current Password
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="password" id="current_password" name="current_password" required
                                            class="input-field pl-10">
                                    </div>
                                </div>

                                <!-- New Password -->
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                        New Password
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="password" id="new_password" name="new_password" required
                                            class="input-field pl-10">
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Minimum 6 characters</p>
                                </div>

                                <!-- Confirm New Password -->
                                <div>
                                    <label for="new_password_confirmation"
                                        class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm New Password
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="password" id="new_password_confirmation"
                                            name="new_password_confirmation" required class="input-field pl-10">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="flex justify-end">
                                    <button type="submit"
                                        class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition shadow-lg hover:shadow-xl">
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Notifications Tab -->
                    <div id="content-notifications" class="settings-content hidden">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Notification Settings</h2>

                            <div class="space-y-4">
                                <!-- Mute Notifications Toggle -->
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <h3 class="text-sm font-medium text-gray-900">Mute All Notifications</h3>
                                        <p class="text-xs text-gray-500 mt-1">Stop receiving all notifications from the app
                                        </p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="notifications-toggle" class="sr-only peer"
                                            {{ auth()->user()->notifications_muted ? 'checked' : '' }}>
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-secondary">
                                        </div>
                                    </label>
                                </div>

                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-start space-x-2">
                                        <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                                        <div class="text-sm text-blue-600">
                                            <p>When notifications are muted, you won't receive any alerts for new messages
                                                or updates.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endsection

@push('styles')
    <style>
        .settings-tab {
            @apply text-gray-600 hover:bg-gray-50 hover:text-gray-900;
        }

        .settings-tab.active {
            @apply bg-green-50 text-primary;
        }

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 16px;
            /* Prevents zoom on iOS */
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }

        .input-field:focus {
            outline: none;
            border-color: #724FF9;
            background-color: white;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function showTab(tabName) {
            // Hide all content
            document.querySelectorAll('.settings-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Add active class to selected tab
            document.getElementById('tab-' + tabName).classList.add('active');

            // Reinitialize icons
            lucide.createIcons();
        }

        // Avatar preview
        document.getElementById('avatar-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Notifications toggle
        $('#notifications-toggle').on('change', function() {
            const isChecked = $(this).is(':checked');

            $.ajax({
                url: '{{ route('settings.toggle-notifications') }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    const message = response.notifications_muted ?
                        'Notifications muted successfully' :
                        'Notifications enabled successfully';

                    // Show success message
                    const successDiv = $(
                        '<div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">' +
                        '<div class="flex items-center space-x-2">' +
                        '<i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>' +
                        '<p class="text-sm text-green-600">' + message + '</p>' +
                        '</div></div>');

                    $('.max-w-4xl').prepend(successDiv);
                    lucide.createIcons();

                    setTimeout(() => {
                        successDiv.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                },
                error: function() {
                    alert('Failed to update notification settings');
                    // Revert toggle
                    $('#notifications-toggle').prop('checked', !isChecked);
                }
            });
        });

        // Initialize icons on page load
        lucide.createIcons();
    </script>
@endpush
