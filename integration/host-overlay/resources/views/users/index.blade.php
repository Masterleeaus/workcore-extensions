@extends('layouts.app')

@section('title', 'User Management')

@section('content')
    <div class="flex-1 overflow-y-auto p-8">
        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
                    <p class="text-gray-600 mt-2">Manage all users in the system</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="document.getElementById('importModal').classList.remove('hidden')"
                            class="px-6 py-3 bg-white border-2 border-primary text-primary font-semibold rounded-lg hover:bg-primary hover:text-white transition shadow-lg hover:shadow-xl flex items-center space-x-2">
                        <i data-lucide="upload" class="w-5 h-5"></i>
                        <span>Import Users</span>
                    </button>
                    <a href="{{ route('users.create') }}"
                       class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 transition shadow-lg hover:shadow-xl flex items-center space-x-2">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                        <span>Add User</span>
                    </a>
                </div>
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

            <!-- Import Errors -->
            @if (session('import_errors'))
                <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-yellow-800 mb-2">Import completed with errors:</p>
                            <details class="text-sm text-yellow-700">
                                <summary class="cursor-pointer hover:text-yellow-900 font-medium">
                                    View {{ count(session('import_errors')) }} error(s)
                                </summary>
                                <ul class="mt-2 space-y-1 list-disc list-inside">
                                    @foreach(session('import_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Validation Errors -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-600 mt-0.5"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-red-800 mb-2">Please fix the following errors:</p>
                            <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                #
                            </th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                            </th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                            </th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Notification Mute
                            </th>
                            <th class="p-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $index => $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if(blank($user->avatar))
                                            <img
                                                src="{{ avatar($user->name) }}"
                                                alt="{{ $user->name }}"
                                                class="w-10 h-10 rounded-full">
                                        @else
                                            <img
                                                src="{{ getImageUrl($user->avatar) }}"
                                                alt="{{ $user->name }}"
                                                class="w-10 h-10 rounded-full">
                                        @endif

                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->notifications_muted)
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Yes
                                </span>
                                    @else
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    No
                                </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="relative inline-block text-left">
                                        <!-- Trigger button -->
                                        <button onclick="toggleDropdown({{ $user->id }})"
                                                class="p-2 rounded hover:bg-gray-100">
                                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                        </button>

                                        <!-- Dropdown menu -->
                                        <div id="dropdown-{{ $user->id }}"
                                             class="hidden absolute right-0 mt-2 w-36 bg-white shadow-lg rounded-md border z-20 p-2">

                                            <a href="{{ route('users.edit', $user) }}"
                                               class="flex items-center px-3 py-1 text-sm text-gray-700 rounded-md hover:bg-gray-100">
                                                <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                                                Edit
                                            </a>

                                            @if($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.destroy', $user) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            onclick="return confirm('Are you sure you want to delete this user?');"
                                                            class="w-full text-left flex items-center px-3 py-1 rounded-md text-sm text-red-600 hover:bg-gray-100">
                                                        <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No users found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($users->hasPages())
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Import Users</h2>
                    <p class="text-sm text-gray-600 mt-1">Upload a CSV file to import multiple users at once</p>
                </div>
                <button onclick="document.getElementById('importModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form action="{{ route('users.import') }}" method="POST" enctype="multipart/form-data" class="p-6">
                @csrf

                <!-- File Upload Area -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                    <div
                        class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary transition"
                        ondragover="event.preventDefault(); this.classList.add('border-primary', 'bg-primary', 'bg-opacity-5');"
                        ondragleave="this.classList.remove('border-primary', 'bg-primary', 'bg-opacity-5');"
                        ondrop="event.preventDefault(); this.classList.remove('border-primary', 'bg-primary', 'bg-opacity-5'); handleFileDrop(event);">
                        <i data-lucide="upload-cloud" class="w-12 h-12 mx-auto text-gray-400 mb-3"></i>
                        <p class="text-sm text-gray-600 mb-2">
                            <label for="csv_file"
                                   class="text-primary hover:text-secondary cursor-pointer font-semibold">
                                Click to upload
                            </label> or drag and drop
                        </p>
                        <p class="text-xs text-gray-500">CSV file (MAX. 2MB)</p>
                        <input type="file"
                               id="csv_file"
                               name="csv_file"
                               accept=".csv,text/csv"
                               class="hidden"
                               onchange="displayFileName(this)"
                               required>
                        <p id="fileName" class="text-sm text-gray-700 mt-3 font-medium hidden"></p>
                    </div>
                </div>

                <!-- CSV Format Info -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-blue-800 mb-2">CSV Format Requirements:</p>
                            <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
                                <li>First row must be headers: <code class="bg-blue-100 px-1 rounded">name,email,password</code>
                                </li>
                                <li>Each row should contain: name, email, and password (min 6 characters)</li>
                                <li>Duplicate emails will be skipped</li>
                                <li>Invalid rows will be reported after import</li>
                            </ul>
                            <div class="mt-3">
                                <a href="data:text/csv;charset=utf-8,name,email,password%0AJohn Doe,john@example.com,password123%0AJane Smith,jane@example.com,password456"
                                   download="sample_users.csv"
                                   class="inline-flex items-center space-x-1 text-sm text-blue-600 hover:text-blue-800 font-medium">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    <span>Download Sample CSV</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-3">
                    <button type="button"
                            onclick="document.getElementById('importModal').classList.add('hidden')"
                            class="px-6 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-primary to-secondary text-white font-semibold rounded-lg hover:opacity-90 transition shadow-lg hover:shadow-xl flex items-center space-x-2">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        <span>Import Users</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        function displayFileName(input) {
            const fileNameDisplay = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                fileNameDisplay.textContent = '📄 ' + input.files[0].name;
                fileNameDisplay.classList.remove('hidden');
            }
        }

        function handleFileDrop(event) {
            const fileInput = document.getElementById('csv_file');
            const dataTransfer = event.dataTransfer;

            if (dataTransfer.files.length > 0) {
                fileInput.files = dataTransfer.files;
                displayFileName(fileInput);
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.getElementById('importModal').classList.add('hidden');
            }
        });

        // Close modal when clicking outside
        document.getElementById('importModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });

        // Dropdown toggle function
        function toggleDropdown(id) {
            const menu = document.getElementById('dropdown-' + id);
            menu.classList.toggle('hidden');

            // Close others
            document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                if (el.id !== 'dropdown-' + id) el.classList.add('hidden');
            });
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function (e) {
            document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                if (!el.contains(e.target) && !e.target.closest('button')) {
                    el.classList.add('hidden');
                }
            });
        });
    </script>
@endpush
