<?php

return [
    'ffmpeg' => [
        'binary_paths' => [
            'ffmpeg' => env('FFMPEG_PATH', ''),
            'ffprobe' => env('FFPROBE_PATH', ''),
        ],
        'timeout' => (int) env('FFMPEG_TIMEOUT', 120),
        'overwrite' => (bool) env('FFMPEG_OVERWRITE', true),
        'temp_directory' => env('FFMPEG_TEMP_DIRECTORY', ''),
        'installer_temp_directory' => env('FFMPEG_INSTALLER_TEMP_DIRECTORY', ''),
        'install_root' => env('FFMPEG_INSTALL_ROOT', ''),
        'download_timeout' => (int) env('FFMPEG_DOWNLOAD_TIMEOUT', 300),
        'download_channel' => env('FFMPEG_DOWNLOAD_CHANNEL', 'release'),
        'download_base_url' => env('FFMPEG_DOWNLOAD_BASE_URL', ''),
        'search_roots' => array_values(array_filter([
            env('FFMPEG_SEARCH_ROOT', ''),
        ])),
        'search_globs' => [],
        'platforms' => [
            'windows-x86_64' => [
                'archive_urls' => [
                    'release' => env('FFMPEG_WINDOWS_X86_64_RELEASE_URL', 'https://www.gyan.dev/ffmpeg/builds/ffmpeg-release-essentials.zip'),
                    'snapshot' => env('FFMPEG_WINDOWS_X86_64_SNAPSHOT_URL', 'https://www.gyan.dev/ffmpeg/builds/ffmpeg-git-essentials.zip'),
                ],
            ],
        ],
    ],
];
