<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $companySetting = CompanySetting::first();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn() => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')
                    : [],
                'roles' => fn() => $request->user()
                    ? $request->user()->getRoleNames()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            'company' => [
                'name' => $companySetting?->company_name ?? null,
                'logo' => $companySetting?->company_logo ? asset('storage' . $companySetting->company_logo) : null,
                'is_registration' => $companySetting?->is_registration ?? false,
            ],
        ];
    }
}
