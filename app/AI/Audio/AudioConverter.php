<?php

namespace App\AI\Audio;

use JsonException;
use Symfony\Component\Process\Process;

class AudioConverter
{
    public function __construct(
        private readonly string $ffmpegBinaryPath,
        private readonly string $ffprobeBinaryPath,
        private readonly int $timeout = 120,
        private readonly bool $overwrite = true,
        private readonly ?string $tempDirectory = null,
    ) {}

    public function convert(string $inputPath, string $targetFormat, ?string $outputPath = null): string
    {
        $targetFormat = strtolower(trim($targetFormat));

        $formatOptions = $this->formatOptions($targetFormat);
        $outputPath ??= $this->temporaryOutputPath($inputPath, $targetFormat);

        $this->assertReadableInput($inputPath);
        $this->ensureEnvironmentReady();

        $command = array_values(array_filter([
            $this->ffmpegBinaryPath,
            $this->overwrite ? '-y' : '-n',
            '-i',
            $inputPath,
            ...$formatOptions,
            $outputPath,
        ], static fn ($argument) => $argument !== ''));

        $this->runProcess(
            $command,
            "FFmpeg failed to convert [{$inputPath}] to [{$targetFormat}].",
        );

        if (! is_file($outputPath) || ! is_readable($outputPath)) {
            throw new AudioConversionException(
                "FFmpeg reported success but the output file [{$outputPath}] was not created."
            );
        }

        return $outputPath;
    }

    public function convertOggToMp3(string $inputPath, ?string $outputPath = null): string
    {
        return $this->convert($inputPath, 'mp3', $outputPath);
    }

    public function convertOggToWav(string $inputPath, ?string $outputPath = null): string
    {
        return $this->convert($inputPath, 'wav', $outputPath);
    }

    public function convertMp3ToOgg(string $inputPath, ?string $outputPath = null): string
    {
        return $this->convert($inputPath, 'ogg', $outputPath);
    }

    public function convertAnyToWav(string $inputPath, ?string $outputPath = null): string
    {
        return $this->convert($inputPath, 'wav', $outputPath);
    }

