<?php

namespace App\AI\Command;

use Illuminate\Console\Command;
use App\AI\Audio\FfmpegInstaller;
use App\AI\Audio\FfmpegPlatformResolver;

class VerifyFfmpegCommand extends Command
{
    protected $signature = 'talk2flow:ffmpeg:verify
        {--platform= : Override platform key (for example linux-x86_64)}';

    protected $description = 'Verify the configured local FFmpeg and FFprobe binaries.';

    public function __construct(
        private readonly FfmpegInstaller $installer,
        private readonly FfmpegPlatformResolver $platformResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $platform = $this->option('platform') ?: $this->platformResolver->currentPlatformKey();
        $result = $this->installer->verify($platform);

        $this->line('FFmpeg:  ' . $result['ffmpeg_path']);
        $this->line('FFprobe: ' . $result['ffprobe_path']);
        $this->line('Version: ' . $result['ffmpeg_version']);
        $this->line('Probe:   ' . $result['ffprobe_version']);
        $this->components->info('FFmpeg environment is ready.');

        return self::SUCCESS;
    }
}
