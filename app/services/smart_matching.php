<?php

declare(strict_types=1);

/**
 * Smart Matching Service
 *
 * Automatically matches 'lost' items with 'found' items based on category,
 * location, date proximity, and textual similarity.
 */

/**
 * Find potential matches for a given report.
 *
 * @param mysqli $db Database connection
 * @param array<string, mixed> $report The target report array
 * @param int $limit Maximum number of recommendations to return
 * @return list<array<string, mixed>> List of matching reports with match score and reasons
 */
function find_smart_matches(mysqli $db, array $report, int $limit = 4): array
{
    $reportId = (int) ($report['report_id'] ?? 0);
    $categoryId = (int) ($report['category_id'] ?? 0);
    $targetType = ($report['report_type'] ?? '') === 'lost' ? 'found' : 'lost';
    $eventDatetime = (string) ($report['event_datetime'] ?? '');

    if ($reportId <= 0 || $categoryId <= 0) {
        return [];
    }

    // Query candidates of the opposite report type in the same or related category, not resolved/closed, not soft-deleted
    $sql = 'SELECT r.report_id, r.reporter_id, r.category_id, r.report_type, r.title,
                   r.description, r.location, r.event_datetime, r.image_filename, r.status,
                   c.category_name, u.full_name AS reporter_name
            FROM item_reports r
            JOIN categories c ON c.category_id = r.category_id
            JOIN users u ON u.user_id = r.reporter_id
            WHERE r.report_id != ?
              AND r.report_type = ?
              AND r.category_id = ?
              AND r.status IN ("open", "claimed")
              AND r.deleted_at IS NULL
            ORDER BY r.created_at DESC
            LIMIT 20';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('isi', $reportId, $targetType, $categoryId);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($candidates === []) {
        return [];
    }

    // Prepare target keywords
    $targetText = mb_strtolower($report['title'] . ' ' . $report['location'] . ' ' . $report['description']);
    $targetWords = extract_meaningful_words($targetText);
    $targetTime = strtotime($eventDatetime);

    $scoredMatches = [];

    foreach ($candidates as $cand) {
        $score = 40; // Base score for same category & active status
        $reasons = ['หมวดหมู่เดียวกัน (' . $cand['category_name'] . ')'];

        // 1. Date proximity (within 7 days = +25, within 21 days = +15, within 45 days = +5)
        $candTime = strtotime((string) $cand['event_datetime']);
        if ($targetTime && $candTime) {
            $diffDays = abs($targetTime - $candTime) / 86400;
            if ($diffDays <= 7) {
                $score += 25;
                $reasons[] = 'ช่วงเวลาใกล้เคียงกัน (ห่างกันไม่เกิน 7 วัน)';
            } elseif ($diffDays <= 21) {
                $score += 15;
                $reasons[] = 'ช่วงเวลาใกล้เคียงกัน (ห่างกันไม่เกิน 3 สัปดาห์)';
            } elseif ($diffDays <= 45) {
                $score += 5;
            }
        }

        // 2. Location match
        $candLocation = mb_strtolower((string) $cand['location']);
        $targetLocation = mb_strtolower((string) $report['location']);
        if ($candLocation === $targetLocation) {
            $score += 25;
            $reasons[] = 'สถานที่ตรงกัน';
        } else {
            $locWords = extract_meaningful_words($candLocation);
            $commonLoc = array_intersect($locWords, extract_meaningful_words($targetLocation));
            if ($commonLoc !== []) {
                $score += 15;
                $reasons[] = 'บริเวณสถานที่ใกล้เคียงกัน (' . implode(', ', array_slice($commonLoc, 0, 2)) . ')';
            }
        }

        // 3. Keyword / title overlap
        $candText = mb_strtolower($cand['title'] . ' ' . $cand['description']);
        $candWords = extract_meaningful_words($candText);
        $commonWords = array_intersect($targetWords, $candWords);

        if ($commonWords !== []) {
            $bonus = min(20, count($commonWords) * 7);
            $score += $bonus;
            $sampleWords = array_slice(array_values($commonWords), 0, 3);
            $reasons[] = 'คำสำคัญตรงกัน (' . implode(', ', $sampleWords) . ')';
        }

        $cand['match_score'] = min(99, $score);
        $cand['match_reasons'] = $reasons;
        $scoredMatches[] = $cand;
    }

    // Sort by match_score descending
    usort($scoredMatches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

    return array_slice($scoredMatches, 0, $limit);
}

/**
 * Helper to split text into words, filtering out short/noise words.
 *
 * @param string $text
 * @return list<string>
 */
function extract_meaningful_words(string $text): array
{
    // Remove punctuation while preserving letters, combining marks (Thai vowels/tones), numbers, and whitespace
    $clean = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $text) ?? '';
    $rawWords = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $stopWords = ['และ', 'หรือ', 'ที่', 'ใน', 'กับ', 'มี', 'ไป', 'มา', 'ของ', 'เป็น', 'ได้', 'ติด', 'ตรง', 'แถว', 'บริเวณ', 'the', 'and', 'for', 'with', 'item', 'lost', 'found'];
    $words = [];

    foreach ($rawWords as $w) {
        $w = trim($w);
        if (mb_strlen($w) >= 3 && !in_array($w, $stopWords, true)) {
            $words[$w] = $w;
        }
    }

    return array_values($words);
}
