<?php
require_once __DIR__ . '/../../includes/finance.php';
$conn = db_connect();

// Handle AR invoice creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_number'])) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($customerName !== '' && $invoiceNumber !== '' && $amount > 0) {
        // Ensure customer exists or create new
        $stmt = $conn->prepare('SELECT id FROM customers WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $customerName);
        $stmt->execute();
        $stmt->bind_result($customerId);
        if ($stmt->fetch()) {
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO customers (name) VALUES (?)');
            $stmt->bind_param('s', $customerName);
            $stmt->execute();
            $customerId = $stmt->insert_id;
            $stmt->close();
        }

        $stmt = $conn->prepare('INSERT INTO ar_invoices (customer_id, invoice_number, invoice_date, amount, status) VALUES (?, ?, ?, ?, "PENDING")');
        $stmt->bind_param('issd', $customerId, $invoiceNumber, $invoiceDate, $amount);
        $stmt->execute();
        $stmt->close();

        echo '<div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">AR Invoice recorded.</div>';
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please fill all fields and amount &gt; 0.</div>';
    }
}

// Handle AR receipt (collection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_invoice_id'])) {
    $invoiceId = (int) $_POST['receipt_invoice_id'];
    $receiptDate = $_POST['receipt_date'] ?? date('Y-m-d');
    $amount = (float) ($_POST['receipt_amount'] ?? 0);
    $method = $_POST['receipt_method'] ?? 'BANK_TRANSFER';
    $reference = trim($_POST['receipt_reference'] ?? '');

    if ($invoiceId > 0 && $amount > 0) {
        $stmt = $conn->prepare('INSERT INTO ar_receipts (ar_invoice_id, receipt_date, amount, method, reference) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isdss', $invoiceId, $receiptDate, $amount, $method, $reference);
        $stmt->execute();
        $receiptId = $stmt->insert_id;
        $stmt->close();

        // Mark invoice as paid if fully settled (simple: single receipt)
        $stmt = $conn->prepare('UPDATE ar_invoices SET status = "PAID" WHERE id = ?');
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $stmt->close();

        // Mirror into Cash & Bank if default bank account exists
        $bankAccountId = null;
        $resBank = $conn->query('SELECT id FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        if ($resBank && $rowBank = $resBank->fetch_assoc()) {
            $bankAccountId = (int) $rowBank['id'];
        }
        if ($bankAccountId) {
            $stmt = $conn->prepare('INSERT INTO bank_transactions (bank_account_id, txn_date, description, direction, amount, source_module, source_id) VALUES (?, ?, ?, "IN", ?, "AR_RECEIPT", ?)');
            $desc = 'Receipt for AR invoice #' . $invoiceId;
            $stmt->bind_param('issdi', $bankAccountId, $receiptDate, $desc, $amount, $receiptId);
            $stmt->execute();
            $stmt->close();
        }

        // GL posting: Dr Cash, Cr AR
        $arAccountId = find_account_id_by_code($conn, '1100'); // Accounts Receivable
        $cashAccountId = find_account_id_by_code($conn, '1000'); // Cash / Bank
        if ($arAccountId && $cashAccountId) {
            post_journal_entry(
                $conn,
                $receiptDate,
                'AR receipt for invoice #' . $invoiceId,
                [
                    ['account_id' => $cashAccountId, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $arAccountId, 'debit' => 0, 'credit' => $amount],
                ],
                'AR_RECEIPT',
                $receiptId
            );
        }

        echo '<div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">Receipt recorded and invoice marked as PAID.</div>';
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please provide a valid receipt amount.</div>';
    }
}

