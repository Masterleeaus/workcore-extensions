<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveDomainBlueprintValidator
{
    /** @param array<int,string> $allowedReferenceTypes */
    public function __construct(
        private array $allowedReferenceTypes = [],
        private int $maxFields = 50,
    ) {
        if ($maxFields < 1 || $maxFields > 200) {
            throw new InvalidArgumentException('Adaptive domain max fields must be between 1 and 200.');
        }
        $this->allowedReferenceTypes = array_values(array_unique(array_map('strval', $allowedReferenceTypes)));
    }
    private const ALLOWED_FIELD_TYPES = [
        'text','textarea','integer','number','boolean','date','datetime','email','phone','url',
        'select','multiselect','money','reference','json',
    ];

    private const RESERVED_SLUGS = [
        'companies','company','users','user','memberships','permissions','roles','actions','events','migrations',
        'config','system','workcore','finance','tenancy','authorization','expansion',
    ];

    private const RESERVED_FIELDS = [
        'id','public_id','company_id','created_at','updated_at','deleted_at','created_by_user_id','updated_by_user_id',
        'domain_id','adaptive_domain_id','blueprint','capability_key','status',
    ];

    /** @param array<string,mixed> $blueprint @return array<string,mixed> */
    public function validate(array $blueprint): array
    {
        $this->assertNoExecutableContent($blueprint);

        $slug = strtolower(trim((string) ($blueprint['slug'] ?? '')));
        if (! preg_match('/^[a-z][a-z0-9-]{2,55}$/', $slug)) {
            throw new InvalidArgumentException('Adaptive domain slug must be 3-56 lowercase letters, numbers or hyphens and start with a letter.');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new InvalidArgumentException("Adaptive domain slug [{$slug}] is reserved.");
        }

        $name = trim((string) ($blueprint['name'] ?? ''));
        if ($name === '' || strlen($name) > 120) {
            throw new InvalidArgumentException('Adaptive domain name is required and must not exceed 120 characters.');
        }

        $capabilityKey = trim((string) ($blueprint['capability_key'] ?? ''));
        if (! preg_match('/^workcore\.adaptive\.[a-z][a-z0-9_]{2,79}$/', $capabilityKey)) {
            throw new InvalidArgumentException('Adaptive capability key must use the workcore.adaptive.* namespace.');
        }

        $risk = (string) ($blueprint['risk'] ?? 'low');
        if (! in_array($risk, ['low', 'medium'], true)) {
            throw new InvalidArgumentException('Adaptive domain risk must be low or medium.');
        }

        $fields = array_values((array) ($blueprint['fields'] ?? []));
        if ($fields === [] || count($fields) > $this->maxFields) {
            throw new InvalidArgumentException("Adaptive domains require between 1 and {$this->maxFields} fields.");
        }

        $validatedFields = [];
        $seenFields = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException('Each adaptive field must be an object.');
            }
            $fieldName = strtolower(trim((string) ($field['name'] ?? '')));
            if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $fieldName)) {
                throw new InvalidArgumentException("Adaptive field name [{$fieldName}] is invalid.");
            }
            if (in_array($fieldName, self::RESERVED_FIELDS, true)) {
                throw new InvalidArgumentException("Adaptive field name [{$fieldName}] is reserved.");
            }
            if (isset($seenFields[$fieldName])) {
                throw new InvalidArgumentException("Adaptive field [{$fieldName}] is duplicated.");
            }
            $seenFields[$fieldName] = true;

            $type = strtolower(trim((string) ($field['type'] ?? '')));
            if (! in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                throw new InvalidArgumentException("Adaptive field type [{$type}] is not allowed.");
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '' || strlen($label) > 120) {
                throw new InvalidArgumentException("Adaptive field [{$fieldName}] requires a label up to 120 characters.");
            }

            $validated = [
                'name' => $fieldName,
                'label' => $label,
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
            ];

            if (in_array($type, ['select', 'multiselect'], true)) {
                $options = array_values(array_unique(array_map('strval', (array) ($field['options'] ?? []))));
                if ($options === [] || count($options) > 100) {
                    throw new InvalidArgumentException("Adaptive field [{$fieldName}] requires between 1 and 100 allowed options.");
                }
                $validated['options'] = $options;
            }

            if ($type === 'reference') {
                $referenceType = trim((string) ($field['reference_type'] ?? ''));
                if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $referenceType)) {
                    throw new InvalidArgumentException("Adaptive reference field [{$fieldName}] requires a valid reference_type.");
                }
                if ($this->allowedReferenceTypes !== [] && ! in_array($referenceType, $this->allowedReferenceTypes, true)) {
                    throw new InvalidArgumentException("Adaptive reference field [{$fieldName}] uses unknown reference type [{$referenceType}].");
                }
                $validated['reference_type'] = $referenceType;
            }

            if (isset($field['description'])) {
                $validated['description'] = trim((string) $field['description']);
            }
            if (array_key_exists('default', $field)) {
                $validated['default'] = $field['default'];
            }

            $validatedFields[] = $validated;
        }

        $statuses = array_values(array_unique(array_map(
            static fn (mixed $status): string => strtolower(trim((string) $status)),
            (array) ($blueprint['statuses'] ?? ['active']),
        )));
        if ($statuses === [] || count($statuses) > 20) {
            throw new InvalidArgumentException('Adaptive domains require between 1 and 20 statuses.');
        }
        foreach ($statuses as $status) {
            if (! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $status)) {
                throw new InvalidArgumentException("Adaptive status [{$status}] is invalid.");
            }
        }

        $transitions = [];
        foreach (array_values((array) ($blueprint['transitions'] ?? [])) as $transition) {
            if (! is_array($transition)) {
                throw new InvalidArgumentException('Adaptive transitions must be objects.');
            }
            $from = strtolower(trim((string) ($transition['from'] ?? '')));
            $to = strtolower(trim((string) ($transition['to'] ?? '')));
            if (! in_array($from, $statuses, true) || ! in_array($to, $statuses, true) || $from === $to) {
                throw new InvalidArgumentException("Adaptive transition [{$from} -> {$to}] is invalid.");
            }
            $transitions[] = ['from' => $from, 'to' => $to];
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'capability_key' => $capabilityKey,
            'description' => trim((string) ($blueprint['description'] ?? '')),
            'risk' => $risk,
            'fields' => $validatedFields,
            'statuses' => $statuses,
            'transitions' => $transitions,
            'version' => max(1, (int) ($blueprint['version'] ?? 1)),
            'metadata' => is_array($blueprint['metadata'] ?? null) ? $blueprint['metadata'] : [],
        ];
    }

    /** @param array<string,mixed> $value */
    private function assertNoExecutableContent(array $value): void
    {
        $needles = [
            '<?php','<?=','shell_exec','passthru(','proc_open','popen(','eval(','system(','exec(',
            'drop table','alter table','truncate table','<script','javascript:','data:text/html','#!/bin/',
        ];

        $scan = static function (mixed $item) use (&$scan, $needles): void {
            if (is_array($item)) {
                foreach ($item as $child) {
                    $scan($child);
                }
                return;
            }
            if (! is_string($item)) {
                return;
            }
            $normalised = strtolower($item);
            foreach ($needles as $needle) {
                if (str_contains($normalised, $needle)) {
                    throw new InvalidArgumentException('Adaptive domain blueprints cannot contain executable or migration content.');
                }
            }
        };

        $scan($value);
    }
}
