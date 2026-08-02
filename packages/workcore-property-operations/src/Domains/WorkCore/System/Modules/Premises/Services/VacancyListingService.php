<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies\VacancyListingState;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies\VacancyPublicationState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyListing;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyPublication;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseVacancyApprovalRequested;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseVacancyPublicationRequested;

class VacancyListingService
{
    public function create(Property $premise, PremiseSpace $space, array $attributes): PremiseVacancyListing
    {
        $this->assertSpace($premise, $space);
        if (!$space->is_occupiable || $space->availability_status === 'decommissioned') {
            throw ValidationException::withMessages(['space_id' => 'Only active occupiable spaces can be listed.']);
        }

        return PremiseVacancyListing::create([
            'company_id' => $premise->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $premise->id,
            'space_id' => $space->id,
            'public_slug' => $this->uniqueSlug($attributes['title'] ?? $space->name),
            'listing_type' => $attributes['listing_type'] ?? 'space',
            'status' => 'draft',
            'title' => $attributes['title'],
            'summary' => $attributes['summary'] ?? null,
            'description' => $attributes['description'] ?? null,
            'public_location' => $attributes['public_location'] ?? null,
            'show_exact_address' => (bool) ($attributes['show_exact_address'] ?? false),
            'available_from' => $attributes['available_from'] ?? null,
            'applications_close_at' => $attributes['applications_close_at'] ?? null,
            'capacity' => $attributes['capacity'] ?? $space->capacity,
            'features' => array_values($attributes['features'] ?? []),
            'eligibility_notes' => array_values($attributes['eligibility_notes'] ?? []),
            'contact_mode' => $attributes['contact_mode'] ?? 'portal',
            'external_contact_reference' => $attributes['external_contact_reference'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function transition(PremiseVacancyListing $listing, string $toStatus, array $attributes = []): PremiseVacancyListing
    {
        return DB::transaction(function () use ($listing, $toStatus, $attributes) {
            $listing = $this->locked($listing);
            $from = $listing->status;
            if (!VacancyListingState::canTransition($from, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Vacancy listing cannot move from {$from} to {$toStatus}."]);
            }

            $updates = ['status' => $toStatus];
            if ($toStatus === 'pending_approval') $updates['approval_requested_at'] = now();
            if ($toStatus === 'approved') {
                $updates['approved_at'] = now();
                $updates['approved_by_user_id'] = $attributes['approved_by_user_id'] ?? $this->actorId();
            }
            if ($toStatus === 'published') $updates['published_at'] = now();
            if ($toStatus === 'paused') $updates['paused_at'] = now();
            if ($toStatus === 'closed') $updates['closed_at'] = now();
            if ($toStatus === 'rejected') $updates['rejection_reason'] = $attributes['rejection_reason'] ?? 'Rejected';

            $listing->update($updates);
            if ($toStatus === 'pending_approval') event(new PremiseVacancyApprovalRequested($listing));
            return $listing->fresh(['premise', 'space', 'publications']);
        });
    }

    public function requestPublication(PremiseVacancyListing $listing, string $channel): PremiseVacancyPublication
    {
        if (!in_array($channel, PremiseVacancyListing::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => 'Unsupported publication channel.']);
        }
        if (!in_array($listing->status, ['approved', 'published', 'paused'], true)) {
            throw ValidationException::withMessages(['status' => 'A vacancy must be approved before publication.']);
        }
        $listing->loadMissing(['premise', 'space']);
        if ($listing->space->availability_status === 'decommissioned') {
            throw ValidationException::withMessages(['space_id' => 'A decommissioned space cannot be published.']);
        }

        $publication = PremiseVacancyPublication::create([
            'company_id' => $listing->company_id,
            'vacancy_listing_id' => $listing->id,
            'requested_by_user_id' => $this->actorId(),
            'channel' => $channel,
            'status' => 'pending',
            'payload_snapshot' => $this->publicPayload($listing),
            'requested_at' => now(),
        ]);

        if ($channel === 'website') {
            $publication->update([
                'status' => 'published',
                'published_at' => now(),
                'external_url' => route('managedpremises.public.vacancies.show', $listing->public_slug),
            ]);
            if ($listing->status !== 'published') $this->transition($listing, 'published');
        } else {
            event(new PremiseVacancyPublicationRequested($publication));
        }

        return $publication->fresh();
    }

    public function recordPublicationResult(PremiseVacancyPublication $publication, string $status, array $attributes = []): PremiseVacancyPublication
    {
        $publication->loadMissing('listing');
        if ($status === 'published' && !in_array($publication->listing->status, ['approved', 'published', 'paused'], true)) {
            throw ValidationException::withMessages(['status' => 'A closed, rejected, or unapproved listing cannot accept a successful publication result.']);
        }
        if (!VacancyPublicationState::canTransition($publication->status, $status)) {
            throw ValidationException::withMessages(['status' => 'Publication result is not valid from the current state.']);
        }
        $updates = ['status' => $status];
        if ($status === 'published') {
            $updates['published_at'] = now();
            $updates['external_reference'] = $attributes['external_reference'] ?? null;
            $updates['external_url'] = $attributes['external_url'] ?? null;
        }
        if ($status === 'failed') {
            $updates['failed_at'] = now();
            $updates['error_message'] = $attributes['error_message'] ?? 'Publication failed';
        }
        if ($status === 'withdrawn') $updates['withdrawn_at'] = now();
        $publication->update($updates);

        if ($status === 'published') {
            $listing = $publication->listing;
            if ($listing->status !== 'published') $this->transition($listing, 'published');
        }
        return $publication->fresh();
    }

    public function publicPayload(PremiseVacancyListing $listing): array
    {
        $listing->loadMissing(['premise', 'space']);
        return [
            'slug' => $listing->public_slug,
            'title' => $listing->title,
            'summary' => $listing->summary,
            'description' => $listing->description,
            'location' => $listing->show_exact_address ? $listing->premise->address : $listing->public_location,
            'exact_address_visible' => (bool) $listing->show_exact_address,
            'profile_key' => $listing->premise->profileKey(),
            'space' => [
                'name' => $listing->space->name,
                'type' => $listing->space->space_type,
                'area' => $listing->space->area,
                'capacity' => $listing->capacity,
            ],
            'available_from' => optional($listing->available_from)->toDateString(),
            'applications_close_at' => optional($listing->applications_close_at)->toDateString(),
            'features' => $listing->features ?? [],
            'eligibility_notes' => $listing->eligibility_notes ?? [],
            'contact_mode' => $listing->contact_mode,
            'contact_reference' => $listing->contact_mode === 'portal' ? null : $listing->external_contact_reference,
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'vacancy';
        do { $slug = $base . '-' . Str::lower(Str::random(8)); }
        while (PremiseVacancyListing::withoutGlobalScopes()->where('public_slug', $slug)->exists());
        return $slug;
    }

    private function assertSpace(Property $premise, PremiseSpace $space): void
    {
        if ((int) $space->company_id !== (int) $premise->company_id || (int) $space->premise_id !== (int) $premise->id) {
            throw ValidationException::withMessages(['space_id' => 'The space does not belong to this premise.']);
        }
    }

    private function locked(PremiseVacancyListing $listing): PremiseVacancyListing
    {
        return PremiseVacancyListing::withoutGlobalScopes()->where('company_id', $listing->company_id)->whereKey($listing->id)->lockForUpdate()->firstOrFail();
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
