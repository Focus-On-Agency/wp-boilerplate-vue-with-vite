<?php

namespace PluginClassName\Support;

use Respect\Validation\Validator as RespectValidator;
use Respect\Validation\Exceptions\NestedValidationException;

if (!defined('ABSPATH')) {
	exit;
}

// Nuova eccezione custom per errori di parsing delle regole
class ValidationParseException extends \Exception {}

/**
 * Eccezione custom per errori di validazione
 * Integra con il flow di risposta Controller->response()
 */
class ValidationException extends \Exception
{
	protected array $errors;

	public function __construct(array $errors)
	{
		parent::__construct('Validation failed', 422);
		$this->errors = $errors;
	}

	/**
	 * Restituisce array errori
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Restituisce struttura per Controller->response()
	 */
	public function toResponse(): array
	{
		return ['errors' => $this->errors];
	}

	/**
	 * Restituisce direttamente una risposta WordPress REST API
	 * @return \WP_REST_Response
	 */
	public function toRestResponse(): \WP_REST_Response
	{
		return new \WP_REST_Response(['errors' => $this->errors], 422);
	}
}

/**
 * Classe Validator globale per validazione stile Laravel
 * Wrapper per Respect\Validation con parsing regole stringa e dot notation
 */
class Validator
{
	static public array $supportedRules = [
		'nullable',
		'required',
		'present',
		'sometimes',
		'bail',
		'email',
		'string',
		'integer',
		'numeric',
		'min',
		'max',
		'length',
		'between',
		'date',
		'after',
		'before',
		'boolean',
		'array',
		'in',
		'exists',
		'is_iso_date',
		'date_format',
	];

	static public $currentFieldRules;

	public static function make(array $data, array $rules, array $messages = [])
	{
		return self::validate($data, $rules, $messages);
	}

	/**
	 * Valida i dati secondo le regole specificate
	 * @param array $data Dati da validare
	 * @param array $rules Regole di validazione stile Laravel
	 * @param array $messages Messaggi di errore personalizzati (opzionale)
	 * @return array Dati validati se tutto ok, altrimenti chiama response() con errore 422
	 * @throws ValidationException
	 */
	public static function validate(array $data, array $rules, array $messages = [], bool $logErrors = false)
	{
		self::convertEmptyStringsToNull($data);

		$errors = [];
		$validated = [];

		$rules = self::expandRulesWithWildcards($data, $rules);

		foreach ($rules as $field => $ruleString) {
			$value = self::getValue($data, $field);
			$ruleParts = explode('|', $ruleString);

			self::$currentFieldRules = $ruleParts;

			$validators = [];
			$bail = in_array('bail', $ruleParts, true);
			$isRequired = in_array('required', $ruleParts, true);
			$isNullable = in_array('nullable', $ruleParts, true);
			$sometimes  = in_array('sometimes', $ruleParts, true);
			$present    = in_array('present', $ruleParts, true);

			$sentinel = new \stdClass();
			$existsInInput = self::dataGet($data, $field, $sentinel) !== $sentinel;

			if ($sometimes && !$existsInInput) {
				continue;
			}

			if ($present && !$existsInInput) {
				$errors[$field][] = "The {$field} field must be present.";
				if ($bail) continue;
			}

			if ($isRequired && ($value === null)) {
				$errors[$field][] = "The {$field} field is required.";
				if ($bail) continue;
			}

			if (!$isRequired && !$present && !$existsInInput) {
				continue;
			}

			if ($isNullable && $value === null) {
				self::setValue($validated, $field, null);
				continue;
			}

			foreach ($ruleParts as $rulePart) {
				if ($rulePart === 'bail' || $rulePart === 'sometimes' || $rulePart === 'present') {
					continue;
				}

				try {
					$validators[] = self::parseRule($rulePart, $field);
				} catch (ValidationParseException $e) {
					$errors[$field][] = $e->getMessage();
					if ($bail) {
						continue 2;
					}
				} catch (\Throwable $e) {
					self::abort500("Validation internal error while parsing rule '{$rulePart}': " . $e->getMessage());
				}
			}

			if (empty($validators)) {
				$normalizedValue = self::normalizeValue($value, $ruleParts, $field, $errors);
				self::setValue($validated, $field, $normalizedValue);
				continue;
			}

			foreach ($validators as $i => $validator) {
				try {
					$validator->assert($value);

					if (in_array('array', $ruleParts, true)) {
						$normalizedValue = self::normalizeValue($value, $ruleParts, $field, $errors);
					} else {
						$normalizedValue = self::normalizeValue($value, $ruleParts);
					}

					self::setValue($validated, $field, $normalizedValue);
				} catch (NestedValidationException $e) {
					$ruleName = self::$currentFieldRules[$i] ?? null;
			
					$customMsg = $ruleName ? self::resolveCustomMessage($field, $ruleName, $messages) : null;
			
					if ($customMsg) {
						$errors[$field][] = $customMsg;
					} else {
						$errors[$field][] = $e->getMessages();
					}
			
					if ($bail) {
						continue 2;
					}
				} catch (\Exception $e) {
					// Catch generico per tutte le altre eccezioni Respect o runtime
					$customMsg = $messages[$field] ?? null;
					if ($customMsg) {
						$errors[$field][] = $customMsg;
					}
					$errors[$field][] = 'Runtime validation error: ' . $e->getMessage();
					if ($bail) {
						continue;
					}
				}
			} 
		}

		if (!empty($errors) && $logErrors === false) {
			try {
				// Restituisce risposta REST 422 e termina il flusso
				$response = (new ValidationException($errors))->toRestResponse();
				// Output JSON e termina subito
				if (function_exists('wp_send_json')) {
					wp_send_json($response->get_data(), 422);
				} else {
					header('Content-Type: application/json', true, 422);
					echo json_encode($response->get_data());
					exit;
				}
			} catch (\Exception $e) {
				// Fallback: termina comunque
				exit;
			}
		}

		if (!empty($errors) && $logErrors === true) {
			Logger::error('Validation errors: ' . json_encode($errors));
			throw new \InvalidArgumentException('Validation failed, check logs for details.');
		}

		return $validated;
	}

