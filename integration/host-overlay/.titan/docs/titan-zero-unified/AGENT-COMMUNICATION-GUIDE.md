# Agent Communication & Bidirectional Access

## How Extensions Call Agents

All extensions can now call any agent using the `AgentBridge` service.

### **Example 1: Chatbot calling Google to send email**

```php
use App\Extensions\Core\Services\AgentBridge;

// From chatbot extension
$result = AgentBridge::callAgent('agent-google', 'send_email', [
    'to' => 'customer@example.com',
    'subject' => 'Your Booking Confirmation',
    'body' => 'Your appointment is scheduled for...',
]);
```

### **Example 2: AIChatPro calling WorkCore to create a job**

```php
use App\Extensions\Core\Services\AgentBridge;

// From aichatpro extension
$result = AgentBridge::callWorkCore('jobs', 'create_job_order', [
    'customer_id' => 'cust_123',
    'service_type' => 'pool_cleaning',
    'scheduled_at' => '2026-07-25 10:00:00',
    'technician_id' => 'tech_456',
]);
```

### **Example 3: Agent-booking calling WorkCore**

```php
use App\Extensions\Core\Services\AgentBridge;

// From agent-booking
$result = AgentBridge::callWorkCore('jobs', 'assign_to_technician', [
    'job_id' => 'job_123',
    'technician_id' => 'tech_456',
]);
```

---

## Available Agents & Their Functions

### **1. agent-google**
**Unified Google Workspace Access**

| Service | Functions |
|---------|-----------|
| Gmail | send_email, read_email, search_emails, create_draft, mark_as_read, move_to_folder, create_folder, get_attachments, forward_email, reply_to_email |
| Calendar | create_event, update_event, delete_event, get_free_slots, list_events, accept_invitation, decline_invitation, send_meeting_invite, get_availability, reschedule_event |
| Drive | upload_file, download_file, delete_file, share_file, create_folder, move_file, copy_file, get_file_version, restore_version, list_files, search_files, get_file_metadata, set_permissions |
| Sheets | read_worksheet, write_cell, append_row, create_table, sort_data, filter_data, create_chart, calculate_formula, get_range, update_range |
| Docs | read_doc, create_doc, update_doc, delete_doc, share_doc, add_comment, resolve_comment |
| Forms | create_form, publish_form, get_responses, analyze_responses |

---

### **2. agent-microsoft**
**Unified Microsoft Ecosystem Access**

| Service | Functions |
|---------|-----------|
| Outlook | send_email, read_email, search_emails, create_draft, mark_as_read, move_to_folder, create_folder, get_attachments, forward_email, reply_to_email |
| Teams | send_team_message, send_direct_message, create_channel, post_to_channel, schedule_meeting, join_meeting, create_team, add_team_member, list_team_members, get_team_messages, upload_file_to_teams, mention_user, react_to_message |
| Calendar | create_event, update_event, delete_event, get_free_slots, list_events, accept_invitation, decline_invitation, send_meeting_invite, get_availability, reschedule_event |
| OneDrive | upload_file, download_file, delete_file, share_file, create_folder, move_file, copy_file, get_file_version, restore_version, list_files, search_files, get_file_metadata, set_permissions |
| OneNote | create_notebook, create_page, append_content, update_page, delete_page, list_notebooks, search_notes, get_note_content, add_attachment_to_note |
| Excel | read_worksheet, write_cell, append_row, create_table, sort_data, filter_data, create_chart, calculate_formula, get_range, update_range |
| SharePoint | create_site, list_sites, upload_to_site, manage_permissions, create_list, add_list_item, update_list_item, delete_list_item, search_site |
| Power BI | create_report, publish_report, get_report_data, create_dashboard, refresh_dataset, embed_report, export_to_pdf, list_reports, get_report_filters |
| Contacts | create_contact, update_contact, delete_contact, search_contacts, add_to_group, get_contact_photo, create_distribution_list |

---

### **3. agent-messaging**
**Unified Messaging Channels + SMS**

