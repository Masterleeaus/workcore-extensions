<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Reviews\Services;
use InvalidArgumentException;
final class ReviewPolicy
{
    public function sentiment(int $rating): string { if($rating<1||$rating>5)throw new InvalidArgumentException('Rating must be between 1 and 5.'); return $rating<=2?'negative':($rating===3?'neutral':'positive'); }
    public function requiresRecovery(int $rating): bool { return $rating<=2; }
    public function assertPublication(bool $permission,string $status): void { if($status==='published'&&!$permission)throw new InvalidArgumentException('Public permission is required before publishing a review.'); }
    public function assertRebookingWindow(?string $start,?string $end): void { if($start&&$end&&strtotime($end)<=strtotime($start))throw new InvalidArgumentException('Preferred rebooking end must be after start.'); }
}
