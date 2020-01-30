<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'title' => env('APP_NAME') . " " . "Artisan UI",
    'ui' => [
        'border_horizontal' => 1,
        'border_vertical' => 2,
        'border_color' => 'green',
        'padding_horizontal' => 1,
        'padding_vertical' => 2,
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