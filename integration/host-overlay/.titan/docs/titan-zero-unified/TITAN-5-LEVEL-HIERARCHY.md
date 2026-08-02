# Titan Platform 5-Level Hierarchy

## The Complete Architecture

```
Tier 0: Titan Zero
        ├─ Understands the user
        ├─ Coordinates the system
        └─ Presents unified response
             ↓
Tier 1: Titan Uno (14 Relationship Managers)
        ├─ Tenant Manager
        ├─ Owner Manager
        ├─ Provider Manager
        └─ [11 other managers representing different user relationships]
             ↓
Tier 2: Titan Duo (61 Specialist Assistants)
        ├─ Property Management Assistant
        ├─ Maintenance Assistant
        ├─ Compliance Assistant
        └─ [58 other assistants managing specialist domains]
             ↓
Tier 3: Titan Trio (330+ Autonomous Agents)
        ├─ Create Work Order Agent
        ├─ Send SMS Agent
        ├─ Verify Credential Agent
        └─ [300+ other agents executing narrow tasks]
             ↓
Tier 4: Titan Quattro (165+ Tools & Connections)
        ├─ SMS Tool
        ├─ Email Tool
        ├─ Work Order Tool
        ├─ Property Register Tool
        └─ [160+ other reusable technical capabilities]
```

---

## The Five-Level Invariant

| Tier | Name | Function | Count |
|------|------|----------|-------|
| 0 | **Titan Zero** | **Understands and coordinates** | 1 |
| 1 | **Titan Uno** | **Represents the person** | 14 |
| 2 | **Titan Duo** | **Manages specialist work** | 61 |
| 3 | **Titan Trio** | **Executes the task** | 330+ |
| 4 | **Titan Quattro** | **Provides the capability** | 165+ |

---

## Level 0: Titan Zero

### Purpose
- Understands user intent
- Selects appropriate Uno manager(s)
- Coordinates all lower tiers
- Returns one unified response

### User Interface
- **Titan Zero Workspace** — Desktop/web for complex interactions
- **Titan Zero Mobile** — Mobile app for field work and residents

### Key Principle
> **Users never need to know about Uno, Duo, Trio, or Quattro. They only speak to Zero.**

---

## Level 1: Titan Uno — Relationship Managers (14 total)

### Purpose
Represent the person's relationship with the platform. Users may have multiple Uno managers active simultaneously.

### The 14 Managers

**Accommodation Users (7)**
- Guest Manager — Visitors and prospective guests
- Applicant Manager — People applying for accommodation
- Resident Manager — People in rooming houses/shared accommodation
- Tenant Manager — People with formal tenancy agreements
- Student Manager — Domestic and international students
- Participant Manager — NDIS participants
- Family and Advocate Manager — Authorized family members and advocates

**Owners and Providers (2)**
- Property Owner Manager — Landlords, investors, property owners
- Accommodation Provider Manager — Organizations operating accommodation services

**Customers and Workforce (5)**
- Customer Manager — Customers purchasing services
- Worker Manager — Employees, technicians, support workers
- Contractor Manager — Independent contractors and subcontractors
- Partner Manager — Universities, hospitals, support coordinators
- Staff Manager — Office staff, administrators, supervisors

### How Uno Works
```
User: "The heater has stopped working"
  ↓
Titan Zero identifies the user is a Tenant
  ↓
Tenant Manager is selected (or Owner Manager if applicable)
  ↓
Tenant Manager calls appropriate Duo assistants
```

---

## Level 2: Titan Duo — Specialist Assistants (61 total)

### Purpose
Manage specialized operational domains. Uno managers call relevant Duo assistants to analyze and coordinate.

### Organized by Domain

**Accommodation & Occupancy (20)**
Property Management, Facility Management, Tenancy Administration, Occupancy, Guest Services, Application, Accommodation Matching, Agreement, Move-In, Move-Out, Inspection, Access & Keys, Resident Communications, Household Relations, Student Accommodation, NDIS Accommodation, Accessibility, Visitor Management, Vacancy Management, and others

