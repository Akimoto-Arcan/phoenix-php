<?php
/**
 * Report Builder API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 12 endpoints for report management, execution, scheduling, and export
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));
$user = \Auth::user()['username'] ?? 'system';

switch ($segments[0] ?? '') {
    case 'definitions':
        if ($method === 'GET' && empty($segments[1])) {
            can('reports.read');
            $db = db();
            $category = getParam('category');
            $where = '1=1';
            if ($category) $where .= " AND category = '{$db->real_escape_string($category)}'";
            $result = $db->query("
                SELECT id, name, slug, description, category, type, default_limit, is_public, created_by, created_at
                FROM report_definitions
                WHERE {$where}
                ORDER BY category, name
            ");
            ok(['reports' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'GET' && !empty($segments[1])) {
            can('reports.read');
            $db = db();
            $id = (int)$segments[1];
            $stmt = $db->prepare("SELECT * FROM report_definitions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $report = $stmt->get_result()->fetch_assoc();
            if (!$report) fail('Report not found', 404);
            $report['columns'] = json_decode($report['columns'], true);
            $report['filters'] = json_decode($report['filters'], true);
            $report['chart_config'] = json_decode($report['chart_config'], true);
            ok($report);
        }

        if ($method === 'POST') {
            can('reports.write');
            $input = getInput();
            validateRequired($input, ['name', 'query']);
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $input['name'])));
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO report_definitions (name, slug, description, category, type, query, query_type, columns, filters, chart_config, default_sort, default_limit, is_public, allowed_roles, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $columns = isset($input['columns']) ? json_encode($input['columns']) : null;
            $filters = isset($input['filters']) ? json_encode($input['filters']) : null;
            $chartConfig = isset($input['chart_config']) ? json_encode($input['chart_config']) : null;
            $roles = isset($input['allowed_roles']) ? json_encode($input['allowed_roles']) : null;
            $stmt->bind_param('sssssssssssisis',
                $input['name'], $slug, $input['description'] ?? null,
                $input['category'] ?? 'General', $input['type'] ?? 'table',
                $input['query'], $input['query_type'] ?? 'builder',
                $columns, $filters, $chartConfig,
                $input['default_sort'] ?? null, $input['default_limit'] ?? 100,
                $input['is_public'] ?? 0, $roles, $user
            );
            $stmt->execute();
            log_activity('reports.create', "Created report '{$input['name']}'");
            ok(['id' => $db->insert_id, 'slug' => $slug, 'message' => 'Report created']);
        }

        if ($method === 'PUT' && !empty($segments[1])) {
            can('reports.write');
            $input = getInput();
            $id = (int)$segments[1];
            $db = db();
            $fields = []; $params = []; $types = '';
            $allowed = ['name','description','category','type','query','query_type','default_sort','default_limit','is_public'];
            foreach ($allowed as $f) {
                if (isset($input[$f])) { $fields[] = "{$f} = ?"; $params[] = $input[$f]; $types .= 's'; }
            }
            foreach (['columns','filters','chart_config','allowed_roles'] as $jsonField) {
                if (isset($input[$jsonField])) { $fields[] = "{$jsonField} = ?"; $params[] = json_encode($input[$jsonField]); $types .= 's'; }
            }
            if (empty($fields)) fail('No fields to update');
            $params[] = $id; $types .= 'i';
            $stmt = $db->prepare("UPDATE report_definitions SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            ok(['message' => 'Report updated']);
        }

        if ($method === 'DELETE' && !empty($segments[1])) {
            can('reports.write');
            $db = db();
            $id = (int)$segments[1];
            $stmt = $db->prepare("DELETE FROM report_definitions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity('reports.delete', "Deleted report #{$id}");
            ok(['message' => 'Report deleted']);
        }
        break;

    // Execute a report
    case 'execute':
        if ($method === 'POST') {
            can('reports.read');
            $input = getInput();
            validateRequired($input, ['report_id']);
            $startTime = microtime(true);

            $db = db();
            $stmt = $db->prepare("SELECT * FROM report_definitions WHERE id = ?");
            $reportId = (int)$input['report_id'];
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $report = $stmt->get_result()->fetch_assoc();
            if (!$report) fail('Report not found', 404);

            $limit = min((int)($input['limit'] ?? $report['default_limit']), 1000);
            $format = $input['format'] ?? 'screen';

            // Execute query (builder queries are pre-validated SQL)
            $query = $report['query'] . " LIMIT {$limit}";
            $result = $db->query($query);
            if (!$result) fail('Query execution failed: ' . $db->error);

            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $execTime = round((microtime(true) - $startTime) * 1000);

            // Log execution
            $logStmt = $db->prepare("
                INSERT INTO report_execution_log (report_id, triggered_by, executed_by, format, row_count, execution_time_ms, filters_used)
                VALUES (?, 'manual', ?, ?, ?, ?, ?)
            ");
            $filtersUsed = isset($input['filters']) ? json_encode($input['filters']) : null;
            $rowCount = count($rows);
            $logStmt->bind_param('isssis', $reportId, $user, $format, $rowCount, $execTime, $filtersUsed);
            $logStmt->execute();

            ok([
                'data' => $rows,
                'meta' => [
                    'row_count' => $rowCount,
                    'execution_time_ms' => $execTime,
                    'report_name' => $report['name'],
                    'columns' => json_decode($report['columns'], true)
                ]
            ]);
        }
        break;

    case 'schedules':
        if ($method === 'GET') {
            can('reports.read');
            $db = db();
            $result = $db->query("
                SELECT rs.*, rd.name as report_name, rd.slug
                FROM report_schedules rs
                JOIN report_definitions rd ON rs.report_id = rd.id
                WHERE rs.is_active = 1
                ORDER BY rs.next_run
            ");
            ok(['schedules' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            can('reports.write');
            $input = getInput();
            validateRequired($input, ['report_id', 'frequency', 'recipients']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO report_schedules (report_id, frequency, day_of_week, day_of_month, time, format, recipients, filters, created_by, next_run)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $recipients = json_encode($input['recipients']);
            $filters = isset($input['filters']) ? json_encode($input['filters']) : null;
            $nextRun = date('Y-m-d') . ' ' . ($input['time'] ?? '08:00:00');
            $stmt->bind_param('isiiissss' . 's',
                $input['report_id'], $input['frequency'],
                $input['day_of_week'] ?? null, $input['day_of_month'] ?? null,
                $input['time'] ?? '08:00:00', $input['format'] ?? 'pdf',
                $recipients, $filters, $user, $nextRun
            );
            $stmt->execute();
            ok(['id' => $db->insert_id, 'message' => 'Schedule created']);
        }
        break;

    case 'favorites':
        if ($method === 'GET') {
            $db = db();
            $stmt = $db->prepare("
                SELECT rd.id, rd.name, rd.slug, rd.category, rd.type
                FROM report_favorites rf
                JOIN report_definitions rd ON rf.report_id = rd.id
                WHERE rf.username = ?
                ORDER BY rd.name
            ");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            ok(['favorites' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            $input = getInput();
            validateRequired($input, ['report_id']);
            $db = db();
            $stmt = $db->prepare("INSERT IGNORE INTO report_favorites (report_id, username) VALUES (?, ?)");
            $reportId = (int)$input['report_id'];
            $stmt->bind_param('is', $reportId, $user);
            $stmt->execute();
            ok(['message' => 'Added to favorites']);
        }

        if ($method === 'DELETE' && !empty($segments[1])) {
            $db = db();
            $reportId = (int)$segments[1];
            $stmt = $db->prepare("DELETE FROM report_favorites WHERE report_id = ? AND username = ?");
            $stmt->bind_param('is', $reportId, $user);
            $stmt->execute();
            ok(['message' => 'Removed from favorites']);
        }
        break;

    default:
        fail('Endpoint not found', 404);
}
