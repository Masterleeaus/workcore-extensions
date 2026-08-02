# ManagedPremises Upgrade Pass 8 — AI Panels, Mobile, Portals and Vacancies

Version: 1.8.0

## Scope

Pass 8 adds presentation and access surfaces above the existing premises domain. It does not create a second occupancy, workflow, identity, marketing-channel, maintenance or finance engine.

## AIChatPro generative panels

Four versioned `managedpremises.panel.v1` schemas are supplied:

- premise operations
- vacancy board
- mobile workflows
- portal summary

Panel payloads contain operational summaries only. Access secrets, portal tokens, restricted incidents, personal contact fields and finance identifiers are excluded. Panel actions point back to existing permissioned services and cannot bypass approvals.

## Mobile workflows

Authenticated Sanctum endpoints and a compact web view expose active or paused workflows assigned to the current user or left unassigned. Mobile clients can read stages and advance the current stage. Assignment, company, status and permission checks still apply.

## Portal access

Portal grants can be linked to a party role, occupancy and agreement. A 256-bit random token is shown once; only its SHA-256 hash and short hint are retained. Grants are time-limited and revocable, and access events store a salted IP hash rather than the raw address.

Portal data is capability-scoped. It can show premise, space, occupancy, agreement, workflow and access-authorisation summaries, and can submit standard incidents. It cannot expose credentials, internal finance references, restricted incidents or unrelated parties.

## Vacancy board

Vacancy listings are linked to occupiable spaces and follow:

`draft → pending approval → approved → published/paused → closed`

A draft cannot publish directly. Website publication is handled by ManagedPremises. External channels create immutable approved payload snapshots and dispatch `property.vacancy.publication_requested`; the installed channel integration owns credentials and delivery.

Exact addresses are hidden unless an authorised user explicitly opts in. Public payloads contain only the approved listing copy, public location, space summary, dates, features, eligibility notes and explicitly supplied public contact reference.

## Boundaries

- AIChatPro owns generative rendering.
- Chatbot PWA or Titan Go owns mobile presentation.
- Host authentication owns full authenticated customer accounts.
- ManagedPremises owns token-scoped portal grants and public vacancy records.
- Channel integrations own Facebook, Instagram, Google Business, marketplace and other credentials/delivery.
- WorkCore owns maintenance execution.
- Titan Money owns financial transactions and balances.
