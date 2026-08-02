# Titan BOS Security Concept Extraction

Source donor: `Modules for Titan BOS.zip` → `Modules for Titan BOS/Security.zip`

Target: `ManagedPremises` v1.9.0 working tree.

## Deep-scan findings

The Security donor combines four older modules. Two domains belong naturally in ManagedPremises:

1. **Physical access cards**
   - request header linked to a unit/site;
   - one request containing multiple card items;
   - item-level approval;
   - physical card number assigned at issue;
   - issue and return lifecycle;
   - requester contact and an optional card fee.

2. **Work permits**
   - contractor/company details;
   - project manager and site coordinator;
   - work type, scope and detailed work description;
   - start and end dates;
   - manager approval;
   - building/premises manager approval;
   - final security validation with remarks/evidence;
   - attached files;
   - unit/site association.

## Donor structures inspected

- `tr_access_card`
- `tr_access_card_items`
- `tr_workpermits`
- `tr_workpermit_files`
- `TrAccessCard`, `CardItems`, `WorkPermits`, `WorkPermitsFile`
- `CardAccessController`, `WorkPermitsController`, `SecurityWPController`
- `Workflows/Definitions/security.json`
- access-card and work-permit views, requests, permissions and reports

## Concepts retained

### Physical access cards

ManagedPremises now owns:

- access-card request batches;
- individual requested card items;
- item-level approval and rejection;
- physical issue and return;
- linkage to premise, hierarchical space, party, occupancy and agreement;
- linkage from each issued request item to the canonical `PremiseAccessItem` custody register;
- external fee/invoice reference only.

A physical card is not duplicated after issue. The request item links to a canonical access item of type `access_card`, which owns the card identifier, holder, expiry and custody-event history.

### Work permits

ManagedPremises now owns:

- permit identity and validity window;
- contractor and site-contact context;
- generic work types and categories;
- risk, hazards and controls;
- induction requirement;
- manager approval;
- premises-manager approval;
- final validation;
- approval history;
- document-vault evidence references;
- external WorkCore record links.

WorkCore remains authoritative for jobs, work orders, scheduling, dispatch and work completion.

## Donor behaviour deliberately not copied

- hard dependency on the legacy `units` table;
- unscoped `Model::all()` access;
- boolean-only approvals that overwrite history;
- hard-coded Indonesian renovation and work-scope enums;
- duplicate work-permit attachment storage;
- direct card-fee value storage and financial calculations;
- plaintext exposure of card numbers or credentials;
- legacy controller, PDF and route duplication;
- automatic inference that validation means the work itself is complete.

## New canonical tables

- `pm_premise_access_card_requests`
- `pm_premise_access_card_request_items`
- `pm_premise_work_permits`
- `pm_premise_work_permit_approvals`

The new tables contain optional legacy source IDs for a later governed data-migration tool. This pass extracts the domain concepts and does not silently import records whose legacy `unit_id` cannot be reliably mapped to a ManagedPremises premise and space.

## Security and AI boundaries

- Card identifiers remain in the canonical access register and are excluded from untrusted AI context.
- Requester contact details are hidden from model serialisation by default.
- Work-permit approval, validation and activation require authorised human actions.
- Approval decisions are append-only.
- Permit evidence references the existing ManagedPremises document vault.
- Titan Money owns card fees, invoices and payment transactions.
- WorkCore owns execution of the permitted work.
