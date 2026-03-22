<?php
$conn = db_connect();

// Very simple trial balance style view: sum debit/credit by account
$sql = '
    SELECT a.code, a.name,
           SUM(jl.debit) AS total_debit,
           SUM(jl.credit) AS total_credit
    FROM journal_lines jl
    JOIN accounts a ON a.id = jl.account_id
    JOIN journal_entries je ON je.id = jl.journal_entry_id
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
';

$result = $conn->query($sql);
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">General Ledger</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">High‑level trial balance by account.</p>
    </div>
</section>

<section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
    <header class="mb-4 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Trial balance by account</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Summarised debit and credit movements across chart of accounts.</p>
        </div>
    </header>
    <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
        <div class="max-h-[480px] overflow-auto">
            <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Account code</th>
                    <th class="px-4 py-2 font-medium">Account name</th>
                    <th class="px-4 py-2 text-right font-medium">Total debit</th>
                    <th class="px-4 py-2 text-right font-medium">Total credit</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['code']) ?></td>
                            <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['name']) ?></td>
                            <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) ($row['total_debit'] ?? 0), 2) ?>
                            </td>
                            <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) ($row['total_credit'] ?? 0), 2) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-slate-400">
                            No journal entries yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
