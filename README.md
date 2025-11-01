# WordPress Plugin Boilerplate — Vue 3 + Tailwind + Vite

This repository is a **modern, production-ready WordPress plugin boilerplate** built with **Vue 3**, **TailwindCSS**, and **Vite**.
It provides a clean, modular PHP backend with a Laravel‑like structure, and a reactive SPA admin interface powered by Vue.

This boilerplate is inspired by and builds upon the excellent open‑source work of [**hasanuzzamanbe**](https://github.com/hasanuzzamanbe/wp-boilerplate-vue-with-vite).

---

## Requirements

- WordPress 6.0 or higher
- PHP 7.4+ (8.x recommended)
- Node.js ≥ 18
- Composer ≥ 2

---

## Quick Start

### 1) Clone and install dependencies

```bash
git clone https://github.com/Focus-On-Agency/wp-boilerplate-vue-with-vite.git
cd wp-boilerplate-vue-with-vite
composer install
npm install
```

### 2) Create your own plugin

Run the **Aladdin** helper to scaffold names, slugs and text domains:

```bash
node aladin
```

### 3) Start development

```bash
npm run dev
```
Runs Vite’s dev server (default: http://localhost:8880/) with HMR.

### 4) Build for production

```bash
npm run build
```
or

```bash
npm run production
```

Distribute only:

```
assets/
includes/
plugin-entry.php
```

---

## Project Structure

```
app/
  Foundation/
    Activator.php      # Handles plugin activation
    LoadAssets.php     # Handles script and style enqueueing
    Route.php          # REST routing system
    Vite.php           # Vite integration for dev/prod
  Http/
    Controllers/       # REST API controllers
  Models/              # Abstract model and ORM-like helpers
  Support/             # Utility classes and functions
routes/
  web.php              # API route definitions
resources/
  admin/               # Vue 3 SPA for WordPress admin
  js/                  # Entry points for Vite
  css/                 # TailwindCSS and stylesheets
assets/                # Compiled output (build/production)
```

---

## Routing (Laravel‑like)

Define routes in `routes/web.php`:

```php
<?php

use PluginClassName\Foundation\Route;
use PluginClassName\Http\Controllers;

Route::prefix('/api', function (Route $route) {
	$route->get('/test', function () {
		return rest_ensure_response(['message' => 'Test API works in prefix!']);
	}, null);
});

Route::get('/test/{id}', [Controllers\TestController::class, 'test'], null);

Route::get('/test-query', [Controllers\TestController::class, 'test'], null, [
	'id' => [
		'required' => true,
		'type' => 'integer',
		'validate_callback' => function ($param, $request, $key) {
			return is_numeric($param) && $param > 0;
		}
	]
]);

Route::get('/test-function', function () {
	return rest_ensure_response(['message' => 'Test API works!']);
}, null);
```

---

## Models and Database Migrations

Models extend the abstract base class `Model` and provide convenient access to the WordPress database or custom tables.

```php
class Post extends Model {
    protected $table = 'posts';
}
```

Migrations can be defined in `database/Migrations` and run during plugin activation.

---

## Vite Integration (Dynamic Asset Loading)

The class `app/Foundation/Vite.php` centralizes logic for dev/prod assets loading.

**Example usage**

```php
Vite::enqueueScript('my-plugin-admin', 'js/admin/main.js', [], PLUGIN_CONST_VERSION, true);
Vite::enqueueStyle('my-plugin-style', 'css/main.css', [], PLUGIN_CONST_VERSION, true);
```

**Manual enqueue (not recommended)**

```php
if (defined('PLUGIN_CONST_DEVELOPMENT') && PLUGIN_CONST_DEVELOPMENT === 'yes') {
    wp_enqueue_script('plugin-script', 'http://localhost:8880/resources/js/admin/main.js', [], PLUGIN_CONST_VERSION, true);
} else {
    wp_enqueue_script('plugin-script', PLUGIN_CONST_URL . 'assets/js/main.js', [], PLUGIN_CONST_VERSION, true);
}
```

---

## NPM & Composer Configuration

**Available npm scripts**

```json
{
  "dev": "npm i && node scripts/set_env_mode.js dev && vite",
  "watch": "npm run dev",
  "build": "node scripts/set_env_mode.js prod && vite build",
  "production": "npm run build && npm run optimize",
  "optimize": "node scripts/optimize.js",
  "zip": "node scripts/zip.js"
}
```

**Composer autoload configuration**

```json
{
  "psr-4": {
    "PluginClassName\\": "app/",
    "PluginClassName\\Database\\": "database/"
  },
  "files": [
    "app/helpers.php"
  ]
}
```

---

## Security Recommendations

- Sanitize and escape all inputs/outputs.
- Use `wp_nonce_field()` and `check_admin_referer()` for admin actions.
- Add `permission_callback` to REST routes.
- Escape HTML, attributes, and URLs using WordPress helpers.

---

## Localization

To generate the `.pot` file for translators:

```bash
wp i18n make-pot . languages/your-plugin.pot
```

---

## Build & Deployment

Create a distributable zip:

```bash
npm run zip
```

Manual method:

```bash
npm run build
composer install --no-dev
zip -r your-plugin.zip assets includes plugin-entry.php
```

---

## Credits

Special thanks to **hasanuzzamanbe** for the original idea and Vite integration approach:
- Repo: https://github.com/hasanuzzamanbe/wp-boilerplate-vue-with-vite
- Docs: https://wpminers.com/make-wordpress-plugin-using-vue-with-vite-build/

---

## License

GPLv2 or later — free to use, modify, and distribute under the same license.