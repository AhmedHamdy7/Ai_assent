<?php

namespace App\AI\Audio;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use ZipArchive;

class FfmpegInstaller
{
    public function __construct(
        private readonly Repository $config,
        private readonly FfmpegPlatformResolver $platformResolver,
    ) {}

    public function resolvedBinaryPaths(?string $platformKey = null): array
    {
        $platformKey ??= $this->platformResolver->currentPlatformKey();
        $ffmpegBinaryName = $this->binaryFileName('ffmpeg', $platformKey);
        $ffprobeBinaryName = $this->binaryFileName('ffprobe', $platformKey);

        $configuredFfmpeg = trim((string) data_get($this->configuration(), 'binary_paths.ffmpeg', ''));
        $configuredFfprobe = trim((string) data_get($this->configuration(), 'binary_paths.ffprobe', ''));
        $configuredPaths = null;

        if ($configuredFfmpeg !== '' && $configuredFfprobe !== '') {
            $configuredPaths = [
                'ffmpeg' => $configuredFfmpeg,
                'ffprobe' => $configuredFfprobe,
            ];

            if ($this->binaryPairExists($configuredFfmpeg, $configuredFfprobe)) {
                return $configuredPaths;
            }
        }

        $existing = $this->existingInstalledBinaryPaths($platformKey);

        if ($existing !== null) {
            return $existing;
        }

        if ($configuredPaths !== null) {
            return $configuredPaths;
        }

        $installRoot = $this->installRoot();

        return [
            'ffmpeg' => $installRoot . DIRECTORY_SEPARATOR . $platformKey . DIRECTORY_SEPARATOR . $ffmpegBinaryName,
            'ffprobe' => $installRoot . DIRECTORY_SEPARATOR . $platformKey . DIRECTORY_SEPARATOR . $ffprobeBinaryName,
        ];
    }

    public function install(?string $platformKey = null, bool $force = false, ?string $channel = null): array
    {
        $platformKey ??= $this->platformResolver->currentPlatformKey();
        $channel ??= $this->channel();
        $platform = $this->platformConfiguration($platformKey);
        $paths = $this->resolvedBinaryPaths($platformKey);

        if (! $force && is_file($paths['ffmpeg']) && is_file($paths['ffprobe'])) {
            return $this->verify($platformKey);
        }

        $workspace = $this->temporaryWorkspace($platformKey);
        $extractDirectory = $workspace . DIRECTORY_SEPARATOR . 'extract';
        $installDirectory = dirname($paths['ffmpeg']);

        $this->ensureDirectory($workspace);
        $this->ensureDirectory($extractDirectory);
        $this->ensureDirectory($installDirectory);

        try {
            $archiveUrl = $this->archiveDownloadUrl($platform, $channel);

            if ($archiveUrl !== null) {
                $archivePath = $workspace . DIRECTORY_SEPARATOR . 'ffmpeg-bundle.zip';
                $bundleExtract = $extractDirectory . DIRECTORY_SEPARATOR . 'bundle';

                $this->downloadFile($archiveUrl, $archivePath);
                $this->extractZip($archivePath, $bundleExtract);

                $this->installBinary(
                    $this->findBinary($bundleExtract, 'ffmpeg', $platformKey),
                    $paths['ffmpeg'],
                );
                $this->installBinary(
                    $this->findBinary($bundleExtract, 'ffprobe', $platformKey),
                    $paths['ffprobe'],
                );
            } else {
                $ffmpegArchive = $workspace . DIRECTORY_SEPARATOR . 'ffmpeg.zip';
                $ffprobeArchive = $workspace . DIRECTORY_SEPARATOR . 'ffprobe.zip';

                $this->downloadFile($this->binaryDownloadUrl($platform, $channel, 'ffmpeg.zip'), $ffmpegArchive);
                $this->downloadFile($this->binaryDownloadUrl($platform, $channel, 'ffprobe.zip'), $ffprobeArchive);

                $ffmpegExtract = $extractDirectory . DIRECTORY_SEPARATOR . 'ffmpeg';
                $ffprobeExtract = $extractDirectory . DIRECTORY_SEPARATOR . 'ffprobe';

                $this->extractZip($ffmpegArchive, $ffmpegExtract);
                $this->extractZip($ffprobeArchive, $ffprobeExtract);

                $this->installBinary(
                    $this->findBinary($ffmpegExtract, 'ffmpeg', $platformKey),
                    $paths['ffmpeg'],
                );
                $this->installBinary(
                    $this->findBinary($ffprobeExtract, 'ffprobe', $platformKey),
                    $paths['ffprobe'],
                );
            }

            return $this->verify($platformKey);
        } finally {
            $this->deleteDirectory($workspace);
        }
    }

