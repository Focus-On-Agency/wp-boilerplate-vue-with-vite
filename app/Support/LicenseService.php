<?php

namespace PluginClassName\Support;

use PluginClassName\Foundation\Http\Http;
use PluginClassName\Foundation\Http\Response;

if (!defined('ABSPATH')) {
	exit;
}

class LicenseService
{
    public const OPTION_EXP                = 'fson_rrt_license_expiration';
    public const OPTION_INSTANCE_UUID      = 'fson_rrt_instance_uuid';
    
    public static function activate(string $key) : ?Response
    {
        $domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $instance = get_option(self::OPTION_INSTANCE_UUID);

        if(!$domain || !$instance || !$key) {
            return null;
        }

        $body = [
            'license_key'    => $key,
            'domain'         => $domain,
            'instance_uuid'  => $instance,
        ];

        $response = Http::acceptJson()
			->baseUrl(PluginClassName_API_URL)
			->post('/license/activate', $body)
        ;

        return $response;
    }
}