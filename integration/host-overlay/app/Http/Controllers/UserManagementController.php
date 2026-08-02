<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportUsersRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(15);

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully!');
    }

    public function create()
    {
        return view('users.create');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6|confirmed',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $data['password'] = bcrypt($validated['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully!');
    }

    public function destroy(User $user)
    {
        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully!');
    }

    public function import(ImportUsersRequest $request)
    {
        $file = $request->file('csv_file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));

        // Remove header row
        $header = array_shift($csvData);

        // Validate header
        $expectedHeader = ['name', 'email', 'password'];
        if ($header !== $expectedHeader) {
            return back()->withErrors(['csv_file' => 'Invalid CSV format. Expected columns: name, email, password']);
        }

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($csvData as $index => $row) {
            $lineNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Validate row has 3 columns
            if (count($row) !== 3) {
                $errors[] = "Line {$lineNumber}: Invalid number of columns";
                $failedCount++;

                continue;
            }

            [$name, $email, $password] = $row;

            // Validate required fields
            if (empty($name) || empty($email) || empty($password)) {
                $errors[] = "Line {$lineNumber}: Missing required fields";
                $failedCount++;

                continue;
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Line {$lineNumber}: Invalid email format ({$email})";
                $failedCount++;

                continue;
            }

            // Check if email already exists
            if (User::where('email', $email)->exists()) {
                $errors[] = "Line {$lineNumber}: Email already exists ({$email})";
                $skippedCount++;

                continue;
            }

            // Validate password length
            if (strlen($password) < 6) {
                $errors[] = "Line {$lineNumber}: Password must be at least 6 characters";
                $failedCount++;

                continue;
            }

            try {
                User::create([
                    'name' => trim($name),
                    'email' => trim($email),
                    'password' => bcrypt($password),
                ]);
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "Line {$lineNumber}: Failed to create user ({$e->getMessage()})";
                $failedCount++;
            }
        }

        $message = "Import completed: {$successCount} users created";
        if ($skippedCount > 0) {
            $message .= ", {$skippedCount} skipped (duplicates)";
        }
        if ($failedCount > 0) {
            $message .= ", {$failedCount} failed";
        }

        if (!empty($errors)) {
            return redirect()->route('users.index')
                ->with('success', $message)
                ->with('import_errors', $errors);
        }

        return redirect()->route('users.index')->with('success', $message);
    }
}
