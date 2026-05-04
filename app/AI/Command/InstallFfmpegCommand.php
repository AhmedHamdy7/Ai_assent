<?php

namespace App\AI\Command;

use Illuminate\Console\Command;
use App\AI\Audio\FfmpegInstaller;
use App\AI\Audio\FfmpegPlatformResolver;

class InstallFfmpegCommand extends Command
{
    protected $signature = 'talk2flow:ffmpeg:install
        {--platform= : Override platform key (for example linux-x86_64)}
        {--channel= : Override download channel (release or snapshot)}
        {--force : Reinstall even when binaries already exist}';

    protected $description = 'Download and install local FFmpeg and FFprobe binaries for the current platform.';

    public function __construct(
        private readonly FfmpegInstaller $installer,
        private readonly FfmpegPlatformResolver $platformResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $platform = $this->option('platform') ?: $this->platformResolver->currentPlatformKey();
        $channel = $this->option('channel') ?: null;

        $this->components->info("Installing FFmpeg for [{$platform}]...");

        $result = $this->installer->install(
            platformKey: $platform,
            force: (bool) $this->option('force'),
            channel: is_string($channel) && $channel !== '' ? $channel : null,
        );

        $this->line('FFmpeg:  ' . $result['ffmpeg_path']);
        $this->line('FFprobe: ' . $result['ffprobe_path']);
        $this->line('Version: ' . $result['ffmpeg_version']);
        $this->components->info('FFmpeg installation verified successfully.');

        return self::SUCCESS;
    }
}
