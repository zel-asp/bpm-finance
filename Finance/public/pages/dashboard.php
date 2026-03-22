<?php
$conn = db_connect();

$counts = [
    'budgets' => 0,
    'ap' => 0,
    'ar' => 0,
    'expenses' => 0,
    'payroll' => 0,
    'requisitions' => 0,
];

$totals = [
    'cash_balance' => 0.0,
];

if ($result = $conn->query('SELECT COUNT(*) AS c FROM budgets')) {
    $counts['budgets'] = (int) $result->fetch_assoc()['c'];
}
if ($result = $conn->query('SELECT COUNT(*) AS c FROM ap_invoices')) {
    $counts['ap'] = (int) $result->fetch_assoc()['c'];
}
if ($result = $conn->query('SELECT COUNT(*) AS c FROM ar_invoices')) {
    $counts['ar'] = (int) $result->fetch_assoc()['c'];
}
if ($result = $conn->query('SELECT COUNT(*) AS c FROM expenses')) {
    $counts['expenses'] = (int) $result->fetch_assoc()['c'];
}
if ($result = $conn->query('SELECT COUNT(*) AS c FROM payroll_batches')) {
    $counts['payroll'] = (int) $result->fetch_assoc()['c'];
}

$requisitions = [];
$pendingRequisitions = 0;
$approvedRequisitions = 0;
$rejectedRequisitions = 0;
$financeRequisitions = [];

function fetchRequisitionsFromHr()
{
    $apiBase = 'https://humanresource.up.railway.app/api';
    $apiKey = 'finance_system_2026_key_67890';
    $url = $apiBase . '/job-requisition.php?api_key=' . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            return $data['data'] ?? [];
        }
    }
    return [];
}

$allRequisitions = fetchRequisitionsFromHr();

foreach ($allRequisitions as $req) {
    if (isset($req['department']) && strtolower($req['department']) === 'finance') {
        $financeRequisitions[] = $req;

        if ($req['status'] === 'pending') {
            $pendingRequisitions++;
        } elseif ($req['status'] === 'approved') {
            $approvedRequisitions++;
        } elseif ($req['status'] === 'rejected') {
            $rejectedRequisitions++;
        }
    }
}
$counts['requisitions'] = count($financeRequisitions);

$message = '';
$error = '';

if (isset($_POST['cancel_requisition'])) {
    $requisitionId = (int) $_POST['requisition_id'];
    if ($requisitionId > 0) {
        $apiBase = 'https://humanresource.up.railway.app/api';
        $apiKey = 'finance_system_2026_key_67890';
        $url = $apiBase . '/job-requisition.php?api_key=' . $apiKey . '&id=' . $requisitionId;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                $message = "Requisition #{$requisitionId} cancelled successfully.";
                echo "<meta http-equiv='refresh' content='1'>";
            } else {
                $error = "Failed to cancel requisition: " . ($data['error'] ?? 'Unknown error');
            }
        } else {
            $error = "Failed to connect to HR API. HTTP Code: " . $httpCode;
        }
    } else {
        $error = "Invalid requisition ID.";
    }
}

$resAccounts = $conn->query('SELECT id, opening_balance FROM bank_accounts WHERE is_active = 1');
if ($resAccounts) {
    $balances = [];
    while ($row = $resAccounts->fetch_assoc()) {
        $balances[(int) $row['id']] = (float) $row['opening_balance'];
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
                $balances[$id] += $amt;
            } else {
                $balances[$id] -= $amt;
            }
        }
    }
    $totals['cash_balance'] = array_sum($balances);
}
?>

<?php if ($message): ?>
    <div
        class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div
        class="mb-6 rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<section class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Operations overview</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            High‑level snapshot across budgets, expenses, payables, receivables, cash and payroll.
        </p>
    </div>
    <button onclick="openRequisitionModal()"
        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-lg shadow-emerald-200/70 transition hover:bg-emerald-700 dark:shadow-emerald-900/40">
        <i class="fas fa-clipboard-list"></i>
        New Job Requisition
    </button>
</section>

