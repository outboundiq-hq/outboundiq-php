<?php

use OutboundIQ\Interceptors\CurlInterceptor;

// Backup original functions
if (!function_exists('curl_init_original')) {
    rename_function('curl_init', 'curl_init_original');
    rename_function('curl_setopt', 'curl_setopt_original');
    rename_function('curl_setopt_array', 'curl_setopt_array_original');
    rename_function('curl_exec', 'curl_exec_original');
    rename_function('curl_close', 'curl_close_original');
}

if (!function_exists('curl_init')) {
    function curl_init($url = null)
    {
        return CurlInterceptor::init($url);
    }
}

if (!function_exists('curl_setopt')) {
    function curl_setopt($handle, $option, $value)
    {
        return CurlInterceptor::setopt($handle, $option, $value);
    }
}

if (!function_exists('curl_setopt_array')) {
    function curl_setopt_array($handle, array $options)
    {
        return CurlInterceptor::setopt_array($handle, $options);
    }
}

if (!function_exists('curl_exec')) {
    function curl_exec($handle)
    {
        return CurlInterceptor::exec($handle);
    }
}

if (!function_exists('curl_close')) {
    function curl_close($handle)
    {
        return CurlInterceptor::close($handle);
    }
}

if (!function_exists('curl_getinfo')) {
    function curl_getinfo($handle, $opt = null)
    {
        return curl_getinfo_original($handle, $opt);
    }
} 