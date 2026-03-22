<?php
$conn = db_connect();

// Handle simple budget creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = (int) ($_POST['fiscal_year'] ?? date('Y'));
    $department = trim($_POST['department'] ?? '');
    $amount = (float) ($_POST['total_amount'] ?? 0);

    if ($department !== '' && $amount > 0) {
        $stmt = $conn->prepare('INSERT INTO budgets (fiscal_year, department, total_amount, status) VALUES (?, ?, ?, "DRAFT")');
        $stmt->bind_param('isd', $year, $department, $amount);
        $stmt->execute();
        $stmt->close();

        echo '<div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">Budget created.</div>';
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please provide department and amount &gt; 0.</div>';
    }
}

$result = $conn->query('SELECT id, fiscal_year, department, status, total_amount, created_at FROM budgets ORDER BY created_at DESC');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Budgets</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Capture fiscal envelopes and see them at a glance.</p>
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Create budget</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Quick capture for a new departmental envelope.</p>
            </div>
        </header>
        <form method="post" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Fiscal year</label>
                <input type="number"
                       name="fiscal_year"
                       value="<?= e(date('Y')) ?>"
                       required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Department</label>
                <input type="text"
                       name="department"
                       required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Total amount</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400 dark:text-slate-500">$</span>
                    <input type="number"
                           step="0.01"
                           name="total_amount"
                           required
                           class="block w-full rounded-xl border border-slate-300 bg-white px-7 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                </div>
            </div>
            <button class="inline-flex items-center justify-center rounded-full bg-sky-600 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-400/60 transition hover:bg-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/80 dark:shadow-sky-900/50">
                Save budget
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Existing budgets</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Recently created envelopes ordered by recency.</p>
            </div>
        </header>
        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                    <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">ID</th>
                        <th class="px-4 py-2 font-medium">Year</th>
                        <th class="px-4 py-2 font-medium">Department</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">Total</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= (int) $row['id'] ?></td>
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['fiscal_year']) ?></td>
                            <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['department']) ?></td>
                            <td class="px-4 py-2 align-middle">
                                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) $row['total_amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
