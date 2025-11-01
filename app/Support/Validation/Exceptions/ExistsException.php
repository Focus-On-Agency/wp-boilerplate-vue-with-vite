<?php

namespace PluginClassName\Support\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

if (!defined('ABSPATH')) {
	exit;
}

final class ExistsException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'The {{name}} does not exist in the database.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'The {{name}} must not exist in the database.',
        ],
    ];
}