<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CompanySetting;

class CheckRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        $companySetting = CompanySetting::first();

        if (!$companySetting || !$companySetting->is_registration) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled.');
        }

        return $next($request);
    }
}
