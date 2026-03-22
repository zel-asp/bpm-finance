<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payroll Batches | Finance System</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>

    <body class="bg-slate-100 dark:bg-slate-950 font-sans antialiased p-4 md:p-8">
        <div class="max-w-7xl mx-auto">
            <?php
            require_once __DIR__ . '/../../includes/finance.php';
            $conn = db_connect();

            // HR API configuration
            define('HR_API_BASE', 'https://humanresource.up.railway.app/api');
            define('HR_API_KEY', 'finance_system_2026_key_67890');

            /**
             * Call HR API endpoint
             */
            function callHrApi($endpoint, $method = 'GET', $params = [])
            {
                $url = HR_API_BASE . '/payroll.php?' . http_build_query(array_merge(['api_key' => HR_API_KEY], $params));
                if ($method === 'GET') {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode === 200) {
                        return json_decode($response, true);
                    }
                    return null;
                } else {
                    // POST actions (approve / mark paid)
                    $actionUrl = $url . '&action=' . $params['action'] . '&id=' . $params['id'];
                    $ch = curl_init($actionUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    return json_decode($response, true);
                }
            }

            /**
             * Fetch pending payroll from HR system (status = 'Processing' means ready for finance approval)
             */
            function fetchPendingPayrollFromHr()
            {
                $result = callHrApi('payroll.php', 'GET', ['status' => 'Processing']);
                if ($result && isset($result['success']) && $result['success']) {
                    return $result['data'] ?? [];
                }
                return [];
            }

            /**
             * Fetch approved payroll from HR system (status = 'Approved')
             */
            function fetchApprovedPayrollFromHr()
            {
                $result = callHrApi('payroll.php', 'GET', ['status' => 'Processed']);
                if ($result && isset($result['success']) && $result['success']) {
                    return $result['data'] ?? [];
                }
                return [];
            }

            /**
             * Approve a payroll record in HR system (finance approves)
             */
            function approvePayrollInHr($payrollId)
            {
                $response = callHrApi('payroll.php', 'POST', ['action' => 'approve', 'id' => $payrollId]);
                return ($response && isset($response['success']) && $response['success']);
            }

            /**
             * Mark payroll as paid in HR system
             */
            function markPayrollAsPaidInHr($payrollId)
            {
                $response = callHrApi('payroll.php', 'POST', ['action' => 'paid', 'id' => $payrollId]);
                return ($response && isset($response['success']) && $response['success']);
            }

            // Handle incoming actions from finance UI (approve/reject/push to GL)
            $message = '';
            $error = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';

                // Create manual batch
                if ($action === 'create') {
                    $period = trim($_POST['period'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $gross = (float) ($_POST['gross_amount'] ?? 0);
                    $net = (float) ($_POST['net_amount'] ?? 0);
                    $employer = (float) ($_POST['employer_contributions'] ?? 0);

                    if ($period !== '' && $gross > 0 && $net > 0) {
                        $stmt = $conn->prepare('INSERT INTO payroll_batches (period, description, gross_amount, net_amount, employer_contributions, status) VALUES (?, ?, ?, ?, ?, "DRAFT")');
                        $stmt->bind_param('ssddd', $period, $description, $gross, $net, $employer);

                        if ($stmt->execute()) {
                            $message = "Payroll batch created successfully.";
                        } else {
                            $error = "Failed to create batch.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Please fill in all required fields.";
                    }
                }

                // Approve from HR pending list -> creates batch in finance
                elseif ($action === 'approve_hr_payroll') {
                    $hrPayrollId = (int) ($_POST['hr_payroll_id'] ?? 0);
                    $period = trim($_POST['period'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $gross = (float) ($_POST['gross_amount'] ?? 0);
                    $net = (float) ($_POST['net_amount'] ?? 0);
                    $employer = (float) ($_POST['employer_contributions'] ?? 0);

                    if ($hrPayrollId > 0 && $period !== '' && $gross > 0 && $net > 0) {
                        // First, approve in HR system
                        $approved = approvePayrollInHr($hrPayrollId);
                        if ($approved) {
                            // Create a payroll batch in finance system
                            $stmt = $conn->prepare('INSERT INTO payroll_batches (period, description, gross_amount, net_amount, employer_contributions, status, hr_reference_id) VALUES (?, ?, ?, ?, ?, "APPROVED", ?)');
                            $stmt->bind_param('ssdddi', $period, $description, $gross, $net, $employer, $hrPayrollId);
                            $stmt->execute();
                            $stmt->close();

                            $message = "Payroll approved in HR and batch created. Ready to post to GL.";
                        } else {
                            $error = "Failed to approve payroll in HR system. Please try again.";
                        }
                    } else {
                        $error = "Invalid payroll data. Please check period, gross and net amounts.";
                    }
                }

                // Reject from HR pending list
                elseif ($action === 'reject_hr_payroll') {
                    $hrPayrollId = (int) ($_POST['hr_payroll_id'] ?? 0);
                    if ($hrPayrollId > 0) {
                        $message = "Payroll #{$hrPayrollId} rejected. No batch created. Please coordinate with HR.";
                    } else {
                        $error = "Invalid payroll ID.";
                    }
                }

                // Post existing finance batch to GL
                elseif ($action === 'post') {
                    $batchId = (int) ($_POST['batch_id'] ?? 0);
                    if ($batchId > 0) {
                        $res = $conn->query('SELECT * FROM payroll_batches WHERE id = ' . $batchId . ' LIMIT 1');
                        if ($res && $batch = $res->fetch_assoc()) {
                            if ($batch['status'] !== 'POSTED') {
                                $gross = (float) $batch['gross_amount'];
                                $employer = (float) $batch['employer_contributions'];
                                $period = $batch['period'];
                                $entryDate = $period . '-01';

                                $salaryExpenseId = find_account_id_by_code($conn, '5000');
                                $payrollLiabId = find_account_id_by_code($conn, '2100');
                                $cashId = find_account_id_by_code($conn, '1000');

                                if ($salaryExpenseId && $payrollLiabId && $cashId) {
                                    $totalCost = $gross + $employer;
                                    $net = (float) $batch['net_amount'];
                                    $liabPortion = $totalCost - $net;

                                    post_journal_entry(
                                        $conn,
                                        $entryDate,
                                        'Payroll ' . $batch['period'],
                                        [
                                            ['account_id' => $salaryExpenseId, 'debit' => $totalCost, 'credit' => 0],
                                            ['account_id' => $payrollLiabId, 'debit' => 0, 'credit' => $liabPortion],
                                            ['account_id' => $cashId, 'debit' => 0, 'credit' => $net],
                                        ],
                                        'PAYROLL',
                                        $batchId
                                    );
                                }

                                $stmt = $conn->prepare('UPDATE payroll_batches SET status = "POSTED", posted_at = NOW() WHERE id = ?');
                                $stmt->bind_param('i', $batchId);
                                $stmt->execute();
                                $stmt->close();

                                // Also mark as paid in HR if the batch has hr_reference_id
                                if (!empty($batch['hr_reference_id'])) {
                                    markPayrollAsPaidInHr($batch['hr_reference_id']);
                                    $message = "Payroll batch posted to GL and marked as PAID in HR.";
                                } else {
                                    $message = "Payroll batch posted to General Ledger.";
                                }
                            } else {
                                $error = "Batch already posted.";
                            }
                        } else {
                            $error = "Batch not found.";
                        }
                    }
                }
            }

            // Fetch pending HR payrolls (status = Processing) for approval
            $pendingHrPayrolls = fetchPendingPayrollFromHr();

            // Fetch approved HR payrolls (status = Approved) for display
            $approvedHrPayrolls = fetchApprovedPayrollFromHr();

            // Fetch existing finance batches
            $batches = $conn->query('SELECT * FROM payroll_batches ORDER BY created_at DESC');

            // Fetch approved finance batches
            $approvedBatches = $conn->query("SELECT * FROM payroll_batches WHERE status = 'APPROVED' OR status = 'POSTED' ORDER BY created_at DESC");
            ?>

            <!-- Flash Messages -->
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

            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pending HR Payrolls</p>
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                <?= count($pendingHrPayrolls) ?>
                            </p>
                        </div>
                        <i class="fas fa-clock text-3xl text-amber-400"></i>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Approved HR Payrolls</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                <?= count($approvedHrPayrolls) ?>
                            </p>
                        </div>
                        <i class="fas fa-check-circle text-3xl text-green-400"></i>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Finance Batches</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                <?= $batches ? $batches->num_rows : 0 ?>
                            </p>
                        </div>
                        <i class="fas fa-file-invoice-dollar text-3xl text-blue-400"></i>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total Payroll Amount</p>
                            <?php
                            $totalAmount = 0;
                            if ($approvedBatches && $approvedBatches->num_rows > 0) {
                                $approvedBatches->data_seek(0);
                                while ($batch = $approvedBatches->fetch_assoc()) {
                                    $totalAmount += $batch['net_amount'];
                                }
                                $approvedBatches->data_seek(0);
                            }
                            ?>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                ₱<?= number_format($totalAmount, 2) ?></p>
                        </div>
                        <i class="fas fa-chart-line text-3xl text-purple-400"></i>
                    </div>
                </div>
            </div>

            <div class="grid gap-6">
                <!-- NEW SECTION: Approved Payroll from HR -->
                <section
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
                    <header class="mb-4 flex items-center justify-between">
                        <div>
                            <h2
                                class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-500"></i> Approved Payroll from HR
                            </h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Payroll records that have been
                                approved and are ready for posting</p>
                        </div>
                        <span
                            class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-200">
                            <?= count($approvedHrPayrolls) ?> approved
                        </span>
                    </header>

                    <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl">
                        <div class="max-h-[480px] overflow-auto">
                            <?php if (empty($approvedHrPayrolls)): ?>
                                <div class="p-6 text-center text-slate-500 dark:text-slate-400">
                                    <i class="fas fa-check-circle text-4xl mb-2 text-green-400"></i>
                                    <p>No approved payrolls from HR</p>
                                </div>
                            <?php else: ?>
                                <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                                    <thead
                                        class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                                        <tr>
                                            <th class="px-4 py-2 font-medium">Employee</th>
                                            <th class="px-4 py-2 font-medium">Period</th>
                                            <th class="px-4 py-2 text-right font-medium">Regular Hours</th>
                                            <th class="px-4 py-2 text-right font-medium">Overtime</th>
                                            <th class="px-4 py-2 text-right font-medium">Gross</th>
                                            <th class="px-4 py-2 text-right font-medium">Deductions</th>
                                            <th class="px-4 py-2 text-right font-medium">Net</th>
                                            <th class="px-4 py-2 font-medium">Status</th>
                                            <th class="px-4 py-2 font-medium">Approved Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                        <?php foreach ($approvedHrPayrolls as $hr):
                                            $periodStart = date('M d, Y', strtotime($hr['period_start'] ?? 'now'));
                                            $periodEnd = date('M d, Y', strtotime($hr['period_end'] ?? 'now'));
                                            $periodLabel = $periodStart . ' - ' . $periodEnd;
                                            $gross = (float) ($hr['gross_pay'] ?? 0);
                                            $net = (float) ($hr['net_pay'] ?? 0);
                                            $deductions = $gross - $net;
                                            $regularHours = (float) ($hr['total_regular_hours'] ?? 0);
                                            $overtimeHours = (float) ($hr['total_overtime_hours'] ?? 0);
                                            $approvedAt = isset($hr['approved_at']) ? date('M d, Y H:i', strtotime($hr['approved_at'])) : 'N/A';
                                            ?>
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/60">
                                                <td class="px-4 py-2 align-middle">
                                                    <div class="font-medium text-slate-800 dark:text-slate-200">
                                                        <?= htmlspecialchars($hr['full_name'] ?? 'N/A') ?>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500">
                                                        <?= htmlspecialchars($hr['position'] ?? '') ?>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400">
                                                        <?= htmlspecialchars($hr['department'] ?? '') ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle text-slate-600 dark:text-slate-300">
                                                    <?= htmlspecialchars($periodLabel) ?>
                                                </td>
                                                <td
                                                    class="px-4 py-2 align-middle text-right font-mono text-slate-900 dark:text-slate-100">
                                                    <?= number_format($regularHours, 1) ?> hrs
                                                </td>
                                                <td
                                                    class="px-4 py-2 align-middle text-right font-mono text-slate-900 dark:text-slate-100">
                                                    <?= number_format($overtimeHours, 1) ?> hrs
                                                </td>
                                                <td
                                                    class="px-4 py-2 align-middle text-right font-mono text-slate-900 dark:text-slate-100">
                                                    ₱<?= number_format($gross, 2) ?>
                                                </td>
                                                <td
                                                    class="px-4 py-2 align-middle text-right font-mono text-red-600 dark:text-red-400">
                                                    ₱<?= number_format($deductions, 2) ?>
                                                </td>
                                                <td
                                                    class="px-4 py-2 align-middle text-right font-mono text-emerald-700 dark:text-emerald-300">
                                                    ₱<?= number_format($net, 2) ?>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <span
                                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-medium uppercase text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                                        <i class="fas fa-check-circle mr-1 text-[10px]"></i>
                                                        <?= htmlspecialchars($hr['status'] ?? 'Approved') ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 align-middle text-slate-600 dark:text-slate-300 text-xs">
                                                    <?= $approvedAt ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="sticky bottom-0 bg-slate-50 dark:bg-slate-900/95">
                                        <tr class="border-t border-slate-200 dark:border-slate-700">
                                            <td colspan="4"
                                                class="px-4 py-2 text-right font-medium text-slate-700 dark:text-slate-300">
                                                Totals:
                                            </td>
                                            <?php
                                            $totalGross = array_sum(array_column($approvedHrPayrolls, 'gross_pay'));
                                            $totalNet = array_sum(array_column($approvedHrPayrolls, 'net_pay'));
                                            $totalDeductions = $totalGross - $totalNet;
                                            $totalRegular = array_sum(array_column($approvedHrPayrolls, 'total_regular_hours'));
                                            $totalOvertime = array_sum(array_column($approvedHrPayrolls, 'total_overtime_hours'));
                                            ?>
                                            <td
                                                class="px-4 py-2 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                                <?= number_format($totalRegular, 1) ?> hrs
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                                <?= number_format($totalOvertime, 1) ?> hrs
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-mono font-bold text-slate-900 dark:text-slate-100">
                                                ₱<?= number_format($totalGross, 2) ?>
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-mono font-bold text-red-600 dark:text-red-400">
                                                ₱<?= number_format($totalDeductions, 2) ?>
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">
                                                ₱<?= number_format($totalNet, 2) ?>
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Original Two-Column Layout -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- LEFT COLUMN: HR Pending Payrolls (Approval/Rejection) -->
                    <section
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
                        <header class="mb-4 flex items-center justify-between">
                            <div>
                                <h2
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                    <i class="fas fa-clock text-amber-500"></i> Pending Payroll from HR
                                </h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Review and approve payroll
                                    records from HR system</p>
                            </div>
                            <span
                                class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                                <?= count($pendingHrPayrolls) ?> pending
                            </span>
                        </header>

                        <div class="-mx-5 -mb-5 overflow-hidden rounded-2xl">
                            <div class="max-h-[480px] overflow-auto">
                                <?php if (empty($pendingHrPayrolls)): ?>
                                    <div class="p-6 text-center text-slate-500 dark:text-slate-400">
                                        <i class="fas fa-check-circle text-4xl mb-2 text-emerald-400"></i>
                                        <p>No pending payrolls from HR</p>
                                    </div>
                                <?php else: ?>
                                    <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                                        <thead
                                            class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                                            <tr>
                                                <th class="px-4 py-2 font-medium">Employee</th>
                                                <th class="px-4 py-2 font-medium">Period</th>
                                                <th class="px-4 py-2 text-right font-medium">Gross</th>
                                                <th class="px-4 py-2 text-right font-medium">Net</th>
                                                <th class="px-4 py-2 font-medium">Status</th>
                                                <th class="px-4 py-2 font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            <?php foreach ($pendingHrPayrolls as $hr):
                                                $periodStart = date('Y-m', strtotime($hr['period_start'] ?? 'now'));
                                                $periodEnd = date('Y-m', strtotime($hr['period_end'] ?? 'now'));
                                                $periodLabel = $periodStart === $periodEnd ? $periodStart : $periodStart . ' to ' . $periodEnd;
                                                $gross = (float) ($hr['gross_pay'] ?? 0);
                                                $net = (float) ($hr['net_pay'] ?? 0);
                                                ?>
                                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/60">
                                                    <td class="px-4 py-2 align-middle">
                                                        <div class="font-medium text-slate-800 dark:text-slate-200">
                                                            <?= htmlspecialchars($hr['full_name'] ?? 'N/A') ?>
                                                        </div>
                                                        <div class="text-[11px] text-slate-500">
                                                            <?= htmlspecialchars($hr['department'] ?? '') ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2 align-middle text-slate-600 dark:text-slate-300">
                                                        <?= htmlspecialchars($periodLabel) ?>
                                                    </td>
                                                    <td
                                                        class="px-4 py-2 align-middle text-right font-mono text-slate-900 dark:text-slate-100">
                                                        ₱<?= number_format($gross, 2) ?>
                                                    </td>
                                                    <td
                                                        class="px-4 py-2 align-middle text-right font-mono text-emerald-700 dark:text-emerald-300">
                                                        ₱<?= number_format($net, 2) ?>
                                                    </td>
                                                    <td class="px-4 py-2 align-middle">
                                                        <span
                                                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-medium uppercase text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                            <?= htmlspecialchars($hr['status'] ?? 'Processing') ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2 align-center">
                                                        <div class="flex gap-2 items-center">
                                                            <!-- Approve Form -->
                                                            <form method="post" class="inline"
                                                                onsubmit="return confirm('Mark as done this payroll? It will create a batch in finance system.')">
                                                                <input type="hidden" name="action" value="approve_hr_payroll">
                                                                <input type="hidden" name="hr_payroll_id"
                                                                    value="<?= (int) $hr['id'] ?>">
                                                                <input type="hidden" name="period"
                                                                    value="<?= htmlspecialchars($periodStart) ?>">
                                                                <input type="hidden" name="description"
                                                                    value="Payroll <?= htmlspecialchars($hr['full_name']) ?> - <?= htmlspecialchars($periodLabel) ?>">
                                                                <input type="hidden" name="gross_amount" value="<?= $gross ?>">
                                                                <input type="hidden" name="net_amount" value="<?= $net ?>">
                                                                <input type="hidden" name="employer_contributions" value="0">
                                                                <button
                                                                    class="text-xs font-medium text-emerald-700 hover:underline dark:text-emerald-300 flex items-center gap-1">
                                                                    <i class="fas fa-check-circle"></i> Done
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- RIGHT COLUMN: Finance Batches + Manual Creation -->
                    <div class="space-y-6">
                        <!-- Manual Batch Creation -->
                        <section
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
                            <header class="mb-4">
                                <h2
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                    <i class="fas fa-plus-circle text-sky-500"></i> Manual Payroll Batch
                                </h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Create a batch manually (if
                                    not from HR approval)</p>
                            </header>
                            <form method="post" class="space-y-3">
                                <input type="hidden" name="action" value="create">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Period
                                            (YYYY-MM)</label>
                                        <input type="month" name="period" value="<?= date('Y-m') ?>" required
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50">
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Description</label>
                                        <input type="text" name="description" placeholder="e.g. March 2026 payroll"
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50">
                                    </div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Gross
                                            amount</label>
                                        <input type="number" step="0.01" name="gross_amount" required
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50">
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Net
                                            amount</label>
                                        <input type="number" step="0.01" name="net_amount" required
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50">
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Employer
                                            contributions</label>
                                        <input type="number" step="0.01" name="employer_contributions" value="0"
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-100 outline-none ring-0 transition focus:border-sky-500 focus:ring-2 focus:ring-sky-600/70 dark:border-slate-700/80 dark:bg-slate-900/70 dark:text-slate-50">
                                    </div>
                                </div>
                                <button
                                    class="inline-flex items-center justify-center rounded-full bg-sky-600 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-400/60 transition hover:bg-sky-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/80">
                                    <i class="fas fa-save mr-1"></i> Save batch
                                </button>
                            </form>
                        </section>

                        <!-- Existing Finance Batches -->
                        <section
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800/80 dark:bg-slate-950/80 dark:shadow-slate-950/70">
                            <header class="mb-4 flex items-center justify-between">
                                <div>
                                    <h2
                                        class="text-sm font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                        <i class="fas fa-file-invoice-dollar text-emerald-600"></i> Finance Payroll
                                        Batches
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Draft batches ready to
                                        post to GL</p>
                                </div>
                            </header>
                            <div
                                class="-mx-5 -mb-5 overflow-hidden rounded-2xl border-t border-slate-100 bg-slate-50 dark:border-slate-800/80 dark:bg-slate-950/60">
                                <div class="max-h-[380px] overflow-auto">
                                    <table class="min-w-full text-left text-xs text-slate-700 dark:text-slate-200/90">
                                        <thead
                                            class="sticky top-0 bg-slate-100 text-slate-500 backdrop-blur dark:bg-slate-900/95 dark:text-slate-400">
                                            <tr>
                                                <th class="px-4 py-2 font-medium">Period</th>
                                                <th class="px-4 py-2 font-medium">Description</th>
                                                <th class="px-4 py-2 text-right font-medium">Gross</th>
                                                <th class="px-4 py-2 text-right font-medium">Net</th>
                                                <th class="px-4 py-2 font-medium">Status</th>
                                                <th class="px-4 py-2 font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            <?php if ($batches && $batches->num_rows > 0): ?>
                                                <?php while ($row = $batches->fetch_assoc()): ?>
                                                    <tr class="hover:bg-slate-100 dark:hover:bg-slate-900/60">
                                                        <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300">
                                                            <?= htmlspecialchars($row['period']) ?>
                                                        </td>
                                                        <td class="px-4 py-2 align-middle text-slate-700 dark:text-slate-300">
                                                            <?= htmlspecialchars($row['description'] ?? '') ?>
                                                        </td>
                                                        <td
                                                            class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                                            ₱<?= number_format((float) $row['gross_amount'], 2) ?>
                                                        </td>
                                                        <td
                                                            class="px-4 py-2 align-middle text-right text-slate-900 dark:text-slate-100">
                                                            ₱<?= number_format((float) $row['net_amount'], 2) ?>
                                                        </td>
                                                        <td class="px-4 py-2 align-middle">
                                                            <?php if ($row['status'] === 'POSTED'): ?>
                                                                <span
                                                                    class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium uppercase text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                                    <i class="fas fa-check-circle mr-1 text-[10px]"></i> POSTED
                                                                </span>
                                                            <?php elseif ($row['status'] === 'APPROVED'): ?>
                                                                <span
                                                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-medium uppercase text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                                    <i class="fas fa-check-circle mr-1 text-[10px]"></i> APPROVED
                                                                </span>
                                                            <?php else: ?>
                                                                <span
                                                                    class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/30 dark:text-amber-200">
                                                                    DRAFT
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-4 py-2 align-middle">
                                                            <?php if ($row['status'] !== 'POSTED'): ?>
                                                                <form method="post" class="inline"
                                                                    onsubmit="return confirm('Post this batch to General Ledger? This action cannot be undone.')">
                                                                    <input type="hidden" name="action" value="post">
                                                                    <input type="hidden" name="batch_id"
                                                                        value="<?= (int) $row['id'] ?>">
                                                                    <button
                                                                        class="text-xs font-medium text-emerald-700 hover:underline dark:text-emerald-300 flex items-center gap-1">
                                                                        <i class="fas fa-pen-alt"></i> Post to GL
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span
                                                                    class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                                                    <i class="fas fa-check-double"></i> Posted
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6"
                                                        class="px-4 py-4 text-center text-slate-500 dark:text-slate-400">
                                                        <i class="fas fa-database mr-1"></i> No finance batches yet.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- Quick help footer -->
            <div
                class="mt-8 text-center text-xs text-slate-500 dark:text-slate-500 border-t border-slate-200 dark:border-slate-800 pt-6">
                <p><i class="fas fa-sync-alt mr-1"></i> Payroll workflow: HR sends payroll → Finance approves (creates
                    batch) → Post to GL → HR marked as Paid automatically.</p>
            </div>
        </div>
    </body>

</html>