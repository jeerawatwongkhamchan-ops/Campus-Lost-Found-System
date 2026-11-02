<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'Campus Lost & Found System') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= file_exists(PUBLIC_PATH . '/assets/css/app.css') ? filemtime(PUBLIC_PATH . '/assets/css/app.css') : time() ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="/assets/js/app.js?v=<?= file_exists(PUBLIC_PATH . '/assets/js/app.js') ? filemtime(PUBLIC_PATH . '/assets/js/app.js') : time() ?>" defer></script>
</head>
<body class="flex min-h-screen flex-col bg-gray-50 text-gray-900 antialiased font-sans">
    <?php require APP_PATH . '/views/components/navbar.php'; ?>
    <main class="mx-auto w-full max-w-5xl grow px-4 py-8">