**NDIS & Support (12)**
Participant Onboarding, Plan Mapping, Service Agreement, Support Coordination, Supported Decision-Making, Participant Goals, Support Roster, Support Handover, Assistive Technology, Behaviour Support, Participant Wellbeing, Participant Complaints

**Field Service & Workforce (23)**
Service Management, Maintenance, Emergency Response, Scheduling, Dispatch, Roster, Workforce, Contractor Assurance, Credential, Training, Field Technician, Cleaning Services, Grounds & Landscaping, Trade Services, Property Turn, Inventory, Procurement, Fleet, Route Planning, Job Costing, Scope of Work, Quote, Field Quality

**Compliance, Safety & Quality (14)**
Compliance, Quality, Incident, Safeguarding, Safety, Fire Safety, Infection Control, Lone Worker Safety, Fatigue Management, Risk, Audit, Corrective Action, Complaint Management, and others

**Finance & Commercial (16)**
Finance, Trust Accounting, Rent, Arrears & Hardship, NDIS Claims, Invoice, Accounts Payable, Accounts Receivable, Reconciliation, Budget, Payroll, Expense, Payment, Financial Anomaly, Owner Statement, Provider Statement, Pricing

**Customer & Relationship (7)**
CRM, Sales, Marketing, Referral Network, Customer Service, Customer Onboarding, Customer Retention, Feedback

**Assets, Property & Sustainability (10)**
Asset Register, Asset Lifecycle, Preventive Maintenance, Capital Works, Accessibility Modification, Condition Monitoring, Warranty, Insurance, Sustainability, Energy Management, and others

**Reporting & System (8)**
Reporting, Executive Briefing, Data Quality, Document Intelligence, Knowledge, Integration, Identity & Access, AI Model Routing, and others

### How Duo Works
```
Tenant Manager needs to handle "heater failure"
  ↓
Calls Duo assistants:
- Property Management Assistant (property details, history)
- Maintenance Assistant (repair assessment)
- Safety Assistant (emergency risk)
- Scheduling Assistant (appointment availability)
  ↓
Each Duo analyzes their domain
```

---

## Level 3: Titan Trio — Autonomous Agents (330+ total)

### Purpose
Execute narrow, specific, auditable tasks. Named **Verb + Object + Agent**.

### Organized by Category

**By Workflow Type:**
- Work Order Agents (3) — Create, Update, Assign
- Inspection Agents — Schedule, Create
- Communication Agents (5+) — Send SMS, Email, Teams, WhatsApp, etc.
- Finance Agents (5+) — Record Payment, Create Invoice, Prepare Claim, etc.
- Compliance Agents (4+) — Verify Credential, Check Expiry, Create Incident, etc.
- And 300+ more across 20+ categories...

### Naming Pattern

**✅ Correct:**
- Create Work Order Agent
- Send SMS Agent
- Verify Credential Agent
- Record Payment Agent
- Schedule Inspection Agent

**❌ Incorrect:**
- Maintenance Agent (too broad)
- Finance Agent (too broad)
- Compliance Agent (too broad)

### How Trio Works
```
Duo assistants invoke Trio agents
  ↓
Maintenance Assistant calls:
- Create Work Order Agent
- Send Access Request Agent
- Send SMS Agent
  ↓
Agents execute narrow tasks via Quattro tools
```

---

## Level 4: Titan Quattro — Tools & Connections (165+ total)

### Purpose
Provide actual functions, APIs, channels, data access and integrations. **Tools perform functions but don't decide why.**

### Organized by Category (16 categories)

**Messaging (12 tools)**
- SMS Tool
- Email Tool
- WhatsApp Tool
- Telegram Tool
- Push Notification Tool
- Voice Call Tool
- Video Communication Tool
- And 5 more...

**Channels (11 tools)**
- Web Chat Tool
- Customer Portal Tool
- Resident Portal Tool
- Participant Portal Tool
- Worker App Tool
- Contractor Portal Tool
- And 5 more...

