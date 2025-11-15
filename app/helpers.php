<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

if (! function_exists('settings')) {
    function settings(?string $key = null, bool $defaults = true): mixed
    {
        if (is_null($key)) {
            return null;
        }

        try {
            $value = Setting::get($key);

            return $defaults ? ($value ?? Setting::default($key)) : $value;
        } catch (QueryException) {
            return null;
        }
    }
}

if (! function_exists('user')) {
    function user(): ?User
    {
        /** @var ?User */
        $user = Auth::user();

        return $user;
    }
}
