<?php
$conn = db_connect();

// High-level aggregates
$totals = [
    'budget_total' => 0,
    'expenses_total' => 0,
    'ap_outstanding' => 0,
    'ar_outstanding' => 0,
];

if ($res = $conn->query('SELECT SUM(total_amount) AS t FROM budgets')) {
    $row = $res->fetch_assoc();
    $totals['budget_total'] = (float) ($row['t'] ?? 0);
}
if ($res = $conn->query('SELECT SUM(amount) AS t FROM expenses')) {
    $row = $res->fetch_assoc();
    $totals['expenses_total'] = (float) ($row['t'] ?? 0);
}
if ($res = $conn->query('SELECT SUM(amount) AS t FROM ap_invoices WHERE status <> "PAID"')) {
    $row = $res->fetch_assoc();
    $totals['ap_outstanding'] = (float) ($row['t'] ?? 0);
}
if ($res = $conn->query('SELECT SUM(amount) AS t FROM ar_invoices WHERE status <> "PAID"')) {
    $row = $res->fetch_assoc();
    $totals['ar_outstanding'] = (float) ($row['t'] ?? 0);
}

// Budget vs actual by department
$deptRows = $conn->query('
    SELECT b.department,
           SUM(b.total_amount) AS budget_total,
           COALESCE(SUM(e.amount), 0) AS expenses_total
    FROM budgets b
    LEFT JOIN expenses e ON e.department = b.department
    GROUP BY b.department
    ORDER BY b.department
');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Analytics &amp; Reports</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">High-level KPIs across budgets, expenses, payables, receivables, and cash.</p>
    </div>
</section>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950/60 dark:shadow-slate-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Budget</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-50">
            <?= number_format($totals['budget_total'], 2) ?>
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950/60 dark:shadow-slate-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Recorded Expenses</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-50">
            <?= number_format($totals['expenses_total'], 2) ?>
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950/60 dark:shadow-slate-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">AP Outstanding</p>
        <p class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-300">
            <?= number_format($totals['ap_outstanding'], 2) ?>
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950/60 dark:shadow-slate-900/60">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">AR Outstanding</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">
            <?= number_format($totals['ar_outstanding'], 2) ?>
        </p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Budget vs Actual by Department</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Departmental budgets compared to recorded expenses.</p>
        </header>
        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                    <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Department</th>
                        <th class="px-4 py-2 text-right font-medium">Budget</th>
                        <th class="px-4 py-2 text-right font-medium">Actual</th>
                        <th class="px-4 py-2 text-right font-medium">Variance</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php if ($deptRows && $deptRows->num_rows > 0): ?>
                        <?php while ($row = $deptRows->fetch_assoc()): ?>
                            <?php
                            $budget = (float) $row['budget_total'];
                            $actual = (float) $row['expenses_total'];
                            $variance = $budget - $actual;
                            ?>
                            <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                                <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['department']) ?></td>
                                <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                    <?= number_format($budget, 2) ?>
                                </td>
                                <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                    <?= number_format($actual, 2) ?>
                                </td>
                                <td class="px-4 py-2 align-middle text-right <?= $variance >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' ?>">
                                    <?= number_format($variance, 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-slate-500 dark:text-slate-400">
                                No budget data yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Narrative</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">How this maps to your BPA diagram.</p>
        </header>
        <ul class="space-y-2 text-xs text-slate-600 dark:text-slate-400">
            <li><strong class="font-semibold">Budget Planning &amp; Control</strong> – totals from the Budget module feed the Budget vs Actual view.</li>
            <li><strong class="font-semibold">Expense Management</strong> – expenses captured in the Expenses module reduce available budget.</li>
            <li><strong class="font-semibold">Accounts Payable</strong> – outstanding AP shows future cash outflows.</li>
            <li><strong class="font-semibold">Accounts Receivable</strong> – outstanding AR shows expected inflows.</li>
            <li><strong class="font-semibold">Cash &amp; Bank</strong> – bank transactions reflect realised movements once payments and receipts are processed.</li>
            <li><strong class="font-semibold">Payroll</strong> – posted payroll batches feed the General Ledger via journal entries.</li>
        </ul>
    </section>
</div>

