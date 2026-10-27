# Agentoom Instant Messaging (`agentoom/core-instant-messaging`)

Multi-channel instant messaging bridge connecting **Agentoom** AI agents to Telegram, WhatsApp, and external chat networks.

---

> [!IMPORTANT]
> **Post-Splitting Versioning & Pre-Release Notice (`v0.8.0`)**
> Version `0.8.0` represents the initial standalone release following the modular package extraction and architectural splitting from the Agentoom core monorepo. This package is currently in a pre-release state undergoing integration testing and requires appropriate validation and test coverage across target environments before being designated as production-stable (`1.0.0`).

## 🌟 Overview

The `agentoom/core-instant-messaging` package extends Agentoom into mobile and messaging channels. It routes incoming messages from instant messaging platforms into Agentoom agent conversations, handles streaming responses back to users, verifies webhook signatures, and manages bot credentials.

- **Package Name**: `agentoom/core-instant-messaging`
- **Version**: `0.8.0` (Pre-release / Post-split testing)
- **Namespace**: `Agentoom\InstantMessaging\`
- **Service Provider**: `Agentoom\InstantMessaging\InstantMessagingServiceProvider`

---

## 🚀 Key Features

### 1. Extensible Multi-Driver Architecture
- **Driver Contract**: Standardized interface (`InstantMessagingDriver`) for integrating messaging networks.
- **Messenger Manager**: Factory service (`Messenger`) to resolve drivers dynamically based on channel configuration.

### 2. Supported Messaging Channels
- **Telegram (`TelegramDriver`)**:
  - Full support for Telegram Bot API.
  - Automated webhook setup and secure validation via cryptographic secret token (`webhook_secret`).
  - Text, command handling, inline keyboard options, and user identification.
- **WhatsApp (`WhatsAppDriver`)**:
  - Integration with Meta WhatsApp Business Cloud API.
  - Webhook handshake verification, message delivery receipts, and template message handling.

### 3. Stateful Conversation Threading
- Seamlessly maps external platform chat IDs (e.g. Telegram chat ID, WhatsApp phone number) to persistent Agentoom `AgentConversation` instances.
- Maintains conversation context, message history, and memory across interactions.

### 4. Administrative Control Panel
- **Livewire Admin Screens**: Easily register new Telegram bots or WhatsApp credentials, assign designated Agentoom agents to specific numbers/bots, test connectivity, and toggle active status.
- **Data Transfer & Migration**: Includes `InstantMessagingSection` for exporting and importing bot configurations across environments.
- **Multi-Language Support**: Fully localized in English (`en`), German (`de`), Spanish (`es`), French (`fr`), and Italian (`it`).

---

## 📁 Package Structure

```
core-instant-messaging/
├── config/
│   └── instant-messaging.php       # Drivers, API endpoints, webhook routes
├── database/migrations/            # Table schemas (telegram_bots, etc.)
├── resources/
│   ├── lang/                       # Translations (de, en, es, fr, it)
│   └── views/livewire/admin/       # Livewire admin management components
├── routes/
│   ├── api.php                     # Inbound webhook endpoints
│   └── web.php                     # Admin configuration routes
└── src/
    ├── Contracts/
    │   └── InstantMessagingDriver.php # Driver interface
    ├── Drivers/
    │   ├── TelegramDriver.php      # Telegram API implementation
    │   └── WhatsAppDriver.php      # WhatsApp Cloud API implementation
    ├── Http/Controllers/           # Webhook controllers
    ├── Livewire/Admin/             # Livewire bot management interfaces
    ├── Models/
    │   └── TelegramBot.php         # TelegramBot Eloquent model
    ├── Messenger.php               # Driver manager
    └── InstantMessagingServiceProvider.php
```

---

## 🛠️ Configuration & Webhook Endpoints

Publish the configuration:

```bash
php artisan vendor:publish --provider="Agentoom\InstantMessaging\InstantMessagingServiceProvider"
```

Inbound Webhook Routes:
- **Telegram**: `POST /api/instant-messaging/telegram/webhook/{bot}`
- **WhatsApp**: `POST /api/instant-messaging/whatsapp/webhook`
