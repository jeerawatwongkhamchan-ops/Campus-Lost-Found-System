<?php

declare(strict_types=1);

/** Fetch only the data needed by the admin dashboard. */
function load_admin_dashboard(mysqli $db): array
{
    $counts = admin_dashboard_rows($db, <<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM categories WHERE is_active = 1) AS categories,
            (SELECT COUNT(*) FROM item_reports WHERE status = 'open') AS open_reports,
            (SELECT COUNT(*) FROM claims WHERE status = 'pending') AS pending_claims,
            (SELECT COUNT(*) FROM item_reports) AS total_reports,
            (SELECT COUNT(*) FROM claims) AS total_claims
        SQL
    )[0];

    $recentReports = admin_dashboard_rows($db, <<<'SQL'
        SELECT r.report_id, r.title, r.report_type, r.status, r.created_at,
               c.category_name, u.full_name AS reporter_name
        FROM item_reports AS r
        JOIN categories AS c ON c.category_id = r.category_id
        JOIN users AS u ON u.user_id = r.reporter_id
        ORDER BY r.created_at DESC, r.report_id DESC
        LIMIT 5
        SQL
    );

    $recentClaims = admin_dashboard_rows($db, <<<'SQL'
        SELECT cl.claim_id, cl.status, cl.created_at, r.title AS report_title,
               u.full_name AS claimant_name
        FROM claims AS cl
        JOIN item_reports AS r ON r.report_id = cl.report_id
        JOIN users AS u ON u.user_id = cl.claimant_id
        ORDER BY cl.created_at DESC, cl.claim_id DESC
        LIMIT 5
        SQL
    );

    $recentLogs = admin_dashboard_rows($db, <<<'SQL'
        SELECT log_id, event_type, outcome, created_at
        FROM security_logs
        ORDER BY created_at DESC, log_id DESC
        LIMIT 5
        SQL
    );

    return [
        'counts' => $counts,
        'reports' => $recentReports,
        'claims' => $recentClaims,
        'logs' => $recentLogs,
    ];
}

/** @return array<int, array<string, mixed>> */
function admin_dashboard_rows(mysqli $db, string $query): array
{
    $statement = $db->prepare($query);
    $statement->execute();
    $result = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $result;
}
