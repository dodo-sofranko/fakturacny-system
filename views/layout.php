<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="/public/css/app.css?v=<?= filemtime(__DIR__ . '/../public/css/app.css') ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Nav -->
<nav class="bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-14">
        <a href="/" class="font-bold text-lg text-gray-900 tracking-tight">📄 Fakturácia</a>
        <div class="flex gap-1">
            <a href="/" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors <?= str_starts_with($_SERVER['REQUEST_URI'], '/faktury') || $_SERVER['REQUEST_URI'] === '/' ? 'bg-gray-100 text-gray-900' : '' ?>">Faktúry</a>
            <a href="/odberatelia" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors <?= str_starts_with($_SERVER['REQUEST_URI'], '/odberatelia') ? 'bg-gray-100 text-gray-900' : '' ?>">Odberatelia</a>
            <a href="/dodavatel" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors <?= str_starts_with($_SERVER['REQUEST_URI'], '/dodavatel') ? 'bg-gray-100 text-gray-900' : '' ?>">Môj účet</a>
        </div>
    </div>
</nav>

<main class="max-w-6xl mx-auto px-3 sm:px-4 py-4 sm:py-6">
    <?php $flash = flash(); if ($flash): ?>
        <div class="<?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>" x-data x-init="setTimeout(() => $el.remove(), 4000)">
            <?= e($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

</body>
</html>
