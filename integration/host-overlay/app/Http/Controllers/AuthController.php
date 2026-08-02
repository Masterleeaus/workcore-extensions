<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        $data['demoUsers'] = User::select('id', 'name', 'email')->take(3)->get();
        return view('login')->with($data);
    }

    public function showRegister()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
            ]);

            $companyId = DB::table('tz_companies')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $validated['name'].' Workspace',
                'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
                'status' => 'active',
                'timezone' => 'Australia/Melbourne',
                'currency_code' => 'AUD',
                'country_code' => 'AU',
                'owner_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tz_company_memberships')->insert([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'role_key' => 'owner',
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user->forceFill(['active_company_id' => $companyId])->save();
            return $user;
        });

        auth()->login($user);
        $request->session()->put('titan_company_id', $user->active_company_id);

        return redirect('/chat');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (auth()->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            $request->session()->put('titan_company_id', Auth::user()->active_company_id);
            return redirect()->route('chat');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
