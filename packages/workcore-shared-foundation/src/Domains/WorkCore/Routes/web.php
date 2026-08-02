<?php

declare(strict_types=1);

// WorkCore intentionally owns no host web routes. Subdomain API routes are
// opt-in and remain disabled until the host supplies authentication, tenant
// context, authorization and rate-limiting middleware.
