<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Parent\Models\ParentDetails;

class ParentMatchingService
{
    /**
     * Normalize a phone number to standard format for reliable comparisons.
     * Handles Kenyan local numbers (07..., 01..., +254..., 254...) and cleans delimiters.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        // Remove whitespace, hyphens, periods, parentheses
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $raw);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        // Convert Kenyan formats to standard 10-digit 07... or 01...
        if (str_starts_with($cleaned, '+254')) {
            $cleaned = '0'.substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '254') && strlen($cleaned) === 12) {
            $cleaned = '0'.substr($cleaned, 3);
        } elseif (preg_match('/^[71]\d{8}$/', $cleaned)) {
            $cleaned = '0'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Normalize a person's name by trimming, lowercasing, and collapsing whitespace.
     */
    public function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $raw = trim($name);
        if ($raw === '') {
            return null;
        }

        // Collapse multiple spaces into single space and lowercase
        $collapsed = preg_replace('/\s+/', ' ', $raw);

        return $collapsed !== null ? mb_strtolower($collapsed, 'UTF-8') : null;
    }

    /**
     * Normalize an email address by trimming and lowercasing.
     */
    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $raw = trim($email);

        return $raw !== '' ? mb_strtolower($raw, 'UTF-8') : null;
    }

    /**
     * Analyze a bulk student upload batch for parent deduplication, sibling groupings, and ambiguities.
     * Strictly isolated to the given institution.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{
     *     matched_existing: array<string, array{parent_id: int, parent_name: string, parent_phone: ?string, children: array<int, string>, rows: array<int, int>}>,
     *     new_parents: array<string, array{parent_name: string, parent_phone: ?string, children: array<int, string>, rows: array<int, int>}>,
     *     ambiguous_matches: array<string, array{key: string, guardian_name: string, candidate_parents: array<int, array{id: int, name: string, phone: ?string, child_count: int}>, children: array<int, string>, rows: array<int, int>}>,
     *     total_existing_reused: int,
     *     total_new_parents: int
     * }
     */
    public function analyzeBatch(int $institutionId, array $rows): array
    {
        // 1. Load existing parents for this institution
        $existingParents = User::role('Parent')
            ->where(function ($query) use ($institutionId) {
                $query->whereHas('parent', fn ($q) => $q->where('institution_id', $institutionId))
                    ->orWhereHas('children', fn ($q) => $q->where('institution_id', $institutionId));
            })
            ->with(['parent', 'children'])
            ->get();

        /** @var array<string, User> $phoneMap */
        $phoneMap = [];
        /** @var array<string, User> $emailMap */
        $emailMap = [];
        /** @var array<string, Collection<int, User>> $nameMap */
        $nameMap = [];

        foreach ($existingParents as $p) {
            $phone = $this->normalizePhone($p->parent?->parent_phone);
            if ($phone) {
                $phoneMap[$phone] = $p;
            }

            $email = $this->normalizeEmail($p->email);
            if ($email && ! str_ends_with($email, '@parents.generated')) {
                $emailMap[$email] = $p;
            }

            $name = $this->normalizeName($p->name);
            if ($name) {
                if (! isset($nameMap[$name])) {
                    $nameMap[$name] = collect();
                }
                $nameMap[$name]->push($p);
            }
        }

        // 2. Group upload rows by unique guardian key
        /** @var array<string, array{name: string, phone: ?string, email: ?string, rows: array<int, int>, children: array<int, string>}> $guardianGroups */
        $guardianGroups = [];

        foreach ($rows as $rowIndex => $row) {
            $studentName = trim((string) ($row['name'] ?? ''));
            if ($studentName === '') {
                continue;
            }

            $guardianName = trim((string) ($row['guardian_name'] ?? $row['parent_name'] ?? ''));
            $rawPhone = trim((string) ($row['guardian_phone'] ?? $row['parent_phone'] ?? ''));
            $rawEmail = trim((string) ($row['guardian_email'] ?? $row['parent_email'] ?? ''));

            $normPhone = $this->normalizePhone($rawPhone);
            $normEmail = $this->normalizeEmail($rawEmail);
            $normName = $this->normalizeName($guardianName);

            if (! $normName && ! $normPhone && ! $normEmail) {
                continue; // Student has no guardian specified
            }

            // Derive grouping key: Phone takes precedence, then email, then name
            if ($normPhone) {
                $groupKey = 'phone:'.$normPhone;
            } elseif ($normEmail) {
                $groupKey = 'email:'.$normEmail;
            } else {
                $groupKey = 'name:'.$normName;
            }

            if (! isset($guardianGroups[$groupKey])) {
                $guardianGroups[$groupKey] = [
                    'name' => $guardianName ?: 'Parent',
                    'phone' => $normPhone,
                    'email' => $normEmail,
                    'rows' => [],
                    'children' => [],
                ];
            }

            $guardianGroups[$groupKey]['rows'][] = $rowIndex;
            $guardianGroups[$groupKey]['children'][] = $studentName;
        }

        // 3. Categorize into matched_existing, new_parents, or ambiguous_matches
        $matchedExisting = [];
        $newParents = [];
        $ambiguousMatches = [];

        foreach ($guardianGroups as $key => $group) {
            // Check phone match
            if ($group['phone'] && isset($phoneMap[$group['phone']])) {
                $parentUser = $phoneMap[$group['phone']];
                $matchedExisting[$key] = [
                    'parent_id' => $parentUser->id,
                    'parent_name' => $parentUser->name,
                    'parent_phone' => $group['phone'],
                    'children' => $group['children'],
                    'rows' => $group['rows'],
                ];

                continue;
            }

            // Check email match
            if ($group['email'] && isset($emailMap[$group['email']])) {
                $parentUser = $emailMap[$group['email']];
                $matchedExisting[$key] = [
                    'parent_id' => $parentUser->id,
                    'parent_name' => $parentUser->name,
                    'parent_phone' => $parentUser->parent?->parent_phone,
                    'children' => $group['children'],
                    'rows' => $group['rows'],
                ];

                continue;
            }

            // Name-only match (no phone and no email provided)
            $normName = $this->normalizeName($group['name']);
            if (! $group['phone'] && ! $group['email'] && $normName && isset($nameMap[$normName])) {
                $candidates = $nameMap[$normName]->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->parent?->parent_phone,
                    'child_count' => $u->children->count(),
                ])->values()->all();

                $ambiguousMatches[$key] = [
                    'key' => $key,
                    'guardian_name' => $group['name'],
                    'candidate_parents' => $candidates,
                    'children' => $group['children'],
                    'rows' => $group['rows'],
                ];

                continue;
            }

            // Otherwise, it's a new parent to be created (shared among siblings in this batch)
            $newParents[$key] = [
                'parent_name' => $group['name'],
                'parent_phone' => $group['phone'],
                'children' => $group['children'],
                'rows' => $group['rows'],
            ];
        }

        return [
            'matched_existing' => $matchedExisting,
            'new_parents' => $newParents,
            'ambiguous_matches' => $ambiguousMatches,
            'total_existing_reused' => count($matchedExisting),
            'total_new_parents' => count($newParents),
        ];
    }

    /**
     * Resolve or create a parent user for a student row, reusing intra-batch parents across siblings.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, User>  $intraBatchCache  Passed by reference to store created/reused parents
     * @param  array<string, string|int>  $adminDecisions  Decisions for ambiguous keys (e.g. ['name:fatima omar' => 42 or 'create_new'])
     */
    public function resolveParentForBatch(
        int $institutionId,
        array $data,
        array &$intraBatchCache,
        array $adminDecisions = []
    ): ?User {
        $name = trim((string) ($data['guardian_name'] ?? $data['parent_name'] ?? ''));
        $rawPhone = trim((string) ($data['guardian_phone'] ?? $data['parent_phone'] ?? ''));
        $rawEmail = trim((string) ($data['guardian_email'] ?? $data['parent_email'] ?? ''));

        $normPhone = $this->normalizePhone($rawPhone);
        $normEmail = $this->normalizeEmail($rawEmail);
        $normName = $this->normalizeName($name);

        if (! $normName && ! $normPhone && ! $normEmail) {
            return null;
        }

        // 1. Determine batch key
        if ($normPhone) {
            $groupKey = 'phone:'.$normPhone;
        } elseif ($normEmail) {
            $groupKey = 'email:'.$normEmail;
        } else {
            $groupKey = 'name:'.$normName;
        }

        // 2. If already resolved in this batch (sibling in earlier row), reuse immediately!
        if (isset($intraBatchCache[$groupKey])) {
            return $intraBatchCache[$groupKey];
        }

        // 3. Check admin decision for ambiguous name match
        if (isset($adminDecisions[$groupKey])) {
            $decision = $adminDecisions[$groupKey];
            if ($decision !== 'create_new' && is_numeric($decision)) {
                $parentUser = User::role('Parent')->find((int) $decision);
                if ($parentUser) {
                    $intraBatchCache[$groupKey] = $parentUser;

                    return $parentUser;
                }
            }
        }

        // 4. Check existing parent in DB by phone
        if ($normPhone) {
            $existingParentDetails = ParentDetails::where('parent_phone', $normPhone)->first();
            if ($existingParentDetails && $existingParentDetails->parent) {
                $parent = $existingParentDetails->parent;
                if ($name !== '' && $parent->name === 'Parent') {
                    $parent->update(['name' => $name]);
                }
                $intraBatchCache[$groupKey] = $parent;

                return $parent;
            }
        }

        // 5. Check existing parent in DB by email
        if ($normEmail) {
            $existingParent = User::role('Parent')->where('email', $normEmail)->first();
            if ($existingParent) {
                $intraBatchCache[$groupKey] = $existingParent;

                return $existingParent;
            }
        }

        // 6. Otherwise create ONE new parent for this group
        $parentEmail = $normEmail ?: 'parent_'.uniqid().'_'.Str::random(4).'@parents.generated';

        $parent = User::create([
            'name' => $name ?: 'Parent',
            'email' => $parentEmail,
            'password' => Hash::make($normPhone ?? 'password123'),
        ]);
        $parent->syncRoles('Parent');

        ParentDetails::create([
            'parent_id' => $parent->id,
            'institution_id' => $institutionId,
            'parent_phone' => $normPhone,
            'parent_occupation' => $data['parent_occupation'] ?? null,
        ]);

        $intraBatchCache[$groupKey] = $parent;

        return $parent;
    }
}
