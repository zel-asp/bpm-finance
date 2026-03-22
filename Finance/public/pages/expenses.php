<?php
$conn = db_connect();

// Handle simple expense capture with soft budget check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee = trim($_POST['employee_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($employee !== '' && $department !== '' && $category !== '' && $amount > 0) {
        $overBudgetWarning = '';

        // Check against department budget for current year (if any)
        $year = (int) date('Y');
        $sqlBudget = sprintf(
            "SELECT SUM(total_amount) AS total FROM budgets WHERE fiscal_year = %d AND department = '%s'",
            $year,
            $conn->real_escape_string($department)
        );
        $budgetTotal = 0.0;
        if ($resB = $conn->query($sqlBudget)) {
            $row = $resB->fetch_assoc();
            $budgetTotal = (float) ($row['total'] ?? 0);
        }

        if ($budgetTotal > 0) {
            $sqlUsed = sprintf(
                "SELECT SUM(amount) AS used FROM expenses WHERE department = '%s' AND YEAR(submitted_at) = %d",
                $conn->real_escape_string($department),
                $year
            );
            $used = 0.0;
            if ($resU = $conn->query($sqlUsed)) {
                $rowU = $resU->fetch_assoc();
                $used = (float) ($rowU['used'] ?? 0);
            }
            $projected = $used + $amount;
            if ($projected > $budgetTotal) {
                $overBudgetWarning = sprintf(
                    'Warning: this will exceed the %s %d budget (%.2f &gt; %.2f).',
                    e($department),
                    $year,
                    $projected,
                    $budgetTotal
                );
            }
        }

        $stmt = $conn->prepare('INSERT INTO expenses (employee_name, department, category, description, amount, status) VALUES (?, ?, ?, ?, ?, "SUBMITTED")');
        $stmt->bind_param('ssssd', $employee, $department, $category, $description, $amount);
        $stmt->execute();
        $stmt->close();

        echo '<div class="mb-2 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">Expense recorded.</div>';
        if ($overBudgetWarning !== '') {
            echo '<div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-50 px-4 py-3 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-100">'
                . $overBudgetWarning .
                '</div>';
        }
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please fill all fields and amount &gt; 0.</div>';
    }
}

$result = $conn->query('SELECT id, employee_name, department, category, amount, status, submitted_at FROM expenses ORDER BY submitted_at DESC');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Expense Management</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Capture operating expenses before they flow into payables and disbursement.</p>
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Record expense</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Minimal details for downstream approval and payment.</p>
            </div>
        </header>
        <form method="post" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Employee / Requestor</label>
                    <input type="text"
                           name="employee_name"
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
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Category</label>
                    <input type="text"
                           name="category"
                           required
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Amount</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400 dark:text-slate-500">$</span>
                        <input type="number"
                               step="0.01"
                               name="amount"
                               required
                               class="block w-full rounded-xl border border-slate-300 bg-white px-7 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                    </div>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Description (optional)</label>
                <textarea name="description"
                          rows="2"
                          class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500"></textarea>
            </div>
            <button class="inline-flex items-center justify-center rounded-full bg-sky-600 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-400/60 transition hover:bg-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/80 dark:shadow-sky-900/50">
                Save Expense
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent expenses</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Submitted expenses ordered by recency.</p>
            </div>
        </header>
        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                    <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">Department</th>
                        <th class="px-4 py-2 font-medium">Category</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Submitted</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                                <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['employee_name']) ?></td>
                                <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['department']) ?></td>
                                <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['category']) ?></td>
                                <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                    <?= number_format((float) $row['amount'], 2) ?>
                                </td>
                                <td class="px-4 py-2 align-middle">
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                        <?= e($row['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 align-middle text-slate-600 dark:text-slate-400">
                                    <?= e($row['submitted_at']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500 dark:text-slate-400">
                                No expenses recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

