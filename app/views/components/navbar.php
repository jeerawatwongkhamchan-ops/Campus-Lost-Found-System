<header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <!-- Brand Logo -->
        <a href="/reports/index.php" class="text-base sm:text-lg font-bold text-gray-900 hover:text-gray-700 transition">
            Campus Lost &amp; Found
        </a>

        <!-- Mobile Navigation Toggle -->
        <button id="nav-toggle" type="button" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 md:hidden" aria-label="Toggle navigation">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        <!-- Desktop Navigation Bar -->
        <nav class="hidden md:flex md:items-center md:gap-5">
            <a href="/reports/index.php" class="text-xs sm:text-sm font-medium text-gray-600 hover:text-gray-900 transition">รายงานสิ่งของ</a>
            <a href="/reports/create.php" class="text-xs sm:text-sm font-medium text-gray-600 hover:text-gray-900 transition">แจ้งพบ/สูญหาย</a>
            <?php if (is_authenticated()): ?>
                <a href="/claims/index.php" class="text-xs sm:text-sm font-medium text-gray-600 hover:text-gray-900 transition">การเคลม</a>
            <?php endif; ?>

            <?php if (is_authenticated() && (has_auth_role('admin') || has_auth_role('staff'))): ?>
                <a href="/admin/index.php" class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-800 hover:bg-gray-200 transition">
                    จัดการระบบ
                </a>
            <?php endif; ?>

            <span class="text-gray-200">|</span>

            <?php if (is_authenticated()): ?>
                <?php
                    $user = get_auth_user();
                    $unread_notifs = function_exists('get_unread_notifications_count') ? get_unread_notifications_count((int) $user['user_id']) : 0;
                ?>
                <!-- Notification Bell Icon with Badge -->
                <a href="/notifications.php" class="relative inline-flex items-center text-gray-500 hover:text-gray-900 transition p-1" title="การแจ้งเตือน">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white shadow-xs">
                            <?= $unread_notifs > 9 ? '9+' : $unread_notifs ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="/auth/profile.php" class="text-xs sm:text-sm font-medium text-gray-700 hover:text-gray-900 transition">
                    <?= e($user['full_name']) ?>
                </a>
                <a href="/auth/logout.php" class="text-xs sm:text-sm font-medium text-gray-500 hover:text-rose-600 transition">ออกจากระบบ</a>
            <?php else: ?>
                <a href="/auth/login.php" class="text-xs sm:text-sm font-medium text-gray-600 hover:text-gray-900 transition">เข้าสู่ระบบ</a>
                <a href="/auth/register.php" class="rounded-lg border border-gray-300 bg-white px-3.5 py-1.5 text-xs sm:text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 hover:border-gray-400 transition">
                    สมัครสมาชิก
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Mobile Dropdown Menu -->
    <nav id="nav-menu" class="hidden border-t border-gray-200 px-4 py-3 md:hidden bg-white">
        <div class="flex flex-col gap-2">
            <a href="/reports/index.php" class="py-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition">รายงานสิ่งของ</a>
            <a href="/reports/create.php" class="py-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition">แจ้งพบ/สูญหาย</a>
            <?php if (is_authenticated()): ?>
                <a href="/claims/index.php" class="py-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition">การเคลม</a>
            <?php endif; ?>

            <?php if (is_authenticated() && (has_auth_role('admin') || has_auth_role('staff'))): ?>
                <a href="/admin/index.php" class="py-1 text-sm font-semibold text-gray-800 hover:text-gray-900 transition">จัดการระบบ (Admin)</a>
            <?php endif; ?>

            <div class="my-1 border-t border-gray-100"></div>

            <?php if (is_authenticated()): ?>
                <?php
                    $user = get_auth_user();
                    $unread_notifs = function_exists('get_unread_notifications_count') ? get_unread_notifications_count((int) $user['user_id']) : 0;
                ?>
                <a href="/notifications.php" class="py-1 text-sm font-medium text-gray-600 hover:text-gray-900 flex items-center justify-between transition">
                    <span>การแจ้งเตือน</span>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="rounded-full bg-rose-600 px-2 py-0.5 text-xs font-bold text-white"><?= $unread_notifs ?></span>
                    <?php endif; ?>
                </a>
                <a href="/auth/profile.php" class="py-1 text-sm font-medium text-gray-700 hover:text-gray-900 transition">
                    โปรไฟล์ (<?= e($user['full_name']) ?>)
                </a>
                <a href="/auth/logout.php" class="py-1 text-sm font-medium text-rose-600 hover:text-rose-700 transition">ออกจากระบบ</a>
            <?php else: ?>
                <a href="/auth/login.php" class="py-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition">เข้าสู่ระบบ</a>
                <a href="/auth/register.php" class="py-1 text-sm font-medium text-gray-700 hover:text-gray-900 transition">สมัครสมาชิก</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
