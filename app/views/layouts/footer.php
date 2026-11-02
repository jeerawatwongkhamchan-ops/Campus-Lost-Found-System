    </main>
    <footer class="mt-auto border-t border-gray-200 bg-white py-6 text-xs text-gray-500">
        <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-900">Campus Lost &amp; Found</span>
                    <span class="text-gray-300">·</span>
                    <span>CP423324 Database and Web Security</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-gray-600">
                    <span class="text-gray-400">ผู้พัฒนา:</span>
                    <a href="https://github.com/Bxmluvuu" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">Bxmluvuu</a>
                    <span class="text-gray-300">·</span>
                    <a href="https://github.com/jeerawatwo-afk" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">jeerawatwo-afk</a>
                    <span class="text-gray-300">·</span>
                    <a href="https://github.com/chitsanupongjitmal" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">chitsanupongjitmal</a>
                    <span class="text-gray-300">·</span>
                    <a href="https://github.com/Sahapol-pond" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">Sahapol-pond</a>
                    <span class="text-gray-300">·</span>
                    <a href="https://github.com/StSw" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">StSw</a>
                    <span class="text-gray-300">·</span>
                    <a href="https://github.com/TIEWLYZM" target="_blank" rel="noopener noreferrer" class="hover:text-gray-900 hover:underline transition">TIEWLYZM</a>
                </div>
            </div>
            <div class="text-gray-400 sm:text-right">
                <p>&copy; <?= date('Y') ?> All rights reserved.</p>
            </div>
        </div>
    </footer>
    <script src="/assets/js/app.js?v=<?= file_exists(PUBLIC_PATH . '/assets/js/app.js') ? filemtime(PUBLIC_PATH . '/assets/js/app.js') : time() ?>"></script>
</body>
</html>