**Google Workspace (10 tools)**
- Gmail Tool
- Google Calendar Tool
- Google Drive Tool
- Google Docs Tool
- Google Sheets Tool
- And 5 more...

**Microsoft Workspace (8 tools)**
- Outlook Mail Tool
- Microsoft Calendar Tool
- OneDrive Tool
- SharePoint Tool
- Excel Tool
- Teams Tool
- And 2 more...

**Property & Accommodation (10 tools)**
- Property Register Tool
- Occupancy Register Tool
- Agreement Tool
- Bond Tool
- Inspection Tool
- Vacancy Publishing Tool
- And 4 more...

**Field Service (11 tools)**
- Work Order Tool
- Job Scheduling Tool
- Dispatch Tool
- Route Optimization Tool
- GPS Location Tool
- Time Tracking Tool
- And 5 more...

**NDIS & Support (10 tools)**
- Participant Record Tool
- Plan Information Tool
- Support Delivery Tool
- Support Roster Tool
- Goal Tracking Tool
- Consent Register Tool
- And 4 more...

**Finance & Payment (13 tools)**
- Invoice Tool
- Payment Tool
- Bank Feed Tool
- Reconciliation Tool
- Trust Ledger Tool
- Rent Ledger Tool
- And 7 more...

**CRM & Commercial (10 tools)**
- CRM Tool
- Lead Capture Tool
- Pipeline Tool
- Proposal Tool
- Campaign Tool
- Survey Tool
- And 4 more...

**Compliance & Quality (12 tools)**
- Compliance Register Tool
- Credential Register Tool
- Incident Register Tool
- Complaint Register Tool
- Corrective Action Tool
- Audit Tool
- And 6 more...

**Document & Media (15 tools)**
- Document Generation Tool
- PDF Tool
- OCR Tool
- Image Analysis Tool
- Audio Transcription Tool
- Speech Generation Tool
- And 9 more...

**Data & Intelligence (16 tools)**
- Database Query Tool
- Search Tool
- Analytics Tool
- Forecasting Tool
- Data Import/Export Tools
- And 11 more...

**AI & Models (12 tools)**
- AI Model Gateway Tool
- Vision Model Tool
- Voice Model Tool
- Embedding Tool
- Content Safety Tool
- And 7 more...

**Workflow & Automation (13 tools)**
- Workflow Engine Tool
- Task Tool
- Approval Tool
- Rules Engine Tool
- Event Bus Tool
- Scheduler Tool
- And 7 more...

**Security & Governance (13 tools)**
- Authentication Tool
- Role and Permission Tool
- Encryption Tool
- Audit Log Tool
- Rate Limit Tool
- Agent Isolation Tool
- And 7 more...

**Device & Infrastructure (11 tools)**
- Offline Queue Tool
- Sync Tool
- Device Identity Tool
- Telemetry Tool
- Health Check Tool
- Module Registry Tool
- And 5 more...

### Key Principle
> **A Quattro tool performs a technical function but does not decide why that function is being used.**

---

## Custom Agent Factory

### Purpose
When Titan Zero detects a capability gap (no suitable existing Trio agent), the factory creates new agents in a controlled way.

### NOT Automatic
- Zero does NOT directly create and activate unrestricted agents
- Factory requires approval for high-risk agents
- All custom agents are tested before production

### The Factory Process

1. **Detection** — Identify missing capability
2. **Search** — Check if existing agents can compose to solve it
3. **Design** — Draft new agent specification
4. **Specify** — Define schemas, permissions, approval gates
5. **Test** — Run sandbox, isolation, and permission tests
6. **Approve** — Request human approval if needed
7. **Register** — Add to capability registry
8. **Monitor** — Track for failures and disable if unsafe

