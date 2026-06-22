<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('specialist/setup.completed.title') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f8; margin: 0; padding: 2rem 1rem; }
        .container { max-width: 480px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 2rem; text-align: center; }
        h1 { color: #10b981; margin: 0 0 .5rem; font-size: 1.5rem; }
        p { color: #4b5563; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('specialist/setup.completed.heading') }}</h1>
        <p>{{ __('specialist/setup.completed.body') }}</p>
    </div>
</body>
</html>