<div class="grid gap-6 md:grid-cols-3 lg:grid-cols-4">
    <a href="?page=budgets"
        class="group relative overflow-hidden rounded-2xl border border-sky-400/40 bg-sky-50 px-5 py-4 shadow-lg shadow-sky-200/70 transition hover:-translate-y-0.5 hover:border-sky-500/70 hover:bg-sky-100 dark:border-sky-500/30 dark:bg-sky-500/10 dark:shadow-sky-900/40 dark:hover:border-sky-400/70 dark:hover:bg-sky-500/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-sky-700 dark:text-sky-200/80">Budgets</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-sky-50">
                    <?= (int) $counts['budgets'] ?>
                </p>
            </div>
            <div
                class="rounded-full bg-sky-100 p-3 text-sky-600 shadow-inner shadow-sky-200/80 dark:bg-sky-500/20 dark:text-sky-100 dark:shadow-sky-900/60">
                📊
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-sky-100/80">Create and monitor departmental spending envelopes.
        </p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-sky-100/90">
            View budgets
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <a href="?page=ap"
        class="group relative overflow-hidden rounded-2xl border border-emerald-400/40 bg-emerald-50 px-5 py-4 shadow-lg shadow-emerald-200/70 transition hover:-translate-y-0.5 hover:border-emerald-500/70 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:shadow-emerald-900/40 dark:hover:border-emerald-400/70 dark:hover:bg-emerald-500/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-200/80">
                    Accounts Payable</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-emerald-50">
                    <?= (int) $counts['ap'] ?>
                </p>
            </div>
            <div
                class="rounded-full bg-emerald-100 p-3 text-emerald-600 shadow-inner shadow-emerald-200/80 dark:bg-emerald-500/20 dark:text-emerald-100 dark:shadow-emerald-900/60">
                📥
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-emerald-100/80">Track outstanding supplier invoices awaiting
            disbursement.</p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-emerald-100/90">
            View AP invoices
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <a href="?page=ar"
        class="group relative overflow-hidden rounded-2xl border border-amber-300/60 bg-amber-50 px-5 py-4 shadow-lg shadow-amber-200/70 transition hover:-translate-y-0.5 hover:border-amber-400/80 hover:bg-amber-100 dark:border-amber-400/40 dark:bg-amber-400/10 dark:shadow-amber-900/40 dark:hover:border-amber-300/80 dark:hover:bg-amber-400/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-amber-700 dark:text-amber-100/80">
                    Accounts Receivable</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-amber-50">
                    <?= (int) $counts['ar'] ?>
                </p>
            </div>
            <div
                class="rounded-full bg-amber-100 p-3 text-amber-600 shadow-inner shadow-amber-200/80 dark:bg-amber-400/20 dark:text-amber-100 dark:shadow-amber-900/60">
                📤
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-amber-100/80">Monitor customer invoices and expected inflows.
        </p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-amber-100/90">
            View AR invoices
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <a href="?page=expenses"
        class="group relative overflow-hidden rounded-2xl border border-rose-300/60 bg-rose-50 px-5 py-4 shadow-lg shadow-rose-200/70 transition hover:-translate-y-0.5 hover:border-rose-400/80 hover:bg-rose-100 dark:border-rose-400/40 dark:bg-rose-400/10 dark:shadow-rose-900/40 dark:hover:border-rose-300/80 dark:hover:bg-rose-400/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-rose-700 dark:text-rose-100/80">Expenses
                </p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-rose-50">
                    <?= (int) $counts['expenses'] ?>
                </p>
            </div>
            <div
                class="rounded-full bg-rose-100 p-3 text-rose-600 shadow-inner shadow-rose-200/80 dark:bg-rose-400/20 dark:text-rose-100 dark:shadow-rose-900/60">
                🧾
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-rose-100/80">Captured operating expenses awaiting approval and
            payment.</p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-rose-100/90">
            Go to Expenses
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <a href="?page=cash_bank"
        class="group relative overflow-hidden rounded-2xl border border-slate-300/70 bg-slate-50 px-5 py-4 shadow-lg shadow-slate-200/70 transition hover:-translate-y-0.5 hover:border-slate-400/80 hover:bg-slate-100 dark:border-slate-500/60 dark:bg-slate-900/40 dark:shadow-slate-900/60">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-700 dark:text-slate-200/80">Cash
                    &amp; Bank</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-50">
                    <?= number_format($totals['cash_balance'], 2) ?>
                </p>
            </div>
            <div
                class="rounded-full bg-slate-100 p-3 text-slate-700 shadow-inner shadow-slate-200/80 dark:bg-slate-800/80 dark:text-slate-100 dark:shadow-slate-900/60">
                🏦
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-slate-200/80">Aggregate cash balance across all active bank
            accounts.</p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-slate-100/90">
            View Cash &amp; Bank
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <a href="?page=payroll"
        class="group relative overflow-hidden rounded-2xl border border-emerald-300/60 bg-emerald-50 px-5 py-4 shadow-lg shadow-emerald-200/70 transition hover:-translate-y-0.5 hover:border-emerald-400/80 hover:bg-emerald-100 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:shadow-emerald-900/40 dark:hover:border-emerald-300/80 dark:hover:bg-emerald-500/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-100/80">
                    Payroll Batches</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-emerald-50">
                    <?= (int) $counts['payroll'] ?>
                </p>
            </div>
            <div
                class="rounded-full bg-emerald-100 p-3 text-emerald-600 shadow-inner shadow-emerald-200/80 dark:bg-emerald-500/20 dark:text-emerald-100 dark:shadow-emerald-900/60">
                🧑‍💼
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-emerald-100/80">HR payroll batches captured and posted into the
            ledger.</p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-emerald-100/90">
            View Payroll
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>

    <div
        class="relative overflow-hidden rounded-2xl border border-indigo-300/60 bg-indigo-50 px-5 py-4 shadow-lg shadow-indigo-200/70 dark:border-indigo-400/40 dark:bg-indigo-500/10 dark:shadow-indigo-900/40">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-indigo-700 dark:text-indigo-200/80">Job
                    Requisitions</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-indigo-50">
                    <?= (int) $counts['requisitions'] ?>
                </p>
                <div class="flex gap-1 mt-1">
                    <span class="inline-flex items-center text-xs">
                        <span class="w-2 h-2 bg-yellow-500 rounded-full mr-1"></span>
                        <span class="text-slate-600 dark:text-slate-400"><?= $pendingRequisitions ?></span>
                    </span>
                    <span class="text-slate-300 dark:text-slate-600">•</span>
                    <span class="inline-flex items-center text-xs">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                        <span class="text-slate-600 dark:text-slate-400"><?= $approvedRequisitions ?></span>
                    </span>
                    <span class="text-slate-300 dark:text-slate-600">•</span>
                    <span class="inline-flex items-center text-xs">
                        <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                        <span class="text-slate-600 dark:text-slate-400"><?= $rejectedRequisitions ?></span>
                    </span>
                </div>
            </div>
            <div
                class="rounded-full bg-indigo-100 p-3 text-indigo-600 shadow-inner shadow-indigo-200/80 dark:bg-indigo-500/20 dark:text-indigo-100 dark:shadow-indigo-900/60">
                📋
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-indigo-100/80">Finance department job requisitions.</p>
        <button onclick="openRequisitionModal()"
            class="mt-4 inline-flex items-center text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:text-indigo-800">
            <i class="fas fa-plus-circle mr-1"></i> Create new
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </button>
    </div>

    <a href="?page=reports"
        class="group relative overflow-hidden rounded-2xl border border-cyan-300/60 bg-cyan-50 px-5 py-4 shadow-lg shadow-cyan-200/70 transition hover:-translate-y-0.5 hover:border-cyan-400/80 hover:bg-cyan-100 dark:border-cyan-400/40 dark:bg-cyan-500/10 dark:shadow-cyan-900/40 dark:hover:border-cyan-300/80 dark:hover:bg-cyan-500/20">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-cyan-700 dark:text-cyan-100/80">Analytics
                    &amp; Reports</p>
                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-cyan-50">
                    Budget vs Actual • AP / AR • Cash
                </p>
            </div>
            <div
                class="rounded-full bg-cyan-100 p-3 text-cyan-600 shadow-inner shadow-cyan-200/80 dark:bg-cyan-500/20 dark:text-cyan-100 dark:shadow-cyan-900/60">
                📈
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-700 dark:text-cyan-100/80">Drill into KPIs powered by your integrated modules.
        </p>
        <span class="mt-4 inline-flex items-center text-xs font-medium text-slate-800 dark:text-cyan-100/90">
            Open Reports
            <span class="ml-1 transition-transform group-hover:translate-x-0.5">→</span>
        </span>
    </a>
