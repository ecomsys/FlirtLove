<?php

return [
    'collections' => [
        'default' => [
            'keep_original' => true,
            'max_file_size_kb' => 10240, // 10MB
            'variants' => [
                'default' => ['size' => '1600w', 'fit' => 'inside', 'format' => 'webp', 'quality' => 80],
            ],
        ],
        'gift' => [
            'keep_original' => true,
            'max_file_size_kb' => 2048, // 2MB
            'alpha' => true,
            'variants' => [
                'sm' => ['size' => '128x128', 'fit' => 'cover', 'format' => 'webp', 'quality' => 70],
                'lg' => ['size' => '512x512', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80],
            ],
        ],
        'post' => [
            'keep_original' => false,
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'thumb'    => ['size' => '300x200', 'fit' => 'cover', 'format' => 'webp', 'quality' => 70],
                'cover_sm' => ['size' => '640x360', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80],
                'cover_lg' => ['size' => '1280x720', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
                'og'       => ['size' => '1200x630', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 90],
                'content'  => ['size' => '1200w', 'fit' => 'inside', 'format' => 'webp', 'quality' => 85],
            ],
        ],
        'notifications' => [
            'keep_original' => false,
            'max_file_size_kb' => 2048, // 2MB
            'variants' => [
                'thumb'   => ['size' => '300x200', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 70],
                'header'  => ['size' => '1200x630', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 85],
                'content' => ['size' => '1200w', 'fit' => 'inside', 'format' => 'jpeg', 'quality' => 85],
            ],
        ],
        'banner_desktop' => [
            'keep_original' => false,
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'default' => ['size' => '1920x640', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
            ],
        ],
        'banner_mobile' => [
            'keep_original' => false,
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'default' => ['size' => '1080x1080', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
            ],
        ],
    ],
];