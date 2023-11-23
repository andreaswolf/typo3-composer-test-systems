<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 Test Extension',
    'description' => '',
    'category' => 'misc',
    'version' => '1.0.0',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.28-11.5.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
