<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class SupportChatActionRegistry
{
    /**
     * @return array<int, array{key: string, label: string, type: string, route?: string, params?: array, message?: string}>
     */
    public function quickActionsFor(User $user): array
    {
        return array_values(array_filter(
            $this->allActions(),
            fn (array $action) => $this->userCanUseAction($user, $action)
        ));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, label: string, type: string, url?: string, message?: string}>
     */
    public function resolveForUser(User $user, array $keys): array
    {
        $resolved = [];
        $byKey = collect($this->allActions())->keyBy('key');

        foreach ($keys as $key) {
            $key = strtolower(trim((string) $key));
            $action = $byKey->get($key);
            if (! $action || ! $this->userCanUseAction($user, $action)) {
                continue;
            }
            $resolved[] = $this->toResolvedAction($action);
        }

        return $resolved;
    }

    public function actionKeysForPrompt(User $user): string
    {
        return collect($this->quickActionsFor($user))
            ->map(fn (array $a) => $a['key'].' ('.$a['label'].')')
            ->implode(', ');
    }

    public function findForUser(User $user, string $key): ?array
    {
        $key = strtolower(trim($key));
        foreach ($this->allActions() as $action) {
            if ($action['key'] === $key && $this->userCanUseAction($user, $action)) {
                return $this->toResolvedAction($action);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, route?: string, params?: array, message?: string, roles?: array}>
     */
    private function allActions(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'type' => 'link', 'route' => 'dashboard'],
            ['key' => 'weekly_summary', 'label' => 'Weekly summary', 'type' => 'link', 'route' => 'weekly.summary'],
            ['key' => 'monthly_summary', 'label' => 'Monthly summary', 'type' => 'link', 'route' => 'monthly.summary'],
            ['key' => 'hptm', 'label' => 'HPTM learning', 'type' => 'link', 'route' => 'hptm.list'],
            ['key' => 'reflections', 'label' => 'My reflections', 'type' => 'link', 'route' => 'admin.reflections.index'],
            ['key' => 'reflection_create', 'label' => 'New reflection', 'type' => 'link', 'route' => 'reflection.create'],
            ['key' => 'offloading_list', 'label' => 'My feedback', 'type' => 'link', 'route' => 'offloading.list', 'roles' => ['organisation_user', 'director']],
            ['key' => 'offloading_create', 'label' => 'Submit feedback', 'type' => 'link', 'route' => 'offloading.create', 'roles' => ['organisation_user', 'director']],
            ['key' => 'notifications', 'label' => 'Notifications', 'type' => 'link', 'route' => 'user.notifications'],
            ['key' => 'billing', 'label' => 'Billing', 'type' => 'link', 'route' => 'subscription.billing', 'roles' => ['organisation_admin', 'super_admin']],
            ['key' => 'basecamp_billing', 'label' => 'Basecamp billing', 'type' => 'link', 'route' => 'basecamp.billing', 'roles' => ['basecamp']],
            ['key' => 'myteam', 'label' => 'My team', 'type' => 'link', 'route' => 'myteam.list', 'roles' => ['organisation_user', 'organisation_admin', 'director', 'super_admin']],
            ['key' => 'diagnostics', 'label' => 'Diagnostics', 'type' => 'link', 'route' => 'diagnostics.index', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'tribeometer', 'label' => 'Tribeometer', 'type' => 'link', 'route' => 'tribeometer.index', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'team_role_map', 'label' => 'Team role map', 'type' => 'link', 'route' => 'connecting.team-role-map.results', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'personality_type', 'label' => 'Personality type', 'type' => 'link', 'route' => 'connecting.personality-type.results', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'culture_structure', 'label' => 'Culture structure', 'type' => 'link', 'route' => 'supercharging.culture-structure.results', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'motivation', 'label' => 'Motivation', 'type' => 'link', 'route' => 'supercharging.motivation.results', 'roles' => ['organisation_user', 'organisation_admin', 'director']],
            ['key' => 'change_password', 'label' => 'Change password', 'type' => 'link', 'route' => 'password.change'],
            ['key' => 'check_sentiment', 'label' => 'My sentiment data', 'type' => 'query', 'message' => 'Search my database and summarise my recent sentiment and happy index entries.'],
            ['key' => 'check_summaries', 'label' => 'My AI summaries', 'type' => 'query', 'message' => 'Search my database and tell me about my latest weekly and monthly summaries.'],
            ['key' => 'check_feedback', 'label' => 'My offloading feedback', 'type' => 'query', 'message' => 'Search my database and list my offloading feedback submissions and their status.', 'roles' => ['organisation_user', 'director']],
            ['key' => 'check_notifications', 'label' => 'My notifications', 'type' => 'query', 'message' => 'Search my database and summarise my recent notifications.'],
            ['key' => 'check_hptm', 'label' => 'My HPTM progress', 'type' => 'query', 'message' => 'Search my database and summarise my HPTM principles and learning checklist progress.'],
        ];
    }

    private function userCanUseAction(User $user, array $action): bool
    {
        if (empty($action['roles'])) {
            return $this->routeExists($action);
        }

        if (! $user->hasAnyRole($action['roles'])) {
            return false;
        }

        return $this->routeExists($action);
    }

    private function routeExists(array $action): bool
    {
        if (($action['type'] ?? '') === 'query') {
            return true;
        }

        $route = $action['route'] ?? null;
        if (! $route || ! Route::has($route)) {
            return false;
        }

        return true;
    }

    private function toResolvedAction(array $action): array
    {
        $resolved = [
            'key' => $action['key'],
            'label' => $action['label'],
            'type' => $action['type'],
        ];

        if ($action['type'] === 'link') {
            $resolved['url'] = route($action['route'], $action['params'] ?? []);
        } else {
            $resolved['message'] = $action['message'] ?? '';
        }

        return $resolved;
    }
}