### Automatic Activation
Custom agents CAN be activated automatically ONLY when ALL are true:
- ✅ Task is low risk
- ✅ Action is reversible
- ✅ Tools are already approved
- ✅ No sensitive/regulated decision
- ✅ No new data permission required
- ✅ No money transferred
- ✅ No legal notice issued
- ✅ No participant safety decision
- ✅ All automated tests pass
- ✅ Company autonomy policy permits it

### Anything else requires approval.

### Custom Agent Naming
**Format:** "Titan Trio — Custom [Task] Agent"

**Examples:**
- Custom Pool Inspection Agent
- Custom University Arrival Agent
- Custom Pressure Washing Quote Agent
- Custom SDA Vacancy Publishing Agent

---

## Complete Data Flow Example

### User: "The heater has stopped working"

```
1. TIER 0: TITAN ZERO
   Input: "The heater has stopped working"
   Analysis: Tenant, property, maintenance issue, potential safety risk
   Action: Select Tenant Manager
   
2. TIER 1: TENANT MANAGER (Uno)
   Request: Handle heater failure
   Analysis: Requires property, maintenance, safety coordination
   Action: Call appropriate Duo assistants
   
3. TIER 2: DUO ASSISTANTS
   - Property Management: Property records, history
   - Maintenance: Repair assessment, urgency
   - Safety: Emergency risk evaluation
   - Scheduling: Find technician availability
   
   Decision: Work order required, safety hold possible, urgent scheduling
   Action: Call specific Trio agents
   
4. TIER 3: TRIO AGENTS
   - Create Work Order Agent → Creates work order record
   - Send Access Request Agent → Requests access from tenant
   - Send SMS Agent → Notifies tenant of urgent repair
   - Send Access Credential Agent → Issues technician access
   - Assign Technician Agent → Assigns to available technician
   - Send Technician Instructions Agent → Sends job details
   
5. TIER 4: QUATTRO TOOLS
   - Work Order Tool: Creates/updates work order
   - Property Register Tool: Reads property details
   - SMS Tool: Sends SMS messages
   - Access Control Tool: Creates/revokes credentials
   - Dispatch Tool: Finds available technicians
   - Communication Delivery Tool: Tracks message delivery

6. SYSTEM ACTIONS
   - Work order created
   - Tenant notified via SMS
   - Access granted to technician
   - Technician assigned and notified
   - System monitoring for completion

7. TIER 0: RESPONSE TO USER
   Zero tells tenant:
   "Your heater repair has been prioritized due to the cold weather. 
    A technician will arrive between 2-3 PM today. 
    You'll receive a message when they're 15 minutes away.
    If you have concerns about the safety risk, reply immediately."
```

---

## Why Five Levels?

### ✅ **Each tier has ONE responsibility**
- Zero: Understand & coordinate
- Uno: Represent the person
- Duo: Manage the domain
- Trio: Execute the task
- Quattro: Provide the capability

### ✅ **Clean separation of concerns**
- Reasoning stays at top (Zero, Uno, Duo)
- Execution stays at bottom (Trio)
- Technical function stays separate (Quattro)

### ✅ **Scalable without rewrite**
- Can add agents/tools without changing architecture
- Can deploy Phases 1-5 independently
- Platform can grow to 500+ components

### ✅ **No tool creep into reasoning**
- API keys (Quattro) never leak into Duo logic
- Duo never decides which SMS provider to use
- Trio never reasons about business logic

### ✅ **Enables custom agents safely**
- New agents don't require new philosophy
- Factory creates within same rules
- Auto-activation only for truly safe tasks

---

## Version Summary

**Product:** Titan Platform v3.0  
**Architecture:** 5-Level Hierarchy  
**Tiers:** Zero (1) + Uno (14) + Duo (61) + Trio (330+) + Quattro (165+)  
**Total Components:** 571+  
**Status:** Phase 1 (foundation + tier 4 tools + factory)  
**Next:** Phase 2-5 (complete all assistants and agents)  

**Final Invariant:**
> **Titan Zero understands and coordinates. Titan Uno represents the person. Titan Duo manages the specialist work. Titan Trio executes the task. Titan Quattro supplies the tools.**
