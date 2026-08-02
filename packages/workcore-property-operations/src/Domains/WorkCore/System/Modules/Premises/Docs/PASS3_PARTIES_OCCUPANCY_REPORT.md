# ManagedPremises Pass 3 — Parties, Occupancy and Allocations

Version: 1.3.0
Date: 22 July 2026

## Delivered

### Premise parties

- Added `pm_premise_party_roles` for company-scoped relationships between a premise or space and a person, organisation, user, CRM contact, customer, or local snapshot.
- Supports owner, manager, agent, resident, tenant, licensee, storage customer, business occupier, guarantor, emergency contact, support worker, advocate, contractor, cleaner, visitor, billing contact, and custom roles.
- Stores validity dates, primary-role state, optional external party references, contact snapshots, space scope, permissions metadata, and source lineage.
- Existing `pm_property_contacts` records are copied into party roles during migration without deleting or rewriting the legacy records.
- Newly created legacy contacts continue to mirror into the new party model.
- Deleting a legacy contact retires its party role when occupancy history exists.

### Occupancy and allocation history

- Added `pm_premise_occupancies` for applications, reservations, pending allocations, active occupancy, notice, ending, suspension, vacancy, and cancellation.
- Supports residents, tenants, licensees, storage customers, owner-occupiers, business occupiers, staff allocations, temporary guests, internal allocations, service clients, and custom occupancy types.
- Stores expected and actual dates, capacity consumed, primary occupant state, move-in/move-out state, notes, agreement references, billing references, deposit/bond references, and metadata.
- Terminal history is preserved. Vacated and cancelled records cannot be reactivated.
- New records cannot be created directly as historical records.

### Capacity and availability

- Reservations, pending allocations, and active lifecycle states consume space capacity.
- Applicants and terminal history do not consume capacity.
- Null space capacity is treated as unlimited; a fixed capacity cannot be exceeded.
- Space availability synchronises to `reserved`, `occupied`, or `available` after lifecycle changes.
- Manual holds such as maintenance, cleaning, compliance hold, owner hold, and decommissioned are not overwritten.
- Spaces with occupancy history cannot be deleted.

### Transfers

- Transfers close the source occupancy and create a linked destination occupancy.
- `previous_occupancy_id` preserves the transfer chain.
- Source and destination spaces are locked transactionally during capacity checks.
- Transfer reason, dates, external references, and move states are retained.
- The original occupancy record is never overwritten to point at the destination space.

### User interface

- Added premise party management.
- Added profile-gated occupancy boards.
- Added create, lifecycle transition, retirement, and transfer controls.
- Added capacity usage to space listings.
- Added current applications, reservations, occupancy, completed history, and transfer lineage views.
- Rooming-house, rental, storage, commercial, office, community-housing, warehouse, mixed-use, and custom profiles can enable the occupancy feature.
- Service-site profiles retain their existing workflow unless occupancy is explicitly enabled.

### Tenancy and security

- `company_id` is now the default authoritative tenant boundary across ManagedPremises.
- `user_id` records the creating actor rather than hiding records from other staff in the same company.
- `managedpremises.tenant_scope_mode = company_and_user` remains available for legacy creator-only behaviour.
- New party and occupancy routes require dedicated permissions as well as property update/view policy access.
- Default AI context contains occupancy counts only, not party names, email addresses, or phone numbers.
- AI manifests explicitly require permission for party personal data and user approval for transfers.

## Added permissions

- `managedpremises.spaces.view`
- `managedpremises.spaces.create`
- `managedpremises.spaces.update`
- `managedpremises.spaces.delete`
- `managedpremises.parties.view`
- `managedpremises.parties.create`
- `managedpremises.parties.update`
- `managedpremises.parties.delete`
- `managedpremises.occupancies.view`
- `managedpremises.occupancies.create`
- `managedpremises.occupancies.update`
- `managedpremises.occupancies.transfer`

The additive permission migration registers these permissions and grants them to company administrator roles. Existing role configurations are not removed on rollback.

## Compatibility boundary

The following remain supported and unchanged:

- Module name, alias, namespace, providers, and existing route names.
- `PropertyContact`, `PropertyUnit`, and `PropertyRoom` compatibility models.
- Existing premises, spaces, units, rooms, contacts, jobs, visits, inspections, documents, keys, access, assets, and service data.
- Existing cleaning and service-site workflows.

New vertical functionality should use `PremisePartyRole`, `PremiseOccupancy`, and `PremiseSpace`.

## Verification available in this archive

- `Tests/contract_pass3.php` tests the framework-independent occupancy state and capacity rules.
- `Tests/verify_pass3_structure.php` checks module identity, version, migrations, routes, permissions, signals, AI restrictions, tenancy configuration, and delete guards.
- PHPUnit unit tests are included for execution in the complete host application.

## Deferred to Pass 4

- Authoritative agreement metadata and renewal lifecycle.
- Agreement documents and signing references.
- Titan Money account, charge, deposit, bond, invoice, payment, and arrears integration.
- Billing schedules remain outside ManagedPremises.
