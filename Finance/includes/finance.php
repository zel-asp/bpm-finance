<?php
require_once __DIR__ . '/db.php';

/**
 * Helper to look up an account id by its code (e.g. '1000').
 */
function find_account_id_by_code(mysqli $conn, string $code): ?int
{
    $stmt = $conn->prepare('SELECT id FROM accounts WHERE code = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return (int) $id;
    }
    $stmt->close();
    return null;
}

/**
 * Post a simple journal entry with one or more lines.
 *
 * @param mysqli $conn
 * @param string $entryDate Y-m-d
 * @param string $description
 * @param array<array{account_id:int,debit:float,credit:float}> $lines
 * @param string|null $sourceModule
 * @param int|null $sourceId
 */
function post_journal_entry(
    mysqli $conn,
    string $entryDate,
    string $description,
    array $lines,
    ?string $sourceModule = null,
    ?int $sourceId = null
): void {
    if (empty($lines)) {
        return;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO journal_entries (entry_date, description, source_module, source_id) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('sssi', $entryDate, $description, $sourceModule, $sourceId);
        $stmt->execute();
        $entryId = $stmt->insert_id;
        $stmt->close();

        $lineStmt = $conn->prepare('INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES (?, ?, ?, ?)');
        foreach ($lines as $line) {
            $accountId = (int) $line['account_id'];
            $debit = (float) $line['debit'];
            $credit = (float) $line['credit'];
            $lineStmt->bind_param('iidd', $entryId, $accountId, $debit, $credit);
            $lineStmt->execute();
        }
        $lineStmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        // For now just log; you could surface this to the UI if needed.
        error_log('Failed to post journal entry: ' . $e->getMessage());
    }
}

