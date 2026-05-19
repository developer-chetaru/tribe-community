
<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
    'support_chat_model' => env('OPENAI_SUPPORT_CHAT_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
    'support_chat_max_tokens' => env('OPENAI_SUPPORT_CHAT_MAX_TOKENS', 800),
    'support_chat_temperature' => env('OPENAI_SUPPORT_CHAT_TEMPERATURE', 0.5),
];
