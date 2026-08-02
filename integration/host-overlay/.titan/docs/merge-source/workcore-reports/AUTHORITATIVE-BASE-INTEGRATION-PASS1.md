# Authoritative Base Integration Pass 1

Source authority:
- source_code(3).zip
- database_with_data(2).sql

Applied upgrades:
- Meetup host hardening
- company tenancy and memberships
- company-scoped chat records
- private attachment handling
- WorkCore canonical runtime under app/Domains/WorkCore
- one host-facing WorkCoreServiceProvider
- versioned API routes
- governed WorkCore action and Titan Zero tool endpoints
- durable offline-operation intake and client queue scaffolding

The supplied SQL database is retained unchanged at database/reference/database_with_data_authoritative.sql for compatibility review. It is not executed automatically.
