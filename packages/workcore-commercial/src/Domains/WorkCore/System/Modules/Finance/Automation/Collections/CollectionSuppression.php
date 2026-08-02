<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

enum CollectionSuppression: string { case DisputeOpen='dispute_open'; case PaymentPlanActive='payment_plan_active'; case PaymentClaimPending='payment_claim_pending'; case Paused='paused'; case Paid='paid'; case Voided='voided'; case Credited='credited'; }
