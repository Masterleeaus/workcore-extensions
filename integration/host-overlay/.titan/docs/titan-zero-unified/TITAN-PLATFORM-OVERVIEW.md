# Titan Platform v3.0 — Complete Architecture

## The Hierarchy

```
Tier 0: Titan Zero
        ├─ Titan Zero Workspace (Desktop/Web)
        └─ Titan Zero Mobile (Mobile App)
             ↓
Tier 1: Titan Uno Managers (13 Relationship Managers)
             ↓
Tier 2: Titan Duo Assistants (22 Specialist Assistants)
             ↓
Tier 3: Titan Trio Agents (30 Task Execution Agents)
             ↓
       Supporting Tools & Integrations
       (11 Agent Extensions + Modules)
```

---

## Primary Extensions (4)

### **Tier 0 Interfaces**
1. **Titan Zero** — Core conversational AI
   - Understands user intent
   - Routes to appropriate Uno managers
   - Coordinates across tiers
   - Version: 3.10.0

2. **Titan Zero Mobile** — Mobile app interface
   - Field-worker version
   - Tenant/resident version
   - Streamlined mobile UX

### **Tier 3 Container**
3. **Titan Trio Agents** — Autonomous task execution
   - 30 registered agents (Verb+Object pattern)
   - Calls underlying tools and integrations
   - Provides audit trail
   - Version: 1.1

### **Setup & Configuration**
4. **Chat Setting Wizard** — Unified setup
   - Configures Titan Zero Workspace
   - Configures Titan Zero Mobile
   - Configures Titan Trio Agents
   - Sets up integrations

---

## Shared Extensions (33)

### **Tier 1 Container**
**Titan Uno Managers** (NEW)
- 13 relationship managers:
  - Guest, Applicant, Resident, Tenant, Student, Participant
  - Owner, Provider
  - Worker, Contractor, Customer, Partner, Staff

### **Tier 2 Container**
**Titan Duo Assistants** (NEW)
- 22 specialist assistants organized by domain:
  - Accommodation & Property (4)
  - Service & Workforce (5)
  - Compliance & Safety (4)
  - Financial (5)
  - Business & Intelligence (4)

### **Tier 3 Tools & Integrations**

#### Communication Agents
- **agent-messaging** — WhatsApp, Telegram, Instagram, Messenger, SMS
- **agent-phone** — Voice calls, recording, transcription
- **agent-slack** — Slack integration
- **agent-whatsapp** — Standalone WhatsApp

#### Workspace & Productivity
- **agent-google** — Gmail, Calendar, Drive, Sheets, Docs, Forms
- **agent-microsoft** — Outlook, Teams, OneDrive, Excel, SharePoint, Power BI

#### Service & Workflow
- **agent-booking** — Appointments, scheduling (WorkCore-synced)
- **agent-chatbot** — Call chatbot functions
- **agent-marketing** — Email, SMS, social campaigns
- **agent-social-media** — Instagram, Facebook, Twitter, LinkedIn

#### Business Operations
- **ai-agent-work-core** — 191 functions across:
  - CRM (30+ functions)
  - Jobs/Service Orders (25+ functions)
  - Projects (20+ functions)
  - Invoicing (22+ functions)
  - Time Tracking (17+ functions)
  - Scheduling (17+ functions)
  - Field Operations (17+ functions)
  - Inventory (18+ functions)
  - Reporting (15+ functions)
  - Communication (10+ functions)
  - Settings (10+ functions)

#### AIChatPro Modules (15)
- Memory management
- Canvas (sketching/notes)
- Folders (organization)
- Skills (custom capabilities)
- File Chat (document interaction)
- Deep Research (knowledge base builder from web/local/gov)
- Notion integration
- Smart Image (image understanding)
- Highlighting & contextual queries
- Web chat
- Temp sessions
- Sharing
- Settings
- Voice isolation

#### Chatbot Modules (5)
- Core chatbot layer
- Agent functions
- Customer tagging
- E-commerce
- Review management

---

## Extension Count & Organization

| Category | Count |
|----------|-------|
| **Primary Extensions** | 4 |
| Tier 0 Interfaces | 2 |
| Tier 3 Container | 1 |
| Setup/Config | 1 |
| **Shared Extensions** | 33 |
| Tier 1 Managers | 1 |
| Tier 2 Assistants | 1 |
| Tier 3 Tools & Agents | 11 |
| AIChatPro Modules | 15 |
| Chatbot Modules | 5 |
| **TOTAL** | **37** |

---

## Tier 3 Agents (30 Total)

### By Category
- Work Order (3): Create, Update, Assign
- Inspection (2): Schedule, Create
- Access (2): Send Request, Grant
- Communication (5): SMS, Email, Teams, WhatsApp, Notification
- CRM (3): Create Contact, Update, Search
- Finance (5): Record Payment, Create Invoice, Send Invoice, Prepare Claim, Reconcile
- Compliance (4): Verify Credential, Check Expiry, Create Incident, Generate Audit Pack
- Documentation (3): Upload Evidence, Generate Report, Generate Agreement
- Reporting (3): Owner Report, Provider Report, Categorise Costs

---

## Complete Data Flow

