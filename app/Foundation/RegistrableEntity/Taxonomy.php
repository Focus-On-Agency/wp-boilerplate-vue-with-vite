<?php

namespace PluginClassName\Foundation\RegistrableEntity;

use PluginClassName\Foundation\RegistrableEntity;
use function register_taxonomy;
use function is_wp_error;

if (!defined('ABSPATH')) {
	exit;
}

class Taxonomy extends RegistrableEntity
{
	protected string $slug;
	protected array $postTypes = [];
	protected array $args = [];

	public function __construct(string $slug)
	{
		if (empty(trim($slug))) {
			throw new \InvalidArgumentException('Taxonomy slug cannot be empty');
		}
		
		if (strlen($slug) > 32) {
			throw new \InvalidArgumentException('Taxonomy slug cannot exceed 32 characters');
		}
		
		$this->slug = $slug;
		$this->args = [
			'public' => true,
			'hierarchical' => true,
			'labels' => [],
		];
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function toArray(): array
	{
		return $this->args;
	}

	public function for(array|string $postTypes): static
	{
		$postTypesArray = (array) $postTypes;
		if (empty($postTypesArray)) {
			throw new \InvalidArgumentException('Post types array cannot be empty');
		}
		$this->postTypes = $postTypesArray;
		return $this;
	}

	public function label(string $key, string $value): static
	{
		$this->args['labels'][$key] = $value;
		return $this;
	}

	public function not_public(): static
	{
		$this->args['public'] = false;
		return $this;
	}

	public function hierarchical(bool $isHierarchical = true): static
	{
		$this->args['hierarchical'] = $isHierarchical;
		return $this;
	}

	public function register(): void
	{
		$result = register_taxonomy($this->slug, $this->postTypes, $this->args);
		
		if (is_wp_error($result)) {
			throw new \Exception("Failed to register taxonomy '{$this->slug}': " . $result->get_error_message());
		}
	}
}