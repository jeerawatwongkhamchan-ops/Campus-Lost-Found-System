<?php

declare(strict_types=1);

/** @return list<array{category_id: int, category_name: string}> */
function reports_categories(mysqli $database): array
{
    $statement = $database->prepare(
        'SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name'
    );
    $statement->execute();
    $categories = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $categories;
}

/** @return array<string, mixed>|null */
function reports_find(mysqli $database, int $reportId): ?array
{
    $statement = $database->prepare(
        'SELECT r.report_id, r.reporter_id, r.category_id, r.report_type, r.title,
                r.description, r.location, r.event_datetime, r.image_filename, r.private_note, r.status, r.created_at,
                c.category_name, u.full_name AS reporter_name, u.email AS reporter_email, u.phone AS reporter_phone, u.student_id AS reporter_student_id
         FROM item_reports r
         JOIN categories c ON c.category_id = r.category_id
         JOIN users u ON u.user_id = r.reporter_id
         WHERE r.report_id = ? AND r.deleted_at IS NULL LIMIT 1'
    );
    $statement->bind_param('i', $reportId);
    $statement->execute();
    $report = $statement->get_result()->fetch_assoc();
    $statement->close();
    return $report;
}

/** @return array{reports: list<array<string, mixed>>, has_next: bool} */
function reports_page(mysqli $database, int $page): array
{
    $limit = 26;
    $offset = ($page - 1) * 25;
    $statement = $database->prepare(
        'SELECT r.report_id, r.report_type, r.title, r.location, r.event_datetime,
                r.status, c.category_name
         FROM item_reports r
         JOIN categories c ON c.category_id = r.category_id
         WHERE r.deleted_at IS NULL
         ORDER BY r.created_at DESC, r.report_id DESC
         LIMIT ? OFFSET ?'
    );
    $statement->bind_param('ii', $limit, $offset);
    $statement->execute();
    $reports = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    $hasNext = count($reports) > 25;
    return ['reports' => array_slice($reports, 0, 25), 'has_next' => $hasNext];
}

/** @return array{values: array<string, string>, errors: array<string, string>} */
function reports_validate(mysqli $database, array $input): array
{
    $fields = ['report_type', 'title', 'category_id', 'location', 'event_datetime', 'description', 'private_note'];
    $values = [];
    $errors = [];
    foreach ($fields as $field) {
        $values[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
    }

    if (!in_array($values['report_type'], ['lost', 'found'], true)) {
        $errors['report_type'] = 'Choose lost or found.';
    }

    foreach (['title' => [3, 255], 'location' => [3, 255], 'description' => [10, 5000]] as $field => [$minimum, $maximum]) {
        $length = preg_match_all('/./us', $values[$field]);
        if ($length === false || $length < $minimum || $length > $maximum) {
            $errors[$field] = "Enter {$minimum}–{$maximum} characters.";
        }
    }

    if ($values['private_note'] !== '') {
        $privateLength = preg_match_all('/./us', $values['private_note']);
        if ($privateLength === false || $privateLength > 2000) {
            $errors['private_note'] = 'ข้อมูลลับเฉพาะต้องมีความยาวไม่เกิน 2,000 ตัวอักษร';
        }
    }

    $categoryId = reports_positive_id($values['category_id']);
    if ($categoryId === null) {
        $errors['category_id'] = 'Choose a category.';
    } else {
        $statement = $database->prepare(
            'SELECT category_id FROM categories WHERE category_id = ? AND is_active = 1 LIMIT 1'
        );
        $statement->bind_param('i', $categoryId);
        $statement->execute();
        $validCategory = $statement->get_result()->fetch_assoc() !== null;
        $statement->close();
        if (!$validCategory) {
            $errors['category_id'] = 'Choose an active category.';
        }
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $values['event_datetime']);
    if ($date === false || (int) $date->format('Y') < 1000
        || $date->format('Y-m-d\TH:i') !== $values['event_datetime']) {
        $errors['event_datetime'] = 'Enter a valid date and time.';
    }

    return ['values' => $values, 'errors' => $errors];
}

function reports_security_log(mysqli $database, int $userId, string $event, int $reportId): void
{
    $targetType = 'item_report';
    $targetId = (string) $reportId;
    $outcome = 'success';
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    $agent = preg_replace('/[^\x20-\x7E]/', '', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '';
    $agent = substr($agent, 0, 500);
    $statement = $database->prepare(
        'INSERT INTO security_logs (user_id, event_type, target_type, target_id, outcome, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('issssss', $userId, $event, $targetType, $targetId, $outcome, $ip, $agent);
    $statement->execute();
    $statement->close();
}

/** @param array<string, string> $values */
function reports_create(mysqli $database, int $userId, array $values, ?string $imageFilename = null): int
{
    $categoryId = (int) $values['category_id'];
    $eventDatetime = str_replace('T', ' ', $values['event_datetime']) . ':00';
    $privateNote = !empty($values['private_note']) ? $values['private_note'] : null;

    $database->begin_transaction();
    try {
        $statement = $database->prepare(
            'INSERT INTO item_reports (reporter_id, category_id, report_type, title, description, location, event_datetime, image_filename, private_note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param('iisssssss', $userId, $categoryId, $values['report_type'], $values['title'],
            $values['description'], $values['location'], $eventDatetime, $imageFilename, $privateNote);
        $statement->execute();
        $reportId = (int) $database->insert_id;
        $statement->close();
        reports_security_log($database, $userId, 'report_created', $reportId);
        $database->commit();
        return $reportId;
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}

/** @param array<string, string> $values */
function reports_update(mysqli $database, int $reportId, int $userId, array $values, ?string $imageFilename = null, bool $updateImage = false): void
{
    $categoryId = (int) $values['category_id'];
    $eventDatetime = str_replace('T', ' ', $values['event_datetime']) . ':00';
    $privateNote = !empty($values['private_note']) ? $values['private_note'] : null;

    $database->begin_transaction();
    try {
        if ($updateImage) {
            $statement = $database->prepare(
                'UPDATE item_reports SET category_id = ?, report_type = ?, title = ?, description = ?,
                 location = ?, event_datetime = ?, private_note = ?, image_filename = ? WHERE report_id = ?'
            );
            $statement->bind_param('isssssssi', $categoryId, $values['report_type'], $values['title'],
                $values['description'], $values['location'], $eventDatetime, $privateNote, $imageFilename, $reportId);
        } else {
            $statement = $database->prepare(
                'UPDATE item_reports SET category_id = ?, report_type = ?, title = ?, description = ?,
                 location = ?, event_datetime = ?, private_note = ? WHERE report_id = ?'
            );
            $statement->bind_param('issssssi', $categoryId, $values['report_type'], $values['title'],
                $values['description'], $values['location'], $eventDatetime, $privateNote, $reportId);
        }
        $statement->execute();
        $statement->close();
        reports_security_log($database, $userId, 'report_updated', $reportId);
        $database->commit();
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}

/**
 * Soft delete a report to preserve audit trail and prevent accidental cascade data loss.
 */
function reports_delete(mysqli $database, int $reportId, int $userId): void
{
    $database->begin_transaction();
    try {
        $statement = $database->prepare('UPDATE item_reports SET deleted_at = NOW(), status = "closed" WHERE report_id = ?');
        $statement->bind_param('i', $reportId);
        $statement->execute();
        $statement->close();
        reports_security_log($database, $userId, 'report_deleted', $reportId);
        $database->commit();
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}
