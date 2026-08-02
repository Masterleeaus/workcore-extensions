<?php

$groups = require dirname(__DIR__) . '/manifests/permissions.php';
$permissions = [];
array_walk_recursive($groups, static function ($value) use (&$permissions): void {
    if (is_string($value)) {
        $permissions[] = $value;
    }
});

return [
    'group' => 'Managed Premises',
    'permissions' => array_values(array_unique($permissions)),
];
