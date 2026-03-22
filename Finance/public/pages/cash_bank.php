<?php
$conn = db_connect();

// Ensure there is at least one active bank account (very simple seed)
$conn->query("INSERT INTO bank_accounts (name, bank_name, opening_balance, is_active)
              SELECT 'Main Operating Account', 'Default Bank', 0, 1
              WHERE NOT EXISTS (SELECT 1 FROM bank_accounts)");

// Handle new bank transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = (int) ($_POST['bank_account_id'] ?? 0);
    $date = $_POST['txn_date'] ?? date('Y-m-d');
    $direction = $_POST['direction'] === 'IN' ? 'IN' : 'OUT';
    $description = trim($_POST['description'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($accountId > 0 && $amount > 0) {
        $stmt = $conn->prepare('INSERT INTO bank_transactions (bank_account_id, txn_date, description, direction, amount, source_module) VALUES (?, ?, ?, ?, ?, "MANUAL")');
        $stmt->bind_param('isssd', $accountId, $date, $description, $direction, $amount);
        $stmt->execute();
        $stmt->close();

        echo '<div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">Bank transaction recorded.</div>';
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please choose an account and amount &gt; 0.</div>';
    }
}

$accounts = $conn->query('SELECT id, name, bank_name, opening_balance FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC');

// For balances we calculate opening_balance + IN - OUT
$balances = [];
if ($accounts) {
    while ($row = $accounts->fetch_assoc()) {
        $balances[(int) $row['id']] = [
            'name' => $row['name'],
            'bank_name' => $row['bank_name'],
            'opening' => (float) $row['opening_balance'],
            'balance' => (float) $row['opening_balance'],
        ];
    }
    $accounts->data_seek(0);
}

$resTx = $conn->query('SELECT bank_account_id, direction, amount FROM bank_transactions');
if ($resTx) {
    while ($row = $resTx->fetch_assoc()) {
        $id = (int) $row['bank_account_id'];
        if (!isset($balances[$id])) {
            continue;
        }
        $amt = (float) $row['amount'];
        if ($row['direction'] === 'IN') {
            $balances[$id]['balance'] += $amt;
        } else {
            $balances[$id]['balance'] -= $amt;
        }
    }
}

$txResult = $conn->query('
    SELECT t.id, t.bank_account_id, t.txn_date, t.description, t.direction, t.amount, a.name AS account_name
    FROM bank_transactions t
    JOIN bank_accounts a ON a.id = t.bank_account_id
    ORDER BY t.txn_date DESC, t.id DESC
    LIMIT 100
');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cash &amp; Bank Management</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">View cash positions and record bank transactions.</p>
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Balances</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Opening balance plus inflows minus outflows.</p>
        </header>
        <div class="space-y-3">
            <?php foreach ($balances as $id => $row): ?>
                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900/60">
                    <div>
                        <div class="font-medium text-slate-800 dark:text-slate-100"><?= e($row['name']) ?></div>
                        <?php if (!empty($row['bank_name'])): ?>
                            <div class="text-slate-500 dark:text-slate-400"><?= e($row['bank_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Balance</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-slate-50">
                            <?= number_format($row['balance'], 2) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="my-4 border-slate-200 dark:border-slate-800">

        <header class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Record bank transaction</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Use this for manual cash movements or statement lines.</p>
        </header>
        <form method="post" class="space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Bank account</label>
                <select name="bank_account_id"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                    <?php if ($accounts && $accounts->num_rows > 0): ?>
                        <?php while ($acc = $accounts->fetch_assoc()): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Date</label>
                    <input type="date"
                           name="txn_date"
                           value="<?= e(date('Y-m-d')) ?>"
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Direction</label>
                    <select name="direction"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                        <option value="IN">IN (receipt)</option>
                        <option value="OUT">OUT (payment)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Amount</label>
                <input type="number"
                       step="0.01"
                       name="amount"
                       required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Description</label>
                <input type="text"
                       name="description"
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500"
                       placeholder="Statement reference (optional)">
            </div>
            <button class="inline-flex items-center justify-center rounded-full bg-sky-600 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-400/60 transition hover:bg-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/80 dark:shadow-sky-900/50">
                Save transaction
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent bank transactions</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Latest 100 cash movements across all active accounts.</p>
        </header>
        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                    <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Account</th>
                        <th class="px-4 py-2 font-medium">Description</th>
                        <th class="px-4 py-2 font-medium">Direction</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php if ($txResult && $txResult->num_rows > 0): ?>
                        <?php while ($row = $txResult->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                                <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['txn_date']) ?></td>
                                <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['account_name']) ?></td>
                                <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['description'] ?? '') ?></td>
                                <td class="px-4 py-2 align-middle">
                                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                        <?= $row['direction'] === 'IN' ? 'IN' : 'OUT' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                    <?= number_format((float) $row['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-slate-500 dark:text-slate-400">
                                No bank transactions recorded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

