<?php
require_once __DIR__ . '/../../includes/finance.php';
$conn = db_connect();

define('HR_API_BASE', 'https://humanresource.up.railway.app/api');
define('HR_API_KEY', 'finance_system_2026_key_67890');

function callHrApi($endpoint, $method = 'GET', $params = [], $body = null)
{
    $url = HR_API_BASE . '/' . $endpoint . '?api_key=' . HR_API_KEY;

    if ($method === 'GET' && !empty($params)) {
        $url .= '&' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}

function fetchCompensationReviews($status = 'pending_finance')
{
    $result = callHrApi('compensation.php', 'GET', ['status' => $status]);
    if ($result && isset($result['success']) && $result['success']) {
        return $result['data'] ?? [];
    }
    return [];
}

function fetchApprovedCompensationReviews()
{
    $result = callHrApi('compensation.php', 'GET', ['status' => 'approved']);
    if ($result && isset($result['success']) && $result['success']) {
        return $result['data'] ?? [];
    }
    return [];
}

function approveCompensationReview($reviewId)
{
    $url = HR_API_BASE . '/compensation.php?api_key=' . HR_API_KEY . '&action=approve&id=' . $reviewId;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return ($result && isset($result['success']) && $result['success']);
    }
    return false;
}

function rejectCompensationReview($reviewId, $reason)
{
    $url = HR_API_BASE . '/compensation.php?api_key=' . HR_API_KEY . '&action=reject&id=' . $reviewId;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reason' => $reason]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return ($result && isset($result['success']) && $result['success']);
    }
    return false;
}

$message = '';
$error = '';
$reviews = [];
$approvedReviews = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_single' && isset($_POST['review_id'])) {
        $reviewId = (int) $_POST['review_id'];
        $result = approveCompensationReview($reviewId);
        if ($result) {
            $message = "Compensation review #{$reviewId} approved successfully.";
            echo "<meta http-equiv='refresh' content='1'>";
        } else {
            $error = "Failed to approve review #{$reviewId}. Please try again.";
        }
    } elseif ($action === 'reject_single' && isset($_POST['review_id'])) {
        $reviewId = (int) $_POST['review_id'];
        $reason = trim($_POST['reason'] ?? 'No reason provided');
        $result = rejectCompensationReview($reviewId, $reason);
        if ($result) {
            $message = "Compensation review #{$reviewId} rejected.";
            echo "<meta http-equiv='refresh' content='1'>";
        } else {
            $error = "Failed to reject review #{$reviewId}. Please try again.";
        }
    }
}

$reviews = fetchCompensationReviews('pending_finance');
$approvedReviews = fetchApprovedCompensationReviews();

usort($reviews, function ($a, $b) {
    $amountA = (float) ($a['proposed_salary'] ?? 0);
    $amountB = (float) ($b['proposed_salary'] ?? 0);
    return $amountB <=> $amountA;
});

usort($approvedReviews, function ($a, $b) {
    $amountA = (float) ($a['proposed_salary'] ?? 0);
    $amountB = (float) ($b['proposed_salary'] ?? 0);
    return $amountB <=> $amountA;
});

$totalPending = count($reviews);
$totalApproved = count($approvedReviews);

// Pending totals
$totalPendingProposedAmount = array_sum(array_column($reviews, 'proposed_salary'));
$totalPendingCurrentAmount = array_sum(array_column($reviews, 'current_salary'));
$totalPendingIncrease = $totalPendingProposedAmount - $totalPendingCurrentAmount;
$averagePendingIncrease = $totalPending > 0 ? $totalPendingIncrease / $totalPending : 0;

// Approved totals
$totalApprovedProposedAmount = array_sum(array_column($approvedReviews, 'proposed_salary'));
$totalApprovedCurrentAmount = array_sum(array_column($approvedReviews, 'current_salary'));
$totalApprovedIncrease = $totalApprovedProposedAmount - $totalApprovedCurrentAmount;

