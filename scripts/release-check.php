#!/usr/bin/env php
<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);

$pluginFile = $repoRoot . '/data-driven-optimizer.php';
$schemaFile = $repoRoot . '/includes/db-schema.php';
$changelogFile = $repoRoot . '/CHANGELOG.md';
$migrationNotesFile = $repoRoot . '/MIGRATION_NOTES.md';

$errors = [];

$pluginContents = @file_get_contents($pluginFile);
if ($pluginContents === false) {
    $errors[] = 'Kon data-driven-optimizer.php niet lezen.';
} else {
    preg_match('/^\s*\*\s*Version:\s*([0-9]+(?:\.[0-9]+)*)\s*$/mi', $pluginContents, $headerMatch);
    preg_match("/define\(\s*'DDO_PLUGIN_VERSION'\s*,\s*'([^']+)'\s*\)\s*;/", $pluginContents, $constantMatch);

    $headerVersion = $headerMatch[1] ?? null;
    $constantVersion = $constantMatch[1] ?? null;

    if ($headerVersion === null || $constantVersion === null) {
        $errors[] = 'Kon pluginversie in header of DDO_PLUGIN_VERSION niet bepalen.';
    } elseif ($headerVersion !== $constantVersion) {
        $errors[] = sprintf(
            'Plugin header versie (%s) en DDO_PLUGIN_VERSION (%s) lopen niet synchroon.',
            $headerVersion,
            $constantVersion
        );
    }
}

$schemaContents = @file_get_contents($schemaFile);
if ($schemaContents === false) {
    $errors[] = 'Kon includes/db-schema.php niet lezen.';
}

$schemaVersion = null;
if ($schemaContents !== false) {
    preg_match("/define\(\s*'DDO_SCHEMA_VERSION'\s*,\s*'([^']+)'\s*\)\s*;/", $schemaContents, $schemaVersionMatch);
    $schemaVersion = $schemaVersionMatch[1] ?? null;

    if ($schemaVersion === null) {
        $errors[] = 'Kon DDO_SCHEMA_VERSION niet bepalen in includes/db-schema.php.';
    }
}

$pluginVersion = null;
if ($pluginContents !== false) {
    preg_match("/define\(\s*'DDO_PLUGIN_VERSION'\s*,\s*'([^']+)'\s*\)\s*;/", $pluginContents, $pluginVersionMatch);
    $pluginVersion = $pluginVersionMatch[1] ?? null;
}

$baseRef = getenv('RELEASE_CHECK_DIFF_BASE');
if ($baseRef === false || trim($baseRef) === '') {
    $githubBaseRef = getenv('GITHUB_BASE_REF');
    if ($githubBaseRef !== false && trim($githubBaseRef) !== '') {
        $candidate = 'origin/' . trim($githubBaseRef);
        $verifyCommand = sprintf('git rev-parse --verify %s 2>/dev/null', escapeshellarg($candidate));
        $candidateExists = trim((string) shell_exec($verifyCommand));
        $baseRef = $candidateExists !== '' ? $candidate : 'HEAD~1';
    } else {
        $baseRef = 'HEAD~1';
    }
}

$changedFilesRaw = (string) shell_exec(
    sprintf('git diff --name-only %s...HEAD 2>/dev/null', escapeshellarg($baseRef))
);
$changedFiles = array_filter(array_map('trim', explode("\n", $changedFilesRaw)));

if (in_array('includes/db-schema.php', $changedFiles, true)) {
    $schemaDiff = (string) shell_exec(
        sprintf('git diff %s...HEAD -- includes/db-schema.php 2>/dev/null', escapeshellarg($baseRef))
    );

    if (strpos($schemaDiff, 'DDO_SCHEMA_VERSION') === false) {
        $errors[] = sprintf(
            'includes/db-schema.php is gewijzigd maar DDO_SCHEMA_VERSION is niet meegeüpdatet (vergelijking met %s).',
            $baseRef
        );
    }
}

if (!is_file($changelogFile)) {
    $errors[] = 'CHANGELOG.md ontbreekt.';
} else {
    $changelogContents = (string) file_get_contents($changelogFile);
    if ($pluginVersion !== null && strpos($changelogContents, $pluginVersion) === false) {
        $errors[] = sprintf('CHANGELOG.md bevat geen entry voor pluginversie %s.', $pluginVersion);
    }
}

if (!is_file($migrationNotesFile)) {
    $errors[] = 'MIGRATION_NOTES.md ontbreekt.';
} else {
    $migrationContents = (string) file_get_contents($migrationNotesFile);
    if ($schemaVersion !== null && strpos($migrationContents, $schemaVersion) === false) {
        $errors[] = sprintf('MIGRATION_NOTES.md bevat geen entry voor schema versie %s.', $schemaVersion);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Release checks gefaald:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Alle release checks geslaagd.\n");
