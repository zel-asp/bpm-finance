<?php
require_once __DIR__ . '/../includes/db.php';

$page = $_GET['page'] ?? 'dashboard';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

    <head>
        <meta charset="UTF-8">
        <title>Finance System</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                500: '#3b82f6',
                                600: '#2563eb',
                                700: '#1d4ed8'
                            }
                        }
                    }
                }
            }
        </script>
        <link rel="stylesheet" href="styles.css">
    </head>

    <body class="h-full min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50">
        <div
            class="min-h-screen bg-slate-50 text-slate-900 dark:bg-gradient-to-br dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:text-slate-100 flex">
            <aside
                class="hidden md:flex md:w-64 lg:w-72 flex-shrink-0 flex-col border-r border-slate-200/80 bg-white/90 backdrop-blur-xl px-5 py-6 dark:border-slate-800/70 dark:bg-slate-950/90">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <div
                            class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-500">
                            Integrated</div>
                        <div class="mt-1 text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-50">Finance
                            System</div>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-sky-500/40 bg-sky-500/10 px-2.5 py-1 text-[10px] font-medium uppercase tracking-wide text-sky-300">
                        v0.1
                    </span>
                </div>
                <nav class="space-y-1 text-sm">
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'dashboard' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=dashboard">
                        <span class="text-lg">🏠</span>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'budgets' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=budgets">
                        <span class="text-lg">📊</span>
                        <span class="font-medium">Budgets</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'ap' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=ap">
                        <span class="text-lg">📥</span>
                        <span class="font-medium">Accounts Payable</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'cash_bank' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=cash_bank">
                        <span class="text-lg">🏦</span>
                        <span class="font-medium">Cash &amp; Bank</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'expenses' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=expenses">
                        <span class="text-lg">🧾</span>
                        <span class="font-medium">Expenses</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'ar' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=ar">
                        <span class="text-lg">📤</span>
                        <span class="font-medium">Accounts Receivable</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'gl' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=gl">
                        <span class="text-lg">📚</span>
                        <span class="font-medium">General Ledger</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'disbursement' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=disbursement">
                        <span class="text-lg">💸</span>
                        <span class="font-medium">Disbursement</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'payroll' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=payroll">
                        <span class="text-lg">🧑‍💼</span>
                        <span class="font-medium">Payroll</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'reports' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=reports">
                        <span class="text-lg">📈</span>
                        <span class="font-medium">Reports</span>
                    </a>
                    <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 <?= $page === 'compensation' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                        href="?page=compensation">
                        <span class="text-lg">📈</span>
                        <span class="font-medium">Compensation</span>
                    </a>
                </nav>
                <div class="mt-auto pt-6 text-xs text-slate-500 border-t border-slate-200/80 dark:border-slate-800/80">
                    <p class="font-medium text-slate-700 dark:text-slate-500">Finance Operations Workspace</p>
                    <p class="mt-1 text-slate-500 dark:text-slate-600">Track budgets, payables, receivables and ledger
                        in one place.</p>
                </div>
            </aside>

            <div class="flex-1 flex flex-col">
                <!-- Mobile top bar -->
                <header
                    class="flex items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur-xl shadow-sm shadow-slate-200/60 md:hidden">
                    <div class="flex items-center gap-3">
                        <button id="mobileMenuButton"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500">
                            <span class="sr-only">Open navigation</span>
                            ☰
                        </button>
                        <div class="flex flex-col">
                            <span
                                class="text-[11px] font-medium uppercase tracking-[0.2em] text-slate-500 dark:text-slate-500">Integrated</span>
                            <span
                                class="mt-0.5 text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-50">Finance
                                System</span>
                        </div>
                    </div>
                    <button id="themeToggle"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm shadow-slate-200/80 transition hover:border-slate-400 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:shadow-slate-900/60">
                        🌙 Dark
                    </button>
                </header>

                <!-- Desktop top bar -->
                <header
                    class="hidden md:flex items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur-xl shadow-sm shadow-slate-200/60 dark:border-slate-800/80 dark:bg-slate-950/80">
                    <div class="flex flex-col">
                        <span
                            class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500 dark:text-slate-500">Integrated
                            Financial Management</span>
                        <span
                            class="mt-1 text-base font-semibold tracking-tight text-slate-900 dark:text-slate-50">Finance
                            Operations Workspace</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="themeToggleDesktop"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm shadow-slate-200/80 transition hover:border-slate-400 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-200 dark:shadow-slate-900/60">
                            🌙 Dark
                        </button>
                    </div>
                </header>

                <!-- Mobile slide-over navigation -->
                <div id="mobileNav" class="fixed inset-0 z-40 hidden md:hidden">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                    <div class="absolute inset-y-0 left-0 flex w-72 max-w-full">
                        <div
                            class="flex w-full flex-col border-r border-slate-200/80 bg-white px-5 py-6 shadow-2xl shadow-slate-900/40 dark:border-slate-800/80 dark:bg-slate-950">
                            <div class="mb-6 flex items-center justify-between">
                                <div>
                                    <div
                                        class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-500">
                                        Integrated</div>
                                    <div
                                        class="mt-1 text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                                        Finance System</div>
                                </div>
                                <button id="mobileNavClose"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-600 shadow-sm transition hover:border-slate-400 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/70 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500">
                                    ✕
                                </button>
                            </div>
                            <nav class="space-y-1 text-sm">
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'dashboard' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=dashboard">
                                    <span class="text-lg">🏠</span>
                                    <span class="font-medium">Dashboard</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'budgets' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=budgets">
                                    <span class="text-lg">📊</span>
                                    <span class="font-medium">Budgets</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'ap' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=ap">
                                    <span class="text-lg">📥</span>
                                    <span class="font-medium">Accounts Payable</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'cash_bank' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=cash_bank">
                                    <span class="text-lg">🏦</span>
                                    <span class="font-medium">Cash &amp; Bank</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'expenses' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=expenses">
                                    <span class="text-lg">🧾</span>
                                    <span class="font-medium">Expenses</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'ar' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=ar">
                                    <span class="text-lg">📤</span>
                                    <span class="font-medium">Accounts Receivable</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'gl' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=gl">
                                    <span class="text-lg">📚</span>
                                    <span class="font-medium">General Ledger</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'disbursement' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=disbursement">
                                    <span class="text-lg">💸</span>
                                    <span class="font-medium">Disbursement</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'payroll' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=payroll">
                                    <span class="text-lg">🧑‍💼</span>
                                    <span class="font-medium">Payroll</span>
                                </a>
                                <a class="flex items-center gap-2 rounded-xl px-3 py-2.5 transition <?= $page === 'reports' ? 'bg-slate-900/5 text-slate-900 shadow-sm shadow-slate-200/70 dark:bg-slate-800/80 dark:text-slate-50 dark:shadow-slate-900/70' : 'text-slate-600 hover:bg-slate-900/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-slate-50' ?>"
                                    href="?page=reports">
                                    <span class="text-lg">📈</span>
                                    <span class="font-medium">Reports</span>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-6xl">
                        <?php
                        $file = __DIR__ . '/pages/' . basename($page) . '.php';
                        if (file_exists($file)) {
                            include $file;
                        } else {
                            echo '<div class="rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100">Page not found.</div>';
                        }
                        ?>
                    </div>
                </main>
            </div>
        </div>

        <script>
                (function () {
                    const root = document.documentElement;
                    const toggleBtnMobile = document.getElementById('themeToggle');
                    const toggleBtnDesktop = document.getElementById('themeToggleDesktop');
                    const mobileMenuButton = document.getElementById('mobileMenuButton');
                    const mobileNav = document.getElementById('mobileNav');
                    const mobileNavClose = document.getElementById('mobileNavClose');

                    function applyTheme(theme) {
                        if (theme === 'light') {
                            root.classList.remove('dark');
                            if (toggleBtnMobile) toggleBtnMobile.textContent = '☀️ Light';
                            if (toggleBtnDesktop) toggleBtnDesktop.textContent = '☀️ Light';
                        } else {
                            root.classList.add('dark');
                            if (toggleBtnMobile) toggleBtnMobile.textContent = '🌙 Dark';
                            if (toggleBtnDesktop) toggleBtnDesktop.textContent = '🌙 Dark';
                        }
                    }

                    const saved = localStorage.getItem('financeTheme') || 'dark';
                    applyTheme(saved);

                    function handleToggleClick() {
                        const next = root.classList.contains('dark') ? 'light' : 'dark';
                        localStorage.setItem('financeTheme', next);
                        applyTheme(next);
                    }

                    if (toggleBtnMobile) {
                        toggleBtnMobile.addEventListener('click', handleToggleClick);
                    }
                    if (toggleBtnDesktop) {
                        toggleBtnDesktop.addEventListener('click', handleToggleClick);
                    }

                    function openMobileNav() {
                        if (!mobileNav) return;
                        mobileNav.classList.remove('hidden');
                    }

                    function closeMobileNav() {
                        if (!mobileNav) return;
                        mobileNav.classList.add('hidden');
                    }

                    if (mobileMenuButton) {
                        mobileMenuButton.addEventListener('click', openMobileNav);
                    }
                    if (mobileNavClose) {
                        mobileNavClose.addEventListener('click', closeMobileNav);
                    }
                    if (mobileNav) {
                        mobileNav.addEventListener('click', function (event) {
                            if (event.target === mobileNav) {
                                closeMobileNav();
                            }
                        });
                    }
                })();
        </script>
    </body>

</html>