	/**
	 * Recupera un valore da array o oggetto usando dot notation.
	 * @param array|object $target
	 * @param string $path
	 * @param mixed $default
	 * @return mixed
	 */
	protected static function dataGet($target, string $path, $default = null)
	{
		if ($path === null || $path === '') {
			return $target;
		}
		$segments = explode('.', $path);
		foreach ($segments as $segment) {
			if (is_array($target) && array_key_exists($segment, $target)) {
				$target = $target[$segment];
			} elseif (is_object($target) && isset($target->{$segment})) {
				$target = $target->{$segment};
			} else {
				return $default;
			}
		}
		return $target;
	}

	/**
	 * Imposta un valore in array o oggetto usando dot notation, creando strutture intermedie se necessario.
	 * @param array|object &$target
	 * @param string $path
	 * @param mixed $value
	 * @return void
	 */
	protected static function dataSet(&$target, string $path, $value): void
	{
		$segments = explode('.', $path);
		$current = &$target;
		foreach ($segments as $i => $segment) {
			$isLast = $i === array_key_last($segments);
			if ($isLast) {
				if (is_array($current)) {
					$current[$segment] = $value;
				} elseif (is_object($current)) {
					$current->{$segment} = $value;
				} else {
					$current = [$segment => $value];
				}
			} else {
				if (is_array($current)) {
					if (!isset($current[$segment]) || (!is_array($current[$segment]) && !is_object($current[$segment]))) {
						$current[$segment] = [];
					}
					$current = &$current[$segment];
				} elseif (is_object($current)) {
					if (!isset($current->{$segment}) || (!is_array($current->{$segment}) && !is_object($current->{$segment}))) {
						$current->{$segment} = [];
					}
					$current = &$current->{$segment};
				} else {
					$current = [];
					$current = &$current[$segment];
				}
			}
		}
	}

	protected static function hasWildcard(string $path): bool
	{
		return strpos($path, '*') !== false;
	}

	protected static function expandRulesWithWildcards(array $data, array $rules): array
	{
		$expanded = [];

		foreach ($rules as $field => $ruleString) {
			if (!self::hasWildcard($field)) {
				$expanded[$field] = $ruleString;
				continue;
			}

			$paths = self::resolveWildcardPaths($data, $field);

			// Se non troviamo istanze concrete, non aggiungiamo nulla.
			// La responsabilità di "resources => required|array" resta alla regola del parent.
			foreach ($paths as $concretePath) {
				$expanded[$concretePath] = $ruleString;
			}
		}

		return $expanded;
	}

