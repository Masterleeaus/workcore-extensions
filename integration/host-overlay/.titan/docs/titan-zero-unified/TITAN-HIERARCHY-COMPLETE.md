# Titan Platform Hierarchy

## The Clean Rule

**Tier 0:** Titan Zero understands and coordinates.  
**Tier 1:** Titan Uno represents the person.  
**Tier 2:** Titan Duo manages the specialist work.  
**Tier 3:** Titan Trio executes the task.

---

## Tier 0 — Titan Zero

### Purpose
- **The platform's single AI persona**
- **The conversational interface**
- The identity and context orchestrator
- The authority that selects the correct Uno managers
- The coordinator when several Uno managers are involved
- The source of all explanations, updates and approval requests

### How It Works
Users never need to know which internal assistant or agent to contact.

**They only speak to Titan Zero.**

### Example
A tenant tells Zero:

> "The bathroom tap is leaking, and it is making the floor slippery."

Zero identifies:
- The person is a tenant
- The property and room
- A tenancy issue
- A maintenance issue
- A possible safety issue

Zero then coordinates the appropriate Uno manager and Duo assistants.

### Interfaces
- **Titan Zero Workspace** — Full desktop/web interface for power users, providers, owners
- **Titan Zero Mobile** — Mobile app for field workers, residents, tenants

---

## Tier 1 — Titan Uno Relationship Managers

### Purpose
Relationship Managers represent different types of people using the platform.

They are **NOT** software departments.

A user can have **more than one Uno manager** when they hold multiple roles.

### The 13 Managers

#### Accommodation Users

**Guest Manager**
- For visitors, accommodation enquirers, pre-applicants
- Owns: Initial enquiries, property information, tours, guest access, pre-application questions

**Applicant Manager**
- For people applying for accommodation
- Owns: Applications, documents, application progress, inspection bookings, assessment communication

**Resident Manager**
- For occupants in residency/rooming-house arrangements
- Owns: House communication, shared living, complaints, maintenance requests, wellbeing, move-in/out, occupancy support

**Tenant Manager**
- For formal residential tenancy relationships
- Owns: Rental agreement communication, rent questions, repairs, notices, condition reports, bond processes, entry requests, tenancy rights

**Student Manager**
- For student accommodation residents
- Owns: Enrolment onboarding, co-signers, semester dates, arrivals/departures, academic calendar communication

**Participant Manager**
- For NDIS participants
- Owns: Participant communication, accessibility preferences, consent, goals, support requirements, service agreements, complaints, safeguarding

#### Owner & Provider

**Owner Manager**
- For property owners, landlords, investors
- Owns: Property performance, owner approvals, statements, capital expenditure, asset condition, vacancies, maintenance costs, compliance, returns reporting

**Provider Manager**
- For accommodation providers, NDIS providers, operators
- Owns: Provider operations, service obligations, registration context, service delivery, workforce oversight, provider reporting, compliance, quality

#### Workforce & Commercial

**Worker Manager**
- For employees, support workers, cleaners, technicians
- Owns: Onboarding, work assignments, availability, rosters, credentials, training, attendance, worker requests, workplace communication

**Contractor Manager**
- For external tradespeople, suppliers, subcontractors
- Owns: Contractor onboarding, work opportunities, quotes, credentials, insurance, job communication, completion evidence, invoices, performance

**Customer Manager**
- For service customers (not tenants/residents/participants)
- Owns: Enquiries, quotes, bookings, job communication, service history, invoices, feedback, repeat services

**Partner Manager**
- For referral and network partners (universities, hospitals, community organizations)
- Owns: The relationship, referrals, shared cases (with consent)

**Staff Manager**
- For internal administrative staff, supervisors
- Owns: Administrative functions, system access, data management, reporting, staff coordination

### Multiple Managers Per User

Users can have:
- **Primary Uno Manager** — The main relationship
- **Secondary Uno Managers** — Additional roles
- **Temporary case-specific Uno Managers** — For specific requests

