@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="h-full bg-white font-sans">
    {{ $slot }}
    <x-overlays />
</body>
</html>
