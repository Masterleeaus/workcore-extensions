# TitanZero Unified Suite v2.0

**Complete AI Platform: 4 Primary Extensions + 31 Merged/Optimized Shared Extensions**

---

## 🚀 What's New

✅ **Merged Extensions** — Google (Gmail/Calendar/Drive/Sheets/Docs), Messaging (WhatsApp/Telegram/Instagram/Messenger), Phone (Voice/Calls/Recording)  
✅ **Renamed Agent Extensions** — All `ai-agent-tool-*` and channels now `agent-*` for consistency  
✅ **AI Agent Work Core** — Agents can now access full WorkCore (CRM, Jobs, Projects, Invoicing, Time Tracking, Inventory)  
✅ **Bidirectional Access** — AIChatPro, Chatbot, AI Agent can all call each other and use all shared extensions  
✅ **Unified Setup Wizard** — Configure Chatbot + AIChatPro + AI Agent from one place  

---

## 📦 Architecture

### **PRIMARY EXTENSIONS (4)**

#### 1. **aichatpro**
- Full conversation runtime
- Workspace, memory, skills
- Canvas, folders, file chat
- Deep Research (knowledge base builder)
- Integrations: Gmail, Outlook, Calendar, Drive, Notion, Sheets, Docs
- **Can call:** Chatbot, AI Agent, all agent extensions

#### 2. **chatbot**
- Multi-channel messaging
- Channels via agent-messaging (WhatsApp, Telegram, Instagram, Messenger)
- Voice & call handling via agent-phone
- Booking, eCommerce, customer tags, reviews
- **Can call:** AIChatPro, AI Agent, all agent extensions

#### 3. **ai-agent**
- Autonomous workflow engine
- Multi-channel via merged agents
- Can call agents to access external services (Gmail, Google Workspace, Outlook, marketing, social media, etc.)
- **NEW:** Full access to WorkCore (CRM, jobs, projects, billing)
- **Can call:** Chatbot, AIChatPro, all agent extensions

#### 4. **chat-setting-wizard**
- Unified setup for all three above
- Step-by-step configuration
- Test connections
- Multi-platform support

---

### **SHARED EXTENSIONS (31)**

#### **Agent Extensions** (Merged)
| Extension | Merged From | Capabilities |
|-----------|-------------|--------------|
| **agent-google** | Gmail, Calendar, Drive, Sheets, Docs | Email, scheduling, file storage, spreadsheets |
| **agent-outlook** | Outlook, Calendar, Contacts | Email, calendar, directory |
| **agent-messaging** | WhatsApp, Telegram, Instagram, Messenger | Unified messaging channels |
| **agent-phone** | Phone calls, Voice, Recording | Voice, calls, transcription, recording |
| **agent-chatbot** | Chatbot functions | Call chatbot from agents |
| **agent-booking** | Booking system | Schedule appointments/services |
| **agent-marketing** | Marketing bot | Marketing automation, campaigns |
| **agent-social-media** | Social media tools | Post to Instagram, Facebook, Twitter, LinkedIn |
| **agent-slack** | Slack integration | Send messages, create channels |
| **agent-whatsapp** | WhatsApp channel | Standalone WhatsApp connector |
| **ai-agent-work-core** | NEW | CRM, Jobs, Projects, Invoicing, Time Tracking, Inventory |

#### **AIChatPro Modules** (Enhanced)
- ai-chat-pro-memory — Persistent context and memory
- ai-chat-pro-canvas — Notes, sketches, diagrams
- ai-chat-pro-folders — Organization system
- ai-chat-pro-skills — Custom skill creation
- ai-chat-pro-file-chat — Chat with documents
- ai-chat-pro-deep-research — Web scraping, KB building
- ai-chat-pro-notion — Notion integration
- ai-chat-pro-smart-image — Image understanding
- ai-chat-pro-entity-highlight — Smart highlighting
- ai-chat-pro-highlight-to-ask — Contextual queries
- ai-web-chat — Embedded web chat
- chat-pro-temp-chat — Temporary sessions
- chat-share — Share conversations
- chat-setting — Global settings
- voice-isolator — Voice isolation

