<?php

declare(strict_types=1);

/** Check relative Markdown links without requiring Composer or network access. */
$root = dirname(__DIR__);
$errors = [];
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo instanceof SplFileInfo && $fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'md') {
        $files[] = $fileInfo->getPathname();
    }
}

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        $errors[] = 'Unable to read ' . $file;
        continue;
    }

    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $contents, $matches);
    foreach ($matches[1] ?? [] as $target) {
        $target = trim($target);
        if ($target === '' || str_starts_with($target, '#') || preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', $target) === 1 || str_starts_with($target, 'mailto:')) {
            continue;
        }

        $path = (string) (explode('#', $target, 2)[0]);
        if ($path === '') {
            continue;
        }

        $resolved = realpath(dirname($file) . '/' . $path);
        if ($resolved === false || !is_file($resolved)) {
            $relativeFile = ltrim(str_replace($root . '/', '', $file), '/');
            $errors[] = $relativeFile . ' -> ' . $target;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Broken local Markdown links:\n" . implode("\n", $errors) . "\n");
    exit(1);
}

echo "Markdown local-link check passed.\n";
