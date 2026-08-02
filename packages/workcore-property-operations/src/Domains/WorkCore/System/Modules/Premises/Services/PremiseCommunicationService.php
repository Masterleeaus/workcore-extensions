<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Communications\MessageVisibility;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApplication;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseConversation;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseMessage;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseMessageCreated;

class PremiseCommunicationService
{
    public function conversationForApplication(PremiseApplication $application): PremiseConversation
    {
        return PremiseConversation::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $application->company_id, 'application_id' => $application->id],
            ['premise_id' => $application->premise_id, 'subject' => 'Application ' . $application->application_number, 'status' => 'open', 'last_message_at' => now()]
        );
    }

    public function conversationForPortal(PremisePortalGrant $grant): PremiseConversation
    {
        return PremiseConversation::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $grant->company_id, 'portal_grant_id' => $grant->id],
            [
                'premise_id' => $grant->premise_id, 'party_role_id' => $grant->party_role_id,
                'occupancy_id' => $grant->occupancy_id, 'subject' => $grant->label ?: 'Portal conversation',
                'status' => 'open', 'last_message_at' => now(),
            ]
        );
    }

    public function sendOperatorMessage(PremiseConversation $conversation, string $body, string $visibility = 'portal'): PremiseMessage
    {
        if (!in_array($visibility, MessageVisibility::VALUES, true)) throw ValidationException::withMessages(['visibility' => 'Unknown message visibility.']);
        return $this->send($conversation, [
            'user_id' => $this->actorId(), 'direction' => $visibility === 'internal' ? 'internal' : 'outbound',
            'channel' => 'portal', 'visibility' => $visibility, 'body' => $body,
        ]);
    }

    public function sendPortalMessage(PremisePortalGrant $grant, string $body): PremiseMessage
    {
        return $this->send($this->conversationForPortal($grant), [
            'portal_grant_id' => $grant->id, 'direction' => 'inbound', 'channel' => 'portal', 'visibility' => 'portal', 'body' => $body,
        ]);
    }

    public function sendApplicantMessage(PremiseApplication $application, string $body): PremiseMessage
    {
        return $this->send($this->conversationForApplication($application), [
            'application_id' => $application->id, 'direction' => 'inbound', 'channel' => 'application_portal', 'visibility' => 'portal', 'body' => $body,
        ]);
    }

    public function portalMessages(PremisePortalGrant $grant)
    {
        $conversation = $this->conversationForPortal($grant);
        return $conversation->messages()->where('visibility', 'portal')->get(['id', 'direction', 'channel', 'body', 'sent_at', 'read_at']);
    }

    public function applicationPortalMessages(PremiseApplication $application)
    {
        $conversation = $this->conversationForApplication($application);
        return $conversation->messages()->where('visibility', 'portal')->get(['id', 'direction', 'channel', 'body', 'sent_at', 'read_at']);
    }

    public function premiseConversations(Property $premise)
    {
        return PremiseConversation::where('company_id', $premise->company_id)->where('premise_id', $premise->id)
            ->with(['messages' => fn ($query) => $query->latest('sent_at')->limit(20)])->latest('last_message_at')->get();
    }

    private function send(PremiseConversation $conversation, array $attributes): PremiseMessage
    {
        return DB::transaction(function () use ($conversation, $attributes): PremiseMessage {
            if ($conversation->status === 'closed') throw ValidationException::withMessages(['conversation' => 'This conversation is closed.']);
            $message = PremiseMessage::create(array_merge($attributes, [
                'company_id' => $conversation->company_id, 'conversation_id' => $conversation->id, 'sent_at' => now(),
            ]));
            $conversation->update(['last_message_at' => now()]);
            event(new PremiseMessageCreated($message));
            return $message;
        });
    }

    private function actorId(): ?int { return function_exists('user') && user() ? user()->id : auth()->id(); }
}