</div>

<div class="mt-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-clipboard-list text-indigo-600 dark:text-indigo-400"></i>
                Finance Department Requisitions
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">All job requisitions submitted by Finance
                department</p>
        </div>
        <span
            class="bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-3 py-1 rounded-full text-sm font-medium">
            Total: <?= count($financeRequisitions) ?>
        </span>
    </div>

    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            ID</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Job Title</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Requested By</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Positions</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Needed By</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Priority</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Status</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Created</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php if (empty($financeRequisitions)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-clipboard-list text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                    <p>No requisitions found for Finance department</p>
                                    <button onclick="openRequisitionModal()"
                                        class="mt-3 text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                                        <i class="fas fa-plus-circle mr-1"></i> Create your first requisition
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($financeRequisitions as $req): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm font-mono text-slate-600 dark:text-slate-400">
                                    #<?= htmlspecialchars($req['id'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($req['job_title'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($req['requested_by'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($req['positions'] ?? 1) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= isset($req['needed_by']) ? date('M d, Y', strtotime($req['needed_by'])) : 'N/A' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                                        'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                                        'urgent' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                    ];
                                    $priorityClass = $priorityColors[strtolower($req['priority'] ?? 'medium')] ?? $priorityColors['medium'];
                                    ?>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $priorityClass ?>">
                                        <?= ucfirst(htmlspecialchars($req['priority'] ?? 'Medium')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                                        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                    ];
                                    $statusClass = $statusColors[strtolower($req['status'] ?? 'pending')] ?? $statusColors['pending'];
                                    ?>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                        <i
                                            class="fas <?= $req['status'] === 'approved' ? 'fa-check-circle' : ($req['status'] === 'rejected' ? 'fa-times-circle' : 'fa-clock') ?> mr-1 text-xs"></i>
                                        <?= ucfirst(htmlspecialchars($req['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= isset($req['created_at']) ? date('M d, Y', strtotime($req['created_at'])) : 'N/A' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="viewRequisitionDetails(<?= htmlspecialchars(json_encode($req)) ?>)"
                                            class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>

                                        <?php if (strtolower($req['status'] ?? '') === 'pending'): ?>
                                            <form method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to cancel this requisition?');">
                                                <input type="hidden" name="cancel_requisition" value="1">
                                                <input type="hidden" name="requisition_id" value="<?= $req['id'] ?>">
                                                <button type="submit"
                                                    class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                                    <i class="fas fa-trash-alt mr-1"></i> Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span
                                                class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed">
                                                <i class="fas fa-ban mr-1"></i> Cancelled
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="requisitionModal"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto shadow-2xl">
        <div
            class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clipboard-list text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Job Requisition</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Create a new job requisition request</p>
                </div>
            </div>
            <button onclick="closeRequisitionModal()"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-6">
            <div class="mb-4 flex flex-wrap gap-2">
                <span
                    class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 px-3 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-plus-circle mr-1"></i>Create
                </span>
                <span
                    class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 px-3 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-eye mr-1"></i>Read
                </span>
                <span
                    class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 px-3 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-check-double mr-1"></i>Approve
                </span>
                <span
                    class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 px-3 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-money-bill-wave mr-1"></i>Pay
                </span>
            </div>

            <form id="requisitionForm" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-briefcase text-gray-500 dark:text-gray-400 mr-1"></i>Job Title *
                        </label>
                        <input type="text" id="jobTitle" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white"
                            placeholder="e.g., Senior Accountant">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-building text-gray-500 dark:text-gray-400 mr-1"></i>Department *
                        </label>
                        <select id="department" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white">
                            <option value="Finance" selected>Finance</option>
                            <option value="Accounting">Accounting</option>
                            <option value="Treasury">Treasury</option>
                            <option value="Budget">Budget</option>
                            <option value="Tax">Tax</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-user text-gray-500 dark:text-gray-400 mr-1"></i>Requested By *
                        </label>
                        <input type="text" id="requestedBy" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white"
                            placeholder="Full name and title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-users text-gray-500 dark:text-gray-400 mr-1"></i>Number of Positions *
                        </label>
                        <input type="number" id="positions" value="1" min="1" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-calendar-alt text-gray-500 dark:text-gray-400 mr-1"></i>Needed By *
                        </label>
                        <input type="date" id="neededBy" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <i class="fas fa-exclamation-circle text-gray-500 dark:text-gray-400 mr-1"></i>Priority *
                        </label>
                        <select id="priority"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        <i class="fas fa-align-left text-gray-500 dark:text-gray-400 mr-1"></i>Justification
                    </label>
                    <textarea id="justification" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:text-white"
                        placeholder="Explain why this position is needed..."></textarea>
                </div>

                <div class="bg-gray-50 dark:bg-slate-700/50 p-3 rounded-lg border border-gray-200 dark:border-gray-600">
                    <div class="flex items-center text-xs text-gray-700 dark:text-gray-300">
                        <i class="fas fa-info-circle mr-2 text-indigo-500"></i>
                        <span>Requisition will be sent to HR for approval. You will be notified of the status.</span>
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white font-semibold py-3 px-4 rounded-lg hover:from-indigo-700 hover:to-indigo-800 transition-all shadow-lg flex items-center justify-center">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit Requisition
                </button>
            </form>

            <div class="mt-4">
                <div
                    class="bg-gray-50 dark:bg-slate-700/30 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                    <div class="bg-gray-100 dark:bg-slate-700 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center">
                            <i class="fas fa-terminal text-gray-500 dark:text-gray-400 mr-2"></i>
                            Response
                        </p>
                    </div>
                    <pre id="response"
                        class="p-3 text-xs font-mono bg-gray-900 text-gray-400 overflow-x-auto max-h-40 overflow-y-auto">// Ready to submit...</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="viewRequisitionModal"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto shadow-2xl">
        <div
            class="sticky top-0 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-alt text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Requisition Details</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400" id="viewRequisitionId"></p>
                </div>
            </div>
            <button onclick="closeViewRequisitionModal()"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="viewRequisitionContent">
        </div>
    </div>
</div>

<script>
    const API_BASE = 'https://humanresource.up.railway.app/api';
    const API_KEY = 'finance_system_2026_key_67890';

    function openRequisitionModal() {
        document.getElementById('requisitionModal').classList.remove('hidden');
        const today = new Date();
        const defaultDate = new Date(today.setDate(today.getDate() + 30));
        document.getElementById('neededBy').value = defaultDate.toISOString().split('T')[0];
        document.getElementById('neededBy').min = new Date().toISOString().split('T')[0];
    }

    function closeRequisitionModal() {
        document.getElementById('requisitionModal').classList.add('hidden');
        document.getElementById('requisitionForm').reset();
        document.getElementById('response').innerHTML = '// Ready to submit...';
    }

    function viewRequisitionDetails(requisition) {
        const content = `
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Job Title</label>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(requisition.job_title || 'N/A')}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Department</label>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(requisition.department || 'N/A')}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Requested By</label>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">${escapeHtml(requisition.requested_by || 'N/A')}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Number of Positions</label>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">${requisition.positions || 1}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Needed By</label>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">${requisition.needed_by ? new Date(requisition.needed_by).toLocaleDateString() : 'N/A'}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Priority</label>
                        <p class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                ${requisition.priority === 'urgent' ? 'bg-red-100 text-red-700' :
                requisition.priority === 'high' ? 'bg-orange-100 text-orange-700' :
                    requisition.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'}">
                                ${requisition.priority ? requisition.priority.toUpperCase() : 'MEDIUM'}
                            </span>
                        </p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Justification</label>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-slate-700/30 p-3 rounded-lg">${escapeHtml(requisition.justification || 'No justification provided')}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</label>
                        <p class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                ${requisition.status === 'approved' ? 'bg-green-100 text-green-700' :
                requisition.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}">
                                <i class="fas ${requisition.status === 'approved' ? 'fa-check-circle' : (requisition.status === 'rejected' ? 'fa-times-circle' : 'fa-clock')} mr-1"></i>
                                ${requisition.status ? requisition.status.toUpperCase() : 'PENDING'}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Created Date</label>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">${requisition.created_at ? new Date(requisition.created_at).toLocaleString() : 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('viewRequisitionId').innerHTML = `ID: #${requisition.id || 'N/A'}`;
        document.getElementById('viewRequisitionContent').innerHTML = content;
        document.getElementById('viewRequisitionModal').classList.remove('hidden');
    }

    function closeViewRequisitionModal() {
        document.getElementById('viewRequisitionModal').classList.add('hidden');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeRequisitionModal();
            closeViewRequisitionModal();
        }
    });

    document.getElementById('requisitionModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeRequisitionModal();
        }
    });

    document.getElementById('viewRequisitionModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeViewRequisitionModal();
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.getElementById('requisitionForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = {
            job_title: document.getElementById('jobTitle').value,
            department: document.getElementById('department').value,
            requested_by: document.getElementById('requestedBy').value,
            positions: parseInt(document.getElementById('positions').value),
            needed_by: document.getElementById('neededBy').value,
            priority: document.getElementById('priority').value,
            justification: document.getElementById('justification').value || null
        };

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
        submitBtn.disabled = true;

        try {
            const url = `${API_BASE}/job-requisition.php?api_key=${API_KEY}`;
            document.getElementById('response').innerHTML = '⏳ Submitting requisition...';

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();
            document.getElementById('response').innerHTML = JSON.stringify(data, null, 2);

            if (data.success) {
                showToast('✅ Requisition created! ID: ' + data.data.id, 'success');
                setTimeout(() => {
                    closeRequisitionModal();
                    location.reload();
                }, 1500);
            } else {
                showToast('❌ Error: ' + (data.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            document.getElementById('response').innerHTML = `Error: ${error.message}`;
            showToast('❌ Error: ' + error.message, 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-5 right-5 px-6 py-3 rounded-lg shadow-2xl text-white font-semibold z-50 ${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>