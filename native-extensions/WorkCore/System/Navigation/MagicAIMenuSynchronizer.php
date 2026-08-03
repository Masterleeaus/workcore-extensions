<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Navigation;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final class MagicAIMenuSynchronizer
{
    public function __construct(
        private ConnectionInterface $db,
        private WorkCoreWorkspaceCatalogue $catalogue,
    ) {}

    /** @return array{created:int,updated:int,unchanged:int,disabled:int} */
    public function sync(): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'disabled' => 0];
        if (! Schema::hasTable('menus')) {
            return $result;
        }

        $definitions = $this->catalogue->menuDefinitions();
        $ownedKeys = array_values(array_map(
            static fn (array $definition): string => (string) $definition['key'],
            $definitions,
        ));

        $result = $this->db->transaction(function () use ($definitions, $ownedKeys): array {
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'disabled' => 0];
            $parentIds = [];

            foreach ($definitions as $definition) {
                if ($definition['parent_key'] !== null) {
                    continue;
                }
                $parentIds[$definition['key']] = $this->upsert($definition, null, $counts);
            }
            foreach ($definitions as $definition) {
                if ($definition['parent_key'] === null) {
                    continue;
                }
                $parentId = $parentIds[$definition['parent_key']] ?? $this->menuId($definition['parent_key']);
                $this->upsert($definition, $parentId, $counts);
            }

            if (Schema::hasColumn('menus', 'is_active')) {
                $retired = $this->db->table('menus')
                    ->where('key', 'like', 'workcore_%')
                    ->whereNotIn('key', $ownedKeys);
                if (Schema::hasColumn('menus', 'extension')) {
                    $retired->where('extension', 'workcore');
                }

                $payload = ['is_active' => false];
                if (Schema::hasColumn('menus', 'updated_at')) {
                    $payload['updated_at'] = now();
                }
                $counts['disabled'] = $retired->where('is_active', true)->update($payload);
            }

            return $counts;
        });

        $this->regenerateMagicAIMenus();

        return $result;
    }

    /**
     * Existing administrator-controlled order and enabled state are deliberately preserved.
     *
     * @param array<string,mixed> $definition
     * @param array{created:int,updated:int,unchanged:int,disabled:int} $counts
     */
    private function upsert(array $definition, ?int $parentId, array &$counts): int
    {
        $existing = $this->db->table('menus')->where('key', $definition['key'])->first();
        $payload = $this->payload($definition, $parentId, $existing === null);

        if ($existing === null) {
            $id = (int) $this->db->table('menus')->insertGetId($payload);
            $counts['created']++;

            return $id;
        }

        $changes = [];
        foreach ($payload as $column => $value) {
            if ($this->different($existing->{$column} ?? null, $value)) {
                $changes[$column] = $value;
            }
        }
        if ($changes === []) {
            $counts['unchanged']++;
        } else {
            if (Schema::hasColumn('menus', 'updated_at')) {
                $changes['updated_at'] = now();
            }
            $this->db->table('menus')->where('id', $existing->id)->update($changes);
            $counts['updated']++;
        }

        return (int) $existing->id;
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function payload(array $definition, ?int $parentId, bool $creating): array
    {
        $candidate = [
            'parent_id' => $parentId,
            'key' => $definition['key'],
            'route' => $definition['route'],
            'route_slug' => $definition['route_slug'],
            'label' => $definition['label'],
            'icon' => $definition['icon'],
            'params' => json_encode($definition['params'], JSON_THROW_ON_ERROR),
            'type' => $definition['type'],
            'extension' => 'workcore',
        ];
        if ($creating) {
            $candidate['order'] = $definition['order'];
            $candidate['is_active'] = true;
            $candidate['custom_menu'] = false;
            $candidate['created_at'] = now();
            $candidate['updated_at'] = now();
        }

        $payload = [];
        foreach ($candidate as $column => $value) {
            if (Schema::hasColumn('menus', $column)) {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    private function menuId(string $key): ?int
    {
        $value = $this->db->table('menus')->where('key', $key)->value('id');

        return $value === null ? null : (int) $value;
    }

    private function different(mixed $existing, mixed $desired): bool
    {
        if (is_bool($desired)) {
            return (bool) $existing !== $desired;
        }
        if ($desired === null) {
            return $existing !== null;
        }
        if (is_array($desired)) {
            return json_decode((string) $existing, true) !== $desired;
        }

        return (string) $existing !== (string) $desired;
    }

    private function regenerateMagicAIMenus(): void
    {
        $menuService = 'App\\Services\\Common\\MenuService';
        if (! class_exists($menuService)) {
            return;
        }

        $service = app($menuService);
        if (method_exists($service, 'regenerate')) {
            $service->regenerate();
        }
    }
}
