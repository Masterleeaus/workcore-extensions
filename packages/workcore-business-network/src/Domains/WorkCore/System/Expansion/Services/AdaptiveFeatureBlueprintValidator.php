<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveFeatureBlueprintValidator
{
    private const STEP_TYPES = ['set_field', 'transition_status', 'notify', 'invoke_action', 'emit_event'];
    private const OPERATORS = ['equals', 'not_equals', 'in', 'not_in', 'exists', 'not_exists', 'gt', 'gte', 'lt', 'lte', 'contains'];
    private const RECIPIENT_TYPES = ['company_user', 'worker', 'customer', 'customer_contact', 'support_queue'];
    private const CHANNELS = ['in_app', 'email', 'sms', 'push', 'whatsapp', 'telegram'];
    private const RISK_LEVELS = ['low', 'medium'];

    /** @param array<int,string> $allowedActionKeys */
    public function __construct(
        private array $allowedActionKeys = [],
        private int $maxSteps = 10,
        private int $maxConditions = 20,
    ) {
        if ($maxSteps < 1 || $maxSteps > 50) {
            throw new InvalidArgumentException('Adaptive feature maximum steps must be between 1 and 50.');
        }
        if ($maxConditions < 0 || $maxConditions > 100) {
            throw new InvalidArgumentException('Adaptive feature maximum conditions must be between 0 and 100.');
        }
        $this->allowedActionKeys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            $allowedActionKeys,
        ))));
    }


    public function allowsAction(string $actionKey): bool
    {
        return in_array(strtolower(trim($actionKey)), $this->allowedActionKeys, true);
    }

    /** @param array<string,mixed> $blueprint @return array<string,mixed> */
    public function validate(array $blueprint): array
    {
        $this->assertNoExecutableContent($blueprint);

        $slug = strtolower(trim((string) ($blueprint['slug'] ?? '')));
        if (! preg_match('/^[a-z][a-z0-9-]{2,79}$/', $slug)) {
            throw new InvalidArgumentException('Adaptive feature slug must be 3-80 lowercase letters, numbers or hyphens and start with a letter.');
        }

        $name = trim((string) ($blueprint['name'] ?? ''));
        if ($name === '' || strlen($name) > 160) {
            throw new InvalidArgumentException('Adaptive feature name is required and must not exceed 160 characters.');
        }

        $capabilityKey = trim((string) ($blueprint['capability_key'] ?? ''));
        if (! preg_match('/^workcore\.adaptive_feature\.[a-z][a-z0-9_]{2,99}$/', $capabilityKey)) {
            throw new InvalidArgumentException('Adaptive feature capability key must use the workcore.adaptive_feature.* namespace.');
        }

        $risk = strtolower(trim((string) ($blueprint['risk'] ?? 'medium')));
        if (! in_array($risk, self::RISK_LEVELS, true)) {
            throw new InvalidArgumentException('Adaptive feature risk must be low or medium.');
        }

        $trigger = $this->validateTrigger((array) ($blueprint['trigger'] ?? []));
        $conditions = $this->validateConditions((array) ($blueprint['conditions'] ?? ['mode' => 'all', 'rules' => []]));
        $steps = array_values((array) ($blueprint['steps'] ?? []));
        if ($steps === [] || count($steps) > $this->maxSteps) {
            throw new InvalidArgumentException("Adaptive features require between 1 and {$this->maxSteps} steps; maximum exceeded.");
        }

        $validatedSteps = [];
        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                throw new InvalidArgumentException("Adaptive feature step [{$index}] must be an object.");
            }
            $validatedSteps[] = $this->validateStep($step, $index);
        }

        $limits = (array) ($blueprint['limits'] ?? []);
        $maxPerHour = (int) ($limits['max_executions_per_hour'] ?? 100);
        $cooldown = (int) ($limits['cooldown_seconds'] ?? 0);
        $maxDepth = (int) ($limits['max_depth'] ?? 2);
        if ($maxPerHour < 1 || $maxPerHour > 10000) {
            throw new InvalidArgumentException('Adaptive feature max_executions_per_hour must be between 1 and 10000.');
        }
        if ($cooldown < 0 || $cooldown > 604800) {
            throw new InvalidArgumentException('Adaptive feature cooldown_seconds must be between 0 and 604800.');
        }
        if ($maxDepth < 1 || $maxDepth > 10) {
            throw new InvalidArgumentException('Adaptive feature max_depth must be between 1 and 10.');
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'capability_key' => $capabilityKey,
            'description' => trim((string) ($blueprint['description'] ?? '')),
            'risk' => $risk,
            'trigger' => $trigger,
            'conditions' => $conditions,
            'steps' => $validatedSteps,
            'limits' => [
                'max_executions_per_hour' => $maxPerHour,
                'cooldown_seconds' => $cooldown,
                'max_depth' => $maxDepth,
            ],
            'version' => max(1, (int) ($blueprint['version'] ?? 1)),
            'metadata' => is_array($blueprint['metadata'] ?? null) ? $blueprint['metadata'] : [],
        ];
    }

    /** @param array<string,mixed> $trigger @return array<string,mixed> */
    private function validateTrigger(array $trigger): array
    {
        $event = strtolower(trim((string) ($trigger['event'] ?? '')));
        if (! preg_match('/^workcore\.[a-z0-9_.-]{3,120}$/', $event)) {
            throw new InvalidArgumentException('Adaptive feature trigger event must use a valid workcore.* event key.');
        }

        $subjectType = strtolower(trim((string) ($trigger['subject_type'] ?? '')));
        if (! preg_match('/^[a-z][a-z0-9_]{2,63}$/', $subjectType)) {
            throw new InvalidArgumentException('Adaptive feature trigger subject_type is invalid.');
        }

        $validated = ['event' => $event, 'subject_type' => $subjectType];
        if (isset($trigger['domain'])) {
            $domain = strtolower(trim((string) $trigger['domain']));
            if (! preg_match('/^[a-z][a-z0-9_.-]{2,119}$/', $domain)) {
                throw new InvalidArgumentException('Adaptive feature trigger domain is invalid.');
            }
            $validated['domain'] = $domain;
        }
        return $validated;
    }

    /** @param array<string,mixed> $conditions @return array<string,mixed> */
    private function validateConditions(array $conditions): array
    {
        $mode = strtolower(trim((string) ($conditions['mode'] ?? 'all')));
        if (! in_array($mode, ['all', 'any'], true)) {
            throw new InvalidArgumentException('Adaptive feature condition mode must be all or any.');
        }

        $rules = array_values((array) ($conditions['rules'] ?? []));
        if (count($rules) > $this->maxConditions) {
            throw new InvalidArgumentException("Adaptive feature conditions exceed the maximum of {$this->maxConditions}.");
        }

        $validated = [];
        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                throw new InvalidArgumentException("Adaptive feature condition [{$index}] must be an object.");
            }
            $path = trim((string) ($rule['path'] ?? ''));
            if (! $this->validPath($path)) {
                throw new InvalidArgumentException("Adaptive feature condition path [{$path}] is invalid.");
            }
            $operator = strtolower(trim((string) ($rule['operator'] ?? '')));
            if (! in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Adaptive feature condition operator [{$operator}] is not allowed.");
            }
            $item = ['path' => $path, 'operator' => $operator];
            if (! in_array($operator, ['exists', 'not_exists'], true)) {
                if (! array_key_exists('value', $rule)) {
                    throw new InvalidArgumentException("Adaptive feature condition [{$path}] requires a value.");
                }
                if (in_array($operator, ['in', 'not_in'], true) && ! is_array($rule['value'])) {
                    throw new InvalidArgumentException("Adaptive feature condition [{$path}] operator [{$operator}] requires an array value.");
                }
                $item['value'] = $rule['value'];
            }
            $validated[] = $item;
        }

        return ['mode' => $mode, 'rules' => $validated];
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateStep(array $step, int $index): array
    {
        $type = strtolower(trim((string) ($step['type'] ?? '')));
        if (! in_array($type, self::STEP_TYPES, true)) {
            throw new InvalidArgumentException("Adaptive feature step type [{$type}] is not allowed.");
        }

        return match ($type) {
            'set_field' => $this->validateSetField($step, $index),
            'transition_status' => $this->validateTransition($step, $index),
            'notify' => $this->validateNotification($step, $index),
            'invoke_action' => $this->validateAction($step, $index),
            'emit_event' => $this->validateEvent($step, $index),
        };
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateSetField(array $step, int $index): array
    {
        $field = strtolower(trim((string) ($step['field'] ?? '')));
        if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $field)) {
            throw new InvalidArgumentException("Adaptive feature set_field step [{$index}] has an invalid field.");
        }
        $hasValue = array_key_exists('value', $step);
        $hasFrom = isset($step['value_from']);
        if ($hasValue === $hasFrom) {
            throw new InvalidArgumentException("Adaptive feature set_field step [{$index}] requires exactly one of value or value_from.");
        }
        $validated = ['type' => 'set_field', 'field' => $field];
        if ($hasValue) {
            $validated['value'] = $step['value'];
        } else {
            $path = trim((string) $step['value_from']);
            if (! $this->validPath($path)) {
                throw new InvalidArgumentException("Adaptive feature set_field value_from [{$path}] is invalid.");
            }
            $validated['value_from'] = $path;
        }
        return $validated;
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateTransition(array $step, int $index): array
    {
        $status = strtolower(trim((string) ($step['status'] ?? '')));
        if (! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $status)) {
            throw new InvalidArgumentException("Adaptive feature transition_status step [{$index}] has an invalid status.");
        }
        return ['type' => 'transition_status', 'status' => $status];
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateNotification(array $step, int $index): array
    {
        $key = strtolower(trim((string) ($step['notification_key'] ?? '')));
        if (! preg_match('/^workcore\.[a-z0-9_.-]{3,120}$/', $key)) {
            throw new InvalidArgumentException("Adaptive feature notify step [{$index}] requires a workcore.* notification key.");
        }
        $recipientType = strtolower(trim((string) ($step['recipient_type'] ?? '')));
        if (! in_array($recipientType, self::RECIPIENT_TYPES, true)) {
            throw new InvalidArgumentException("Adaptive feature notify step [{$index}] has an unsupported recipient_type.");
        }
        $recipientFrom = trim((string) ($step['recipient_id_from'] ?? ''));
        if (! $this->validPath($recipientFrom)) {
            throw new InvalidArgumentException("Adaptive feature notify step [{$index}] has an invalid recipient_id_from path.");
        }
        $channels = array_values(array_unique(array_map('strval', (array) ($step['channel_hints'] ?? []))));
        foreach ($channels as $channel) {
            if (! in_array($channel, self::CHANNELS, true)) {
                throw new InvalidArgumentException("Adaptive feature notify channel [{$channel}] is not allowed.");
            }
        }
        return [
            'type' => 'notify',
            'notification_key' => $key,
            'recipient_type' => $recipientType,
            'recipient_id_from' => $recipientFrom,
            'channel_hints' => $channels,
            'payload' => $this->validatePayloadMap((array) ($step['payload'] ?? []), "notify step [{$index}]"),
        ];
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateAction(array $step, int $index): array
    {
        $key = strtolower(trim((string) ($step['action_key'] ?? '')));
        if (! preg_match('/^workcore\.[a-z0-9_.-]{3,120}$/', $key)) {
            throw new InvalidArgumentException("Adaptive feature invoke_action step [{$index}] has an invalid action key.");
        }
        if ($this->allowedActionKeys === [] || ! in_array($key, $this->allowedActionKeys, true)) {
            throw new InvalidArgumentException("Adaptive feature action [{$key}] is not allowlisted.");
        }
        return [
            'type' => 'invoke_action',
            'action_key' => $key,
            'payload' => $this->validatePayloadMap((array) ($step['payload'] ?? []), "invoke_action step [{$index}]"),
        ];
    }

    /** @param array<string,mixed> $step @return array<string,mixed> */
    private function validateEvent(array $step, int $index): array
    {
        $event = strtolower(trim((string) ($step['event'] ?? '')));
        if (! preg_match('/^workcore\.(adaptive|feature)\.[a-z0-9_.-]{3,120}$/', $event)) {
            throw new InvalidArgumentException("Adaptive feature emit_event step [{$index}] must use an allowed adaptive event prefix.");
        }
        return [
            'type' => 'emit_event',
            'event' => $event,
            'payload' => $this->validatePayloadMap((array) ($step['payload'] ?? []), "emit_event step [{$index}]"),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function validatePayloadMap(array $payload, string $label): array
    {
        if (count($payload) > 50) {
            throw new InvalidArgumentException("Adaptive feature {$label} payload exceeds 50 keys.");
        }
        $validated = [];
        foreach ($payload as $key => $value) {
            $key = (string) $key;
            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                throw new InvalidArgumentException("Adaptive feature {$label} payload key [{$key}] is invalid.");
            }
            if (! is_array($value) || count(array_intersect(array_keys($value), ['from', 'value'])) !== 1) {
                throw new InvalidArgumentException("Adaptive feature {$label} payload [{$key}] must define exactly one of from or value.");
            }
            if (isset($value['from'])) {
                $path = trim((string) $value['from']);
                if (! $this->validPath($path)) {
                    throw new InvalidArgumentException("Adaptive feature {$label} payload path [{$path}] is invalid.");
                }
                $validated[$key] = ['from' => $path];
            } else {
                $validated[$key] = ['value' => $value['value'] ?? null];
            }
        }
        return $validated;
    }

    private function validPath(string $path): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){0,7}$/', $path);
    }

    /** @param array<string,mixed> $value */
    private function assertNoExecutableContent(array $value): void
    {
        $needles = [
            '<?php', '<?=', 'shell_exec', 'passthru(', 'proc_open', 'popen(', 'eval(', 'system(', 'exec(',
            'drop table', 'alter table', 'truncate table', '<script', 'javascript:', 'data:text/html', '#!/bin/',
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
                    throw new InvalidArgumentException('Adaptive feature blueprints cannot contain executable or migration content.');
                }
            }
        };
        $scan($value);
    }
}
