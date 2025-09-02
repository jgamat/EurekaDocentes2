<?php
// Polyfills for removed magic quotes functions on PHP 8+
// Some third-party libs (e.g., FPDF 1.8) still call these.

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime(): bool
    {
        return false;
    }
}

if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($new_setting): bool
    {
        // No-op on modern PHP, return false to indicate disabled
        return false;
    }
}

if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc(): bool
    {
        return false;
    }
}
