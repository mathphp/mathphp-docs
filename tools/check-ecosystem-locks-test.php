<?php

declare(strict_types=1);

require __DIR__ . '/check-ecosystem-locks.php';

function assertCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/mathphp-lock-check-' . bin2hex(random_bytes(5));
$paths = [];
foreach (['core', 'units', 'visuals', 'explaining'] as $name) {
    $paths[$name] = $root . '/' . $name;
    mkdir($paths[$name], 0777, true);
}

$coreHead = str_repeat('a', 40);
$visualsHead = str_repeat('b', 40);
$lock = static function (string $path, array $packages): void {
    file_put_contents($path . '/composer.lock', json_encode(['packages' => array_map(
        static fn (string $name, string $reference): array => [
            'name' => $name,
            'source' => ['reference' => $reference],
        ],
        array_keys($packages),
        array_values($packages),
    )], JSON_THROW_ON_ERROR));
};
$lock($paths['units'], ['mathphp/mathphp' => $coreHead]);
$lock($paths['visuals'], ['mathphp/mathphp' => $coreHead]);
$lock($paths['explaining'], [
    'mathphp/mathphp' => $coreHead,
    'mathphp/mathphp-visuals' => $visualsHead,
]);

$resolver = static function (string $path) use ($paths, $coreHead, $visualsHead): string {
    if ($path === $paths['core']) {
        return $coreHead;
    }
    if ($path === $paths['visuals']) {
        return $visualsHead;
    }

    return str_repeat('c', 40);
};
$result = auditEcosystemLocks($paths, $resolver);
assertCheck($result['ok'], 'matching lock references should pass');
assertCheck(count($result['checks']) === 8, 'all checkout and dependency checks should be reported');

$lock($paths['explaining'], [
    'mathphp/mathphp' => $coreHead,
    'mathphp/mathphp-visuals' => str_repeat('d', 40),
]);
$mismatch = auditEcosystemLocks($paths, $resolver);
assertCheck(!$mismatch['ok'], 'stale Visuals lock reference should fail');
assertCheck(str_contains(implode("\n", $mismatch['errors']), 'Visuals HEAD'), 'failure should name the expected Visuals HEAD');

echo "ecosystem lock checks passed\n";
