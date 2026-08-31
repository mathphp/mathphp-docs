<?php

declare(strict_types=1);

/**
 * Check that local MathPHP checkouts and Composer locks describe one release.
 *
 * This is intentionally dependency-free and network-free. It is a release
 * operator check, not access or package-provisioning automation.
 *
 * Usage:
 *   php tools/check-ecosystem-locks.php \
 *     --core=/path/to/mathphp \
 *     --units=/path/to/mathphp-units \
 *     --visuals=/path/to/mathphp-visuals \
 *     --explaining=/path/to/mathphp-explaining
 */

/** @return array{ok:bool,checks:list<string>,errors:list<string>} */
function auditEcosystemLocks(array $paths, ?callable $headResolver = null): array
{
    $checks = [];
    $errors = [];
    $headResolver ??= static fn (string $path): string => gitHead($path);

    $heads = [];
    foreach (['core', 'units', 'visuals', 'explaining'] as $name) {
        $path = $paths[$name] ?? null;
        if (!is_string($path) || !is_dir($path)) {
            $errors[] = $name . ' checkout is missing: ' . (is_string($path) ? $path : '(not provided)');
            continue;
        }

        try {
            $heads[$name] = $headResolver($path);
            $checks[] = $name . ' checkout HEAD ' . $heads[$name];
        } catch (RuntimeException $error) {
            $errors[] = $name . ': ' . $error->getMessage();
        }
    }

    $lockReferences = [];
    foreach (['units', 'visuals', 'explaining'] as $name) {
        $path = $paths[$name] ?? '';
        $lockPath = rtrim((string) $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lockPath)) {
            $errors[] = $name . ' composer.lock is missing: ' . $lockPath;
            continue;
        }

        $lockReferences[$name] = [];
        foreach (['mathphp/mathphp', 'mathphp/mathphp-visuals'] as $package) {
            $reference = lockedReference($lockPath, $package);
            if ($reference !== null) {
                $lockReferences[$name][$package] = $reference;
            }
        }
    }

    $relations = [
        ['units', 'mathphp/mathphp', 'core', 'core'],
        ['visuals', 'mathphp/mathphp', 'core', 'core'],
        ['explaining', 'mathphp/mathphp', 'core', 'core'],
        ['explaining', 'mathphp/mathphp-visuals', 'visuals', 'Visuals'],
    ];
    foreach ($relations as [$lockOwner, $package, $headName, $label]) {
        $locked = $lockReferences[$lockOwner][$package] ?? null;
        $expected = $heads[$headName] ?? null;
        $description = $lockOwner . ' locks ' . $package . ' to ' . ($locked ?? '(missing)');
        if ($locked === null) {
            $errors[] = $description . '; expected ' . $label . ' HEAD ' . ($expected ?? '(missing)');
            continue;
        }
        if ($expected === null || !referencesMatch($locked, $expected)) {
            $errors[] = $description . '; expected ' . $label . ' HEAD ' . ($expected ?? '(missing)');
            continue;
        }
        $checks[] = $description . ' (matches ' . $label . ' HEAD)';
    }

    return ['ok' => $errors === [], 'checks' => $checks, 'errors' => $errors];
}

function gitHead(string $path): string
{
    $command = 'git -C ' . escapeshellarg($path) . ' rev-parse HEAD 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $head = trim(implode("\n", $output));
    if ($exitCode !== 0 || preg_match('/^[0-9a-f]{7,64}$/i', $head) !== 1) {
        throw new RuntimeException('cannot resolve a Git HEAD');
    }

    return strtolower($head);
}

function lockedReference(string $lockPath, string $package): ?string
{
    $contents = file_get_contents($lockPath);
    if ($contents === false) {
        throw new RuntimeException('cannot read ' . $lockPath);
    }
    try {
        $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('invalid JSON in ' . $lockPath . ': ' . $error->getMessage());
    }

    foreach (['packages', 'packages-dev'] as $section) {
        foreach (($lock[$section] ?? []) as $installed) {
            if (!is_array($installed) || ($installed['name'] ?? null) !== $package) {
                continue;
            }
            $source = is_array($installed['source'] ?? null) ? $installed['source'] : [];
            $dist = is_array($installed['dist'] ?? null) ? $installed['dist'] : [];
            $reference = $source['reference'] ?? $dist['reference'] ?? null;

            return is_string($reference) && preg_match('/^[0-9a-f]{7,64}$/i', $reference) === 1
                ? strtolower($reference)
                : null;
        }
    }

    return null;
}

function referencesMatch(string $actual, string $expected): bool
{
    $actual = strtolower(trim($actual));
    $expected = strtolower(trim($expected));

    return $actual === $expected
        || str_starts_with($expected, $actual)
        || str_starts_with($actual, $expected);
}

/** @return never */
function runCli(): never
{
    $options = getopt('', ['core:', 'units:', 'visuals:', 'explaining:']);
    $paths = [
        'core' => $options['core'] ?? null,
        'units' => $options['units'] ?? null,
        'visuals' => $options['visuals'] ?? null,
        'explaining' => $options['explaining'] ?? null,
    ];
    if (in_array(null, $paths, true)) {
        fwrite(STDERR, "Provide --core, --units, --visuals, and --explaining checkout paths.\n");
        exit(2);
    }

    $result = auditEcosystemLocks($paths);
    foreach ($result['checks'] as $check) {
        echo "ok - {$check}\n";
    }
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, "error - {$error}\n");
    }
    exit($result['ok'] ? 0 : 1);
}

if (basename(__FILE__) === basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    runCli();
}
