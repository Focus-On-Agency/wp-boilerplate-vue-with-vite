<?php

namespace PluginClassName\Foundation\Console;

if (!defined('ABSPATH')) { exit; }

/**
 * WP-CLI: wp fson make:job <Name>
 *
 * Opzioni:
 *  --namespace=<Ns\Path>   Namespace della classe (default: PluginClassName\Http\Jobs)
 *  --action=<wp_action>    Nome action/hook WordPress (default: rrt_fson_job_<snake_case(name)>)
 *  --queue=<group>         Nome coda/gruppo (default: default)
 *  --max-attempts=<int>    Tentativi massimi (default: 5)
 *  --force                 Sovrascrive se esiste già
 *
 * Esempi:
 *  wp fson make:job SendEmail
 *  wp fson make:job ProcessBooking --queue=high --max-attempts=7
 *  wp fson make:job ExportOrders --namespace="PluginClassName\\Background\\Jobs" --action=my_custom_action
 */
class MakeJob
{
    public function __invoke(array $args, array $assoc_args): void
    {
        [$name] = $args + [null];
        if (!$name) {
            \WP_CLI::error('Provide a job name. E.g. SendEmail');
        }

        $class = $this->studly($name);

        // Root plugin
        $rootDir = defined('PluginClassName_DIR')
            ? rtrim(PluginClassName_DIR, '/').'/'
            : plugin_dir_path(__FILE__) . '../../..' . '/'; // fallback

        // Stubs dir
        $stubsDir = $rootDir . 'stubs/jobs/';
        $stubFile = $stubsDir . 'job.php.stub';
        if (!file_exists($stubFile)) {
            \WP_CLI::error("Stub not found: {$stubFile}");
        }

        // Namespace di default
        $namespace = $assoc_args['namespace'] ?? 'PluginClassName\\Http\\Jobs';

        // Defaults coerenti con BaseJob/Queue
        $defaultAction = 'rrt_fson_job_' . $this->snake($class);
        $action        = $assoc_args['action'] ?? $defaultAction;
        $queue         = $assoc_args['queue']  ?? 'default';
        $maxAttempts   = isset($assoc_args['max-attempts']) ? (int)$assoc_args['max-attempts'] : 5;
        $force         = isset($assoc_args['force']);

        // Calcola cartella di destinazione a partire dal namespace
        [$jobsDir, $target] = $this->resolvePaths($rootDir, $namespace, $class);
        if (!is_dir($jobsDir) && !wp_mkdir_p($jobsDir)) {
            \WP_CLI::error("Cannot create jobs dir: {$jobsDir}");
        }

        if (file_exists($target) && !$force) {
            \WP_CLI::error("File already exists: {$target}. Use --force to overwrite.");
        }

        $stub = file_get_contents($stubFile);
        $replaced = strtr($stub, [
            '{{NAMESPACE}}'    => $namespace,
            '{{CLASS}}'        => $class,
            '{{ACTION}}'       => $action,
            '{{QUEUE}}'        => $queue,
            '{{MAX_ATTEMPTS}}' => (string)$maxAttempts,
        ]);

        if (file_put_contents($target, $replaced) === false) {
            \WP_CLI::error("Failed writing file: {$target}");
        }

        \WP_CLI::success("Job created: {$target}");
        \WP_CLI::log("Class: {$namespace}\\{$class}");
        \WP_CLI::log("Remember to call ::register() at bootstrap time, e.g.: \\{$namespace}\\{$class}::register();");
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    private function snake(string $value): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
        return $snake ?: strtolower($value);
    }

    /**
     * Converte il namespace in path sotto app/ se inizia con il vendor root,
     * altrimenti salva sotto app/Jobs/ generico.
     */
    private function resolvePaths(string $rootDir, string $namespace, string $class): array
    {
        $baseNs = 'PluginClassName\\';
        if (strpos($namespace, $baseNs) === 0) {
            $relative = substr($namespace, strlen($baseNs)); // es: Http\Jobs
            $relativePath = str_replace('\\', '/', $relative); // es: Http/Jobs
            $jobsDir = $rootDir . 'app/' . trim($relativePath, '/') . '/';
        } else {
            // fallback neutro
            $jobsDir = $rootDir . 'app/Jobs/';
        }

        $target = $jobsDir . $class . '.php';
        return [$jobsDir, $target];
        }
}