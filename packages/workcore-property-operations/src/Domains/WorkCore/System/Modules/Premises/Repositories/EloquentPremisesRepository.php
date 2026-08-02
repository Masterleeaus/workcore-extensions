<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;
use App\Domains\WorkCore\System\Modules\Premises\Data\PremisesData;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentPremisesRepository extends TenantScopedRepository implements PremisesRepositoryContract
{
    private const TABLE = 'tz_premises';

    public function __construct(
        ConnectionInterface $db,
        \App\Domains\WorkCore\System\Contracts\TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
    ) { parent::__construct($db, $tenant); }

    public function search(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, ?string $customerPublicId = null, ?string $query = null, int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $builder = $this->db->table(self::TABLE.' as premises')
            ->join('tz_customers as customers', function ($join): void {
                $join->on('customers.id', '=', 'premises.customer_id')->on('customers.company_id', '=', 'premises.company_id');
            })
            ->where('premises.company_id', $companyId)
            ->whereNull('premises.deleted_at')
            ->whereNull('customers.deleted_at');
        $this->authorizer->apply($builder, $accessLevel, $actorId, 'premises.created_by_user_id', ['premises.created_by_user_id']);
        $builder->when($customerPublicId, static fn ($q) => $q->where('customers.public_id', $customerPublicId));
        $builder->when($query, static fn ($q) => $q->where(static function ($nested) use ($query): void {
            $nested->where('premises.name', 'like', "%{$query}%")
                ->orWhere('premises.address_line_1', 'like', "%{$query}%")
                ->orWhere('premises.suburb', 'like', "%{$query}%")
                ->orWhere('customers.name', 'like', "%{$query}%");
        }));
        return $builder->select($this->columns())->orderBy('premises.name')->paginate(max(1, min($perPage, 100)));
    }

    public function create(PremisesData $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $customer = $this->db->table('tz_customers')->where('company_id', $companyId)->where('public_id', $data->customerPublicId)->whereNull('deleted_at')->first(['id']);
        if (! $customer) { throw new InvalidArgumentException('Customer does not belong to the active company.'); }
        if ($data->isPrimary) {
            $this->db->table(self::TABLE)->where('company_id', $companyId)->where('customer_id', $customer->id)->whereNull('deleted_at')->update(['is_primary' => false, 'updated_at' => now()]);
        }
        $publicId = (string) Str::ulid();
        $this->db->table(self::TABLE)->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'customer_id'=>$customer->id,'name'=>$data->name,
            'premises_type'=>'service_location','address_line_1'=>$data->addressLine1,'address_line_2'=>$data->addressLine2,'suburb'=>$data->suburb,
            'state'=>$data->state,'postcode'=>$data->postcode,'country_code'=>$data->countryCode,
            'latitude'=>$data->latitude,'longitude'=>$data->longitude,'access_instructions'=>$data->accessInstructions,
            'parking_instructions'=>$data->parkingInstructions,'hazards'=>$data->hazards,
            'service_requirements'=>$data->serviceRequirements,'is_primary'=>$data->isPrimary,'status'=>'active',
            'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->findByPublicId($companyId, $publicId) ?? ['public_id'=>$publicId];
    }

    public function findByPublicId(int $companyId, string $publicId, WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All, ?int $actorId = null): ?array
    {
        $this->assertCompany($companyId);
        $builder = $this->db->table(self::TABLE.' as premises')
            ->join('tz_customers as customers', function ($join): void {
                $join->on('customers.id', '=', 'premises.customer_id')->on('customers.company_id', '=', 'premises.company_id');
            })
            ->where('premises.company_id',$companyId)->where('premises.public_id',$publicId)
            ->whereNull('premises.deleted_at')->whereNull('customers.deleted_at');
        if ($actorId !== null) { $this->authorizer->apply($builder, $accessLevel, $actorId, 'premises.created_by_user_id', ['premises.created_by_user_id']); }
        $row=$builder->select($this->columns())->first(); return $row ? (array)$row : null;
    }

    public function profile(string $premisesPublicId, int $companyId): array
    {
        $premises = $this->premises($premisesPublicId, $companyId);
        $id = (int) $premises->id;
        return [
            'premises' => (array) $premises,
            'spaces' => $this->rows('tz_premises_spaces', $companyId, $id),
            'contacts' => $this->rows('tz_premises_contact_links', $companyId, $id),
            'access_points' => $this->rows('tz_premises_access_points', $companyId, $id),
            'hazards' => $this->rows('tz_premises_hazards', $companyId, $id),
            'service_windows' => $this->rows('tz_premises_service_windows', $companyId, $id),
            'service_plans' => $this->rows('tz_premises_service_plans', $companyId, $id),
            'recent_meter_readings' => $this->db->table('tz_premises_meter_readings')->where('company_id',$companyId)->where('premises_id',$id)->orderByDesc('read_at')->limit(50)->get()->map(fn ($row)=>(array)$row)->all(),
            'identifiers' => $this->rows('tz_premises_identifiers', $companyId, $id),
            'documents' => $this->rows('tz_premises_document_links', $companyId, $id),
        ];
    }

    public function createSpace(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $parentId = $this->spaceId($data['parent_public_id'] ?? null, $companyId, (int)$premises->id);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_spaces')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'parent_id'=>$parentId,
            'space_type'=>$data['space_type'],'code'=>$data['code']??null,'name'=>$data['name'],'floor_label'=>$data['floor_label']??null,
            'area_square_metres'=>$data['area_square_metres']??null,'capacity'=>$data['capacity']??null,'status'=>$data['status']??'active',
            'service_attributes'=>isset($data['service_attributes'])?json_encode($data['service_attributes']):null,'notes'=>$data['notes']??null,
            'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_spaces', $companyId, $publicId);
    }

    public function addHazard(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $spaceId = $this->spaceId($data['space_public_id'] ?? null, $companyId, (int)$premises->id);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_hazards')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,
            'hazard_type'=>$data['hazard_type'],'severity'=>$data['severity']??'medium','status'=>'active','description'=>$data['description'],
            'control_measures'=>$data['control_measures']??null,'required_credential_public_ids'=>isset($data['required_credential_public_ids'])?json_encode($data['required_credential_public_ids']):null,
            'evidence_file_references'=>isset($data['evidence_file_references'])?json_encode($data['evidence_file_references']):null,'review_due_on'=>$data['review_due_on']??null,
            'reported_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_hazards', $companyId, $publicId);
    }

    public function createAccessPoint(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        if (isset($data['access_code']) || isset($data['secret'])) {
            throw new InvalidArgumentException('Raw access secrets are forbidden; provide a Titan Vault reference.');
        }
        $premises = $this->premises($premisesPublicId, $companyId);
        $spaceId = $this->spaceId($data['space_public_id'] ?? null, $companyId, (int)$premises->id);
        $assetId = null;
        if (! empty($data['asset_public_id'])) {
            $asset = $this->db->table('tz_assets')->where('company_id',$companyId)->where('public_id',$data['asset_public_id'])->whereNull('deleted_at')->first(['id']);
            if (! $asset) { throw new InvalidArgumentException('Access asset does not belong to the active company.'); }
            $assetId = (int)$asset->id;
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_access_points')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,'asset_id'=>$assetId,
            'name'=>$data['name'],'access_type'=>$data['access_type']??'entry','vault_secret_reference'=>$data['vault_secret_reference']??null,
            'availability_status'=>$data['availability_status']??'available','instructions'=>isset($data['instructions'])?json_encode($data['instructions']):null,
            'metadata'=>isset($data['metadata'])?json_encode($data['metadata']):null,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_access_points', $companyId, $publicId);
    }

    public function createServiceWindow(string $premisesPublicId, array $data, int $companyId): array
    {
        $premises = $this->premises($premisesPublicId, $companyId);
        $spaceId = $this->spaceId($data['space_public_id'] ?? null, $companyId, (int)$premises->id);
        if ($data['starts_at'] >= $data['ends_at']) { throw new InvalidArgumentException('Service window must end after it starts.'); }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_service_windows')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,'day_of_week'=>$data['day_of_week'],
            'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'timezone'=>$data['timezone']??'Australia/Melbourne','window_type'=>$data['window_type']??'service',
            'active'=>$data['active']??true,'effective_from'=>$data['effective_from']??null,'effective_until'=>$data['effective_until']??null,'notes'=>$data['notes']??null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_service_windows', $companyId, $publicId);
    }

    public function createServicePlan(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $spaceId = $this->spaceId($data['space_public_id'] ?? null, $companyId, (int)$premises->id);
        $serviceId = null;
        if (! empty($data['service_public_id'])) {
            $service = $this->db->table('tz_services')->where('company_id',$companyId)->where('public_id',$data['service_public_id'])->whereNull('deleted_at')->first(['id']);
            if (! $service) { throw new InvalidArgumentException('Service does not belong to the active company.'); }
            $serviceId=(int)$service->id;
        }
        $publicId=(string)Str::ulid();
        $this->db->table('tz_premises_service_plans')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,'service_id'=>$serviceId,
            'name'=>$data['name'],'frequency_type'=>$data['frequency_type']??'recurring','interval_value'=>$data['interval_value']??null,
            'interval_unit'=>$data['interval_unit']??null,'starts_on'=>$data['starts_on']??null,'ends_on'=>$data['ends_on']??null,'next_due_on'=>$data['next_due_on']??null,
            'status'=>$data['status']??'active','requirements'=>isset($data['requirements'])?json_encode($data['requirements']):null,
            'task_template_public_ids'=>isset($data['task_template_public_ids'])?json_encode($data['task_template_public_ids']):null,'notes'=>$data['notes']??null,
            'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_service_plans',$companyId,$publicId);
    }

    public function recordMeterReading(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises=$this->premises($premisesPublicId,$companyId);
        $spaceId=$this->spaceId($data['space_public_id']??null,$companyId,(int)$premises->id);
        $assetId=null;
        if (! empty($data['asset_public_id'])) {
            $asset=$this->db->table('tz_assets')->where('company_id',$companyId)->where('public_id',$data['asset_public_id'])->whereNull('deleted_at')->first(['id','premises_id']);
            if (! $asset || ($asset->premises_id && (int)$asset->premises_id !== (int)$premises->id)) { throw new InvalidArgumentException('Meter asset is not allocated to this premises.'); }
            $assetId=(int)$asset->id;
        }
        $publicId=(string)Str::ulid();
        $this->db->table('tz_premises_meter_readings')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,'asset_id'=>$assetId,
            'meter_reference'=>$data['meter_reference'],'reading_type'=>$data['reading_type'],'reading_value'=>$data['reading_value'],'unit'=>$data['unit'],
            'read_at'=>$data['read_at']??now(),'source'=>$data['source']??'manual','evidence_file_references'=>isset($data['evidence_file_references'])?json_encode($data['evidence_file_references']):null,
            'recorded_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_premises_meter_readings',$companyId,$publicId);
    }

    public function registerIdentifier(string $premisesPublicId, array $data, int $companyId): array
    {
        $premises=$this->premises($premisesPublicId,$companyId);
        $spaceId=$this->spaceId($data['space_public_id']??null,$companyId,(int)$premises->id);
        $publicId=(string)Str::ulid();
        try {
            $this->db->table('tz_premises_identifiers')->insert([
                'public_id'=>$publicId,'company_id'=>$companyId,'premises_id'=>$premises->id,'space_id'=>$spaceId,
                'identifier_type'=>$data['identifier_type'],'value'=>$data['value'],'active'=>$data['active']??true,
                'metadata'=>isset($data['metadata'])?json_encode($data['metadata']):null,'created_at'=>now(),'updated_at'=>now(),
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Identifier already exists within the active company.', previous: $exception);
        }
        return $this->byPublicId('tz_premises_identifiers',$companyId,$publicId);
    }

    public function linkContact(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $contact = $this->db->table('tz_customer_contacts')
            ->where('company_id', $companyId)
            ->where('public_id', (string) $data['contact_public_id'])
            ->whereNull('deleted_at')
            ->first(['id', 'customer_id']);
        if (! $contact || (int) $contact->customer_id !== (int) $premises->customer_id) {
            throw new InvalidArgumentException('Contact does not belong to this premises customer.');
        }
        if (($data['primary'] ?? false) === true) {
            $this->db->table('tz_premises_contact_links')
                ->where('company_id', $companyId)
                ->where('premises_id', $premises->id)
                ->where('role', $data['role'] ?? 'site_contact')
                ->update(['primary' => false, 'updated_at' => now()]);
        }
        $existing = $this->db->table('tz_premises_contact_links')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('contact_id', $contact->id)
            ->where('role', $data['role'] ?? 'site_contact')
            ->first();
        if ($existing) {
            $this->db->table('tz_premises_contact_links')->where('id', $existing->id)->update([
                'primary' => $data['primary'] ?? $existing->primary,
                'notification_preferences' => isset($data['notification_preferences']) ? json_encode($data['notification_preferences']) : $existing->notification_preferences,
                'updated_at' => now(),
            ]);

            return $this->byPublicId('tz_premises_contact_links', $companyId, (string) $existing->public_id);
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_contact_links')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'premises_id' => $premises->id,
            'contact_id' => $contact->id,
            'role' => $data['role'] ?? 'site_contact',
            'primary' => $data['primary'] ?? false,
            'notification_preferences' => isset($data['notification_preferences']) ? json_encode($data['notification_preferences']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->byPublicId('tz_premises_contact_links', $companyId, $publicId);
    }

    public function linkDocument(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $spaceId = $this->spaceId($data['space_public_id'] ?? null, $companyId, (int) $premises->id);
        $existing = $this->db->table('tz_premises_document_links')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('file_reference', (string) $data['file_reference'])
            ->first();
        if ($existing) {
            $this->db->table('tz_premises_document_links')->where('id', $existing->id)->update([
                'space_id' => $spaceId,
                'document_type' => $data['document_type'],
                'title' => $data['title'] ?? $existing->title,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : $existing->metadata,
                'updated_at' => now(),
            ]);

            return $this->byPublicId('tz_premises_document_links', $companyId, (string) $existing->public_id);
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_premises_document_links')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'premises_id' => $premises->id,
            'space_id' => $spaceId,
            'file_reference' => $data['file_reference'],
            'document_type' => $data['document_type'],
            'title' => $data['title'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->byPublicId('tz_premises_document_links', $companyId, $publicId);
    }

    public function resolveHazard(string $premisesPublicId, string $hazardPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $hazard = $this->db->table('tz_premises_hazards')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('public_id', $hazardPublicId)
            ->lockForUpdate()
            ->first();
        if (! $hazard) {
            throw new InvalidArgumentException('Hazard does not belong to the selected premises.');
        }
        if ($hazard->status === 'resolved') {
            return (array) $hazard;
        }
        if (empty($data['resolution_notes']) && empty($data['evidence_file_references'])) {
            throw new InvalidArgumentException('Hazard resolution requires notes or evidence.');
        }
        $metadata = [
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'resolved_by_user_id' => $actorId,
        ];
        $this->db->table('tz_premises_hazards')->where('id', $hazard->id)->update([
            'status' => 'resolved',
            'control_measures' => $data['control_measures'] ?? $hazard->control_measures,
            'evidence_file_references' => isset($data['evidence_file_references']) ? json_encode($data['evidence_file_references']) : $hazard->evidence_file_references,
            'resolved_at' => $data['resolved_at'] ?? now(),
            'updated_at' => now(),
        ]);

        $record = $this->byPublicId('tz_premises_hazards', $companyId, $hazardPublicId);
        $record['resolution'] = $metadata;

        return $record;
    }

    public function readiness(string $premisesPublicId, int $companyId): array
    {
        $premises = $this->premises($premisesPublicId, $companyId);
        $criticalHazards = (int) $this->db->table('tz_premises_hazards')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('status', 'active')
            ->whereIn('severity', ['high', 'critical'])
            ->count();
        $accessTotal = (int) $this->db->table('tz_premises_access_points')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->whereNull('deleted_at')
            ->count();
        $availableAccess = (int) $this->db->table('tz_premises_access_points')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->whereNull('deleted_at')
            ->where('availability_status', 'available')
            ->count();
        $serviceWindows = (int) $this->db->table('tz_premises_service_windows')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('active', true)
            ->count();
        $overduePlans = (int) $this->db->table('tz_premises_service_plans')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('status', 'active')
            ->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', now()->toDateString())
            ->count();
        $blockers = [];
        $warnings = [];
        if ((string) $premises->status !== 'active') {
            $blockers[] = 'premises_inactive';
        }
        if ($criticalHazards > 0) {
            $blockers[] = 'uncontrolled_high_risk_hazards';
        }
        if ($accessTotal > 0 && $availableAccess === 0) {
            $blockers[] = 'no_available_access_point';
        }
        if ($premises->approved_at === null) {
            $warnings[] = 'premises_not_approved';
        }
        if ($accessTotal === 0) {
            $warnings[] = 'access_not_documented';
        }
        if ($serviceWindows === 0) {
            $warnings[] = 'service_window_not_configured';
        }
        if ($overduePlans > 0) {
            $warnings[] = 'service_plan_overdue';
        }
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'attention' : 'ready');

        return [
            'premises_public_id' => $premisesPublicId,
            'status' => $status,
            'ready' => $status === 'ready',
            'blockers' => $blockers,
            'warnings' => $warnings,
            'metrics' => [
                'active_high_risk_hazards' => $criticalHazards,
                'access_points' => $accessTotal,
                'available_access_points' => $availableAccess,
                'active_service_windows' => $serviceWindows,
                'overdue_service_plans' => $overduePlans,
            ],
        ];
    }

    public function approve(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        if ((string) $premises->status === 'archived') {
            throw new InvalidArgumentException('Archived premises cannot be approved without an authorised restore workflow.');
        }
        $this->db->table(self::TABLE)->where('id', $premises->id)->update([
            'approved_at' => $data['approved_at'] ?? now(),
            'approved_by_user_id' => $actorId,
            'status' => 'active',
            'updated_by_user_id' => $actorId,
            'updated_at' => now(),
        ]);

        return $this->findByPublicId($companyId, $premisesPublicId) ?? ['public_id' => $premisesPublicId];
    }

    public function archive(string $premisesPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $premises = $this->premises($premisesPublicId, $companyId);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Archiving premises requires a reason.');
        }
        $openWork = $this->db->table('tz_work_orders')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();
        if ($openWork) {
            throw new InvalidArgumentException('Premises with active work orders cannot be archived.');
        }
        $activeCustody = $this->db->table('tz_asset_assignments')
            ->where('company_id', $companyId)
            ->where('premises_id', $premises->id)
            ->where('active', true)
            ->exists();
        if ($activeCustody) {
            throw new InvalidArgumentException('Premises with active asset custody cannot be archived.');
        }
        $this->db->table(self::TABLE)->where('id', $premises->id)->update([
            'status' => 'archived',
            'archived_at' => $data['archived_at'] ?? now(),
            'archived_by_user_id' => $actorId,
            'archive_reason' => $reason,
            'updated_by_user_id' => $actorId,
            'updated_at' => now(),
        ]);

        return $this->findByPublicId($companyId, $premisesPublicId) ?? ['public_id' => $premisesPublicId];
    }

    private function premises(string $publicId, int $companyId): object
    {
        $this->assertCompany($companyId);
        $row=$this->db->table(self::TABLE)->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->first();
        if (! $row) { throw new InvalidArgumentException('Premises does not belong to the active company.'); }
        return $row;
    }

    private function spaceId(?string $publicId, int $companyId, int $premisesId): ?int
    {
        if ($publicId === null || $publicId === '') { return null; }
        $row=$this->db->table('tz_premises_spaces')->where('company_id',$companyId)->where('premises_id',$premisesId)->where('public_id',$publicId)->whereNull('deleted_at')->first(['id']);
        if (! $row) { throw new InvalidArgumentException('Premises space does not belong to the selected premises.'); }
        return (int)$row->id;
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) { throw new \RuntimeException('Repository actor does not match the active WorkCore actor.'); }
    }

    private function rows(string $table, int $companyId, int $premisesId): array
    {
        return $this->db->table($table)->where('company_id',$companyId)->where('premises_id',$premisesId)->orderBy('id')->get()->map(fn ($row)=>(array)$row)->all();
    }

    private function byPublicId(string $table, int $companyId, string $publicId): array
    {
        $row=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->first();
        return $row?(array)$row:['public_id'=>$publicId];
    }

    private function columns(): array
    {
        return ['premises.public_id','customers.public_id as customer_public_id','customers.name as customer_name','premises.name','premises.premises_type','premises.external_reference','premises.address_line_1','premises.address_line_2','premises.suburb','premises.state','premises.postcode','premises.country_code','premises.latitude','premises.longitude','premises.access_instructions','premises.parking_instructions','premises.hazards','premises.service_requirements','premises.tags','premises.operating_notes','premises.is_primary','premises.status','premises.approved_at','premises.approved_by_user_id','premises.archived_at','premises.archived_by_user_id','premises.archive_reason'];
    }
}
