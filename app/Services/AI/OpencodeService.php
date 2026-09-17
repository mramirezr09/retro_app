<?php

namespace App\Services\AI;

class OpencodeService implements AiServiceInterface
{
    public function generate(string $systemPrompt, string $userMessage, array $config, array $attachments = []): array
    {
        $binary = $this->resolveBinary((string) ($config['binary'] ?? 'opencode'));
        if ($binary === null) {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'No se encontro el ejecutable de opencode. Configure la ruta en Ajustes.'];
        }

        $model = trim((string) ($config['modelo'] ?? ''));
        $message = trim($systemPrompt) . "\n\n" . trim($userMessage) . $this->attachmentsText($attachments);

        $arguments = ['run', $message, '--format', 'json'];
        if ($model !== '') {
            $arguments[] = '-m';
            $arguments[] = $model;
        }

        $command = $this->buildCommand($binary, $arguments);
        $env = $this->buildEnvironment($config);
        $workDir = $this->workDir();

        $result = $this->execute($command, $env, $workDir, 300);
        if ($result['timeout']) {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'El comando de opencode excedio el tiempo limite.'];
        }

        $stdout = trim($this->stripAnsi($result['stdout']));
        if ($result['exit_code'] !== 0) {
            $error = trim($this->stripAnsi($result['stderr'])) ?: 'opencode termino con codigo ' . $result['exit_code'];
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => $error];
        }

        $text = $this->extractText($stdout);
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'opencode no devolvio contenido.'];
        }

        return ['ok' => true, 'text' => $text, 'tokens' => $this->extractTokens($stdout), 'error' => null];
    }

    private function attachmentsText(array $attachments): string
    {
        $images = array_values(array_filter((array) ($attachments['imagenes'] ?? []), 'is_string'));
        $documents = array_values(array_filter((array) ($attachments['documentos'] ?? []), 'is_string'));

        if (empty($images) && empty($documents)) {
            return '';
        }

        $lines = [];
        if (!empty($images)) {
            $lines[] = 'Imagenes adjuntas:';
            foreach ($images as $url) {
                $lines[] = '- ' . $url;
            }
        }
        if (!empty($documents)) {
            $lines[] = 'Documentos adjuntos:';
            $fetcher = new AttachmentFetcher();
            $extractor = new DocumentTextExtractor();
            foreach ($documents as $url) {
                $lines[] = '- ' . $url;
                $mime = $fetcher->documentMime($url);
                if ($mime === null || $mime === 'application/pdf') {
                    continue;
                }
                $binary = $fetcher->binary($url, $mime);
                if ($binary === null) {
                    continue;
                }
                $text = $extractor->extract($binary, $fetcher->filename($url));
                if (trim($text) !== '') {
                    $lines[] = '  Contenido del documento "' . $fetcher->filename($url) . '":';
                    $lines[] = '  ' . str_replace("\n", "\n  ", $text);
                }
            }
        }

        return "\n\n" . implode("\n", $lines);
    }

    private function resolveBinary(string $configured): ?string
    {
        $configured = trim($configured);
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidate = $configured !== '' ? $configured : 'opencode';
        $probe = PHP_OS_FAMILY === 'Windows' ? 'where.exe' : 'which';
        $output = @shell_exec($probe . ' ' . escapeshellarg($candidate) . ' 2>&1');
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim($output))), fn ($line) => $line !== ''));
        if (empty($lines)) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['.exe', '.cmd', '.bat'] as $extension) {
                foreach ($lines as $line) {
                    if (strtolower(substr($line, -strlen($extension))) === $extension) {
                        if ($extension === '.cmd') {
                            $native = $this->nativeExecutableFor($line);
                            if ($native !== null) {
                                return $native;
                            }
                        }
                        return $line;
                    }
                }
            }
        }

        return $lines[0];
    }

    private function nativeExecutableFor(string $shim): ?string
    {
        $dir = dirname($shim);
        $candidate = $dir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'opencode-ai' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opencode.exe';
        return is_file($candidate) ? $candidate : null;
    }

    private function buildCommand(string $binary, array $arguments): array
    {
        return array_merge([$binary], $arguments);
    }

    private function buildEnvironment(array $config): array
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }

        $apiKey = (string) ($config['api_key'] ?? '');
        $apiKeyEnv = (string) ($config['api_key_env'] ?? '');
        if ($apiKey !== '') {
            if ($apiKeyEnv !== '') {
                $env[$apiKeyEnv] = $apiKey;
            }
            $env['OPENCODE_API_KEY'] = $apiKey;
        }

        if (PHP_OS_FAMILY === 'Windows' && getenv('SystemRoot') === false) {
            $env['SystemRoot'] = 'C:\\Windows';
        }

        return $env;
    }

    private function workDir(): string
    {
        $dir = storage_path('opencode');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function execute($command, array $env, string $cwd, int $timeout): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($process)) {
            return ['stdout' => '', 'stderr' => 'No se pudo iniciar el proceso de opencode', 'exit_code' => -1, 'timeout' => false];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();
        $timedOut = false;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1);
            if ($changed === false) {
                break;
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    continue;
                }
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            if ((time() - $start) > $timeout) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode, 'timeout' => $timedOut];
    }

    private function extractText(string $output): string
    {
        if ($output === '') {
            return '';
        }

        $lines = preg_split('/\r?\n/', $output);
        $jsonText = [];
        $plainLines = [];
        $sawJson = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $sawJson = true;
                $collected = [];
                $this->collectText($decoded, $collected);
                foreach ($collected as $piece) {
                    $jsonText[] = $piece;
                }
            } else {
                $plainLines[] = $line;
            }
        }

        if ($sawJson && !empty($jsonText)) {
            return trim(implode("\n", array_unique($jsonText)));
        }

        return trim(implode("\n", $plainLines)) !== '' ? trim(implode("\n", $plainLines)) : $output;
    }

    private function extractTokens(string $output): ?int
    {
        foreach (preg_split('/\r?\n/', $output) as $line) {
            $decoded = json_decode(trim((string) $line), true);
            if (!is_array($decoded)) {
                continue;
            }
            $tokens = $decoded['part']['tokens']['total'] ?? $decoded['tokens']['total'] ?? null;
            if (is_numeric($tokens)) {
                return (int) $tokens;
            }
        }
        return null;
    }

    private function collectText($node, array &$collected): void
    {
        if (!is_array($node)) {
            return;
        }
        if (isset($node['type'], $node['text']) && $node['type'] === 'text' && is_string($node['text'])) {
            $collected[] = $node['text'];
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectText($value, $collected);
            }
        }
    }

    private function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text);
    }
}