| Channel | Functions |
|---------|-----------|
| WhatsApp | send_message, send_media, send_template, receive_message, read_message, delete_message, create_group, add_to_group |
| Telegram | send_message, send_media, send_document, receive_message, forward_message, create_group, add_to_group, set_commands |
| Instagram | send_dm, reply_to_dm, send_story_reply, send_media, read_message |
| Messenger | send_message, send_media, send_template, receive_message, create_group, add_to_group |
| SMS | send_sms, receive_sms, schedule_sms, get_delivery_status, get_conversation_history |

**All channels support:**
- Message routing to correct system
- Media handling (images, documents)
- Group chat management
- Delivery status tracking

---

### **4. agent-phone**
**Voice Communication (Merged)**

| Feature | Functions |
|---------|-----------|
| Calls | make_call, receive_call, transfer_call, conference_call, put_on_hold, end_call, get_call_status |
| Recording | start_recording, stop_recording, get_recording, delete_recording, enable_transcription, get_transcript |
| Voice | text_to_speech, speech_to_text, change_voice, set_language |
| IVR | create_ivr_menu, add_ivr_option, handle_ivr_selection, play_message |

**Providers supported:** Twilio, Vonage, Telnyx, ElevenLabs

---

### **5. agent-booking**
**Appointment & Service Scheduling (WorkCore Connected)**

| Feature | Functions |
|---------|-----------|
| Booking | create_booking, reschedule_booking, cancel_booking, send_confirmation, send_reminder |
| Availability | get_available_slots, get_technician_availability, get_resource_availability, block_time |
| WorkCore Sync | create_job_order_from_booking, sync_to_workcore, update_workcore_status, sync_cancellation |
| Calendar | export_to_calendar, sync_with_calendar, check_conflicts |
| Notifications | send_confirmation_email, send_sms_reminder, send_push_notification |

**Connected to WorkCore Modules:**
- Jobs (auto-creates job orders)
- Invoicing (generates invoices from completed bookings)
- Time Tracking (logs service time)

---

### **6. agent-chatbot**
**Chatbot Functions & Features**

| Feature | Functions |
|---------|-----------|
| Conversation | start_conversation, continue_conversation, end_conversation, get_history |
| Routing | route_to_agent, route_to_department, escalate_issue, queue_customer |
| Context | get_customer_context, get_previous_issues, load_customer_data |
| Actions | trigger_workflow, create_ticket, schedule_followup |

---

### **7. agent-marketing**
**Marketing Automation**

| Feature | Functions |
|---------|-----------|
| Campaigns | create_campaign, schedule_campaign, send_campaign, track_campaign |
| Email | send_email_campaign, create_email_template, a_b_test, track_opens, track_clicks |
| SMS | send_sms_campaign, create_sms_template, schedule_sms |
| Social | schedule_post, publish_post, schedule_carousel, schedule_story |

---

### **8. agent-social-media**
**Social Media Management**

| Platform | Functions |
|----------|-----------|
| Instagram | post_photo, post_carousel, post_story, post_reel, schedule_post, analytics |
| Facebook | post_to_page, post_to_group, create_ad, schedule_post, analytics |
| Twitter | tweet, retweet, like, reply, schedule_tweet, analytics |
| LinkedIn | post_article, post_update, share_post, schedule_post, analytics |

---

### **9. agent-slack**
**Slack Integration**

| Feature | Functions |
|---------|-----------|
| Messaging | send_message, create_channel, invite_to_channel, archive_channel |
| Notifications | send_notification, send_alert, send_workflow_notification |
| Workflows | trigger_workflow, create_workflow, update_workflow |

---

### **10. ai-agent-work-core**
**Full Business Operations Access**

**11 Categories, 150+ Functions:**