    public function getMetadata(string $inputPath): array
    {
        $this->assertReadableInput($inputPath);
        $this->ensureEnvironmentReady();

        $result = $this->runProcess(
            [
                $this->ffprobeBinaryPath,
                '-v',
                'error',
                '-print_format',
                'json',
                '-show_format',
                '-show_streams',
                $inputPath,
            ],
            "FFprobe failed to inspect audio file [{$inputPath}].",
        );

        try {
            $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AudioConversionException(
                "FFprobe returned invalid JSON metadata for [{$inputPath}].",
                stdout: $result['stdout'],
                stderr: $result['stderr'],
                previous: $e,
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function verifyEnvironment(): array
    {
        $this->ensureEnvironmentReady();

        return [
            'ffmpeg_path' => $this->ffmpegBinaryPath,
            'ffprobe_path' => $this->ffprobeBinaryPath,
            'ffmpeg_version' => $this->binaryVersion($this->ffmpegBinaryPath, 'ffmpeg'),
            'ffprobe_version' => $this->binaryVersion($this->ffprobeBinaryPath, 'ffprobe'),
        ];
    }

    protected function formatOptions(string $targetFormat): array
    {
        return match ($targetFormat) {
            'mp3' => ['-vn', '-codec:a', 'libmp3lame', '-b:a', '128k'],
            'wav' => ['-vn', '-codec:a', 'pcm_s16le', '-ar', '16000', '-ac', '1'],
            'ogg' => ['-vn', '-codec:a', 'libopus', '-b:a', '32k'],
            default => throw new AudioConversionException(
                "Unsupported audio conversion target format [{$targetFormat}]."
            ),
        };
    }

    protected function temporaryOutputPath(string $inputPath, string $targetFormat): string
    {
        $directory = $this->resolvedTempDirectory();

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new AudioConversionException(
                "Unable to create the audio conversion temp directory [{$directory}]."
            );
        }

        $baseName = pathinfo($inputPath, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName ?: 'audio-input') ?: 'audio-input';
        $suffix = bin2hex(random_bytes(12));

        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$baseName}-{$suffix}.{$targetFormat}";
    }

    protected function mimeTypeForFormat(string $targetFormat): string
    {
        return match ($targetFormat) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }

    private function ensureEnvironmentReady(): void
    {
        $this->ensureExecutable($this->ffmpegBinaryPath, 'ffmpeg');
        $this->ensureExecutable($this->ffprobeBinaryPath, 'ffprobe');
    }

    private function binaryVersion(string $binaryPath, string $binaryName): string
    {
        $result = $this->runProcess(
            [$binaryPath, '-version'],
            "Unable to execute [{$binaryName}] to read its version.",
        );

        $firstLine = strtok($result['stdout'], "\n");

        return trim((string) $firstLine);
    }

    private function resolvedTempDirectory(): string
    {
        return $this->resolveWritableTempDirectory([
            trim((string) ($this->tempDirectory ?? '')),
            storage_path('framework/talk2flow-agentic/ffmpeg-temp'),
            base_path('bootstrap/cache/talk2flow-agentic/ffmpeg-temp'),
            storage_path('app/talk2flow-agentic/ffmpeg-temp'),
            $this->homeDirectoryPath('.cache/talk2flow-agentic/ffmpeg-temp'),
            $this->homeDirectoryPath('.talk2flow-agentic/ffmpeg-temp'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . '.talk2flow-agentic' . DIRECTORY_SEPARATOR . 'ffmpeg-temp',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'talk2flow-agentic-ffmpeg',
        ]);
    }

    private function ensureExecutable(string $binaryPath, string $binaryName): void
    {
        $binaryPath = trim($binaryPath);

        if ($binaryPath === '') {
            throw new AudioConversionException("No local {$binaryName} binary path has been configured.");
        }

        if (! is_file($binaryPath)) {
            throw new AudioConversionException("Local {$binaryName} binary was not found at [{$binaryPath}].");
        }

        if (! is_executable($binaryPath)) {
            @chmod($binaryPath, 0755);
        }

        if (! is_executable($binaryPath)) {
            throw new AudioConversionException("Local {$binaryName} binary at [{$binaryPath}] is not executable.");
        }
    }

    private function assertReadableInput(string $inputPath): void
    {
        if (! is_file($inputPath) || ! is_readable($inputPath)) {
            throw new AudioConversionException("Audio input file [{$inputPath}] is missing or not readable.");
        }
    }

    /**
     * @param  list<string>  $candidates
     */
    private function resolveWritableTempDirectory(array $candidates): string
    {
        $attempted = [];

        foreach ($candidates as $candidate) {
            $candidate = rtrim(trim($candidate), DIRECTORY_SEPARATOR);

            if ($candidate === '') {
                continue;
            }

            $attempted[] = $candidate;

            if (is_dir($candidate) || @mkdir($candidate, 0755, true)) {
                if (is_dir($candidate) && is_writable($candidate)) {
                    return $candidate;
                }
            }
        }

        $attemptedList = implode(', ', $attempted);

        throw new AudioConversionException(
            "Unable to resolve a writable audio conversion temp directory. Tried: [{$attemptedList}]."
        );
    }

    private function homeDirectoryPath(string $suffix): string
    {
        $home = $this->homeDirectory();

        if ($home === null) {
            return '';
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($suffix, DIRECTORY_SEPARATOR);
    }

    private function homeDirectory(): ?string
    {
        $candidates = [
            getenv('HOME') ?: null,
            $_SERVER['HOME'] ?? null,
        ];

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            $candidates[] = is_array($info) ? ($info['dir'] ?? null) : null;
        }

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? trim($candidate) : '';

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{stdout: string, stderr: string}
     */
    private function runProcess(array $command, string $failureMessage): array
    {
        $process = new Process($command);
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $detail = $stderr !== '' ? $stderr : $stdout;
            $message = $failureMessage . ($detail !== '' ? ' ' . $detail : '');

            throw new AudioConversionException(
                $message,
                command: $command,
                exitCode: $process->getExitCode(),
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
            );
        }

        return [
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
