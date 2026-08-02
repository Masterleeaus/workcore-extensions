# ManagedPremises Upgrade Pass 6 — Compliance Register

Version 1.6.0 adds company-scoped rule packs, requirement provenance, recurring occurrences, evidence review, due-state refresh, profile-compatible starter templates, and compliance dashboards.

## Boundary

ManagedPremises records obligations, sources, evidence, review status, and dates. It does not determine whether a premise is legally compliant, provide legal advice, or auto-activate unverified rule packs. Applicability and legal interpretation remain with the customer or a qualified adviser.

## Scheduler

Run `php artisan managedpremises:refresh-compliance --company_id=<id>` on an appropriate schedule to refresh due and overdue states.
