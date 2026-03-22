<?php
require_once __DIR__ . '/../../includes/finance.php';
$conn = db_connect();

// When a payment is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice_id'])) {
    $invoiceId = (int) $_POST['pay_invoice_id'];
    $paymentDate = $_POST['payment_date'] ?: date('Y-m-d');
    $amount = (float) ($_POST['payment_amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'BANK_TRANSFER';
    $reference = trim($_POST['reference'] ?? '');

    if ($invoiceId > 0 && $amount > 0) {
        $stmt = $conn->prepare('INSERT INTO ap_payments (ap_invoice_id, payment_date, amount, method, reference) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isdss', $invoiceId, $paymentDate, $amount, $method, $reference);
        $stmt->execute();
        $paymentId = $stmt->insert_id;
        $stmt->close();

        // Mark invoice as paid
        $stmt = $conn->prepare('UPDATE ap_invoices SET status = "PAID" WHERE id = ?');
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $stmt->close();

        // Mirror into Cash & Bank if a default bank account exists
        $bankAccountId = null;
        $resBank = $conn->query('SELECT id FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        if ($resBank && $rowBank = $resBank->fetch_assoc()) {
            $bankAccountId = (int) $rowBank['id'];
        }
        if ($bankAccountId) {
            $stmt = $conn->prepare('INSERT INTO bank_transactions (bank_account_id, txn_date, description, direction, amount, source_module, source_id) VALUES (?, ?, ?, "OUT", ?, "AP_DISBURSEMENT", ?)');
            $desc = 'Payment for AP invoice #' . $invoiceId;
            $stmt->bind_param('issdi', $bankAccountId, $paymentDate, $desc, $amount, $paymentId);
            $stmt->execute();
            $stmt->close();
        }

        // GL posting (simple: credit cash, debit AP) if codes exist
        $apAccountId = find_account_id_by_code($conn, '2000'); // Accounts Payable
        $cashAccountId = find_account_id_by_code($conn, '1000'); // Cash / Bank
        if ($apAccountId && $cashAccountId) {
            post_journal_entry(
                $conn,
                $paymentDate,
                'AP payment for invoice #' . $invoiceId,
                [
                    ['account_id' => $apAccountId, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount],
                ],
                'AP_DISBURSEMENT',
                $paymentId
            );
        }

        echo '<div class="mb-4 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">Payment recorded and invoice marked as PAID.</div>';
    } else {
        echo '<div class="mb-4 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">Please provide a valid amount.</div>';
    }
}

// List unpaid / pending invoices
$result = $conn->query('
    SELECT ap.id, v.name AS vendor_name, ap.invoice_number, ap.invoice_date, ap.amount, ap.status
    FROM ap_invoices ap
    JOIN vendors v ON v.id = ap.vendor_id
    WHERE ap.status <> "PAID"
    ORDER BY ap.invoice_date ASC, ap.id ASC
');
?>

<section class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Disbursement / Payment processing</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Clear pending supplier invoices and record outgoing payments.</p>
    </div>
</section>

<section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
    <header class="mb-4 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pending supplier invoices</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Invoices not yet marked as paid.</p>
        </div>
    </header>
    <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
        <div class="max-h-[520px] overflow-auto">
            <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                <thead class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">ID</th>
                    <th class="px-4 py-2 font-medium">Vendor</th>
                    <th class="px-4 py-2 font-medium">Invoice #</th>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 text-right font-medium">Amount</th>
                    <th class="px-4 py-2 font-medium">Disburse</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="align-top hover:bg-slate-100 dark:hover:bg-slate-900/60">
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= (int) $row['id'] ?></td>
                            <td class="px-4 py-2 text-slate-800 dark:text-slate-200"><?= e($row['vendor_name']) ?></td>
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($row['invoice_number']) ?></td>
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-300"><?= e($row['invoice_date']) ?></td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-800 dark:border-slate-600/80 dark:bg-slate-800/80 dark:text-slate-100">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right text-slate-900 dark:text-slate-100">
                                <?= number_format((float) $row['amount'], 2) ?>
                            </td>
                            <td class="px-4 py-2">
                                <form method="post" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="pay_invoice_id" value="<?= (int) $row['id'] ?>">
                                    <input type="date"
                                           name="payment_date"
                                           value="<?= e(date('Y-m-d')) ?>"
                                           class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                    <input type="number"
                                           step="0.01"
                                           name="payment_amount"
                                           value="<?= number_format((float) $row['amount'], 2, '.', '') ?>"
                                           class="h-8 w-24 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                    <select name="payment_method"
                                            class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60">
                                        <option value="BANK_TRANSFER">Bank transfer</option>
                                        <option value="CHECK">Check</option>
                                        <option value="CASH">Cash</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                    <input type="text"
                                           name="reference"
                                           placeholder="Ref #"
                                           class="h-8 w-28 rounded-lg border border-slate-300 bg-white px-2 text-[11px] text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50 dark:shadow-slate-950/60 dark:placeholder:text-slate-500">
                                    <button class="inline-flex h-8 items-center justify-center rounded-full bg-emerald-600 px-3 text-[11px] font-medium text-white shadow-md shadow-emerald-400/60 transition hover:bg-emerald-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/80 dark:shadow-emerald-900/50">
                                        Pay
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-4 py-4 text-center text-slate-400">
                            No pending invoices for disbursement.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
