# Pass 4 — Complete Business Flow Activation

## Activated flow

`customer → premises/property → work order → domain events → outbox → Meetup operations conversation`

## Governance

- All three writes execute through `BusinessActionDispatcher`.
- Tenant and actor context are inherited from authenticated middleware.
- Explicit confirmation is mandatory.
- Each step has a deterministic child idempotency key.
- Flow state is durable and replay-safe.
- Partial failures remain visible as `failed`; completed steps replay idempotently on retry.
- No direct controller writes to WorkCore operational tables.

## Endpoint

`POST /api/v1/workcore/flows/customer-property-work-order`

Required payload groups: `customer`, `property`, `work_order`, `confirmation_id`.

## Remaining runtime verification

Composer dependencies and an active database are required to execute migrations and the full Pest suite. Static syntax, route, count and archive-integrity checks are included in packaging.