    public function verify(?string $platformKey = null): array
    {
        $paths = $this->resolvedBinaryPaths($platformKey);

        $converter = new AudioConverter(
            $paths['ffmpeg'],
            $paths['ffprobe'],
            (int) data_get($this->configuration(), 'timeout', 120),
            (bool) data_get($this->configuration(), 'overwrite', true),
            (string) data_get($this->configuration(), 'temp_directory', ''),
        );

        return $converter->verifyEnvironment();
    }

    public function availablePlatforms(): array
    {
        return array_keys((array) data_get($this->configuration(), 'platforms', []));
    }

    private function binaryDownloadUrl(array $platform, string $channel, string $binaryArchive): string
    {
        $baseUrl = rtrim((string) data_get($this->configuration(), 'download_base_url', ''), '/');

        if ($baseUrl === '') {
            throw new AudioConversionException('No FFmpeg download base URL is configured.');
        }

        $osSegment = trim((string) ($platform['os_segment'] ?? ''));
        $archSegment = trim((string) ($platform['arch_segment'] ?? ''));

        if ($osSegment === '' || $archSegment === '') {
            throw new AudioConversionException('FFmpeg platform download configuration is incomplete.');
        }

        return "{$baseUrl}/{$osSegment}/{$archSegment}/{$channel}/{$binaryArchive}";
    }

    private function archiveDownloadUrl(array $platform, string $channel): ?string
    {
        $urls = data_get($platform, 'archive_urls');

        if (is_array($urls)) {
            $channelUrl = trim((string) ($urls[$channel] ?? ''));

            if ($channelUrl !== '') {
                return $channelUrl;
            }

            $defaultUrl = trim((string) ($urls['default'] ?? ''));

            if ($defaultUrl !== '') {
                return $defaultUrl;
            }
        }

        $url = trim((string) data_get($platform, 'archive_url', ''));

        return $url !== '' ? $url : null;
    }

    private function platformConfiguration(string $platformKey): array
    {
        $platform = data_get($this->configuration(), "platforms.{$platformKey}");

        if (! is_array($platform)) {
            throw new AudioConversionException("No FFmpeg download configuration exists for platform [{$platformKey}].");
        }

        return $platform;
    }

    private function channel(): string
    {
        $channel = strtolower(trim((string) data_get($this->configuration(), 'download_channel', 'release')));

        return in_array($channel, ['release', 'snapshot'], true) ? $channel : 'release';
    }

    private function installRoot(): string
    {
        return $this->resolveWritableDirectory($this->installRootCandidates(), 'FFmpeg install root');
    }

