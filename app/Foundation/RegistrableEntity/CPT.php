<?php

namespace PluginClassName\Foundation\RegistrableEntity;

use PluginClassName\Foundation\RegistrableEntity;
use PluginClassName\Support\Logger;
use function current_user_can;
use function register_post_type;
use function is_wp_error;
use function taxonomy_exists;
use function register_taxonomy_for_object_type;
use function register_post_meta;
use const WP_DEBUG;

if (!defined('ABSPATH')) {
	exit;
}

class CPT extends RegistrableEntity
{
	protected string $slug;
	protected array $args = [];
	protected array $taxonomies = [];
	protected array $meta = [];

	public function __construct(string $slug)
	{
		$this->slug = $slug;
		$this->args = [
			'public' => true,
			'has_archive' => true,
			'show_in_rest' => true,
			'show_in_menu' => true,
			'supports' => ['title', 'editor', 'custom-fields'],
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

	public function title(string $title): static
	{
		if (empty(trim($title))) {
			throw new \InvalidArgumentException('Title cannot be empty');
		}
		$this->args['labels']['name'] = $title;
		$this->args['labels']['menu_name'] = $title;
		return $this;
	}

	public function singular_name(string $singularName): static
	{
		if (empty(trim($singularName))) {
			throw new \InvalidArgumentException('Singular name cannot be empty');
		}
		$this->args['labels']['singular_name'] = $singularName;
		$this->args['labels']['add_new'] = "Add New $singularName";
		$this->args['labels']['add_new_item'] = "Add New $singularName";
		$this->args['labels']['edit_item'] = "Edit $singularName";
		$this->args['labels']['new_item'] = "New $singularName";
		$this->args['labels']['view_item'] = "View $singularName";
		$this->args['labels']['search_items'] = "Search $singularName";
		return $this;
	}

	public function plural_name(string $pluralName): static
	{
		$this->args['labels']['all_items'] = "All $pluralName";
		$this->args['labels']['name'] = $pluralName;
		return $this;
	}

	public function supports(array $supports): static
	{
		if (empty($supports)) {
			throw new \InvalidArgumentException('Supports array cannot be empty');
		}
		$this->args['supports'] = $supports;
		return $this;
	}

	public function menu_icon(string $icon): static
	{
		$this->args['menu_icon'] = $icon;
		return $this;
	}

	public function show_in_menu(): static
	{
		$this->args['show_in_menu'] = true;
		return $this;
	}

	public function hide_in_menu(): static
	{
		$this->args['show_in_menu'] = false;
		return $this;
	}

	public function not_public(): static
	{
		$this->args['public'] = false;
		return $this;
	}

	public function setPubliclyQueryable(bool $value = true): static
	{
		$this->args['publicly_queryable'] = $value;
		return $this;
	}

	public function not_has_archive(): static
	{
		$this->args['has_archive'] = false;
		return $this;
	}

	public function add_category_support(array|string $taxonomies = []): static
	{
		$this->taxonomies = array_merge(
			$this->taxonomies,
			is_array($taxonomies) ? $taxonomies : [$taxonomies]
		);
	
		return $this;
	}

	public function add_meta(string $key, string $type = 'string', bool $single = true, bool $show_in_rest = true, ?callable $auth_callback = null): static
	{
		$this->meta[] = [
			'key' => $key,
			'args' => [
				'type'         => $type,
				'single'       => $single,
				'show_in_rest' => $show_in_rest,
				'auth_callback' => $auth_callback ?? fn() => current_user_can('edit_posts'),
			]
		];

		return $this;
	}

	public function register(): void
	{
		$result = register_post_type($this->slug, $this->args);

		if (is_wp_error($result)) {
			throw new \Exception("Failed to register post type '{$this->slug}': " . $result->get_error_message());
		}

		// Register taxonomies with error handling
		foreach ($this->taxonomies as $taxonomy) {
			if (!taxonomy_exists($taxonomy)) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("CPT: Taxonomy '{$taxonomy}' does not exist for post type '{$this->slug}'");
				}
				continue;
			}
			register_taxonomy_for_object_type($taxonomy, $this->slug);
		}

		// Register meta fields with validation
		foreach ($this->meta as $metaField) {
			if (empty($metaField['key'])) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("CPT: Empty meta key for post type '{$this->slug}'");
				}
				continue;
			}
			$res = register_post_meta($this->slug, $metaField['key'], $metaField['args']);
		}

	}
}