<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use function array_key_exists;
use function count;
use function explode;
use function filter_var;
use function hash_equals;
use function in_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Declarative field validation.
 *
 * Rules run per field: presence and type are checked before any length or
 * comparison operation, so malformed input (arrays, missing keys) produces a
 * 422-worthy error instead of a fatal error. Supported rules: required, string,
 * integer, email, min, max, unique, exists, confirmed.
 *
 * `unique`/`exists` never resolve a model on their own: they require an injected
 * lookup callable `fn(table, column, value, ignoreId): bool`, typically backed by
 * a repository. Without it the rule reports an explicit error instead of failing.
 */
final class BaseValidator
{
    private const KIND_LENGTH = 'length';

    private const KIND_NUMERIC = 'numeric';

    /**
     * @param array<string, mixed>                            $data
     * @param array<string, string>                           $rules
     * @param callable(string, string, mixed, ?int): bool|null $lookup
     *
     * @return array<string, list<string>>
     */
    public static function make(array $data, array $rules, ?callable $lookup = null): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $field = (string) $field;
            $fieldRules = self::splitRules((string) $ruleSet);
            $kind = in_array('integer', $fieldRules, true) ? self::KIND_NUMERIC : self::KIND_LENGTH;

            foreach ($fieldRules as $rule) {
                $error = self::validate($field, $data, $rule, $lookup, $kind);

                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>                            $data
     * @param callable(string, string, mixed, ?int): bool|null $lookup
     */
    public static function validate(
        string $field,
        array $data,
        string $rule,
        ?callable $lookup = null,
        string $kind = self::KIND_LENGTH,
    ): ?string {
        [$name, $parameters] = self::splitRule($rule);
        $value = $data[$field] ?? null;

        if ($name === 'required') {
            return $value === null || $value === '' || $value === [] ? sprintf('The %s field is required.', $field) : null;
        }

        if (!array_key_exists($field, $data) || $value === null) {
            return null;
        }

        return match ($name) {
            'string' => self::validateString($field, $value),
            'integer' => self::validateInteger($field, $value),
            'email' => self::validateString($field, $value) ?? self::validateEmail($field, $value),
            'min' => self::validateLength($field, $value, $parameters, $kind, true),
            'max' => self::validateLength($field, $value, $parameters, $kind, false),
            'unique' => self::validateLookup($field, $value, $parameters, $lookup, false),
            'exists' => self::validateLookup($field, $value, $parameters, $lookup, true),
            'confirmed' => self::validateConfirmed($field, $value, $data),
            default => sprintf('The %s field uses the unsupported rule %s.', $field, $name),
        };
    }

    /**
     * @return list<string>
     */
    private static function splitRules(string $ruleSet): array
    {
        $rules = [];

        foreach (explode('|', $ruleSet) as $rule) {
            $rule = trim($rule);

            if ($rule !== '') {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function splitRule(string $rule): array
    {
        $parts = explode(':', trim($rule), 2);
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        return [$parts[0], $parameters];
    }

    private static function validateString(string $field, mixed $value): ?string
    {
        if (is_string($value)) {
            return null;
        }

        return sprintf('The %s field must be a string.', $field);
    }

    private static function validateInteger(string $field, mixed $value): ?string
    {
        if (is_int($value)) {
            return null;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return null;
        }

        return sprintf('The %s field must be an integer.', $field);
    }

    private static function validateEmail(string $field, mixed $value): ?string
    {
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return null;
        }

        return sprintf('The %s field must be a valid email address.', $field);
    }

    /**
     * @param list<string> $parameters
     */
    private static function validateLength(
        string $field,
        mixed $value,
        array $parameters,
        string $kind,
        bool $isMinimum,
    ): ?string {
        $limit = self::limitOf($parameters);

        if ($limit === null) {
            return sprintf('The %s field uses the %s rule without a numeric limit.', $field, $isMinimum ? 'min' : 'max');
        }

        return self::validateBoundary($field, $value, $limit, $kind, $isMinimum);
    }

    /**
     * @param list<string> $parameters
     */
    private static function limitOf(array $parameters): ?int
    {
        $limit = $parameters[0] ?? '';

        if ($limit === '' || preg_match('/^\d+$/', $limit) !== 1) {
            return null;
        }

        return (int) $limit;
    }

    private static function validateBoundary(
        string $field,
        mixed $value,
        int $limit,
        string $kind,
        bool $isMinimum,
    ): ?string {
        $isNumeric = $kind === self::KIND_NUMERIC;
        $typeError = $isNumeric ? self::validateInteger($field, $value) : self::validateString($field, $value);

        if ($typeError !== null) {
            return $typeError;
        }

        // Type checked above: integers compare by value, strings by character count.
        $actual = $isNumeric ? (int) trim((string) $value) : mb_strlen((string) $value, 'UTF-8');
        $satisfied = $isMinimum ? $actual >= $limit : $actual <= $limit;
        $boundary = $isMinimum ? "at least {$limit}" : "at most {$limit}";
        $unit = $isNumeric ? '' : ' characters';

        return $satisfied ? null : sprintf('The %s field must be %s%s.', $field, $boundary, $unit);
    }

    /**
     * @param list<string>                                     $parameters
     * @param callable(string, string, mixed, ?int): bool|null $lookup
     */
    private static function validateLookup(
        string $field,
        mixed $value,
        array $parameters,
        ?callable $lookup,
        bool $mustExist,
    ): ?string {
        $typeError = self::validateString($field, $value);

        if ($typeError !== null) {
            return $typeError;
        }

        $setupError = self::lookupSetupError($field, $parameters, $lookup, $mustExist);

        if ($setupError !== null) {
            return $setupError;
        }

        return self::lookupOutcome($field, $value, $parameters, $lookup, $mustExist);
    }

    /**
     * @param list<string>                                     $parameters
     * @param callable(string, string, mixed, ?int): bool|null $lookup
     */
    private static function lookupSetupError(string $field, array $parameters, ?callable $lookup, bool $mustExist): ?string
    {
        $name = $mustExist ? 'exists' : 'unique';

        if (count($parameters) < 2 || trim($parameters[0]) === '' || trim($parameters[1]) === '') {
            return sprintf('The %s field uses the %s rule without a table and a column.', $field, $name);
        }

        if ($lookup === null) {
            return sprintf('The %s field uses the %s rule without a repository to check against.', $field, $name);
        }

        return null;
    }

    /**
     * @param list<string>                                     $parameters
     * @param callable(string, string, mixed, ?int): bool|null $lookup
     */
    private static function lookupOutcome(
        string $field,
        mixed $value,
        array $parameters,
        ?callable $lookup,
        bool $mustExist,
    ): ?string {
        $name = $mustExist ? 'exists' : 'unique';
        $ignoreId = isset($parameters[2]) && preg_match('/^\d+$/', $parameters[2]) === 1 ? (int) $parameters[2] : null;

        try {
            $found = (bool) $lookup(trim($parameters[0]), trim($parameters[1]), $value, $ignoreId);
        } catch (InvalidArgumentException) {
            return sprintf('The %s field uses the %s rule against an unsupported table or column.', $field, $name);
        }

        return match (true) {
            $mustExist && !$found => sprintf('The selected %s is invalid.', $field),
            !$mustExist && $found => sprintf('The %s has already been taken.', $field),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function validateConfirmed(string $field, mixed $value, array $data): ?string
    {
        $confirmation = $data["{$field}_confirmation"] ?? null;

        if (is_string($value) && is_string($confirmation) && hash_equals($value, $confirmation)) {
            return null;
        }

        return sprintf('The %s confirmation does not match.', $field);
    }
}