    private function temporaryWorkspace(string $platformKey): string
    {
        $base = $this->resolveWritableDirectory([
            trim((string) data_get($this->configuration(), 'installer_temp_directory', '')),
            storage_path('framework/talk2flow-agentic/ffmpeg-installer-temp'),
            base_path('bootstrap/cache/talk2flow-agentic/ffmpeg-installer-temp'),
            storage_path('app/talk2flow-agentic/ffmpeg-installer-temp'),
            $this->homeDirectoryPath('.cache/talk2flow-agentic/ffmpeg-installer-temp'),
            $this->homeDirectoryPath('.talk2flow-agentic/ffmpeg-installer-temp'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . '.talk2flow-agentic' . DIRECTORY_SEPARATOR . 'ffmpeg-installer-temp',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'talk2flow-agentic-ffmpeg-installer',
        ], 'FFmpeg installer temp directory');

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $platformKey . '-' . Str::uuid();
    }

    private function downloadFile(string $url, string $destination): void
    {
        $response = Http::timeout((int) data_get($this->configuration(), 'download_timeout', 300))
            ->retry(2, 500)
            ->get($url);

        $response->throw();

        $written = file_put_contents($destination, $response->body());

        if ($written === false) {
            throw new AudioConversionException("Unable to write downloaded FFmpeg asset [{$destination}].");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return (array) $this->config->get('talk2flow-agentic.ffmpeg', []);
    }

    private function extractZip(string $archivePath, string $destination): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new AudioConversionException('The PHP zip extension is required to install FFmpeg binaries automatically.');
        }

        $this->ensureDirectory($destination);

        $archive = new ZipArchive();
        $opened = $archive->open($archivePath);

        if ($opened !== true) {
            throw new AudioConversionException("Unable to open ZIP archive [{$archivePath}].");
        }

        try {
            if (! $archive->extractTo($destination)) {
                throw new AudioConversionException("Unable to extract ZIP archive [{$archivePath}].");
            }
        } finally {
            $archive->close();
        }
    }