	protected static function resolveWildcardPaths(array $data, string $pattern): array
	{
		$segments = explode('.', $pattern);

		$stack = [[ 'target' => $data, 'path' => [] ]];

		foreach ($segments as $seg) {
			$nextStack = [];

			foreach ($stack as $frame) {
				$currentTarget = $frame['target'];
				$currentPath   = $frame['path'];

				if ($seg === '*') {
					if (is_array($currentTarget)) {
						foreach ($currentTarget as $k => $v) {
							$nextStack[] = [
								'target' => $v,
								'path'   => array_merge($currentPath, [(string)$k]),
							];
						}
					}
					// se non è array, non espando nulla
				} else {
					if (is_array($currentTarget) && array_key_exists($seg, $currentTarget)) {
						$nextStack[] = [
							'target' => $currentTarget[$seg],
							'path'   => array_merge($currentPath, [$seg]),
						];
					} elseif (is_object($currentTarget) && isset($currentTarget->{$seg})) {
						$nextStack[] = [
							'target' => $currentTarget->{$seg},
							'path'   => array_merge($currentPath, [$seg]),
						];
					}
					// else: segmento non risolvibile → nessuna diramazione
				}
			}

			$stack = $nextStack;
			if (empty($stack)) {
				break;
			}
		}

		$results = [];
		foreach ($stack as $frame) {
			if (!empty($frame['path'])) {
				$results[] = implode('.', $frame['path']);
			}
		}

		return $results;
	}

	/**
	 * Estrae il valore da array multidimensionale usando dot notation
	 */
	protected static function getValue(array|object $data, string $field)
	{
		return self::dataGet($data, $field, null);
	}

	/**
	 * Imposta valore in array multidimensionale usando dot notation
	 */
	protected static function setValue(array|object &$data, string $field, $value): void
	{
		self::dataSet($data, $field, $value);
	}

