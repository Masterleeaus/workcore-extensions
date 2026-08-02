<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Applications\ApplicationState;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Applications\EnquiryState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApplication;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApplicationEvent;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyEnquiry;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyListing;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationSubmitted;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationStatusChanged;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationConverted;

class PremiseApplicationService
{
    public function __construct(
        private readonly PremiseOccupancyService $occupancies,
        private readonly PremiseCommunicationService $communications,
    ) {}

    public function submitEnquiry(PremiseVacancyListing $listing, array $attributes): PremiseVacancyEnquiry
    {
        $this->assertPublicListing($listing);
        return PremiseVacancyEnquiry::create([
            'company_id' => $listing->company_id,
            'premise_id' => $listing->premise_id,
            'vacancy_listing_id' => $listing->id,
            'space_id' => $listing->space_id,
            'public_reference' => 'ENQ-' . Str::upper(Str::random(12)),
            'status' => 'new',
            'display_name' => $attributes['display_name'],
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'preferred_contact' => $attributes['preferred_contact'] ?? null,
            'message' => $attributes['message'] ?? null,
            'source' => $attributes['source'] ?? 'public_vacancy',
            'consent_at' => now(),
            'metadata' => ['privacy_notice_version' => $attributes['privacy_notice_version'] ?? '1'],
        ]);
    }

    public function submitApplication(PremiseVacancyListing $listing, array $attributes): array
    {
        $this->assertPublicListing($listing);
        return DB::transaction(function () use ($listing, $attributes): array {
            $token = $this->newToken();
            $application = PremiseApplication::create([
                'company_id' => $listing->company_id,
                'user_id' => null,
                'premise_id' => $listing->premise_id,
                'space_id' => $listing->space_id,
                'vacancy_listing_id' => $listing->id,
                'enquiry_id' => $attributes['enquiry_id'] ?? null,
                'application_number' => 'APP-' . now()->format('ymd') . '-' . Str::upper(Str::random(10)),
                'status' => 'submitted',
                'applicant_name' => $attributes['applicant_name'],
                'applicant_email' => $attributes['applicant_email'] ?? null,
                'applicant_phone' => $attributes['applicant_phone'] ?? null,
                'portal_token_hash' => hash('sha256', $token),
                'portal_token_hint' => substr($token, -8),
                'portal_expires_at' => now()->addDays((int) ($attributes['portal_days'] ?? 90)),
                'applicant_payload' => $attributes['applicant_payload'] ?? [],
                'eligibility_answers' => $attributes['eligibility_answers'] ?? [],
                'submitted_at' => now(),
                'metadata' => ['privacy_notice_version' => $attributes['privacy_notice_version'] ?? '1'],
            ]);
            $this->event($application, 'submitted', null, 'submitted', 'Application submitted.', 'portal');
            $this->communications->conversationForApplication($application);
            event(new PremiseApplicationSubmitted($application));
            if ($application->enquiry_id) {
                $enquiry = PremiseVacancyEnquiry::withoutGlobalScopes()->where('company_id', $application->company_id)->whereKey($application->enquiry_id)->lockForUpdate()->first();
                if ($enquiry && !EnquiryState::isTerminal($enquiry->status)) {
                    $enquiry->update(['status' => 'converted', 'converted_application_id' => $application->id, 'closed_at' => now()]);
                }
            }
            return [$application->fresh(), $token];
        });
    }

