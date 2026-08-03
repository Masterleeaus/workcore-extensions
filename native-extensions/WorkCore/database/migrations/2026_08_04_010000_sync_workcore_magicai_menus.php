<?php

declare(strict_types=1);

use App\Extensions\WorkCore\System\Navigation\MagicAIMenuSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        app(MagicAIMenuSynchronizer::class)->sync();
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasColumn('menus', 'is_active')) {
            return;
        }

        $query = DB::table('menus')->where('key', 'like', 'workcore_%');
        if (Schema::hasColumn('menus', 'extension')) {
            $query->where('extension', 'workcore');
        }

        $payload = ['is_active' => false];
        if (Schema::hasColumn('menus', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        $query->update($payload);
    }
};
