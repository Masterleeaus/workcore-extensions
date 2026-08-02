<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_feedback_links', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('record_type', 64);
            $table->string('record_public_id', 191);
            $table->char('record_hash', 64);
            $table->string('relationship', 64)->default('context');
            $table->char('link_hash', 64);
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'subject_type', 'subject_id', 'link_hash'], 'tz_fb_links_subject_record_uq');
            $table->index(['company_id', 'record_hash'], 'tz_fb_links_record_ix');
            $table->index(['company_id', 'subject_type', 'subject_id'], 'tz_fb_links_subject_ix');
        });

        $this->backfillDirectContext();
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_feedback_links');
    }

    private function backfillDirectContext(): void
    {
        $mappings = [
            'customer_id' => ['tz_customers', 'customer'],
            'contact_id' => ['tz_customer_contacts', 'contact'],
            'premises_id' => ['tz_premises', 'premises'],
            'work_order_id' => ['tz_work_orders', 'work_order'],
            'appointment_id' => ['tz_appointments', 'appointment'],
            'worker_id' => ['tz_workers', 'worker'],
            'service_id' => ['tz_services', 'service'],
        ];

        foreach ([['tz_feedback_items', 'feedback_item'], ['tz_feedback_requests', 'feedback_request']] as [$sourceTable, $subjectType]) {
            foreach ($mappings as $sourceColumn => [$recordTable, $recordType]) {
                if (! Schema::hasColumn($sourceTable, $sourceColumn) || ! Schema::hasTable($recordTable)) {
                    continue;
                }
                DB::table($sourceTable)
                    ->whereNotNull($sourceColumn)
                    ->orderBy('id')
                    ->chunkById(250, function ($rows) use ($sourceColumn, $recordTable, $recordType, $subjectType): void {
                        foreach ($rows as $row) {
                            $publicId = DB::table($recordTable)
                                ->where('company_id', $row->company_id)
                                ->where('id', $row->{$sourceColumn})
                                ->value('public_id');
                            if ($publicId === null) {
                                continue;
                            }
                            DB::table('tz_feedback_links')->insertOrIgnore([
                                'public_id' => (string) Str::ulid(),
                                'company_id' => (int) $row->company_id,
                                'subject_type' => $subjectType,
                                'subject_id' => (int) $row->id,
                                'record_type' => $recordType,
                                'record_public_id' => (string) $publicId,
                                'record_hash' => hash('sha256', $recordType . ':' . (string) $publicId),
                                'relationship' => 'context',
                                'link_hash' => hash('sha256', $recordType . ':' . (string) $publicId . ':context'),
                                'created_by_user_id' => null,
                                'metadata' => json_encode(['backfilled' => true], JSON_THROW_ON_ERROR),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    });
            }
        }

        if (Schema::hasTable('tz_feedback_service_recoveries') && Schema::hasTable('tz_support_tickets')) {
            DB::table('tz_feedback_service_recoveries')
                ->whereNotNull('support_ticket_id')
                ->orderBy('id')
                ->chunkById(250, function ($rows): void {
                    foreach ($rows as $row) {
                        $publicId = DB::table('tz_support_tickets')
                            ->where('company_id', $row->company_id)
                            ->where('id', $row->support_ticket_id)
                            ->value('public_id');
                        if ($publicId === null) {
                            continue;
                        }
                        DB::table('tz_feedback_links')->insertOrIgnore([
                            'public_id' => (string) Str::ulid(),
                            'company_id' => (int) $row->company_id,
                            'subject_type' => 'feedback_item',
                            'subject_id' => (int) $row->feedback_item_id,
                            'record_type' => 'support_ticket',
                            'record_public_id' => (string) $publicId,
                            'record_hash' => hash('sha256', 'support_ticket:' . (string) $publicId),
                            'relationship' => 'service_recovery',
                            'link_hash' => hash('sha256', 'support_ticket:' . (string) $publicId . ':service_recovery'),
                            'created_by_user_id' => null,
                            'metadata' => json_encode(['backfilled' => true], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }
};
