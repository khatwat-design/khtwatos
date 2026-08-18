<?php

/**
 * ملصقات الدردشة — PNG من حزم تيليجرام (telegram-stickers.github.io).
 * التسميات للمعاينة في الردود فقط — لا تُعرض تحت الملصق في اللوحة.
 */
return [
    'packs' => [
        [
            'id' => 'unofficial',
            'label' => 'تفاعلات',
            'subtitle' => 'أسلوب تيليجرام',
            'stickers' => array_map(
                fn (int $i) => ['id' => (string) $i, 'label' => 'ملصق'],
                range(1, 14),
            ),
        ],
        [
            'id' => 'asdfmovie',
            'label' => 'asdfmovie',
            'subtitle' => 'ميمز TomSka',
            'stickers' => array_map(
                fn (int $i) => ['id' => (string) $i, 'label' => 'ملصق'],
                range(1, 10),
            ),
        ],
        [
            'id' => 'college-dog',
            'label' => 'College Dog',
            'subtitle' => 'فريق وعمل',
            'stickers' => array_map(
                fn (int $i) => ['id' => (string) $i, 'label' => 'ملصق'],
                range(1, 6),
            ),
        ],
        [
            'id' => 'dumb-ways-to-die',
            'label' => 'Dumb Ways',
            'subtitle' => 'ميمز يوتيوب',
            'stickers' => array_map(
                fn (int $i) => ['id' => (string) $i, 'label' => 'ملصق'],
                range(1, 9),
            ),
        ],
        [
            'id' => 'xkcd',
            'label' => 'xkcd',
            'subtitle' => 'كوميكس وفكاهة',
            'stickers' => array_map(
                fn (int $i) => ['id' => (string) $i, 'label' => 'ملصق'],
                range(1, 22),
            ),
        ],
    ],
];
