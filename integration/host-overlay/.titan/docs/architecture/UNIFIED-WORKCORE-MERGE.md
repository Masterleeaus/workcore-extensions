# Unified WorkCore

This package consolidates the canonical WorkCore domain, selected Agent 1 native AI/host/Rewind runtime additions, WorkCore-focused Agent 4 verification tests, and the neutral Laravel host from the merged application.

## Removed

- Meetup chat controllers, models, routes, views and migrations
- conversations, participants, messages and read receipts
- operational conversation links and Titan chat proposals
- Meetup broadcast channels and message attachment delivery
- Meetup outbox transport
- bundled extension archive tree

## Retained

- WorkCore canonical domain and migrations
- company tenancy and permissions
- WorkCore action API and business flows
- offline operation sync
- native AI orchestration inside WorkCore
- WorkCore host adapters and Rewind adapters
- authentication, users, settings and neutral dashboard host

The default outbox transport is `NullOutboxTransport`. Configure a production transport through `WORKCORE_OUTBOX_TRANSPORT`.
