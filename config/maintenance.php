<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline maintenance-attention model
    |--------------------------------------------------------------------------
    |
    | The deterministic Laravel rules remain the source of truth. The local
    | model is an optional, additive signal and is ignored when unavailable.
    |
    */
    'attention_ai' => [
        'enabled' => env('MAINTENANCE_AI_ENABLED', true),
        'auto_train' => env('MAINTENANCE_AI_AUTO_TRAIN', true),
        'train_day' => (int) env('MAINTENANCE_AI_TRAIN_DAY', 1),
        'train_time' => env('MAINTENANCE_AI_TRAIN_TIME', '03:30'),
        'min_samples' => (int) env('MAINTENANCE_AI_MIN_SAMPLES', 20),
        'python' => env('MAINTENANCE_AI_PYTHON', 'python'),
        'script' => base_path('ai/maintenance_attention.py'),
        'model' => storage_path('app/ai/maintenance_attention.onnx'),
        'metadata' => storage_path('app/ai/maintenance_attention.json'),
        'timeout' => (int) env('MAINTENANCE_AI_TIMEOUT', 10),
        'training_timeout' => (int) env('MAINTENANCE_AI_TRAINING_TIMEOUT', 60),
        'cache_minutes' => (int) env('MAINTENANCE_AI_CACHE_MINUTES', 10),
    ],
];
