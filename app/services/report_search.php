<?php

declare(strict_types=1);

const REPORTS_PER_PAGE = 25;

/**
 * Normalize public report-list filters before they are used in a query.
 *
 * @param array<string, mixed> $input
 * @return array{q: string, report_type: string, category_id: int|null, status: string, page: int, reporter_id: int|null, my: bool}
 */
function normalize_report_filters(array $input): array
{
    $query_value = $input['q'] ?? '';
    $query = is_string($query_value) ? trim($query_value) : '';
    if ($query !== '') {
        $query = preg_match('/^.{0,150}/us', $query, $matches) === 1
            ? $matches[0]
            : '';
    }

    $report_type_value = $input['report_type'] ?? '';
    $report_type = is_string($report_type_value) ? $report_type_value : '';
    if (!in_array($report_type, ['lost', 'found'], true)) {
        $report_type = '';
    }

    $category_id_value = $input['category_id'] ?? null;
    $category_id = is_string($category_id_value) || is_int($category_id_value)
        ? filter_var($category_id_value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ])
        : false;

    $status_value = $input['status'] ?? '';
    $status = is_string($status_value) ? $status_value : '';
    if (!in_array($status, ['open', 'claimed', 'resolved', 'closed'], true)) {
        $status = '';
    }

    $page_value = $input['page'] ?? null;
    $page = is_string($page_value) || is_int($page_value)
        ? filter_var($page_value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ])
        : false;

    $reporter_id_value = $input['reporter_id'] ?? null;
    $reporter_id = is_string($reporter_id_value) || is_int($reporter_id_value)
        ? filter_var($reporter_id_value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ])
        : false;

    $my = ($input['my'] ?? '') === '1';

    return [
        'q' => $query,
        'report_type' => $report_type,
        'category_id' => $category_id === false ? null : $category_id,
        'status' => $status,
        'page' => $page === false ? 1 : $page,
        'reporter_id' => $reporter_id === false ? null : $reporter_id,
        'my' => $my,
    ];
}

/**
 * @return list<array{category_id: int, category_name: string}>
 */
function get_active_report_categories(mysqli $connection): array
{
    $statement = $connection->prepare(
        'SELECT category_id, category_name
         FROM categories
         WHERE is_active = 1
         ORDER BY category_name ASC'
    );
    $statement->execute();

    /** @var list<array{category_id: int, category_name: string}> $categories */
    $categories = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $categories;
}

/**
 * @param array<string, mixed> $filters
 * @return array{where_clause: string, types: string, parameters: list<string|int>}
 */
function build_report_filter_query(array $filters): array
{
    $conditions = ['r.deleted_at IS NULL'];
    $types = '';
    $parameters = [];

    if (!empty($filters['q'])) {
        // Use an explicit escape character so behavior is independent of SQL mode.
        $search_term = '%' . str_replace(
            ['=', '%', '_'],
            ['==', '=%', '=_'],
            (string) $filters['q']
        ) . '%';
        $conditions[] = "(r.title LIKE ? ESCAPE '=' OR r.description LIKE ? ESCAPE '=' OR r.location LIKE ? ESCAPE '=')";
        $types .= 'sss';
        array_push($parameters, $search_term, $search_term, $search_term);
    }

    if (!empty($filters['report_type'])) {
        $conditions[] = 'r.report_type = ?';
        $types .= 's';
        $parameters[] = (string) $filters['report_type'];
    }

    if (!empty($filters['category_id'])) {
        $conditions[] = 'r.category_id = ?';
        $types .= 'i';
        $parameters[] = (int) $filters['category_id'];
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'r.status = ?';
        $types .= 's';
        $parameters[] = (string) $filters['status'];
    }

    if (!empty($filters['reporter_id'])) {
        $conditions[] = 'r.reporter_id = ?';
        $types .= 'i';
        $parameters[] = (int) $filters['reporter_id'];
    }

    return [
        'where_clause' => $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions),
        'types' => $types,
        'parameters' => $parameters,
    ];
}

/**
 * @param array<string, mixed> $filters
 */
function count_item_reports(mysqli $connection, array $filters): int
{
    $query_parts = build_report_filter_query($filters);
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total
         FROM item_reports AS r
         INNER JOIN categories AS c ON c.category_id = r.category_id'
        . $query_parts['where_clause']
    );

    if ($query_parts['parameters'] !== []) {
        $statement->bind_param($query_parts['types'], ...$query_parts['parameters']);
    }

    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    $statement->close();

    return (int) ($row['total'] ?? 0);
}

/**
 * @param array<string, mixed> $filters
 * @return list<array{report_id: int, report_type: string, title: string, category_name: string, location: string, event_datetime: string, status: string}>
 */
function search_item_reports(
    mysqli $connection,
    array $filters,
    int $limit = REPORTS_PER_PAGE,
    int $offset = 0
): array {
    $limit = max(1, min($limit, 100));
    $offset = max(0, $offset);
    $query_parts = build_report_filter_query($filters);

    $statement = $connection->prepare(
        'SELECT r.report_id, r.report_type, r.title, r.description, c.category_name, r.location,
                r.event_datetime, r.status, r.image_filename
         FROM item_reports AS r
         INNER JOIN categories AS c ON c.category_id = r.category_id'
        . $query_parts['where_clause'] .
        ' ORDER BY r.event_datetime DESC, r.report_id DESC
          LIMIT ? OFFSET ?'
    );

    $types = $query_parts['types'] . 'ii';
    $parameters = [...$query_parts['parameters'], $limit, $offset];
    $statement->bind_param($types, ...$parameters);

    $statement->execute();

    /** @var list<array{report_id: int, report_type: string, title: string, category_name: string, location: string, event_datetime: string, status: string}> $reports */
    $reports = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $reports;
}
