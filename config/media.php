<?php

return [
    'collections' => [
        'default' => [
            'keep_original' => true,
            'max_file_size_kb' => 10240, // 10MB
            'variants' => [
                'thumb' => ['size' => '300x300', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75], // ФИКС: Увеличили до 300px
                'sm'    => ['size' => '500x500', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75],
                'md'    => ['size' => '800w', 'fit' => 'inside', 'format' => 'webp', 'quality' => 80],
                'lg'    => ['size' => '1200w', 'fit' => 'inside', 'format' => 'webp', 'quality' => 85],
            ],
        ],
        'gift' => [
            'keep_original' => true, 
            'max_file_size_kb' => 2048, // 2MB
            'alpha' => true, 
            'variants' => [
                'thumb' => ['size' => '200x200', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75], // ФИКС: 200px для подарков хватит
                'sm'    => ['size' => '400x400', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80],
                'md'    => ['size' => '600x600', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
                'lg'    => ['size' => '800x800', 'fit' => 'cover', 'format' => 'webp', 'quality' => 90],
            ],
        ],
        'post' => [
            'keep_original' => false, 
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'thumb'    => ['size' => '300x300', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75], // ФИКС: 300px
                'sm'       => ['size' => '640x360', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80],
                'md'       => ['size' => '960x540', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
                'lg'       => ['size' => '1280x720', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
                'og'       => ['size' => '1200x630', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 90], 
            ],
        ],
        'notifications' => [
            'keep_original' => false,
            'max_file_size_kb' => 2048, // 2MB
            'variants' => [
                'thumb'  => ['size' => '300x300', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 75], // ФИКС: 300px
                'sm'     => ['size' => '640x360', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 80],
                'md'     => ['size' => '800x420', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 85], 
                'lg'     => ['size' => '1200x630', 'fit' => 'cover', 'format' => 'jpeg', 'quality' => 85],
            ],
        ],
        'banner_desktop' => [
            'keep_original' => false,
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'thumb' => ['size' => '300x300', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75], // ФИКС: 300px
                'sm'    => ['size' => '640x213', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80], 
                'lg'    => ['size' => '1920x640', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
            ],
        ],
        'banner_mobile' => [
            'keep_original' => false,
            'max_file_size_kb' => 5120, // 5MB
            'variants' => [
                'thumb' => ['size' => '300x300', 'fit' => 'cover', 'format' => 'webp', 'quality' => 75], // ФИКС: 300px
                'sm'    => ['size' => '400x400', 'fit' => 'cover', 'format' => 'webp', 'quality' => 80], 
                'lg'    => ['size' => '1080x1080', 'fit' => 'cover', 'format' => 'webp', 'quality' => 85],
            ],
        ],
    ],
];