### Simple Booking → Invoice Flow
```
User (via Titan Zero)
  ↓
Tenant Manager (Tier 1)
  ↓
Scheduling Assistant + Service Management Assistant (Tier 2)
  ↓
Create Work Order Agent
Send Access Request Agent
Send SMS Agent (Tier 3)
  ↓
agent-booking + ai-agent-work-core + agent-messaging (Tools)
  ↓
Technician scheduled, tenant notified
  ↓
Upon completion:
  ↓
Facility Management Assistant (Tier 2)
  ↓
Invoice Assistant (Tier 2)
  ↓
Create Invoice Agent
Send Invoice Agent
Record Payment Agent (Tier 3)
  ↓
ai-agent-work-core + agent-google (Tools)
  ↓
Invoice sent, payment recorded
```

### Complex Compliance Audit Flow
```
User: Owner/Provider (via Titan Zero)
  ↓
Owner Manager or Provider Manager (Tier 1)
  ↓
Compliance Assistant
Quality Assistant
Credential Assistant
Facility Management Assistant (Tier 2)
  ↓
Verify Credential Agent
Check Certificate Expiry Agent
Create Incident Agent
Generate Audit Pack Agent (Tier 3)
  ↓
ai-agent-work-core + agent-google (Tools)
  ↓
Audit package prepared, actions identified
```

---

## API Access Patterns

### From Tier 0 (Titan Zero)
```php
// Route to appropriate Uno manager
$managers = TitanUnoManagers::getManagersForUser($userType);
```

### From Tier 1 (Uno Managers)
```php
// Call appropriate Duo assistants
$assistants = TitanDuoAssistants::getAssistantsForRequest($requestType);
```

### From Tier 2 (Duo Assistants)
```php
// Invoke specific Trio agents
$agents = TitanTrioAgentsRegistry::getAgentsForWorkflow($workflowType);
foreach ($agents as $agent) {
    TitanTrioAgentsRegistry::executeAgent($agent['id']);
}
```

### From Tier 3 (Trio Agents)
```php
// Call underlying tool/integration
AgentBridge::callAgent('agent-google', 'send_email', [...]); 
AgentBridge::callWorkCore('jobs', 'create_job_order', [...]);
```

---

## Key Features

✅ **Clean separation of concerns** — Each tier has single responsibility  
✅ **User-facing simplicity** — Users only speak to Titan Zero  
✅ **Internal scalability** — Each tier expandable independently  
✅ **Bidirectional communication** — Tiers call down, report up  
✅ **Context awareness** — Zero maintains full conversation context  
✅ **Audit trail** — Every agent call logged and traceable  
✅ **No duplicate code** — Shared services across all extensions  
✅ **191+ business functions** — Full operation management via WorkCore  

---

## Installation

```bash
# Extract
unzip TitanZero-Unified.zip

# Install primary + shared
cp -r TitanZero-Unified/primary/* your-magicai/app/Extensions/
cp -r TitanZero-Unified/shared/* your-magicai/app/Extensions/

# Install dependencies
composer require \
  twilio/sdk \
  vonage/vonage-php-sdk \
  telnyx/telnyx-php \
  elevenlabs/elevenlabs \
  google/apiclient \
  microsoft/graph

# Migrate & Configure
php artisan migrate
php artisan config:cache
php artisan route:cache

# Visit setup
# http://your-app/dashboard/wizard/chatbot-setup
```

---

## Configuration

### Tier 0 Settings (Titan Zero)
- Voice input preferences
- Memory retention policy
- Context window size
- Escalation thresholds

### Tier 1 Settings (Uno Managers)
- User type mappings
- Permission levels per manager
- Notification preferences
- Auto-routing rules

### Tier 2 Settings (Duo Assistants)
- Domain-specific configurations
- Service levels
- SLA definitions
- Approval workflows

### Tier 3 Settings (Trio Agents)
- Tool/agent configurations
- Credentials and API keys
- Retry logic
- Timeout thresholds

---

## Documentation Files

1. **TITAN-HIERARCHY-COMPLETE.md** — Full architecture explained
2. **README.md** — Quick start and overview
3. **AGENT-COMMUNICATION-GUIDE.md** — API reference for agents
4. **MERGED-EXTENSIONS-GUIDE.md** — What was merged and why
5. **CHANGES-SUMMARY-v2.1.md** — Previous changes
6. This file — Final structure

---

## Version Info

**Product:** Titan Platform v3.0  
**Architecture:** Clean hierarchy with 4 tiers  
**Extensions:** 37 total (4 primary + 33 shared)  
**Managers:** 13 Relationship Managers  
**Assistants:** 22 Specialist Assistants  
**Agents:** 30 Task Execution Agents  
**Business Functions:** 191+ via WorkCore  
**Status:** ✅ Production Ready  

**Date:** July 23, 2026  
**Build:** Complete Unified Suite  

---

## Next Steps

1. Deploy to MagicAI platform
2. Configure API keys (.env)
3. Run migrations
4. Configure user mappings (who is which Uno manager)
5. Test workflows through Titan Zero interface
6. Train staff on the platform
7. Monitor and optimize

---

**The Titan Platform: Where users speak to Titan Zero. Zero orchestrates Uno. Uno coordinates Duo. Duo directs Trio. Trio executes. Everyone gets their job done.**
