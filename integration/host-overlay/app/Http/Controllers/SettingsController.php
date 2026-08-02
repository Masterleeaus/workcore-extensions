<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|max:2048', // 2MB max
        ]);

        $user = auth()->user();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && file_exists(public_path('storage/' . $user->avatar))) {
                unlink(public_path('storage/' . $user->avatar));
            }

            $file = $request->file('avatar');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('avatars', $fileName, 'public');
            $validated['avatar'] = $filePath;
        }

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully!');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        // Check if current password is correct
        if (!password_verify($validated['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        // Update password
        auth()->user()->update([
            'password' => bcrypt($validated['new_password'])
        ]);

        return back()->with('success', 'Password changed successfully!');
    }

    public function toggleNotifications(Request $request)
    {
        $user = auth()->user();
        $user->update([
            'notifications_muted' => !$user->notifications_muted
        ]);

        return response()->json([
            'success' => true,
            'notifications_muted' => $user->notifications_muted
        ]);
    }
}
