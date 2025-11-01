<?php

namespace PluginClassName\Support\Validation\Rules;

use DateTimeImmutable;
use Exception;
use Respect\Validation\Rules\Core\Simple;

if (!defined('ABSPATH')) {
	exit;
}

final class IsIsoDate extends Simple
{
    public function isValid(mixed $input): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $input)) {
            return false;
        }
    
        try {
            $d = new DateTimeImmutable($input);
        } catch (Exception $e) {
            return false;
        }
    
        return $d->format('Y-m-d\TH:i:s.v\Z') === $input;
    }
}
