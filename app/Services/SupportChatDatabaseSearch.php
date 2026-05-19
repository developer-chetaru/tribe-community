<?php

namespace App\Services;

use App\Models\DiagnosticIndividualUserStatus;
use App\Models\HappyIndex;
use App\Models\HptmLearningChecklist;
use App\Models\HptmLearningChecklistForUserReadStatus;
use App\Models\HptmPrinciple;
use App\Models\IotFeedback;
use App\Models\IotNotification;
use App\Models\MonthlySummary;
use App\Models\Organisation;
use App\Models\Reflection;
use App\Models\SubscriptionRecord;
use App\Models\User;
use App\Models\WeeklySummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupportChatDatabaseSearch
{
    /**
     * @return array{context: string, sources: array<int, string>}
     */
    public function search(User $user, string $query): array
    {
        $queryLower = strtolower($query);
        $sections = [];
        $sources = [];

        $sections[] = $this->profileContext($user);
        $sources[] = 'profile';

        $peopleSection = $this->peopleSearchContext($user, $query);
        if ($peopleSection !== null) {
            $sections[] = $peopleSection;
            $sources[] = 'teammates';
        }

        $searches = [
            ['keywords' => ['sentiment', 'happy', 'mood', 'feeling', 'emotion', 'hi ', 'index'], 'source' => 'sentiment'],
            ['keywords' => ['weekly', 'week summary'], 'source' => 'weekly_summaries'],
            ['keywords' => ['monthly', 'month summary'], 'source' => 'monthly_summaries'],
            ['keywords' => ['reflection', 'reflect'], 'source' => 'reflections'],
            ['keywords' => ['offload', 'feedback', 'iot'], 'source' => 'offloading'],
            ['keywords' => ['notification', 'alert'], 'source' => 'notifications'],
            ['keywords' => ['hptm', 'principle', 'learning', 'checklist'], 'source' => 'hptm'],
            ['keywords' => ['billing', 'subscription', 'invoice', 'payment'], 'source' => 'billing'],
            ['keywords' => ['diagnostic', 'assessment', 'tribeometer', 'personality', 'motivation', 'culture'], 'source' => 'assessments'],
            ['keywords' => ['organisation', 'organization', 'org ', 'office'], 'source' => 'organisation'],
        ];

        $matchedSpecific = false;
        foreach ($searches as $search) {
            if ($this->matchesAny($queryLower, $search['keywords'])) {
                $section = $this->searchBySource($user, $search['source'], $query);
                if ($section !== null) {
                    $sections[] = $section;
                    $sources[] = $search['source'];
                    $matchedSpecific = true;
                }
            }
        }

        $textHits = $this->textSearchAcrossRecords($user, $query);
        if ($textHits !== '') {
            $sections[] = $textHits;
            $sources[] = 'text_search';
        } elseif (! $matchedSpecific) {
            $sections[] = $this->recentActivitySnapshot($user);
            $sources[] = 'recent_activity';
        }

        return [
            'context' => implode("\n\n", array_filter($sections)),
            'sources' => array_values(array_unique($sources)),
        ];
    }

    private function searchBySource(User $user, string $source, string $query): ?string
    {
        return match ($source) {
            'sentiment' => $this->sentimentContext($user),
            'weekly_summaries' => $this->weeklySummariesContext($user),
            'monthly_summaries' => $this->monthlySummariesContext($user),
            'reflections' => $this->reflectionsContext($user, $query),
            'offloading' => $this->offloadingContext($user, $query),
            'notifications' => $this->notificationsContext($user),
            'hptm' => $this->hptmContext($user, $query),
            'billing' => $this->billingContext($user),
            'assessments' => $this->assessmentsContext($user),
            'organisation' => $this->organisationContext($user),
            default => null,
        };
    }

    private function peopleSearchContext(User $user, string $query): ?string
    {
        if (! $user->orgId) {
            return null;
        }

        $queryLower = strtolower(trim($query));
        $terms = $this->extractPersonSearchTerms($query);
        $isPeopleQuery = $this->matchesAny($queryLower, [
            'who is', "who's", 'who are', 'tell me about', 'find person', 'find user',
            'teammate', 'colleague', 'team member', 'my team', 'coworker',
        ]);

        if (empty($terms) && $this->matchesAny($queryLower, ['my team', 'teammates', 'team members', 'list team', 'all colleagues'])) {
            return $this->teammatesListContext($user);
        }

        if (empty($terms) && ! $isPeopleQuery) {
            return null;
        }

        if (empty($terms) && $isPeopleQuery) {
            return $this->teammatesListContext($user);
        }

        $members = $this->findTeammates($user, $terms);

        if ($members->isEmpty()) {
            $termList = implode(', ', $terms);

            return "[Teammates / people search]\nNo teammate found matching: {$termList} in your organisation directory.\nSuggest checking My Teammates page for the full list.";
        }

        $lines = $members->map(fn (User $m) => $this->formatTeammateLine($m))->implode("\n");

        return "[Teammates / people search — matches in your organisation]\n{$lines}\n(View full team on My Teammates page.)";
    }

    private function teammatesListContext(User $user): string
    {
        $members = User::with(['roles', 'department.allDepartment', 'office'])
            ->where('orgId', $user->orgId)
            ->where('id', '!=', $user->id)
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        if ($members->isEmpty()) {
            return '[Teammates]\nNo other teammates found in your organisation.';
        }

        $lines = $members->map(fn (User $m) => $this->formatTeammateLine($m))->implode("\n");
        $total = User::where('orgId', $user->orgId)->where('id', '!=', $user->id)->count();
        $more = $total > 20 ? "\n(Showing 20 of {$total} teammates.)" : '';

        return "[Teammates in your organisation]\n{$lines}{$more}";
    }

    /**
     * @return array<int, string>
     */
    private function extractPersonSearchTerms(string $query): array
    {
        $terms = [];
        $query = trim($query);

        if (preg_match('/\b(?:who\s+is|who\'s|who\s+are|tell\s+me\s+about|find|search\s+for|lookup)\s+(.+?)\??\s*$/iu', $query, $matches)) {
            $terms[] = trim($matches[1], " \t\n\r\0\x0B?.!");
        }

        $stopWords = [
            'who', 'is', 'are', 'the', 'a', 'an', 'my', 'our', 'your', 'their', 'this', 'that',
            'what', 'where', 'when', 'how', 'please', 'tell', 'about', 'find', 'search', 'user',
            'person', 'someone', 'teammate', 'colleague', 'member', 'team', 'from', 'in',
        ];

        foreach (preg_split('/\s+/u', strtolower($query)) as $word) {
            $word = trim($word, '?.!,');
            if (strlen($word) >= 3 && ! in_array($word, $stopWords, true)) {
                $terms[] = $word;
            }
        }

        return array_values(array_unique(array_filter($terms)));
    }

    /**
     * @param  array<int, string>  $terms
     * @return Collection<int, User>
     */
    private function findTeammates(User $user, array $terms): Collection
    {
        $members = User::with(['roles', 'department.allDepartment', 'office'])
            ->where('orgId', $user->orgId)
            ->where('id', '!=', $user->id)
            ->get();

        return $members->filter(function (User $member) use ($terms) {
            foreach ($terms as $term) {
                $term = strtolower(trim($term));
                if (strlen($term) < 3) {
                    continue;
                }
                if ($this->memberMatchesTerm($member, $term)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function memberMatchesTerm(User $member, string $term): bool
    {
        $fields = [
            strtolower($member->first_name ?? ''),
            strtolower($member->last_name ?? ''),
            strtolower(trim(($member->first_name ?? '').' '.($member->last_name ?? ''))),
            strtolower($member->email ?? ''),
        ];

        foreach ($fields as $haystack) {
            if ($haystack === '') {
                continue;
            }
            if (str_contains($haystack, $term)) {
                return true;
            }
            foreach (preg_split('/[\s@.]+/', $haystack) as $part) {
                if (strlen($part) < 3 || strlen($term) < 3) {
                    continue;
                }
                if (levenshtein($part, $term) <= 2) {
                    return true;
                }
            }
        }

        return false;
    }

    private function formatTeammateLine(User $member): string
    {
        $roles = $member->getRoleNames()->implode(', ') ?: 'Staff';
        $dept = $member->department?->allDepartment?->name
            ?? $member->department?->department
            ?? 'n/a';
        $office = $member->office?->name ?? 'n/a';

        return "- {$member->name} | Email: {$member->email} | Role: {$roles} | Department: {$dept} | Office: {$office}";
    }

    private function profileContext(User $user): string
    {
        $roles = $user->getRoleNames()->implode(', ') ?: 'user';
        $orgName = $user->organisation?->name ?? 'N/A';
        $lastLogin = $user->last_login_at?->format('d M Y H:i') ?? 'unknown';
        $status = $user->status ?? 'unknown';

        return <<<CTX
[User profile]
Name: {$user->name}
Email: {$user->email}
Roles: {$roles}
Account status: {$status}
Organisation: {$orgName}
Timezone: {$user->timezone}
Last login: {$lastLogin}
CTX;
    }

    private function sentimentContext(User $user): string
    {
        $entries = HappyIndex::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(14)
            ->get(['mood_value', 'description', 'created_at']);

        if ($entries->isEmpty()) {
            return "[Sentiment / Happy Index]\nNo sentiment entries found for this user.";
        }

        $lines = $entries->map(function ($row) {
            $mood = match ((int) $row->mood_value) {
                3 => 'Good',
                1 => 'Bad',
                default => 'Ok',
            };

            $date = $row->created_at instanceof Carbon
                ? $row->created_at->format('d M Y')
                : (string) $row->created_at;
            $note = $row->description ? ' — '.Str::limit($row->description, 120) : '';

            return "- {$date}: {$mood}{$note}";
        })->implode("\n");

        $lastDate = $user->lastHIDate ?? 'not set';

        return "[Sentiment / Happy Index — last 14 entries]\nLast HI date field: {$lastDate}\n{$lines}";
    }

    private function weeklySummariesContext(User $user): string
    {
        $rows = WeeklySummary::where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(4)
            ->get();

        if ($rows->isEmpty()) {
            return '[Weekly summaries]\nNo weekly summaries stored for this user.';
        }

        $lines = $rows->map(fn ($r) => '- '.($r->week_label ?? "Week {$r->week_number}/{$r->year}").': '
            .Str::limit($r->summary ?? '', 200))->implode("\n");

        return "[Weekly summaries — latest]\n{$lines}";
    }

    private function monthlySummariesContext(User $user): string
    {
        $rows = MonthlySummary::where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(3)
            ->get();

        if ($rows->isEmpty()) {
            return '[Monthly summaries]\nNo monthly summaries stored for this user.';
        }

        $lines = $rows->map(fn ($r) => '- '.($r->month_label ?? "{$r->month}/{$r->year}").': '
            .Str::limit($r->summary ?? '', 200))->implode("\n");

        return "[Monthly summaries — latest]\n{$lines}";
    }

    private function reflectionsContext(User $user, string $query): string
    {
        $builder = Reflection::where('userId', $user->id)->orderByDesc('created_at');

        if (strlen($query) >= 3) {
            $term = '%'.$query.'%';
            $builder->where(function ($q) use ($term) {
                $q->where('topic', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        $rows = $builder->limit(8)->get(['id', 'topic', 'message', 'status', 'created_at']);

        if ($rows->isEmpty()) {
            return '[Reflections]\nNo reflections found for this user.';
        }

        $lines = $rows->map(fn ($r) => '- #'.$r->id.' ['.($r->status ?? 'n/a').'] '
            .Str::limit(($r->topic ?: $r->message), 100)
            .' ('.$r->created_at->format('d M Y').')')->implode("\n");

        return "[Reflections]\n{$lines}";
    }

    private function offloadingContext(User $user, string $query): string
    {
        $builder = IotFeedback::where('userId', $user->id)->orderByDesc('created_at');

        if (strlen($query) >= 3) {
            $term = '%'.$query.'%';
            $builder->where('message', 'like', $term);
        }

        $rows = $builder->limit(8)->get(['id', 'message', 'feedbackStatus', 'status', 'created_at']);

        if ($rows->isEmpty()) {
            return '[Offloading feedback]\nNo feedback submissions found.';
        }

        $lines = $rows->map(fn ($r) => '- #'.$r->id.' status='.($r->feedbackStatus ?? $r->status ?? 'n/a').': '
            .Str::limit($r->message ?? '', 100)
            .' ('.$r->created_at->format('d M Y').')')->implode("\n");

        return "[Offloading feedback]\n{$lines}";
    }

    private function notificationsContext(User $user): string
    {
        $rows = IotNotification::where('to_bubble_user_id', $user->id)
            ->where('archive', false)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['title', 'description', 'notificationType', 'created_at']);

        if ($rows->isEmpty()) {
            return '[Notifications]\nNo active notifications.';
        }

        $lines = $rows->map(fn ($r) => '- '.($r->title ?? 'Notification')
            .($r->notificationType ? " ({$r->notificationType})" : '')
            .': '.Str::limit($r->description ?? '', 80)
            .' — '.$r->created_at->format('d M Y'))->implode("\n");

        return "[Notifications — unread/active]\n{$lines}";
    }

    private function hptmContext(User $user, string $query): string
    {
        $principles = HptmPrinciple::orderBy('priority')->limit(10)->get(['id', 'title', 'description']);
        $principleLines = $principles->map(fn ($p) => '- '.$p->title.': '.Str::limit($p->description ?? '', 80))->implode("\n");

        $readIds = HptmLearningChecklistForUserReadStatus::where('userId', $user->id)
            ->where('readStatus', 1)
            ->pluck('checklistId');

        $totalChecklists = HptmLearningChecklist::count();
        $readCount = $readIds->count();

        $checklistQuery = HptmLearningChecklist::with('principle')->orderBy('id');
        if (strlen($query) >= 3) {
            $term = '%'.$query.'%';
            $checklistQuery->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $checklists = $checklistQuery->limit(6)->get();
        $checklistLines = $checklists->map(function ($c) use ($readIds) {
            $read = $readIds->contains($c->id) ? 'read' : 'unread';

            return '- ['.$read.'] '.($c->title ?? 'Item').' (principle: '.($c->principle?->title ?? 'n/a').')';
        })->implode("\n");

        return "[HPTM]\nUser checklist progress: {$readCount}/{$totalChecklists} items marked read.\nPrinciples:\n{$principleLines}\nChecklist items:\n{$checklistLines}";
    }

    private function billingContext(User $user): string
    {
        if (! $user->orgId) {
            return '[Billing]\nUser is not linked to an organisation (may be Basecamp). Check basecamp billing in the app.';
        }

        $org = Organisation::find($user->orgId);
        $subscription = SubscriptionRecord::where('organisation_id', $user->orgId)
            ->orderByDesc('id')
            ->first();

        $orgLine = $org ? "Organisation: {$org->name}, status: ".($org->status ?? 'n/a') : 'Organisation not found';
        $subLine = $subscription
            ? "Subscription: status={$subscription->status}, tier={$subscription->tier}, users={$subscription->user_count}, next billing="
                .($subscription->next_billing_date?->format('d M Y') ?? 'n/a')
            : 'No subscription record found for organisation.';

        return "[Billing]\n{$orgLine}\n{$subLine}";
    }

    private function assessmentsContext(User $user): string
    {
        $diagnostic = DiagnosticIndividualUserStatus::where('userId', $user->id)
            ->orderByDesc('date')
            ->first();

        $diagLine = $diagnostic
            ? 'Diagnostics last status: '.($diagnostic->completeStatus ?? 'unknown').' on '.($diagnostic->date?->format('d M Y') ?? 'n/a')
            : 'Diagnostics: no completion record found.';

        return "[Assessments]\n{$diagLine}\n(User may also have Connecting/Supercharging/Tribeometer results in respective modules.)";
    }

    private function organisationContext(User $user): string
    {
        if (! $user->orgId) {
            return '[Organisation]\nUser has no organisation assigned.';
        }

        $org = Organisation::with(['offices', 'departments'])->find($user->orgId);
        if (! $org) {
            return '[Organisation]\nOrganisation record not found.';
        }

        $office = $user->office?->name ?? 'n/a';
        $department = $user->department?->allDepartment?->name
            ?? $user->department?->department
            ?? 'n/a';
        $officeCount = $org->offices?->count() ?? 0;

        return "[Organisation]\nName: {$org->name}\nUser office: {$office}\nUser department: {$department}\nOffices in org: {$officeCount}";
    }

    private function recentActivitySnapshot(User $user): string
    {
        $lastSentiment = HappyIndex::where('user_id', $user->id)->orderByDesc('created_at')->first();
        $lastReflection = Reflection::where('userId', $user->id)->orderByDesc('created_at')->first();
        $notifCount = IotNotification::where('to_bubble_user_id', $user->id)->where('archive', false)->count();

        $sentimentLine = $lastSentiment
            ? 'Last sentiment: '.$lastSentiment->created_at->format('d M Y')
            : 'Last sentiment: none';

        $reflectionLine = $lastReflection
            ? 'Last reflection #'.$lastReflection->id.' on '.$lastReflection->created_at->format('d M Y')
            : 'Last reflection: none';

        return "[Recent activity snapshot]\n{$sentimentLine}\n{$reflectionLine}\nActive notifications: {$notifCount}";
    }

    private function textSearchAcrossRecords(User $user, string $query): string
    {
        if (strlen(trim($query)) < 3) {
            return '';
        }

        $term = '%'.addcslashes($query, '%_').'%';
        $hits = [];

        $reflections = Reflection::where('userId', $user->id)
            ->where(function ($q) use ($term) {
                $q->where('topic', 'like', $term)->orWhere('message', 'like', $term);
            })
            ->limit(3)
            ->get();

        foreach ($reflections as $r) {
            $hits[] = 'Reflection #'.$r->id.': '.Str::limit($r->topic ?: $r->message, 80);
        }

        $feedbacks = IotFeedback::where('userId', $user->id)
            ->where('message', 'like', $term)
            ->limit(3)
            ->get();

        foreach ($feedbacks as $f) {
            $hits[] = 'Feedback #'.$f->id.': '.Str::limit($f->message, 80);
        }

        $summaries = WeeklySummary::where('user_id', $user->id)
            ->where('summary', 'like', $term)
            ->limit(2)
            ->get();

        foreach ($summaries as $s) {
            $hits[] = 'Weekly summary '.($s->week_label ?? '').': '.Str::limit($s->summary, 80);
        }

        if (empty($hits)) {
            return '';
        }

        return "[Text search matches for \"{$query}\"]\n- ".implode("\n- ", $hits);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesAny(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