#### Examples
- NDIS participant in supported accommodation: Primary: Participant Manager + Secondary: Resident Manager
- Student renting a room: Primary: Student Manager + Secondary: Tenant Manager
- Owner who operates accommodation: Primary: Provider Manager + Secondary: Owner Manager
- Cleaner who is also independent contractor: Primary: Contractor Manager + Secondary: Worker Manager

**Titan Zero determines which relationship is active for each request.**

---

## Tier 2 — Titan Duo Specialist Assistants

### Purpose
Specialist Assistants manage operational domains and coordinate workflows.

They are **functional area experts**, NOT relationship managers.

### Naming Convention
All Duo names end in **"Assistant"** (NOT "Manager"):
- ✅ Property Management Assistant (not Property Manager)
- ✅ Facility Management Assistant (not Facility Manager)
- ✅ Compliance Assistant (not Compliance Manager)

This prevents confusion between Uno (relationship) and Duo (operational).

### The 22 Assistants

#### Accommodation & Property (4)

**Property Management Assistant**
- Handles: Property records, occupancy status, rooms, agreements, rent workflows, entry, inspections, repairs, owner approvals, coordination

**Facility Management Assistant**
- Handles: Buildings, shared areas, plant/equipment, common services, preventive maintenance, emergency systems, contractors, building operations

**Tenancy Administration Assistant**
- Handles: Agreements, notices, bond workflows, rent reviews, condition reports, entry notices, tenancy documentation

**Occupancy Assistant**
- Handles: Rooms, beds, allocations, vacancies, transfers, move-ins, move-outs, occupancy registers

#### Service & Workforce (5)

**Service Management Assistant**
- Handles: Field-service delivery, request coordination, completion tracking, service quality

**Maintenance Assistant**
- Handles: Repair requests, maintenance triage, work-order preparation, asset tracking

**Scheduling Assistant**
- Handles: Time slot availability, conflict checking, resource allocation, calendar coordination

**Dispatch Assistant**
- Handles: Job-to-worker matching, contractor assignment, route optimization, real-time updates

**Workforce Assistant**
- Handles: Availability management, workload balancing, skills matching, staff allocation

#### Compliance & Safety (4)

**Compliance Assistant**
- Handles: Applicable obligations, deadline tracking, evidence requirements, compliance status

**Quality Assistant**
- Handles: Inspections, service quality assessment, failed work tracking, corrective actions

**Incident Assistant**
- Handles: Incident structuring, severity assessment, escalation preparation, incident tracking

**Safety Assistant**
- Handles: Hazard identification, emergency risk assessment, safety controls, incident prevention

#### Financial (5)

**Finance Assistant**
- Handles: General business finance, expense tracking, budget management, financial workflows

**Rent Assistant**
- Handles: Rent schedules, receipts, arrears tracking, rent collection

**Claims Assistant**
- Handles: NDIS claims preparation, supporting evidence, claim submission, payment tracking

**Invoice Assistant**
- Handles: Invoice review, invoice preparation, billing workflows, payment terms

**Payroll Assistant**
- Handles: Payroll information preparation, tax calculations, deductions tracking, payroll reporting

#### Business & Intelligence (4)

**CRM Assistant**
- Handles: Lead management, relationship tracking, service history, contact management

**Reporting Assistant**
- Handles: Operational reports, executive reports, analytics, data insights

**Portfolio Intelligence Assistant**
- Handles: Cross-property analysis, performance metrics, portfolio optimization, trend analysis

**Asset Lifecycle Assistant**
- Handles: Service forecasting, failure prediction, replacement planning, depreciation tracking

### How Duo Works
When a Uno manager needs specialist analysis, it calls appropriate Duo assistants.

Each Duo contributes specialized planning and analysis.

Example: "Heater has stopped working"
- Tenant Manager calls:
  - Property Management Assistant
  - Maintenance Assistant
  - Safety Assistant
  - Scheduling Assistant

---

## Tier 3 — Titan Trio Autonomous Agents