// Recent invoices (all)
$result = $conn->query('
    SELECT ar.id, c.name AS customer_name, ar.invoice_number, ar.invoice_date, ar.status, ar.amount
    FROM ar_invoices ar
    JOIN customers c ON c.id = ar.customer_id
    ORDER BY ar.invoice_date DESC, ar.id DESC
');

// Open invoices for collection
$openResult = $conn->query('
    SELECT ar.id, c.name AS customer_name, ar.invoice_number, ar.invoice_date, ar.amount, ar.status
    FROM ar_invoices ar
    JOIN customers c ON c.id = ar.customer_id
    WHERE ar.status <> "PAID"
    ORDER BY ar.invoice_date ASC, ar.id ASC
');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Accounts Receivable</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Generate customer invoices and see expected inflows.</p>
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Generate customer invoice</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Fast capture for an outbound receivable.</p>
            </div>
        </header>
        <form method="post" class="space-y-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Customer name</label>
                <input type="text"
                       name="customer_name"
                       required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Invoice number</label>
                <input type="text"
                       name="invoice_number"
                       required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Invoice date</label>
                    <input type="date"
                           name="invoice_date"
                           value="<?= e(date('Y-m-d')) ?>"
                           required
                           class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Amount</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400 dark:text-slate-500">$</span>
                        <input type="number"
                               step="0.01"
                               name="amount"
                               required
                               class="block w-full rounded-xl border border-slate-300 bg-white px-7 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                    </div>
                </div>
            </div>
            <button class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-emerald-400/60 transition hover:bg-emerald-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 dark:shadow-emerald-900/50">
                Save invoice
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
        <header class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent AR invoices</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Customer invoices ordered by date and recency.</p>
            </div>
        </header>
        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                    <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">ID</th>
                        <th class="px-4 py-2 font-medium">Customer</th>
                        <th class="px-4 py-2 font-medium">Invoice #</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">Amount</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= (int) $row['id'] ?></td>
                            <td class="px-4 py-2 align-middle text-slate-800 dark:text-slate-200"><?= e($row['customer_name']) ?></td>
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['invoice_number']) ?></td>
                            <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300"><?= e($row['invoice_date']) ?></td>
                            <td class="px-4 py-2 align-middle">
                                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) $row['amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<section class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
    <header class="mb-4 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Collections – pending invoices</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Record customer receipts and clear open AR.</p>
        </div>
    </header>
    <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
        <div class="max-h-[420px] overflow-auto">
            <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Customer</th>
                    <th class="px-4 py-2 font-medium">Invoice #</th>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 text-right font-medium">Amount</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Collect</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                <?php if ($openResult && $openResult->num_rows > 0): ?>
                    <?php while ($row = $openResult->fetch_assoc()): ?>
                        <tr class="align-top hover:bg-slate-100 dark:hover:bg-slate-900/60">
                            <td class="px-4 py-2 text-slate-800 dark:text-slate-200"><?= e($row['customer_name']) ?></td>
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($row['invoice_number']) ?></td>
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($row['invoice_date']) ?></td>
                            <td class="px-4 py-2 text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) $row['amount'], 2) ?>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <form method="post" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="receipt_invoice_id" value="<?= (int) $row['id'] ?>">
                                    <input type="date"
                                           name="receipt_date"
                                           value="<?= e(date('Y-m-d')) ?>"
                                           class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                    <input type="number"
                                           step="0.01"
                                           name="receipt_amount"
                                           value="<?= number_format((float) $row['amount'], 2, '.', '') ?>"
                                           class="h-8 w-24 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                    <select name="receipt_method"
                                            class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                        <option value="BANK_TRANSFER">Bank transfer</option>
                                        <option value="CHECK">Check</option>
                                        <option value="CASH">Cash</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                    <input type="text"
                                           name="receipt_reference"
                                           placeholder="Ref #"
                                           class="h-8 w-28 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                                    <button class="inline-flex h-8 items-center justify-center rounded-full bg-emerald-600 px-3 text-[11px] font-medium text-white shadow-md shadow-emerald-400/60 transition hover:bg-emerald-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 dark:shadow-emerald-900/50">
                                        Receive
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-4 text-center text-slate-500 dark:text-slate-400">
                            No open invoices for collection.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
