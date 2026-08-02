<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Enums;

enum InterfaceOrigin: string
{
    case AIChatPro = 'aichatpro';
    case ChatbotPwa = 'chatbot_pwa';
    case ChatbotCustomer = 'chatbot_customer';
    case AiAgent = 'ai_agent';
    case Pulse = 'pulse';
    case Api = 'api';
    case Web = 'web';
    case System = 'system';
    case Integration = 'integration';
}
