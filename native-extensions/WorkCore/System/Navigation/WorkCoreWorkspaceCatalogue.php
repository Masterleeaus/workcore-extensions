<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Navigation;

use InvalidArgumentException;

final class WorkCoreWorkspaceCatalogue
{
    /** @var array<string,array<string,mixed>> */
    private array $workspaces;

    /** @param array<string,array<string,mixed>>|null $definitions */
    public function __construct(?array $definitions = null)
    {
        $definitions ??= require __DIR__ . '/../../config/workcore-workspaces.php';
        $this->workspaces = $this->normalize($definitions);
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return $this->workspaces;
    }

    /** @return array<string,mixed>|null */
    public function workspace(string $key): ?array
    {
        return $this->workspaces[trim($key)] ?? null;
    }

    /** @return array<string,mixed>|null */
    public function section(string $workspace, string $section): ?array
    {
        return $this->workspaces[trim($workspace)]['sections'][trim($section)] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function menuDefinitions(): array
    {
        $definitions = [];
        foreach ($this->workspaces as $workspaceKey => $workspace) {
            $definitions[] = $this->menuDefinition($workspaceKey, null, $workspace);
            foreach ($workspace['sections'] as $sectionKey => $section) {
                $definitions[] = $this->menuDefinition($sectionKey, $workspace['menu_key'], $section);
            }
        }

        return $definitions;
    }

    /**
     * @param array<string,array<string,mixed>> $definitions
     * @return array<string,array<string,mixed>>
     */
    private function normalize(array $definitions): array
    {
        if ($definitions === []) {
            throw new InvalidArgumentException('At least one WorkCore workspace definition is required.');
        }

        $seenMenuKeys = [];
        $seenRoutes = [];
        $seenPaths = [];
        $normalized = [];

        foreach ($definitions as $workspaceKey => $workspace) {
            $workspaceKey = $this->validKey($workspaceKey, 'workspace');
            $root = $this->normalizeDefinition($workspace, $workspaceKey, $seenMenuKeys, $seenRoutes, $seenPaths);
            $sections = $workspace['sections'] ?? null;
            if (! is_array($sections) || $sections === []) {
                throw new InvalidArgumentException("WorkCore workspace [{$workspaceKey}] requires sections.");
            }

            $root['sections'] = [];
            foreach ($sections as $sectionKey => $section) {
                $sectionKey = $this->validKey($sectionKey, "section in {$workspaceKey}");
                if (! is_array($section)) {
                    throw new InvalidArgumentException("WorkCore section [{$workspaceKey}.{$sectionKey}] is invalid.");
                }
                $root['sections'][$sectionKey] = $this->normalizeDefinition(
                    $section,
                    "{$workspaceKey}.{$sectionKey}",
                    $seenMenuKeys,
                    $seenRoutes,
                    $seenPaths,
                );
            }
            uasort($root['sections'], self::byOrder(...));
            $normalized[$workspaceKey] = $root;
        }

        uasort($normalized, self::byOrder(...));

        return $normalized;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,true> $seenMenuKeys
     * @param array<string,true> $seenRoutes
     * @param array<string,true> $seenPaths
     * @return array<string,mixed>
     */
    private function normalizeDefinition(
        array $definition,
        string $context,
        array &$seenMenuKeys,
        array &$seenRoutes,
        array &$seenPaths,
    ): array {
        $menuKey = $this->requiredString($definition, 'menuKey', $context);
        $routeName = $this->requiredString($definition, 'routeName', $context);
        $path = trim($this->requiredString($definition, 'path', $context), '/');
        $capabilities = array_values(array_unique(array_filter(
            $definition['capabilities'] ?? [],
            static fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'workcore.'),
        )));
        if ($capabilities === []) {
            throw new InvalidArgumentException("WorkCore definition [{$context}] requires capabilities.");
        }

        $this->reserve($seenMenuKeys, $menuKey, 'menu key', $context);
        $this->reserve($seenRoutes, $routeName, 'route name', $context);
        $this->reserve($seenPaths, $path, 'path', $context);

        return [
            'menu_key' => $menuKey,
            'label' => $this->requiredString($definition, 'label', $context),
            'icon' => $this->requiredString($definition, 'icon', $context),
            'route_name' => $routeName,
            'path' => $path,
            'order' => max(0, (int) ($definition['order'] ?? 0)),
            'capabilities' => $capabilities,
            'description' => $this->requiredString($definition, 'description', $context),
        ];
    }

    /** @param array<string,mixed> $definition */
    private function requiredString(array $definition, string $key, string $context): string
    {
        $value = $definition[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("WorkCore definition [{$context}] requires [{$key}].");
        }

        return trim($value);
    }

    private function validKey(mixed $value, string $context): string
    {
        if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9-]*$/', $value)) {
            throw new InvalidArgumentException("Invalid WorkCore {$context} key.");
        }

        return $value;
    }

    /** @param array<string,true> $seen */
    private function reserve(array &$seen, string $value, string $type, string $context): void
    {
        if (isset($seen[$value])) {
            $label = match ($type) {
                'menu key' => 'Duplicate WorkCore workspace menu key',
                'route name' => 'Duplicate WorkCore workspace route name',
                default => 'Duplicate WorkCore workspace path',
            };
            throw new InvalidArgumentException("{$label} [{$value}] at [{$context}].");
        }
        $seen[$value] = true;
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function byOrder(array $left, array $right): int
    {
        return [$left['order'], $left['menu_key']] <=> [$right['order'], $right['menu_key']];
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function menuDefinition(string $key, ?string $parentKey, array $definition): array
    {
        return [
            'key' => $definition['menu_key'],
            'catalogue_key' => $key,
            'parent_key' => $parentKey,
            'route' => $definition['route_name'],
            'route_slug' => $definition['path'],
            'label' => $definition['label'],
            'icon' => $definition['icon'],
            'order' => $definition['order'],
            'is_active' => true,
            'params' => [],
            'type' => $parentKey === null ? 'dropdown' : 'item',
            'extension' => 'workcore',
            'active_condition' => [$definition['route_name'], $definition['route_name'] . '.*'],
            'capabilities' => $definition['capabilities'],
        ];
    }
}
