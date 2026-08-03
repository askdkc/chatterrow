<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies must be installed before MarkItDown.\n");
    exit(1);
}

require_once $autoload;

/**
 * @param  list<string>  $command
 * @return array{exit_code: int|null, output: string, error: string}
 */
function runCommand(array $command, string $workingDirectory, int $timeout = 900): array
{
    $process = new Process($command, $workingDirectory);
    $process->setTimeout($timeout);
    $process->run();

    return [
        'exit_code' => $process->getExitCode(),
        'output' => $process->getOutput(),
        'error' => $process->getErrorOutput(),
    ];
}

/**
 * @param  list<string>  $command
 */
function commandForDisplay(array $command): string
{
    return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
}

/**
 * @param  list<string>  $command
 */
function runRequired(array $command, string $workingDirectory, int $timeout = 900): void
{
    $result = runCommand($command, $workingDirectory, $timeout);

    if ($result['exit_code'] === 0) {
        return;
    }

    $details = trim($result['error'] !== '' ? $result['error'] : $result['output']);
    if (strlen($details) > 2000) {
        $details = substr($details, 0, 2000).'...';
    }

    throw new RuntimeException(sprintf(
        'Command failed (%s, exit code %s): %s',
        commandForDisplay($command),
        (string) $result['exit_code'],
        $details,
    ));
}

/**
 * @return array{command: list<string>, version: string}
 */
function findPython(string $root): array
{
    $candidates = PHP_OS_FAMILY === 'Windows'
        ? [['py', '-3'], ['python'], ['python3']]
        : [['python3'], ['python']];

    foreach ($candidates as $candidate) {
        try {
            $result = runCommand([
                ...$candidate,
                '-c',
                'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")',
            ], $root, 20);
        } catch (Throwable) {
            continue;
        }

        if ($result['exit_code'] !== 0) {
            continue;
        }

        $version = trim($result['output']);
        if (preg_match('/^(\d+\.\d+)/', $version, $matches) !== 1) {
            continue;
        }

        if (version_compare($matches[1], '3.10', '>=')) {
            return ['command' => $candidate, 'version' => $matches[1]];
        }
    }

    throw new RuntimeException('Python 3.10 or newer is required to install MarkItDown.');
}

try {
    $requirements = $root.'/requirements-markitdown.txt';
    if (! is_file($requirements)) {
        throw new RuntimeException("Could not find {$requirements}.");
    }

    $python = findPython($root);
    $venvDirectory = $root.'/.markitdown/venv';
    $venvParent = dirname($venvDirectory);

    if (! is_dir($venvParent) && ! mkdir($venvParent, 0755, true) && ! is_dir($venvParent)) {
        throw new RuntimeException("Could not create {$venvParent}.");
    }

    $venvCommand = [...$python['command'], '-m', 'venv'];
    if (is_dir($venvDirectory)) {
        $venvCommand[] = '--upgrade';
    }
    $venvCommand[] = $venvDirectory;
    runRequired($venvCommand, $root, 120);

    $venvPython = PHP_OS_FAMILY === 'Windows'
        ? $venvDirectory.'\\Scripts\\python.exe'
        : $venvDirectory.'/bin/python';
    $markitdown = PHP_OS_FAMILY === 'Windows'
        ? $venvDirectory.'\\Scripts\\markitdown.exe'
        : $venvDirectory.'/bin/markitdown';

    if (! is_file($venvPython)) {
        throw new RuntimeException("The virtual environment Python was not created at {$venvPython}.");
    }

    runRequired([
        $venvPython,
        '-m',
        'pip',
        'install',
        '--disable-pip-version-check',
        '--upgrade',
        '--requirement',
        $requirements,
    ], $root);
    runRequired([$venvPython, '-m', 'pip', 'check'], $root, 120);

    if (! is_file($markitdown)) {
        throw new RuntimeException("MarkItDown CLI was not created at {$markitdown}.");
    }

    if (PHP_OS_FAMILY !== 'Windows' && ! is_executable($markitdown)) {
        chmod($markitdown, 0755);
    }

    if (PHP_OS_FAMILY !== 'Windows' && ! is_executable($markitdown)) {
        throw new RuntimeException("MarkItDown CLI is not executable: {$markitdown}.");
    }

    $version = runCommand([$markitdown, '--version'], $root, 30);
    $versionOutput = trim($version['output'].'\n'.$version['error']);
    if ($version['exit_code'] !== 0 || ! str_contains($versionOutput, '0.1.7')) {
        throw new RuntimeException(sprintf(
            'MarkItDown CLI version check failed. Expected 0.1.7, received: %s',
            $versionOutput === '' ? '(no output)' : $versionOutput,
        ));
    }

    printf("Python %s detected.\n", $python['version']);
    printf("MarkItDown 0.1.7 is ready at %s.\n", $markitdown);
} catch (Throwable $exception) {
    fwrite(STDERR, "MarkItDown installation failed: {$exception->getMessage()}\n");
    exit(1);
}
