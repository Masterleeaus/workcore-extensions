<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

final class RequirementNormalizer
{
    /** @var array<string,bool> */
    private array $stopWords;

    /** @var array<string,string> */
    private array $synonyms = [
        'book' => 'appointment',
        'booking' => 'appointment',
        'bookings' => 'appointment',
        'calendar' => 'schedule',
        'client' => 'customer',
        'clients' => 'customer',
        'employee' => 'worker',
        'employees' => 'worker',
        'staff' => 'worker',
        'technician' => 'worker',
        'technicians' => 'worker',
        'property' => 'premises',
        'properties' => 'premises',
        'job' => 'workorder',
        'jobs' => 'workorder',
    ];

    public function __construct()
    {
        $words = [
            'a','an','and','are','as','at','be','by','can','could','do','for','from','have','i','in','is','it','me','my',
            'need','of','on','or','our','please','that','the','their','them','this','to','track','use','user','users','want',
            'we','when','with','would','feature','function','system','allow','allows','required','requires',
        ];
        $this->stopWords = array_fill_keys($words, true);
    }

    public function normalise(string $requirement): string
    {
        return implode(' ', $this->tokens($requirement));
    }

    /** @return array<int,string> */
    public function tokens(string $value): array
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value;
        $value = strtolower(str_replace(['.', '_', '-', '/', '\\'], ' ', $value));
        $parts = preg_split('/[^a-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            if (isset($this->stopWords[$part]) || strlen($part) < 2) {
                continue;
            }

            $token = $this->stem($part);
            $token = $this->synonyms[$token] ?? $token;
            if ($token !== '' && ! isset($this->stopWords[$token])) {
                $tokens[$token] = true;
            }
        }

        return array_keys($tokens);
    }

    public function slug(string $requirement, int $maxLength = 56): string
    {
        $tokens = $this->tokens($requirement);
        $slug = implode('-', array_slice($tokens, 0, 8));
        $slug = trim(substr($slug, 0, max(8, $maxLength)), '-');

        return $slug !== '' ? $slug : 'adaptive-domain';
    }

    public function title(string $requirement): string
    {
        return ucwords(str_replace('-', ' ', $this->slug($requirement, 72)));
    }

    private function stem(string $word): string
    {
        if (strlen($word) > 5 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (strlen($word) > 5 && str_ends_with($word, 'ing')) {
            $base = substr($word, 0, -3);
            if (str_ends_with($base, 'ul')) {
                return $base . 'e';
            }
            return $base;
        }
        if (strlen($word) > 4 && str_ends_with($word, 'ed')) {
            return substr($word, 0, -2);
        }
        if (strlen($word) > 4 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }
}
