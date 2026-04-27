<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function disk(): string
    {
        $default = config('filesystems.default');
        return $default === 'local' ? 'public' : $default;
    }
}