    public function transition(PremiseApplication $application, string $toStatus, array $attributes = []): PremiseApplication
    {
        return DB::transaction(function () use ($application, $toStatus, $attributes): PremiseApplication {
            $application = $this->locked($application);
            $from = $application->status;
            if (!ApplicationState::canTransition($from, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Application cannot move from {$from} to {$toStatus}."]);
            }
            if ($toStatus === 'converted') {
                throw ValidationException::withMessages(['status' => 'Use the authorised conversion action to create premise records.']);
            }
            $updates = ['status' => $toStatus];
            if (array_key_exists('screening_notes', $attributes)) $updates['screening_notes'] = $attributes['screening_notes'];
            if (array_key_exists('decision_reason', $attributes)) $updates['decision_reason'] = $attributes['decision_reason'];
            if (array_key_exists('assigned_user_id', $attributes)) $updates['assigned_user_id'] = $attributes['assigned_user_id'];
            $timestamp = ['submitted' => 'submitted_at', 'screening' => 'screened_at', 'offered' => 'offered_at', 'accepted' => 'accepted_at', 'rejected' => 'rejected_at', 'withdrawn' => 'withdrawn_at'][$toStatus] ?? null;
            if ($timestamp) $updates[$timestamp] = now();
            $application->update($updates);
            $visibility = in_array($toStatus, ['screening'], true) ? 'internal' : 'portal';
            $this->event($application, 'status_changed', $from, $toStatus, $attributes['summary'] ?? "Application moved to {$toStatus}.", $visibility);
            event(new PremiseApplicationStatusChanged($application));
            return $application->fresh(['events']);
        });
    }

    public function convertAcceptedApplication(PremiseApplication $application, array $attributes = []): PremiseApplication
    {
        return DB::transaction(function () use ($application, $attributes): PremiseApplication {
            $application = $this->locked($application);
            if ($application->status !== 'accepted') {
                throw ValidationException::withMessages(['application' => 'Only accepted applications may be converted.']);
            }
            $application->loadMissing(['premise', 'space']);
            if (!$application->space) throw ValidationException::withMessages(['space_id' => 'The application no longer has an available space.']);

            [$role, $occupancyType] = $this->profileRole($application->premise->profileKey());
            $partyRole = PremisePartyRole::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $application->company_id, 'source_type' => 'premise_application', 'source_id' => $application->id],
                [
                    'user_id' => $this->actorId(), 'premise_id' => $application->premise_id, 'space_id' => $application->space_id,
                    'party_type' => 'snapshot', 'role' => $attributes['role'] ?? $role, 'display_name' => $application->applicant_name,
                    'email' => $application->applicant_email, 'phone' => $application->applicant_phone,
                    'valid_from' => $attributes['start_date'] ?? now()->toDateString(), 'is_primary' => true,
                    'metadata' => ['application_id' => $application->id],
                ]
            );
            $occupancy = $this->occupancies->create($application->premise, $application->space, [
                'party_role_id' => $partyRole->id,
                'occupancy_type' => $attributes['occupancy_type'] ?? $occupancyType,
                'status' => $attributes['occupancy_status'] ?? 'reserved',
                'start_date' => $attributes['start_date'] ?? null,
                'end_date' => $attributes['end_date'] ?? null,
                'capacity_used' => $attributes['capacity_used'] ?? 1,
                'metadata' => ['source_application_id' => $application->id],
                'notes' => $attributes['notes'] ?? null,
            ]);
            $application->update([
                'status' => 'converted', 'applicant_party_role_id' => $partyRole->id,
                'converted_party_role_id' => $partyRole->id, 'converted_occupancy_id' => $occupancy->id,
                'converted_at' => now(),
            ]);
            $this->event($application, 'converted', 'accepted', 'converted', 'Application converted to a party and occupancy record.', 'internal', [
                'party_role_id' => $partyRole->id, 'occupancy_id' => $occupancy->id,
            ]);
            event(new PremiseApplicationConverted($application));
            return $application->fresh(['convertedPartyRole', 'convertedOccupancy']);
        });
    }

    public function transitionEnquiry(PremiseVacancyEnquiry $enquiry, string $toStatus): PremiseVacancyEnquiry
    {
        return DB::transaction(function () use ($enquiry, $toStatus): PremiseVacancyEnquiry {
            $enquiry = PremiseVacancyEnquiry::withoutGlobalScopes()->where('company_id', $enquiry->company_id)->whereKey($enquiry->id)->lockForUpdate()->firstOrFail();
            if (!EnquiryState::canTransition($enquiry->status, $toStatus)) throw ValidationException::withMessages(['status' => "Enquiry cannot move from {$enquiry->status} to {$toStatus}."]);
            $updates = ['status' => $toStatus];
            if ($toStatus === 'contacted') $updates['contacted_at'] = now();
            if ($toStatus === 'qualified') $updates['qualified_at'] = now();
            if (in_array($toStatus, ['disqualified', 'closed'], true)) $updates['closed_at'] = now();
            $enquiry->update($updates);
            return $enquiry->fresh();
        });
    }

    public function resolvePortal(string $token, ?Request $request = null): PremiseApplication
    {
        $hash = hash('sha256', $token);
        $application = PremiseApplication::withoutGlobalScopes()->where('portal_token_hash', $hash)->firstOrFail();
        if (!$application->portal_expires_at || $application->portal_expires_at->isPast()) abort(410, 'This application portal has expired.');
        $application->forceFill(['portal_last_accessed_at' => now()])->save();
        return $application->fresh(['listing', 'space', 'events']);
    }

    public function portalData(PremiseApplication $application): array
    {
        $application->loadMissing(['listing', 'space']);
        return [
            'application_number' => $application->application_number,
            'status' => $application->status,
            'applicant_name' => $application->applicant_name,
            'listing_title' => $application->listing?->title,
            'space_name' => $application->space?->name,
            'submitted_at' => optional($application->submitted_at)->toIso8601String(),
            'events' => $application->events()->where('visibility', 'portal')->get(['event_type', 'summary', 'occurred_at']),
            'messages' => $this->communications->applicationPortalMessages($application),
        ];
    }

    private function event(PremiseApplication $application, string $type, ?string $from, ?string $to, string $summary, string $visibility, array $payload = []): void
    {
        PremiseApplicationEvent::create([
            'company_id' => $application->company_id, 'application_id' => $application->id,
            'user_id' => $this->actorId(), 'actor_type' => $this->actorId() ? 'operator' : 'applicant',
            'event_type' => $type, 'from_status' => $from, 'to_status' => $to,
            'visibility' => $visibility, 'summary' => $summary, 'payload' => $payload, 'occurred_at' => now(),
        ]);
    }

    private function assertPublicListing(PremiseVacancyListing $listing): void
    {
        if ($listing->status !== 'published') throw ValidationException::withMessages(['listing' => 'This vacancy is not accepting public enquiries or applications.']);
        if ($listing->applications_close_at && $listing->applications_close_at->isPast()) throw ValidationException::withMessages(['listing' => 'Applications have closed.']);
    }

    private function locked(PremiseApplication $application): PremiseApplication
    {
        return PremiseApplication::withoutGlobalScopes()->where('company_id', $application->company_id)->whereKey($application->id)->lockForUpdate()->firstOrFail();
    }

    private function newToken(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
    private function actorId(): ?int { return function_exists('user') && user() ? user()->id : auth()->id(); }
    private function profileRole(string $profile): array
    {
        return match ($profile) {
            'rooming_house', 'community_housing' => ['resident', 'resident'],
            'residential_rental', 'strata_property' => ['tenant', 'tenant'],
            'storage_facility' => ['storage_customer', 'storage_customer'],
            'commercial_property', 'office_centre', 'warehouse' => ['business_occupier', 'business_occupier'],
            default => ['licensee', 'licensee'],
        };
    }
}
