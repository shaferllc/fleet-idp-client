<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use RuntimeException;

/**
 * Merges KEY=value pairs into a .env file (creates the file if missing).
 * Skips keys whose value is null or empty string so existing secrets are not erased.
 */
class EnvFileWriter
{
    /**
     * @param  array<string, string|null>  $keyToValue
     */
    public static function mergeIntoFile(string $absolutePath, array $keyToValue): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            throw new RuntimeException('Directory does not exist: '.$dir);
        }

        $raw = is_readable($absolutePath) ? (string) file_get_contents($absolutePath) : '';
        $lines = $raw === '' ? [] : preg_split('/\r\n|\r|\n/', $raw);

        $pending = [];
        foreach ($keyToValue as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            $pending[$key] = self::formatValue($value);
        }

        if ($pending === []) {
            return;
        }

        $written = [];
        $out = [];

        foreach ($lines as $line) {
            $matched = false;
            foreach ($pending as $key => $escaped) {
                if (preg_match('/^'.preg_quote($key, '/').'=/', $line)) {
                    $out[] = $key.'='.$escaped;
                    $written[$key] = true;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $out[] = $line;
            }
        }

        $commentAdded = false;
        foreach ($pending as $key => $escaped) {
            if (isset($written[$key])) {
                continue;
            }
            if (! $commentAdded) {
                if ($out !== [] && end($out) !== '') {
                    $out[] = '';
                }
                $out[] = '# fleet/idp-client (fleet:idp:configure)';
                $commentAdded = true;
            }
            $out[] = $key.'='.$escaped;
        }

        $content = implode("\n", $out);
        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        if (file_put_contents($absolutePath, $content) === false) {
            throw new RuntimeException('Could not write env file: '.$absolutePath);
        }
    }

    private static function formatValue(string $value): string
    {
        if (preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"'.addcslashes($value, "\\\"\r\n").'"';
        }

        return $value;
    }
}