### Purpose
Autonomous Agents execute narrow, specific tasks.

They are **NOT** high-level managers or coordinators.

They perform **single actions**.

### Naming Convention
All Trio names follow: **Verb + Object + Agent**

Examples:
- ✅ Create Work Order Agent
- ✅ Send SMS Agent
- ✅ Verify Credential Agent
- ✅ Generate Agreement Agent
- ✅ Record Payment Agent
- ✅ Reconcile Transaction Agent

### Avoid
- ❌ Property Agent (too broad — belongs at Duo)
- ❌ Finance Agent (too broad — belongs at Duo)
- ❌ Compliance Agent (too broad — belongs at Duo)

### The 30 Agents

#### Work Order Agents (3)
- **Create Work Order Agent** — Creates new work order
- **Update Work Order Agent** — Updates existing work order
- **Assign Technician Agent** — Assigns to technician

#### Inspection Agents (2)
- **Schedule Inspection Agent** — Schedules inspection
- **Create Inspection Agent** — Creates inspection record

#### Access Agents (2)
- **Send Access Request Agent** — Sends access request to tenant
- **Grant Access Agent** — Grants or approves access

#### Communication Agents (5)
- **Send SMS Agent** — Sends SMS message
- **Send Email Agent** — Sends email notification
- **Send Teams Message Agent** — Sends Microsoft Teams message
- **Send WhatsApp Agent** — Sends WhatsApp message
- **Send Notification Agent** — Sends app/push notification

#### CRM Agents (3)
- **Create Contact Agent** — Creates new contact
- **Update Contact Agent** — Updates contact information
- **Search Contact Agent** — Searches contacts

#### Finance Agents (5)
- **Record Payment Agent** — Records payment transaction
- **Create Invoice Agent** — Creates invoice from work order
- **Send Invoice Agent** — Sends invoice to customer
- **Prepare Claim Agent** — Prepares NDIS claim with evidence
- **Reconcile Transaction Agent** — Matches/reconciles transactions

#### Compliance Agents (4)
- **Verify Credential Agent** — Verifies worker/contractor credentials
- **Check Certificate Expiry Agent** — Checks for expiring certifications
- **Create Incident Agent** — Creates incident and escalates if needed
- **Generate Audit Pack Agent** — Prepares evidence for audit

#### Documentation Agents (3)
- **Upload Evidence Agent** — Uploads photos/documents/proof
- **Generate Service Report Agent** — Generates service completion report
- **Generate Agreement Agent** — Generates tenancy/service agreement

#### Reporting Agents (3)
- **Generate Owner Report Agent** — Generates property owner report
- **Generate Provider Report Agent** — Generates provider compliance report
- **Categorise Maintenance Costs Agent** — Analyzes maintenance expenditure

---

## Complete Workflow Example

### Scenario: Tenant Reports Heater Failure

#### Step 1: User Speaks to Titan Zero
> "The heater has stopped working."

#### Step 2: Zero Identifies & Routes (Tier 0)
Determines:
- User is a tenant
- Property and room involved
- Potential safety issue

#### Step 3: Uno Manager Takes Ownership (Tier 1)
**Titan Uno — Tenant Manager**

Also involves if applicable:
- **Titan Uno — Participant Manager** (if tenant is also NDIS participant)

#### Step 4: Duo Assistants Analyze & Coordinate (Tier 2)
Tenant Manager calls:
- **Property Management Assistant** — Check property records, agreements, maintenance history
- **Maintenance Assistant** — Assess repair priority and requirements
- **Safety Assistant** — Check for emergency/health risks (cold weather, vulnerable occupant)
- **Scheduling Assistant** — Find available appointment times

#### Step 5: Trio Agents Execute (Tier 3)
Duo assistants invoke narrow agents:
- **Create Work Order Agent** → Creates work order in system
- **Send Access Request Agent** → Sends access request to tenant
- **Send SMS Agent** → Sends tenant confirmation message
- **Upload Evidence Agent** → Stores photos/documentation
- **Generate Service Report Agent** → Logs completion
- **Record Payment Agent** → Processes invoice if not under warranty

