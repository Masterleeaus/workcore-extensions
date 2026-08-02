# Operational Workflow Activation — Pass 3

## Implemented

- Added a company-scoped `WorkCore Operations` Meetup channel.
- Bound the WorkCore outbox to a real Meetup transport rather than the null transport.
- Domain events now create idempotent Meetup messages keyed by event ID.
- Added polymorphic-style operational conversation links without duplicating WorkCore records.
- Preserved authority boundaries: WorkCore owns operational records/events; Meetup owns conversations/messages.
- Added a feature test covering event delivery, idempotency, company scoping and aggregate linkage.

## Activated flow

`WorkCore action -> domain event -> outbox -> Meetup transport -> operations conversation -> realtime MessageSent event`

## Remaining runtime verification

Composer dependencies and a configured database are required to execute migrations and Pest tests in the target deployment.
