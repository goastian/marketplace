<?php

namespace App\Services;

final class InputSanitizer
{
    /**
     * Patterns that suggest prompt injection attempts.
     */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?previous\s+instructions/i',
        '/ignore\s+(all\s+)?above/i',
        '/disregard\s+(all\s+)?previous/i',
        '/\bsystem\s*:\s*/i',
        '/\bassistant\s*:\s*/i',
        '/\b(jailbreak|DAN|do\s+anything\s+now)\b/i',
        '/you\s+are\s+now\s+(a|an)\s+/i',
        '/pretend\s+you\s+are/i',
        '/act\s+as\s+(a|an)\s+/i',
        '/new\s+instructions?\s*:/i',
        '/override\s+(all\s+)?rules/i',
        '/forget\s+(all\s+)?(your\s+)?instructions/i',
        '/\{\{.*\}\}/s',
        '/<\|.*\|>/s',
    ];

    /**
     * Sanitize a text field: strip HTML, check for injection patterns.
     *
     * @throws \App\Services\InputSanitizationException
     */
    public function sanitize(string $value, int $maxLength = 5000): string
    {
        $value = strip_tags($value);
        $value = trim($value);

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        $this->detectInjection($value);

        return $value;
    }

    /**
     * Check a value for prompt injection patterns.
     *
     * @throws \App\Services\InputSanitizationException
     */
    public function detectInjection(string $value): void
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new InputSanitizationException('Input contains suspicious content and was rejected.');
            }
        }
    }

    /**
     * Sanitize an array of fields using a schema of field => maxLength.
     *
     * @param  array<string, mixed> $data
     * @param  array<string, int>   $schema  field => maxLength
     * @return array<string, mixed>
     * @throws \App\Services\InputSanitizationException
     */
    public function sanitizeFields(array $data, array $schema): array
    {
        foreach ($schema as $field => $maxLength) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->sanitize($data[$field], $maxLength);
            }
        }

        return $data;
    }
}