	/**
	 * Normalizza il valore in base al tipo richiesto (string, integer, boolean, array)
	 * @param mixed $value
	 * @param array $ruleParts
	 * @param string|null $field
	 * @param array|null $errors
	 * @return mixed
	 */
	protected static function normalizeValue($value, array $ruleParts, ?string $field = null, ?array &$errors = null)
	{
		// Per tipi semplici, ignora field/errors
		if (in_array('string', $ruleParts, true)) {
			return (string)$value;
		}
		if (in_array('integer', $ruleParts, true)) {
			return (int)$value;
		}
		if (in_array('boolean', $ruleParts, true)) {
			// Usa filter_var per conversione sicura
			return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		}
		if (in_array('array', $ruleParts, true)) {
			// Miglioria: validazione tipo array senza coercizione brute
			if (is_array($value)) {
				return $value;
			}
			// Tenta json_decode sicuro se stringa JSON
			if (is_string($value)) {
				$decoded = json_decode($value, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					return $decoded;
				} else {
					// Logging opzionale per JSON malformato
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('[Validator] Malformed JSON for array field ' . ($field ?? 'unknown') . ': ' . $value);
					}
				}
			}
			// Se non è array valido, aggiungi errore e ritorna null
			if ($errors !== null && $field !== null) {
				$errors[$field][] = 'The field must be a valid array.';
			}
			return null;
		}
		// Estendibile facilmente per altri tipi
		return $value;
	}

	/**
	 * Parsing regola singola stile Laravel in Respect\Validation
	 * Ora accetta anche il nome del campo per errori più chiari
	 */
	protected static function parseRule(string $rule, ?string $field = null)
	{
		$ruleName = explode(':', $rule, 2)[0];
		if(!in_array($ruleName, self::$supportedRules, true)) {
			throw new ValidationParseException("Rule not supported: $rule");
		}

		// Gestione regole con parametri
		if (str_starts_with($rule, 'min:')) {
			$min = (int) substr($rule, 4);
			if (in_array('string', self::$currentFieldRules, true)) {
				return RespectValidator::length($min, null);
			}

			return RespectValidator::min((int)$min);
		}
		if (str_starts_with($rule, 'max:')) {
			$max = substr($rule, 4);
			if (in_array('string', self::$currentFieldRules, true)) {
				return RespectValidator::length(null, $max);
			}

			return RespectValidator::max((int)$max);
		}
		if (str_starts_with($rule, 'length:')) {
			$args = explode(',', substr($rule, 7));
			return RespectValidator::length((int)$args[0], (int)($args[1] ?? null));
		}
		if (str_starts_with($rule, 'in:')) {
			$args = explode(',', substr($rule, 3));
			return RespectValidator::in($args);
		}
		if (str_starts_with($rule, 'between:')) {
			[$a, $b] = array_map('intval', explode(',', substr($rule, 8)));
			if (in_array('string', self::$currentFieldRules, true)) {
				return RespectValidator::length($a, $b);
			}
			return RespectValidator::between($a, $b);
		}
		if (str_starts_with($rule, 'date_format:')) {
			$format = substr($rule, 12);
			return RespectValidator::date($format);
		}
		if(str_starts_with($rule, 'is_iso_date')) {
			return RespectValidator::isIsoDate();
		}
		if (str_starts_with($rule, 'after:')) {
			$date = substr($rule, 6);
			return RespectValidator::date()->min($date);
		}
		if (str_starts_with($rule, 'before:')) {
			$date = substr($rule, 7);
			return RespectValidator::date()->max($date);
		}
		if (str_starts_with($rule, 'exists:')) {
			$params = explode(',', substr($rule, 7));
			$table = $params[0] ?? null;
			$column = $params[1] ?? null;

			return RespectValidator::exists($table, $column);
		}

		switch ($rule) {
			case 'required':
				return RespectValidator::notOptional()->notBlank();
			case 'email':
				return RespectValidator::email();
			case 'string':
				return RespectValidator::stringType();
			case 'integer':
				return RespectValidator::intType();
			case 'numeric':
				return RespectValidator::numericVal();
			case 'boolean':
				return RespectValidator::anyOf(
					RespectValidator::boolType(),
					RespectValidator::in(['true','false','1','0','on','off'])
				);
			case 'array':
				return RespectValidator::arrayType();
			case 'date':
				return RespectValidator::date();
			case 'nullable':
				return RespectValidator::alwaysValid();
			default:
				$fieldMsg = $field ? " per il campo '$field'" : '';
				throw new ValidationParseException("Rule not supported: $rule$fieldMsg");
		}
	}

	protected static function abort500(string $message): void
	{
		if (function_exists('wp_send_json')) {
			wp_send_json([
				'message' => $message,
			], 500);
		} else {
			header('Content-Type: application/json', true, 500);
			echo json_encode([
				'message' => $message,
			]);
			exit;
		}
	}

	/**
	 * Risolve un messaggio custom per un campo e regola, seguendo la priorità richiesta.
	 * Usa dataGet per la ricerca progressiva.
	 * @param string $field
	 * @param string $ruleName
	 * @param array $messages
	 * @return string|null
	 */
	protected static function resolveCustomMessage(string $field, string $ruleName, array $messages): ?string
	{
		// 1. Cerca match completo "field.rule"
		if (isset($messages["{$field}.{$ruleName}"])) {
			return $messages["{$field}.{$ruleName}"];
		}

		// 2. Cerca match "field" generico
		if (isset($messages[$field])) {
			return $messages[$field];
		}

		// 3. Cerca riducendo campi nested
		while (str_contains($field, '.')) {
			$field = substr($field, 0, strrpos($field, '.'));

			// Prova "field.rule"
			if (isset($messages["{$field}.{$ruleName}"])) {
				return $messages["{$field}.{$ruleName}"];
			}

			// Prova "field" generico
			if (isset($messages[$field])) {
				return $messages[$field];
			}
		}

		$wildcardCandidates = self::wildcardizeField($field);
		foreach ($wildcardCandidates as $wc) {
			if (isset($messages["{$wc}.{$ruleName}"])) {
				return $messages["{$wc}.{$ruleName}"];
			}

			if (isset($messages[$wc])) {
				return $messages[$wc];
			}
		}

		// 4. Nessun match trovato
		return null;
	}

	protected static function wildcardizeField(string $field): array
	{
		$segments = explode('.', $field);
		$indexes = [];
		foreach ($segments as $i => $seg) {
			if (ctype_digit($seg)) {
				$indexes[] = $i;
			}
		}

		if (empty($indexes)) {
			return [];
		}

		$variants = [];

		// genera tutti i sottoinsiemi di indici da wildcardizzare (dal più ampio al più specifico)
		$n = count($indexes);

		// bitmask da 1..(2^n - 1)
		for ($mask = (1 << $n) - 1; $mask >= 1; $mask--) {
			$copy = $segments;
			for ($b = 0; $b < $n; $b++) {
				if ($mask & (1 << $b)) {
					$copy[$indexes[$b]] = '*';
				}
			}
			$variants[] = implode('.', $copy);
		}

		return array_values(array_unique($variants));
	}

	/**
	 * Replica il middleware di Laravel: converte le stringhe vuote in null (ricorsivo)
	 */
	private static function convertEmptyStringsToNull(&$data): void
	{
		if (is_array($data)) {
			foreach ($data as &$v) {
				self::convertEmptyStringsToNull($v);
			}
			unset($v);
			return;
		}
		if ($data === '') {
			$data = null;
		}
	}

}