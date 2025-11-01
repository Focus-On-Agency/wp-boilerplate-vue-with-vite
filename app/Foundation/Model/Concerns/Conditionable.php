<?php

namespace PluginClassName\Foundation\Model\Concerns;

use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

trait Conditionable
{
    /**
     * Apply the callback if the given "value" is true.
     *
     * @template TWhenParameter
     * @template TWhenReturnType
     *
     * @param  mixed  $value
     * @param  callable($this, TWhenParameter): (TWhenReturnType)  $callback
     * @param  (callable($this, TWhenParameter): (TWhenReturnType)|null)  $default
     * @return $this|TWhenReturnType
     */
    public function when($value, callable $callback, ?callable $default = null)
	{
		if ($value) {
			return $callback($this) ?? $this;
		}

		if ($default) {
			return $default($this, $value) ?? $this;
		}

		return $this;
	}

    /**
     * Apply the callback if the given "value" is false.
     *
     * @template TUnlessParameter
     * @template TUnlessReturnType
     *
     * @param  mixed  $value
     * @param  callable($this, TUnlessParameter): (TUnlessReturnType)  $callback
     * @param  (callable($this, TUnlessParameter): (TUnlessReturnType)|null)  $default
     * @return $this|TUnlessReturnType
     */
    public function unless($value, callable $callback, ?callable $default = null)
    {
        return $this->when(! $value, $callback, $default);
    }
}
