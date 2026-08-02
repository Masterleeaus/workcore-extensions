<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Policies\Concerns;

trait ChecksPmPermissions
{
    protected function has($user, string $perm): bool
    {
        if (function_exists('user_can')) return (bool) user_can($perm);
        if (method_exists($user, 'can')) return $user->can($perm);
        return false;
    }
}