// Combined totals for the stats cards
$totalOverallProposedAmount = $totalPendingProposedAmount + $totalApprovedProposedAmount;
$totalOverallIncrease = $totalPendingIncrease + $totalApprovedIncrease;
$averageOverallIncrease = ($totalPending + $totalApproved) > 0 ? $totalOverallIncrease / ($totalPending + $totalApproved) : 0;
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50 flex items-center gap-3">
                <i class="fas fa-money-bill-wave text-emerald-600 dark:text-emerald-400"></i>
                Compensation Approval
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Review and approve employee compensation adjustments from HR system
            </p>
        </div>
        <div class="flex gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                <i class="fas fa-clock"></i>
                Pending: <?= $totalPending ?>
            </span>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                <i class="fas fa-check-circle"></i>
                Approved: <?= $totalApproved ?>
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div
            class="rounded-xl border border-emerald-500/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100">
            <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            class="rounded-xl border border-red-500/40 bg-red-50 px-4 py-3 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-100">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pending Reviews</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400"><?= $totalPending ?></p>
                </div>
                <i class="fas fa-hourglass-half text-3xl text-amber-400"></i>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Approved Reviews</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $totalApproved ?></p>
                </div>
                <i class="fas fa-check-circle text-3xl text-emerald-400"></i>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Total Approved Amount</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        ₱<?= number_format($totalApprovedProposedAmount, 2) ?></p>
                </div>
                <i class="fas fa-chart-line text-3xl text-purple-400"></i>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Total Approved Increase</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        ₱<?= number_format($totalApprovedIncrease, 2) ?></p>
                </div>
                <i class="fas fa-arrow-trend-up text-3xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <!-- Pending Reviews Section -->
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div
            class="border-b border-slate-200 dark:border-slate-700 px-6 py-4 bg-gradient-to-r from-amber-50 to-transparent dark:from-amber-900/10">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-clock text-amber-500"></i>
                        Pending Compensation Reviews
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reviews awaiting your approval or
                        rejection</p>
                </div>
                <span
                    class="bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-3 py-1 rounded-full text-sm font-medium">
                    <?= $totalPending ?> pending
                </span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-12">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-4">
                        <i class="fas fa-check-circle text-3xl text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-1">No Pending Reviews</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">All compensation reviews have been processed</p>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Employee</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Department</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Current Salary</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Proposed Salary</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Increase</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Review Type</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Effective Date</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php foreach ($reviews as $review):
                            $currentSalary = (float) ($review['current_salary'] ?? 0);
                            $proposedSalary = (float) ($review['proposed_salary'] ?? 0);
                            $increase = $proposedSalary - $currentSalary;
                            $increasePercent = $currentSalary > 0 ? ($increase / $currentSalary) * 100 : 0;
                            $increaseColor = $increasePercent > 20 ? 'text-red-600' : ($increasePercent > 10 ? 'text-amber-600' : 'text-emerald-600');
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($review['employee_name'] ?? $review['full_name'] ?? 'N/A') ?>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        ID: #<?= $review['employee_id'] ?? $review['id'] ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($review['department'] ?? 'Finance') ?>
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-sm text-slate-700 dark:text-slate-300">
                                    ₱<?= number_format($currentSalary, 2) ?>
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    ₱<?= number_format($proposedSalary, 2) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-mono text-sm font-semibold <?= $increaseColor ?>">
                                        ₱<?= number_format($increase, 2) ?>
                                    </div>
                                    <div class="text-xs <?= $increaseColor ?>">
                                        (+<?= number_format($increasePercent, 1) ?>%)
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($review['review_type'] ?? 'annual') === 'promotion' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : (($review['review_type'] ?? 'annual') === 'adjustment' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300') ?>">
                                        <?= ucfirst(htmlspecialchars($review['review_type'] ?? 'annual')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= isset($review['effective_date']) ? date('M d, Y', strtotime($review['effective_date'])) : 'N/A' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-3">
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('Approve this compensation review? This will update the employee\'s salary in HR system.')">
                                            <input type="hidden" name="action" value="approve_single">
                                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                            <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm">
                                                <i class="fas fa-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <button onclick="showRejectModal(<?= $review['id'] ?>)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition shadow-sm">
                                            <i class="fas fa-times-circle"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approved Reviews Section -->
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <div
            class="border-b border-slate-200 dark:border-slate-700 px-6 py-4 bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/10">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-check-circle text-emerald-500"></i>
                        Approved Compensation Reviews
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reviews that have been approved and
                        processed</p>
                </div>
                <span
                    class="bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-3 py-1 rounded-full text-sm font-medium">
                    <?= $totalApproved ?> approved
                </span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($approvedReviews)): ?>
                <div class="text-center py-12">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                        <i class="fas fa-inbox text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-1">No Approved Reviews</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Approved compensation reviews will appear here</p>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Employee</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Department</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Previous Salary</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                New Salary</th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Increase</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Review Type</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Approved Date</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                                Effective Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php foreach ($approvedReviews as $review):
                            $currentSalary = (float) ($review['current_salary'] ?? 0);
                            $proposedSalary = (float) ($review['proposed_salary'] ?? 0);
                            $increase = $proposedSalary - $currentSalary;
                            $increasePercent = $currentSalary > 0 ? ($increase / $currentSalary) * 100 : 0;
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($review['employee_name'] ?? $review['full_name'] ?? 'N/A') ?>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        ID: #<?= $review['employee_id'] ?? $review['id'] ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= htmlspecialchars($review['department'] ?? 'Finance') ?>
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-mono text-sm text-slate-500 dark:text-slate-400 line-through">
                                    ₱<?= number_format($currentSalary, 2) ?>
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                    ₱<?= number_format($proposedSalary, 2) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-mono text-sm font-semibold text-emerald-600">
                                        ₱<?= number_format($increase, 2) ?>
                                    </div>
                                    <div class="text-xs text-emerald-500">
                                        (+<?= number_format($increasePercent, 1) ?>%)
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($review['review_type'] ?? 'annual') === 'promotion' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : (($review['review_type'] ?? 'annual') === 'adjustment' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300') ?>">
                                        <?= ucfirst(htmlspecialchars($review['review_type'] ?? 'annual')) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= isset($review['approved_at']) ? date('M d, Y', strtotime($review['approved_at'])) : (isset($review['updated_at']) ? date('M d, Y', strtotime($review['updated_at'])) : 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                    <?= isset($review['effective_date']) ? date('M d, Y', strtotime($review['effective_date'])) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-700/50 border-t border-slate-200 dark:border-slate-700">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right font-medium text-slate-700 dark:text-slate-300">
                                Total Approved:</td>
                            <td class="px-6 py-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">
                                ₱<?= number_format($totalApprovedProposedAmount, 2) ?></td>
                            <td class="px-6 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                ₱<?= number_format($totalApprovedIncrease, 2) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Single Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md mx-4 shadow-2xl">
        <div class="border-b border-slate-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-times-circle text-red-500"></i>
                Reject Compensation Review
            </h3>
            <button onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="reject_single">
            <input type="hidden" name="review_id" id="rejectReviewId" value="">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rejection Reason
                    *</label>
                <textarea name="reason" id="rejectReason" rows="3" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    placeholder="Please provide a reason for rejection..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()"
                    class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 dark:text-slate-400">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Confirm
                    Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRejectModal(reviewId) {
        document.getElementById('rejectReviewId').value = reviewId;
        document.getElementById('rejectModal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        document.getElementById('rejectReason').value = '';
    }

    document.getElementById('rejectModal')?.addEventListener('click', function (e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeRejectModal();
        }
    });
</script>