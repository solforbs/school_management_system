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

        // If multiple numbers are separated by / , ; or words like 'or', 'and', take the first phone candidate
        if (preg_match('/^([^\/,\;]+)/', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        // Remove notes in parentheses e.g. "0712345678 (Father)"
        $raw = preg_replace('/\s*\(.*?\)/', '', $raw) ?? $raw;

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

        // 2. Group upload rows by unique guardian key (connecting siblings across name & phone)
        /** @var array<string, array{name: string, phone: ?string, email: ?string, rows: array<int, int>, children: array<int, string>}> $guardianGroups */
        $guardianGroups = [];
        /** @var array<string, string> $nameToGroupKey */
        $nameToGroupKey = [];

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

            // Derive grouping key with cross-key sibling linking
            if ($normPhone) {
                $groupKey = 'phone:'.$normPhone;
            } elseif ($normEmail) {
                $groupKey = 'email:'.$normEmail;
            } elseif ($normName && isset($nameToGroupKey[$normName])) {
                $groupKey = $nameToGroupKey[$normName];
            } else {
                $groupKey = 'name:'.$normName;
            }

            if ($normName && ! isset($nameToGroupKey[$normName])) {
                $nameToGroupKey[$normName] = $groupKey;
            }

            if (! isset($guardianGroups[$groupKey])) {
                $guardianGroups[$groupKey] = [
                    'name' => $guardianName ?: 'Parent',
                    'phone' => $normPhone,
                    'email' => $normEmail,
                    'rows' => [],
                    'children' => [],
                ];
            } else {
                // Enrich group if phone was previously missing
                if (! $guardianGroups[$groupKey]['phone'] && $normPhone) {
                    $guardianGroups[$groupKey]['phone'] = $normPhone;
                }
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
     * Resolve or create a parent user.
     * Unified logic for single student enrollment, updates, spreadsheet import, and in-place roster editing.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, User>  $intraBatchCache  Optional in-memory cache for batch processing
     * @param  array<string, string|int>  $adminDecisions  Optional manual decisions
     */
    public function resolveParent(
        int $institutionId,
        array $data,
        array &$intraBatchCache = [],
        array $adminDecisions = []
    ): ?User {
        // 1. Explicit parent_id provided (e.g. chosen from dropdown)
        if (! empty($data['parent_id'])) {
            $parent = User::whereKey($data['parent_id'])->first();
            if ($parent instanceof User && $parent->hasRole('Parent')) {
                $this->cacheResolvedParent($intraBatchCache, $parent);

                return $parent;
            }
        }

        $rawName = trim((string) ($data['guardian_name'] ?? $data['parent_name'] ?? ''));
        $rawPhone = trim((string) ($data['guardian_phone'] ?? $data['parent_phone'] ?? ''));
        $rawEmail = trim((string) ($data['guardian_email'] ?? $data['parent_email'] ?? ''));
        $occupation = $data['parent_occupation'] ?? null;

        $normPhone = $this->normalizePhone($rawPhone);
        $normEmail = $this->normalizeEmail($rawEmail);
        $normName = $this->normalizeName($rawName);

        if (! $normName && ! $normPhone && ! $normEmail) {
            return null;
        }

        // 2. Check intra-batch cache across phone, email, and name keys
        if ($normPhone && isset($intraBatchCache['phone:'.$normPhone])) {
            return $intraBatchCache['phone:'.$normPhone];
        }
        if ($normEmail && isset($intraBatchCache['email:'.$normEmail])) {
            return $intraBatchCache['email:'.$normEmail];
        }
        if ($normName && isset($intraBatchCache['name:'.$normName])) {
            $cachedParent = $intraBatchCache['name:'.$normName];
            $cachedPhone = $this->normalizePhone($cachedParent->parent?->parent_phone);
            $cachedEmail = $this->normalizeEmail($cachedParent->email);

            $phoneConflict = $normPhone && $cachedPhone && $normPhone !== $cachedPhone;
            $emailConflict = $normEmail && $cachedEmail && ! str_ends_with($cachedEmail, '@parents.generated') && $normEmail !== $cachedEmail;

            if (! $phoneConflict && ! $emailConflict) {
                // If the cached parent doesn't have phone registered yet and this row provides it, enrich it
                if ($normPhone && empty($cachedPhone)) {
                    $details = ParentDetails::firstOrNew(['parent_id' => $cachedParent->id]);
                    if (empty($details->parent_phone)) {
                        $details->parent_phone = $normPhone;
                        $details->institution_id ??= $institutionId;
                        $details->save();
                        $this->cacheResolvedParent($intraBatchCache, $cachedParent, $normPhone, $normEmail, $normName);
                    }
                }

                return $cachedParent;
            }
        }

        // 3. Check admin decision for ambiguous name match
        $primaryKey = $normPhone ? 'phone:'.$normPhone : ($normEmail ? 'email:'.$normEmail : 'name:'.$normName);
        if (isset($adminDecisions[$primaryKey])) {
            $decision = $adminDecisions[$primaryKey];
            if ($decision !== 'create_new' && is_numeric($decision)) {
                $parentUser = User::role('Parent')->find((int) $decision);
                if ($parentUser) {
                    $this->cacheResolvedParent($intraBatchCache, $parentUser, $normPhone, $normEmail, $normName);

                    return $parentUser;
                }
            }
        }

        // 4. Check existing parent in DB by phone within this institution (or linked children here)
        if ($normPhone || $rawPhone) {
            $existingParentDetails = ParentDetails::where(function ($q) use ($normPhone, $rawPhone) {
                if ($normPhone) {
                    $q->where('parent_phone', $normPhone);
                }
                if ($rawPhone && $rawPhone !== $normPhone) {
                    $q->orWhere('parent_phone', $rawPhone);
                }
            })
                ->where(function ($q) use ($institutionId) {
                    $q->where('institution_id', $institutionId)
                        ->orWhereHas('parent.children', fn ($cq) => $cq->where('institution_id', $institutionId));
                })
                ->first();

            // Fallback: check phone globally if institution was unassigned on legacy records
            if (! $existingParentDetails) {
                $existingParentDetails = ParentDetails::where(function ($q) use ($normPhone, $rawPhone) {
                    if ($normPhone) {
                        $q->where('parent_phone', $normPhone);
                    }
                    if ($rawPhone && $rawPhone !== $normPhone) {
                        $q->orWhere('parent_phone', $rawPhone);
                    }
                })->whereNull('institution_id')->first();
            }

            if ($existingParentDetails && $existingParentDetails->parent) {
                $parent = $existingParentDetails->parent;
                if ($rawName !== '' && ($parent->name === 'Parent' || empty($parent->name))) {
                    $parent->update(['name' => $rawName]);
                }
                if ($occupation && empty($existingParentDetails->parent_occupation)) {
                    $existingParentDetails->update(['parent_occupation' => $occupation]);
                }
                if (empty($existingParentDetails->institution_id)) {
                    $existingParentDetails->update(['institution_id' => $institutionId]);
                }
                $this->cacheResolvedParent($intraBatchCache, $parent, $normPhone, $normEmail, $normName);

                return $parent;
            }
        }

        // 5. Check existing parent in DB by email
        if ($normEmail && ! str_ends_with($normEmail, '@parents.generated')) {
            $existingParent = User::role('Parent')
                ->where('email', $normEmail)
                ->where(function ($q) use ($institutionId) {
                    $q->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institutionId))
                        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $institutionId));
                })
                ->first();

            if (! $existingParent) {
                // If unique real email exists anywhere, reuse it
                $existingParent = User::role('Parent')->where('email', $normEmail)->first();
            }

            if ($existingParent) {
                $this->cacheResolvedParent($intraBatchCache, $existingParent, $normPhone, $normEmail, $normName);

                return $existingParent;
            }
        }

        // 6. Check existing parent in DB by normalized name within this institution
        if ($normName) {
            $existingParentsByName = User::role('Parent')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normName])
                ->where(function ($q) use ($institutionId) {
                    $q->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institutionId))
                        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $institutionId));
                })
                ->with('parent')
                ->get();

            foreach ($existingParentsByName as $existingParentByName) {
                $existingPhone = $this->normalizePhone($existingParentByName->parent?->parent_phone);
                $existingEmail = $this->normalizeEmail($existingParentByName->email);

                // If both have phone and they differ, this is not a match (different parents with same name)
                if ($normPhone && $existingPhone && $normPhone !== $existingPhone) {
                    continue;
                }

                // If both have email and they differ, this is not a match
                if ($normEmail && $existingEmail && ! str_ends_with($existingEmail, '@parents.generated') && $normEmail !== $existingEmail) {
                    continue;
                }

                // If parent has no phone recorded and this row has one, enrich parent_details
                if ($normPhone && empty($existingPhone)) {
                    $details = ParentDetails::firstOrNew(['parent_id' => $existingParentByName->id]);
                    $details->institution_id ??= $institutionId;
                    if (empty($details->parent_phone)) {
                        $details->parent_phone = $normPhone;
                        $details->save();
                    }
                }
                $this->cacheResolvedParent($intraBatchCache, $existingParentByName, $normPhone, $normEmail, $normName);

                return $existingParentByName;
            }
        }

        // 7. Otherwise, create ONE new parent user for this family
        $parentEmail = ($normEmail && ! str_ends_with($normEmail, '@parents.generated'))
            ? $normEmail
            : 'parent_'.uniqid().'_'.Str::random(4).'@parents.generated';

        $parent = User::create([
            'name' => $rawName ?: 'Parent',
            'email' => $parentEmail,
            'password' => Hash::make($normPhone ?? 'password123'),
        ]);
        $parent->syncRoles('Parent');

        ParentDetails::create([
            'parent_id' => $parent->id,
            'institution_id' => $institutionId,
            'parent_phone' => $normPhone ?: ($rawPhone ?: null),
            'parent_occupation' => $occupation,
        ]);

        $this->cacheResolvedParent($intraBatchCache, $parent, $normPhone, $normEmail, $normName);

        return $parent;
    }

    /**
     * Cache resolved parent across all its identifier keys so siblings in the same batch
     * match regardless of whether they provide phone, email, or just guardian name.
     *
     * @param  array<string, User>  $cache
     */
    public function cacheResolvedParent(array &$cache, User $parent, ?string $normPhone = null, ?string $normEmail = null, ?string $normName = null): void
    {
        $cache['id:'.$parent->id] = $parent;

        $phone = $normPhone ?: $this->normalizePhone($parent->parent?->parent_phone);
        if ($phone) {
            $cache['phone:'.$phone] = $parent;
        }

        $email = $normEmail ?: $this->normalizeEmail($parent->email);
        if ($email && ! str_ends_with($email, '@parents.generated')) {
            $cache['email:'.$email] = $parent;
        }

        $name = $normName ?: $this->normalizeName($parent->name);
        if ($name) {
            $cache['name:'.$name] = $parent;
        }
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
        return $this->resolveParent($institutionId, $data, $intraBatchCache, $adminDecisions);
    }
}