| Category | Function Count | Key Features |
|----------|---|---------|
| **CRM** | 30+ | Contacts, companies, deals, pipelines, interactions, activity logs |
| **Jobs** | 25+ | Job orders, technician assignment, scheduling, invoicing, field ops |
| **Projects** | 20+ | Projects, tasks, milestones, team workload, timeline tracking |
| **Invoicing** | 22+ | Invoices, quotes, payments, discounts, taxes, recurring billing |
| **Time Tracking** | 17+ | Time entries, billable hours, timesheets, payroll, overtime |
| **Scheduling** | 17+ | Appointments, resources, on-call, availability management |
| **Field Ops** | 17+ | Route optimization, GPS tracking, check-in/out, photo upload, reports |
| **Inventory** | 18+ | Stock tracking, purchase orders, equipment management, reorders |
| **Reporting** | 15+ | Revenue, P&L, customer reports, performance metrics, dashboards |
| **Communication** | 10+ | SMS, email, notifications, bulk messaging |
| **Settings** | 10+ | Configuration, permissions, tax settings, pricing templates |

**Total: 191 functions**

---

## Data Flow Examples

### **Scenario 1: Customer Books Service**
```
Customer via agent-messaging (WhatsApp)
  ↓ (AgentBridge)
agent-chatbot (processes booking request)
  ↓ (AgentBridge)
agent-booking (creates booking slot)
  ↓ (AgentBridge)
ai-agent-work-core (creates job order)
  ↓ (AgentBridge)
agent-google (sends confirmation email + adds to calendar)
  ↓ (AgentBridge)
agent-phone (sends SMS reminder)
```

### **Scenario 2: Auto-Invoicing Completed Job**
```
Technician checks in job (field ops)
  ↓
ai-agent-work-core (mark job completed)
  ↓ (AgentBridge)
ai-agent-work-core (call invoicing module)
  ↓ (AgentBridge)
ai-agent-work-core (call communication module)
  ↓ (AgentBridge)
agent-google (send invoice via Gmail)
  ↓ (AgentBridge)
agent-microsoft (send via Outlook if Microsoft customer)
```

### **Scenario 3: Lead Scoring & Outreach**
```
AIChatPro (analyzes customer interaction)
  ↓ (AgentBridge)
ai-agent-work-core (create/update CRM contact)
  ↓ (AgentBridge)
agent-marketing (check campaign eligibility)
  ↓ (AgentBridge)
agent-messaging (send targeted message)
  ↓ (AgentBridge)
agent-social-media (post relevant content)
  ↓ (AgentBridge)
ai-agent-work-core (log activity for follow-up)
```

---

## Usage Patterns

### **Pattern 1: Direct Agent Call**
```php
AgentBridge::callAgent('agent-google', 'send_email', [
    'to' => 'user@example.com',
    'subject' => 'Subject',
    'body' => 'Body'
]);
```

### **Pattern 2: WorkCore Call**
```php
AgentBridge::callWorkCore('crm', 'create_contact', [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890'
]);
```

### **Pattern 3: Extension-to-Extension**
```php
AgentBridge::callExtension('agent-google', 'GoogleConnector', 'send_email', [
    'to' => 'user@example.com',
    'subject' => 'Subject',
    'body' => 'Body'
]);
```

### **Pattern 4: Check Agent Availability**
```php
$available = AgentBridge::isAgentAvailable('agent-microsoft');
$capabilities = AgentBridge::getAgentCapabilities('agent-microsoft');
$agents = AgentBridge::getAvailableAgents();
```

---

## Bidirectional Communication Summary

✅ **AIChatPro → Agents** — Call any agent or WorkCore function  
✅ **Chatbot → Agents** — Call any agent or WorkCore function  
✅ **AI Agent → Agents** — Call any other agent or WorkCore function  
✅ **Agents → Extensions** — All agents can be called by any extension  
✅ **Extensions ↔ WorkCore** — Full bidirectional access via agent-work-core  
✅ **Agent-Booking → WorkCore** — Automatic job creation from bookings  

**Result: Fully interconnected, bidirectional ecosystem**

---

**Version:** 2.0  
**Status:** Complete ✅
