<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_agreements')) {
            Schema::create('pm_premise_agreements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('previous_agreement_id')->nullable()->index();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->string('agreement_type', 80)->index();
                $table->string('status', 40)->default('draft')->index();
                $table->string('reference', 191)->nullable()->index();
                $table->string('title', 191);
                $table->date('offered_at')->nullable();
                $table->date('signed_at')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('notice_given_at')->nullable();
                $table->date('terminated_at')->nullable();
                $table->string('renewal_type', 40)->default('fixed');
                $table->unsignedInteger('notice_period_days')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->string('document_reference', 191)->nullable();
                $table->text('terms_summary')->nullable();
                $table->string('source_type', 80)->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('previous_agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->foreign('document_id')->references('id')->on('pm_property_documents')->nullOnDelete();
                $table->unique(['company_id', 'source_type', 'source_id'], 'pm_agreement_source_unique');
                $table->index(['company_id', 'premise_id', 'status'], 'pm_agreement_premise_status_index');
                $table->index(['company_id', 'space_id', 'status'], 'pm_agreement_space_status_index');
                $table->index(['company_id', 'occupancy_id', 'status'], 'pm_agreement_occupancy_status_index');
            });
        }

        if (!Schema::hasTable('pm_premise_agreement_parties')) {
            Schema::create('pm_premise_agreement_parties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->string('party_type', 80)->default('snapshot')->index();
                $table->unsignedBigInteger('party_id')->nullable()->index();
                $table->string('display_name', 191);
                $table->string('agreement_role', 80)->index();
                $table->string('signing_status', 40)->default('not_required')->index();
                $table->dateTime('signed_at')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->index(['company_id', 'agreement_id', 'agreement_role'], 'pm_agreement_party_role_index');
                $table->index(['company_id', 'party_type', 'party_id'], 'pm_agreement_party_reference_index');
            });
        }

        if (!Schema::hasTable('pm_premise_agreement_finance_links')) {
            Schema::create('pm_premise_agreement_finance_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->index();
                $table->string('provider', 80)->default('titan_money')->index();
                $table->string('link_type', 80)->index();
                $table->string('external_id', 191)->nullable()->index();
                $table->string('external_reference', 191)->nullable()->index();
                $table->string('status', 40)->default('linked')->index();
                $table->dateTime('linked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->cascadeOnDelete();
                $table->index(['company_id', 'agreement_id', 'link_type'], 'pm_agreement_finance_type_index');
                $table->unique(
                    ['company_id', 'agreement_id', 'provider', 'link_type', 'external_id'],
                    'pm_agreement_finance_link_unique'
                );
            });
        }

        $this->migrateLegacyOccupancyReferences();
    }

    private function migrateLegacyOccupancyReferences(): void
    {
        if (!Schema::hasTable('pm_premise_occupancies')) {
            return;
        }

        $now = now();
        DB::table('pm_premise_occupancies')
            ->whereNotNull('agreement_reference')
            ->where('agreement_reference', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($occupancies) use ($now) {
                foreach ($occupancies as $occupancy) {
                    $agreementType = match ($occupancy->occupancy_type) {
                        'resident' => 'residency_agreement',
                        'tenant' => 'residential_lease',
                        'storage_customer' => 'storage_agreement',
                        'business_occupier' => 'commercial_lease',
                        'licensee' => 'licence_to_occupy',
                        'staff_allocation', 'internal_allocation' => 'internal_allocation',
                        default => 'custom',
                    };

                    $status = match ($occupancy->status) {
                        'applicant' => 'draft',
                        'reserved', 'pending' => 'offered',
                        'active', 'notice_given', 'ending', 'suspended' => 'active',
                        'vacated' => 'expired',
                        'cancelled' => 'cancelled',
                        default => 'draft',
                    };

                    DB::table('pm_premise_agreements')->updateOrInsert(
                        [
                            'company_id' => $occupancy->company_id,
                            'source_type' => 'premise_occupancy',
                            'source_id' => $occupancy->id,
                        ],
                        [
                            'user_id' => $occupancy->user_id,
                            'premise_id' => $occupancy->premise_id,
                            'space_id' => $occupancy->space_id,
                            'occupancy_id' => $occupancy->id,
                            'agreement_type' => $agreementType,
                            'status' => $status,
                            'reference' => $occupancy->agreement_reference,
                            'title' => $occupancy->display_name . ' agreement',
                            'signed_at' => $occupancy->start_date,
                            'start_date' => $occupancy->start_date,
                            'end_date' => $occupancy->end_date,
                            'terminated_at' => $occupancy->actual_end_date,
                            'renewal_type' => 'fixed',
                            'metadata' => json_encode([
                                'migrated_from' => 'pm_premise_occupancies.agreement_reference',
                            ]),
                            'created_at' => $occupancy->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );

                    $agreementId = DB::table('pm_premise_agreements')
                        ->where('company_id', $occupancy->company_id)
                        ->where('source_type', 'premise_occupancy')
                        ->where('source_id', $occupancy->id)
                        ->value('id');

                    if (!$agreementId) {
                        continue;
                    }

                    if ($occupancy->party_role_id) {
                        $partyRole = DB::table('pm_premise_party_roles')->where('id', $occupancy->party_role_id)->first();
                        if ($partyRole) {
                            DB::table('pm_premise_agreement_parties')->updateOrInsert(
                                [
                                    'company_id' => $occupancy->company_id,
                                    'agreement_id' => $agreementId,
                                    'party_role_id' => $partyRole->id,
                                    'agreement_role' => 'occupant',
                                ],
                                [
                                    'user_id' => $occupancy->user_id,
                                    'party_type' => $partyRole->party_type,
                                    'party_id' => $partyRole->party_id,
                                    'display_name' => $partyRole->display_name,
                                    'signing_status' => $status === 'active' ? 'signed' : 'not_required',
                                    'signed_at' => $status === 'active' ? $occupancy->start_date : null,
                                    'is_primary' => true,
                                    'metadata' => json_encode(['migrated_from' => 'pm_premise_occupancies']),
                                    'created_at' => $occupancy->created_at ?? $now,
                                    'updated_at' => $now,
                                ]
                            );
                        }
                    }

                    $this->migrateFinanceReference(
                        (int) $occupancy->company_id,
                        $occupancy->user_id ? (int) $occupancy->user_id : null,
                        (int) $agreementId,
                        'billing_account',
                        $occupancy->billing_account_reference,
                        $now
                    );
                    $this->migrateFinanceReference(
                        (int) $occupancy->company_id,
                        $occupancy->user_id ? (int) $occupancy->user_id : null,
                        (int) $agreementId,
                        'deposit',
                        $occupancy->deposit_reference,
                        $now
                    );
                }
            });
    }

    private function migrateFinanceReference(
        int $companyId,
        ?int $userId,
        int $agreementId,
        string $linkType,
        ?string $reference,
        $now
    ): void {
        if (!$reference) {
            return;
        }

        DB::table('pm_premise_agreement_finance_links')->updateOrInsert(
            [
                'company_id' => $companyId,
                'agreement_id' => $agreementId,
                'provider' => 'external',
                'link_type' => $linkType,
                'external_id' => $reference,
            ],
            [
                'user_id' => $userId,
                'external_reference' => $reference,
                'status' => 'linked',
                'linked_at' => $now,
                'metadata' => json_encode(['migrated_from' => 'pm_premise_occupancies']),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_agreement_finance_links');
        Schema::dropIfExists('pm_premise_agreement_parties');
        Schema::dropIfExists('pm_premise_agreements');
    }
};
