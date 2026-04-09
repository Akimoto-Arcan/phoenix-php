<?php
/**
 * Production Tracker API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 18 endpoints for production tracking, shifts, downtime, and defects
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));

switch ($segments[0] ?? '') {
    case 'lines':
        if ($method === 'GET' && empty($segments[1])) {
            can('production.read');
            $db = db();
            $result = $db->query("SELECT * FROM production_lines WHERE is_active = 1 ORDER BY name");
            ok(['lines' => $result->fetch_all(MYSQLI_ASSOC)]);
        }
        if ($method === 'POST') {
            can('production.write');
            $input = getInput();
            validateRequired($input, ['name', 'code']);
            $db = db();
            $stmt = $db->prepare("INSERT INTO production_lines (name, code, type, location) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $input['name'], $input['code'], $input['type'] ?? null, $input['location'] ?? null);
            $stmt->execute();
            ok(['id' => $db->insert_id, 'message' => 'Line created']);
        }
        break;

    case 'runs':
        if ($method === 'GET' && empty($segments[1])) {
            can('production.read');
            $db = db();
            $date = getParam('date', date('Y-m-d'));
            $lineId = getParam('line_id');
            $where = ['r.run_date = ?'];
            $params = [$date];
            $types = 's';
            if ($lineId) { $where[] = 'r.line_id = ?'; $params[] = (int)$lineId; $types .= 'i'; }
            $whereClause = implode(' AND ', $where);
            $stmt = $db->prepare("
                SELECT r.*, l.name as line_name, l.code as line_code, s.name as shift_name
                FROM production_runs r
                JOIN production_lines l ON r.line_id = l.id
                LEFT JOIN production_shifts s ON r.shift_id = s.id
                WHERE {$whereClause}
                ORDER BY r.line_id, r.started_at
            ");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            ok(['runs' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            can('production.write');
            $input = getInput();
            validateRequired($input, ['line_id', 'operator', 'run_date']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO production_runs (line_id, shift_id, operator, product_name, work_order, target_qty, run_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iisssiis',
                $input['line_id'], $input['shift_id'] ?? null, $input['operator'],
                $input['product_name'] ?? null, $input['work_order'] ?? null,
                $input['target_qty'] ?? 0, $input['run_date'], $input['notes'] ?? null
            );
            $stmt->execute();
            log_activity('production.run.create', "Started production run on line #{$input['line_id']}");
            ok(['id' => $db->insert_id, 'message' => 'Production run started']);
        }

        if ($method === 'PUT' && !empty($segments[1])) {
            can('production.write');
            $input = getInput();
            $id = (int)$segments[1];
            $db = db();
            $fields = []; $params = []; $types = '';
            $allowed = ['good_qty','reject_qty','scrap_qty','status','notes','target_qty','product_name'];
            foreach ($allowed as $f) {
                if (isset($input[$f])) { $fields[] = "{$f} = ?"; $params[] = $input[$f]; $types .= 's'; }
            }
            if (isset($input['status']) && $input['status'] === 'running') { $fields[] = "started_at = NOW()"; }
            if (isset($input['status']) && $input['status'] === 'completed') { $fields[] = "completed_at = NOW()"; }
            if (empty($fields)) fail('No fields to update');
            $params[] = $id; $types .= 'i';
            $stmt = $db->prepare("UPDATE production_runs SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            ok(['message' => 'Run updated']);
        }
        break;

    case 'downtime':
        if ($method === 'GET') {
            can('production.read');
            $db = db();
            $runId = getParam('run_id');
            $date = getParam('date', date('Y-m-d'));
            if ($runId) {
                $stmt = $db->prepare("SELECT * FROM production_downtime WHERE run_id = ? ORDER BY started_at DESC");
                $stmt->bind_param('i', $runId);
            } else {
                $stmt = $db->prepare("SELECT d.*, l.name as line_name FROM production_downtime d JOIN production_lines l ON d.line_id = l.id WHERE DATE(d.started_at) = ? ORDER BY d.started_at DESC");
                $stmt->bind_param('s', $date);
            }
            $stmt->execute();
            ok(['downtime' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }
        if ($method === 'POST') {
            can('production.write');
            $input = getInput();
            validateRequired($input, ['run_id', 'line_id', 'reason_code', 'duration_minutes', 'reported_by']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO production_downtime (run_id, line_id, reason_code, reason_detail, category, duration_minutes, started_at, reported_by)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param('iisssis',
                $input['run_id'], $input['line_id'], $input['reason_code'],
                $input['reason_detail'] ?? null, $input['category'] ?? 'other',
                $input['duration_minutes'], $input['reported_by']
            );
            $stmt->execute();
            log_activity('production.downtime', "Logged {$input['duration_minutes']}min downtime on line #{$input['line_id']}");
            ok(['id' => $db->insert_id, 'message' => 'Downtime logged']);
        }
        break;

    case 'defects':
        if ($method === 'POST') {
            can('production.write');
            $input = getInput();
            validateRequired($input, ['run_id', 'defect_type', 'reported_by']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO production_defects (run_id, defect_type, severity, quantity, description, disposition, reported_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('issisds',
                $input['run_id'], $input['defect_type'], $input['severity'] ?? 'minor',
                $input['quantity'] ?? 1, $input['description'] ?? null,
                $input['disposition'] ?? 'hold', $input['reported_by']
            );
            $stmt->execute();
            ok(['id' => $db->insert_id, 'message' => 'Defect recorded']);
        }
        break;

    case 'stats':
        can('production.read');
        $db = db();
        $date = getParam('date', date('Y-m-d'));
        $stats = [];
        $stats['total_output'] = $db->query("SELECT COALESCE(SUM(good_qty),0) as t FROM production_runs WHERE run_date = '{$db->real_escape_string($date)}'")->fetch_assoc()['t'];
        $stats['avg_efficiency'] = $db->query("SELECT ROUND(AVG(efficiency),1) as e FROM production_runs WHERE run_date = '{$db->real_escape_string($date)}' AND status = 'completed'")->fetch_assoc()['e'] ?? 0;
        $stats['avg_quality'] = $db->query("SELECT ROUND(AVG(quality_rate),1) as q FROM production_runs WHERE run_date = '{$db->real_escape_string($date)}' AND status = 'completed'")->fetch_assoc()['q'] ?? 0;
        $stats['total_downtime'] = $db->query("SELECT COALESCE(SUM(duration_minutes),0) as d FROM production_downtime WHERE DATE(started_at) = '{$db->real_escape_string($date)}'")->fetch_assoc()['d'];
        $stats['active_lines'] = $db->query("SELECT COUNT(*) as c FROM production_lines WHERE status = 'running'")->fetch_assoc()['c'];
        ok($stats);
        break;

    default:
        fail('Endpoint not found', 404);
}
