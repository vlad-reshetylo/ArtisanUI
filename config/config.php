<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'title' => env('APP_NAME') . " " . "Artisan UI",
    'ui' => [
        'border_horizontal' => 3,
        'border_vertical' => 5,
        'border_color' => 'green',
        'padding_horizontal' => 2,
        'padding_vertical' => 4,
        'text_color' => 'white',
        'background_color' => 'blue',
        'checked_marker' => '[X]',
        'unchecked_marker' => '[ ]',
        'selected_marker' => ' > ',
        'unselected_marker' => ' o ',
        'line_break' => '='
    ],
    'favourite' => [
        'make:migration',
    ] 
];