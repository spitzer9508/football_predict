<?php

return [
    'provider' => 'flashscore4',
    'flashscore4' => [
        'base_url' => getenv('FLASHSCORE_BASE_URL') ?: 'https://flashscore4.p.rapidapi.com/api/flashscore/v2',
        'host' => getenv('FLASHSCORE_HOST') ?: 'flashscore4.p.rapidapi.com',
        'api_key' => getenv('FLASHSCORE_API_KEY') ?: '',
        'timeout' => 30,
        'connect_timeout' => 10,
    ],
];