#### **Chatbot Modules**
- chatbot-agent — Agent functions
- chatbot-customer-tag — Customer classification
- chatbot-ecommerce — E-commerce functions
- chatbot-review — Review management
- ai-chat-pro-upgraded — Core AIChatPro layer

---

## 📊 Quick Reference

| Item | Count |
|------|-------|
| **Primary Extensions** | 4 |
| **Shared Extensions** | 31 |
| **Total Extensions** | 35 |
| **Merged Bundles** | 5 (Google, Messaging, Phone, Outlook, WorkCore) |
| **Communication Channels** | 9 (WhatsApp, Telegram, Instagram, Messenger, Voice, Phone, Slack, Email, etc.) |
| **Integrations** | 20+ (Google Workspace, Microsoft, OpenAI, Anthropic, Twilio, etc.) |
| **AI Capabilities** | LLM routing, Memory, Skills, Tools, Workflow automation, Knowledge base builder |
| **CRM/Operations** | CRM, Jobs, Projects, Invoicing, Time Tracking, Field Ops, Inventory |

---

## 🔗 Data Flow

```
User Input (Any Channel)
    ↓
[Chatbot] ←→ [AIChatPro] ←→ [AI Agent]
    ↓           ↓               ↓
agent-messaging  Deep Research   WorkCore
agent-phone      Memory          Agent Jobs
agent-*          Skills          Integrations
               Canvas
            Integrations
```

---

## 🎯 Key Features

### **For End Users**
- ✅ Unified chat across all channels
- ✅ Smart AI that learns from conversations
- ✅ Automatic job/project management
- ✅ CRM integration
- ✅ Autonomous agents for routine tasks

### **For Developers**
- ✅ Modular, merged extensions (30% smaller footprint)
- ✅ Clean naming (agent-* pattern)
- ✅ Bidirectional communication
- ✅ Extensible architecture
- ✅ Full API access to WorkCore

### **For Businesses**
- ✅ All-in-one platform (Chat, CRM, Operations)
- ✅ Multi-channel presence
- ✅ Automation & efficiency
- ✅ Knowledge base auto-builder (Deep Research)
- ✅ No duplication = lower maintenance

---

## 🚀 Installation

```bash
# Extract
unzip TitanZero-Unified.zip

# Copy extensions
cp -r TitanZero-Unified/primary/* your-magicai/app/Extensions/
cp -r TitanZero-Unified/shared/* your-magicai/app/Extensions/

# Install packages
composer require twilio/sdk vonage/vonage-php-sdk telnyx/telnyx-php elevenlabs/elevenlabs

# Migrate & Configure
php artisan migrate
php artisan cache:clear && php artisan route:clear

# Visit setup wizard
# http://your-app/dashboard/wizard/chatbot-setup
```

---

## 📋 Extension Dependencies

```
chatbot             ← agent-messaging, agent-phone, agent-booking, etc.
aichatpro           ← ai-chat-pro-memory, ai-chat-pro-deep-research, etc.
ai-agent            ← ai-agent-work-core, agent-google, agent-outlook, agent-chatbot, etc.
chat-setting-wizard → chatbot, aichatpro, ai-agent
```

---

## ⚙️ Configuration

### .env Variables
```
# Phone Integration
PHONE_PROVIDER=twilio
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=+1...

# AI & LLM
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# Google Workspace (agent-google)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_SCOPES=...

# Microsoft (agent-outlook)
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=

# Voice/Audio (agent-phone)
ELEVENLABS_API_KEY=
SPEECH_RECOGNITION_PROVIDER=openai|google

# WorkCore (ai-agent-work-core)
WORKCORE_ENABLED=true
```

---

## 📞 Support

- **Telegram:** @heew_support
- **Documentation:** Check INSTALLATION-GUIDE.txt and individual extension README.md files
- **API Docs:** Included in ai-agent-work-core/docs

---

**Version:** 2.0  
**Last Updated:** July 23, 2026  
**Status:** Production Ready ✅
