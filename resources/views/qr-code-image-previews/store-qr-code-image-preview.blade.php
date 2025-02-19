<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>QR Code for {{ $store->name }}</title>

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Scan to access {{ $store->name }}">
    <meta property="og:description" content="Tap to open the store and explore products">
    <meta property="og:image" content="{{ $store->qr_code_file_path }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Twitter Card (Optional) -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Scan to access {{ $store->name }}">
    <meta name="twitter:description" content="Tap to open the store and explore products">
    <meta name="twitter:image" content="{{ $store->qr_code_file_path }}">

</head>
<body>
    <h1>Scan this QR Code to access {{ $store->name }}</h1>
    <img src="{{ $store->qr_code_file_path }}" alt="QR Code">
</body>
</html>