    private function findBinary(string $searchRoot, string $binaryName, string $platformKey): string
    {
        $expectedFileName = $this->binaryFileName($binaryName, $platformKey);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getBasename()) === strtolower($expectedFileName)) {
                return $file->getPathname();
            }
        }

        throw new AudioConversionException("Unable to find [{$expectedFileName}] inside extracted archive [{$searchRoot}].");
    }

    private function installBinary(string $sourcePath, string $destinationPath): void
    {
        $this->ensureDirectory(dirname($destinationPath));

        if (! @copy($sourcePath, $destinationPath)) {
            throw new AudioConversionException(
                "Unable to install binary from [{$sourcePath}] to [{$destinationPath}]."
            );
        }

        @chmod($destinationPath, 0755);

        if (! is_file($destinationPath) || ! is_executable($destinationPath)) {
            throw new AudioConversionException("Installed binary [{$destinationPath}] is not executable.");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new AudioConversionException("Unable to create directory [{$directory}].");
        }
    }

    /**
     * @param  list<string>  $candidates
     */
    private function resolveWritableDirectory(array $candidates, string $label): string
    {
        $attempted = [];

        foreach ($candidates as $candidate) {
            $candidate = rtrim(trim($candidate), DIRECTORY_SEPARATOR);

            if ($candidate === '') {
                continue;
            }

            $attempted[] = $candidate;

            try {
                $this->ensureDirectory($candidate);

                return $candidate;
            } catch (AudioConversionException) {
                continue;
            }
        }

        $attemptedList = implode(', ', $attempted);

        throw new AudioConversionException(
            "Unable to resolve a writable {$label}. Tried: [{$attemptedList}]."
        );
    }

    private function existingInstalledBinaryPaths(string $platformKey): ?array
    {
        $ffmpegBinaryName = $this->binaryFileName('ffmpeg', $platformKey);
        $ffprobeBinaryName = $this->binaryFileName('ffprobe', $platformKey);

        foreach ($this->binarySearchRoots() as $root) {
            $root = rtrim(trim($root), DIRECTORY_SEPARATOR);

            if ($root === '') {
                continue;
            }

            $ffmpegPath = $root . DIRECTORY_SEPARATOR . $platformKey . DIRECTORY_SEPARATOR . $ffmpegBinaryName;
            $ffprobePath = $root . DIRECTORY_SEPARATOR . $platformKey . DIRECTORY_SEPARATOR . $ffprobeBinaryName;

            if (is_file($ffmpegPath) && is_file($ffprobePath)) {
                return [
                    'ffmpeg' => $ffmpegPath,
                    'ffprobe' => $ffprobePath,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function installRootCandidates(): array
    {
        return [
            trim((string) data_get($this->configuration(), 'install_root', '')),
            storage_path('framework/talk2flow-agentic/ffmpeg-bin'),
            base_path('bootstrap/cache/talk2flow-agentic/ffmpeg-bin'),
            storage_path('app/talk2flow-agentic/ffmpeg-bin'),
            $this->homeDirectoryPath('.local/share/talk2flow-agentic/ffmpeg-bin'),
            $this->homeDirectoryPath('.talk2flow-agentic/ffmpeg-bin'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . '.talk2flow-agentic' . DIRECTORY_SEPARATOR . 'ffmpeg-bin',
        ];
    }

    /**
     * @return list<string>
     */
    private function binarySearchRoots(): array
    {
        return $this->uniqueDirectories([
            ...$this->installRootCandidates(),
            ...$this->configuredSearchRoots(),
            ...$this->globbedSearchRoots(),
        ]);
    }

    private function binaryPairExists(string $ffmpegPath, string $ffprobePath): bool
    {
        return is_file($ffmpegPath) && is_file($ffprobePath);
    }

    /**
     * @return list<string>
     */
    private function configuredSearchRoots(): array
    {
        return $this->uniqueDirectories((array) data_get($this->configuration(), 'search_roots', []));
    }

    /**
     * @return list<string>
     */
    private function globbedSearchRoots(): array
    {
        $roots = [];

        foreach ($this->searchGlobPatterns() as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $directory) {
                $roots[] = $directory;
            }
        }

        return $this->uniqueDirectories($roots);
    }

    /**
     * @return list<string>
     */
    private function searchGlobPatterns(): array
    {
        return $this->uniqueDirectories([
            ...$this->systemSearchGlobPatterns(),
            ...(array) data_get($this->configuration(), 'search_globs', []),
        ]);
    }

    /**
     * @return list<string>
     */
    private function systemSearchGlobPatterns(): array
    {
        return match (strtolower(PHP_OS_FAMILY)) {
            'linux' => [
                '/home/*/.local/share/talk2flow-agentic/ffmpeg-bin',
                '/home/*/.talk2flow-agentic/ffmpeg-bin',
                '/root/.local/share/talk2flow-agentic/ffmpeg-bin',
                '/root/.talk2flow-agentic/ffmpeg-bin',
            ],
            'darwin' => [
                '/Users/*/.local/share/talk2flow-agentic/ffmpeg-bin',
                '/Users/*/.talk2flow-agentic/ffmpeg-bin',
            ],
            'windows' => array_values(array_filter([
                getenv('LOCALAPPDATA') ? rtrim((string) getenv('LOCALAPPDATA'), '\\/') . '\\talk2flow-agentic\\ffmpeg-bin' : null,
                getenv('APPDATA') ? rtrim((string) getenv('APPDATA'), '\\/') . '\\talk2flow-agentic\\ffmpeg-bin' : null,
                getenv('USERPROFILE') ? rtrim((string) getenv('USERPROFILE'), '\\/') . '\\.talk2flow-agentic\\ffmpeg-bin' : null,
            ])),
            default => [],
        };
    }

    private function binaryFileName(string $binaryName, string $platformKey): string
    {
        return str_starts_with($platformKey, 'windows-') ? "{$binaryName}.exe" : $binaryName;
    }

    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    private function uniqueDirectories(array $directories): array
    {
        $unique = [];
        $seen = [];

        foreach ($directories as $directory) {
            $directory = rtrim(trim((string) $directory), DIRECTORY_SEPARATOR);

            if ($directory === '' || isset($seen[$directory])) {
                continue;
            }

            $seen[$directory] = true;
            $unique[] = $directory;
        }

        return $unique;
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

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
