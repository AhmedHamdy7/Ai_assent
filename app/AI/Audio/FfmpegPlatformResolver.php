<?php

namespace App\AI\Audio;

class FfmpegPlatformResolver
{
    public function currentPlatformKey(): string
    {
        return $this->platformKeyFor(PHP_OS_FAMILY, php_uname('m'));
    }

    public function platformKeyFor(string $osFamily, string $machineArchitecture): string
    {
        $osFamily = strtolower(trim($osFamily));
        $architecture = $this->normalizeArchitecture($machineArchitecture);

        return match ([$osFamily, $architecture]) {
            ['linux', 'x86_64'] => 'linux-x86_64',
            ['linux', 'arm64'] => 'linux-arm64',
            ['darwin', 'x86_64'] => 'darwin-x86_64',
            ['darwin', 'arm64'] => 'darwin-arm64',
            ['windows', 'x86_64'] => 'windows-x86_64',
            ['windows', 'arm64'] => 'windows-arm64',
            default => throw new AudioConversionException(
                "Unsupported FFmpeg platform [{$osFamily}/{$machineArchitecture}]."
            ),
        };
    }

    private function normalizeArchitecture(string $machineArchitecture): string
    {
        return match (strtolower(trim($machineArchitecture))) {
            'x86_64', 'amd64' => 'x86_64',
            'aarch64', 'arm64' => 'arm64',
            default => strtolower(trim($machineArchitecture)),
        };
    }
}
