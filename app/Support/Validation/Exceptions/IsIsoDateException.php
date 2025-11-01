<?php

namespace PluginClassName\Support\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

if (!defined('ABSPATH')) {
	exit;
}

final class IsIsoDateException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'The {{name}} is not a valid ISO date format.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'The {{name}} must not be a valid ISO date format.',
        ],
    ];
}