#### Step 6: User Stays Informed
Zero provides updates to tenant throughout. Tenant never needs to know about Uno, Duo, or Trio layers.

---

## Owner Workflow Example

### Scenario: Owner Asks "Why Did Maintenance Costs Increase?"

#### Step 1: Owner Speaks to Zero
> "Why did maintenance costs increase this quarter?"

#### Step 2: Zero Routes (Tier 0)
**Titan Uno — Owner Manager**

#### Step 3: Duo Assistants Analyze (Tier 2)
- **Property Management Assistant** — Analyzes property-level costs
- **Finance Assistant** — Reviews expenditure trends
- **Asset Lifecycle Assistant** — Checks for repeat repairs, aging assets
- **Reporting Assistant** — Compiles findings

#### Step 4: Trio Agents Execute (Tier 3)
- **Categorise Maintenance Costs Agent** → Analyzes cost categories
- **Compare Prior Period Agent** → Compares to previous quarters
- **Retrieve Repeat Repairs Agent** → Identifies recurring issues
- **Generate Owner Report Agent** → Creates detailed owner report

#### Step 5: Zero Presents Answer
Zero tells owner: "Maintenance costs increased 23% this quarter due to: [breakdown]. The primary driver was repeat repairs on the HVAC system, which is 12 years old and approaching end-of-life. Here's a replacement timeline..."

---

## Provider Workflow Example

### Scenario: Provider Asks "Are We Compliant for Audit?"

#### Step 1: Provider Speaks to Zero
> "Are all our rooming houses compliant for the next audit?"

#### Step 2: Zero Routes (Tier 0)
**Titan Uno — Provider Manager**

#### Step 3: Duo Assistants Analyze (Tier 2)
- **Compliance Assistant** — Identifies all applicable obligations
- **Facility Management Assistant** — Checks facility conditions
- **Credential Assistant** — Verifies staff certifications
- **Audit Assistant** — Prepares evidence pack
- **Quality Assistant** — Checks inspection status

#### Step 4: Trio Agents Execute (Tier 3)
- **Retrieve Compliance Obligations Agent** → Lists all requirements
- **Check Certificate Expiry Agent** → Identifies expiring credentials
- **Identify Missing Evidence Agent** → Flags gaps in documentation
- **Create Corrective Action Agent** → Schedules fixes
- **Generate Audit Pack Agent** → Prepares complete evidence

#### Step 5: Zero Presents Answer
Zero tells provider: "All 3 facilities are audit-ready with these minor action items due by [date]..."

---

## Architecture Summary

```
User
  ↓
Titan Zero (Tier 0)
  ↓
Titan Uno Manager (Tier 1)
  ├─ Tenant Manager
  ├─ Owner Manager
  ├─ Provider Manager
  └─ [10 other managers]
  ↓
Titan Duo Assistants (Tier 2)
  ├─ Property Management Assistant
  ├─ Maintenance Assistant
  ├─ Compliance Assistant
  ├─ Finance Assistant
  └─ [18 other assistants]
  ↓
Titan Trio Agents (Tier 3)
  ├─ Create Work Order Agent
  ├─ Send SMS Agent
  ├─ Verify Credential Agent
  ├─ Record Payment Agent
  └─ [26 other agents]
```

---

## Key Principles

1. **Users only speak to Titan Zero** — Never need to know about layers below
2. **One manager per relationship type** — Guest, Tenant, Owner, Provider, etc.
3. **One assistant per operational domain** — Property, Finance, Compliance, etc.
4. **One agent per action** — Verb+Object naming ensures clarity
5. **Bidirectional calling** — Tiers call downward, report upward
6. **Scalable to thousands** — Each tier can expand without changing architecture

---

**Version:** 2.1 - Complete Hierarchy  
**Date:** July 23, 2026  
**Status:** ✅ Production Ready
