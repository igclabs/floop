<?php

return [

    'auto_inject' => true,

    'storage_path' => storage_path('app/feedback'),

    'route_prefix' => '_feedback',

    'middleware' => ['web'],

    'environments' => ['local'],

    'position' => 'bottom-right',

    'exclude_session_keys' => [
        '_token',
        '_flash',
        '_previous',
        'password',
        'password_confirmation',
    ],

    'default_type' => 'feedback',

    'show_priority' => true,

    'shortcut' => 'ctrl+shift+f',

    'hide_shortcut' => 'ctrl+shift+h',

    'screenshot_max_size' => 5242880,

];
