<?php
/**
 * CMMS Maintenance Management API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 20 endpoints covering work orders, PM schedules, equipment, and parts
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));

switch ($segments[0] ?? '') {
    // ==================== WORK ORDERS ====================
    case 'work-orders':
        if ($method === 'GET' && empty($segments[1])) {
            can('maintenance.read');
            $page = (int) getParam('page', 1);
            $perPage = min((int) getParam('per_page', 20), 100);
            $offset = ($page - 1) * $perPage;
            $status = getParam('status');
            $priority = getParam('priority');
            $assigned = getParam('assigned_to');

            $where = ['1=1'];
            $params = [];
            $types = '';

            if ($status) { $where[] = 'w.status = ?'; $params[] = $status; $types .= 's'; }
            if ($priority) { $where[] = 'w.priority = ?'; $params[] = $priority; $types .= 's'; }
            if ($assigned) { $where[] = 'w.assigned_to = ?'; $params[] = $assigned; $types .= 's'; }

            $whereClause = implode(' AND ', $where);
            $db = db();

            $countStmt = $db->prepare("SELECT COUNT(*) FROM cmms_work_orders w WHERE {$whereClause}");
            if ($types) $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];

            $stmt = $db->prepare("
                SELECT w.*, e.name as equipment_name, e.asset_tag, e.location as equipment_location
                FROM cmms_work_orders w
                LEFT JOIN cmms_equipment e ON w.equipment_id = e.id
                WHERE {$whereClause}
                ORDER BY FIELD(w.priority, 'emergency','high','medium','low'), w.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $types .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            ok([
                'work_orders' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => (int)$total, 'pages' => ceil($total / $perPage)]
            ]);
        }

        if ($method === 'GET' && !empty($segments[1]) && is_numeric($segments[1])) {
            can('maintenance.read');
            $db = db();
            $stmt = $db->prepare("
                SELECT w.*, e.name as equipment_name, e.asset_tag
                FROM cmms_work_orders w
                LEFT JOIN cmms_equipment e ON w.equipment_id = e.id
                WHERE w.id = ?
            ");
            $id = (int) $segments[1];
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $wo = $stmt->get_result()->fetch_assoc();
            if (!$wo) fail('Work order not found', 404);

            // Get parts used
            $partStmt = $db->prepare("
                SELECT wp.*, p.name as part_name, p.part_number
                FROM cmms_wo_parts wp
                JOIN cmms_parts p ON wp.part_id = p.id
                WHERE wp.work_order_id = ?
            ");
            $partStmt->bind_param('i', $id);
            $partStmt->execute();
            $wo['parts'] = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Get comments
            $commentStmt = $db->prepare("SELECT * FROM cmms_wo_comments WHERE work_order_id = ? ORDER BY created_at DESC");
            $commentStmt->bind_param('i', $id);
            $commentStmt->execute();
            $wo['comments'] = $commentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            ok($wo);
        }

        if ($method === 'POST' && empty($segments[1])) {
            can('maintenance.write');
            $input = getInput();
            validateRequired($input, ['title', 'requested_by']);

            $db = db();
            // Generate WO number
            $result = $db->query("SELECT MAX(id) as max_id FROM cmms_work_orders");
            $nextId = ($result->fetch_assoc()['max_id'] ?? 0) + 1;
            $woNumber = 'WO-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO cmms_work_orders (wo_number, title, description, equipment_id, type, priority, requested_by, assigned_to, estimated_hours, due_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssississss',
                $woNumber, $input['title'], $input['description'] ?? null,
                $input['equipment_id'] ?? null, $input['type'] ?? 'corrective',
                $input['priority'] ?? 'medium', $input['requested_by'],
                $input['assigned_to'] ?? null, $input['estimated_hours'] ?? null,
                $input['due_date'] ?? null, $input['notes'] ?? null
            );
            $stmt->execute();

            log_activity('maintenance.wo.create', "Created work order {$woNumber}");
            ok(['id' => $db->insert_id, 'wo_number' => $woNumber, 'message' => 'Work order created']);
        }

        if ($method === 'PUT' && !empty($segments[1])) {
            can('maintenance.write');
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $fields = [];
            $params = [];
            $types = '';
            $allowed = ['title','description','equipment_id','type','priority','status','assigned_to','estimated_hours','actual_hours','labor_cost','parts_cost','downtime_hours','due_date','notes'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= 's';
                }
            }

            // Handle status transitions
            if (isset($input['status'])) {
                if ($input['status'] === 'in_progress' && !isset($input['started_at'])) {
                    $fields[] = "started_at = NOW()";
                }
                if ($input['status'] === 'completed') {
                    $fields[] = "completed_at = NOW()";
                }
            }

            if (empty($fields)) fail('No fields to update');

            $params[] = $id;
            $types .= 'i';

            $stmt = $db->prepare("UPDATE cmms_work_orders SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            log_activity('maintenance.wo.update', "Updated work order #{$id}");
            ok(['message' => 'Work order updated']);
        }

        // POST work-orders/{id}/comment
        if ($method === 'POST' && !empty($segments[1]) && ($segments[2] ?? '') === 'comment') {
            can('maintenance.write');
            $input = getInput();
            validateRequired($input, ['comment']);
            $db = db();
            $user = \Auth::user()['username'] ?? 'system';
            $stmt = $db->prepare("INSERT INTO cmms_wo_comments (work_order_id, user, comment) VALUES (?, ?, ?)");
            $woId = (int) $segments[1];
            $stmt->bind_param('iss', $woId, $user, $input['comment']);
            $stmt->execute();
            ok(['message' => 'Comment added']);
        }
        break;

    // ==================== EQUIPMENT ====================
    case 'equipment':
        if ($method === 'GET' && empty($segments[1])) {
            can('maintenance.read');
            $db = db();
            $status = getParam('status');
            $where = $status ? "WHERE status = '{$db->real_escape_string($status)}'" : '';
            $result = $db->query("SELECT * FROM cmms_equipment {$where} ORDER BY name");
            ok(['equipment' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            can('maintenance.write');
            $input = getInput();
            validateRequired($input, ['asset_tag', 'name']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO cmms_equipment (asset_tag, name, description, category, manufacturer, model, serial_number, location, department, install_date, warranty_expiry, criticality, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssssssssss',
                $input['asset_tag'], $input['name'], $input['description'] ?? null,
                $input['category'] ?? null, $input['manufacturer'] ?? null,
                $input['model'] ?? null, $input['serial_number'] ?? null,
                $input['location'] ?? null, $input['department'] ?? null,
                $input['install_date'] ?? null, $input['warranty_expiry'] ?? null,
                $input['criticality'] ?? 'medium', $input['notes'] ?? null
            );
            $stmt->execute();
            log_activity('maintenance.equipment.create', "Created equipment {$input['asset_tag']}");
            ok(['id' => $db->insert_id, 'message' => 'Equipment created']);
        }

        if ($method === 'GET' && !empty($segments[1]) && ($segments[2] ?? '') === 'history') {
            can('maintenance.read');
            $db = db();
            $eqId = (int) $segments[1];
            $stmt = $db->prepare("SELECT * FROM cmms_work_orders WHERE equipment_id = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->bind_param('i', $eqId);
            $stmt->execute();
            ok(['history' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }
        break;

    // ==================== PM SCHEDULES ====================
    case 'pm-schedules':
        if ($method === 'GET' && empty($segments[1])) {
            can('maintenance.read');
            $db = db();
            $result = $db->query("
                SELECT pm.*, e.name as equipment_name, e.asset_tag
                FROM cmms_pm_schedules pm
                JOIN cmms_equipment e ON pm.equipment_id = e.id
                WHERE pm.is_active = 1
                ORDER BY pm.next_due ASC
            ");
            ok(['schedules' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'GET' && ($segments[1] ?? '') === 'overdue') {
            can('maintenance.read');
            $db = db();
            $result = $db->query("
                SELECT pm.*, e.name as equipment_name, e.asset_tag
                FROM cmms_pm_schedules pm
                JOIN cmms_equipment e ON pm.equipment_id = e.id
                WHERE pm.is_active = 1 AND pm.next_due < CURDATE()
                ORDER BY pm.next_due ASC
            ");
            ok(['overdue' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            can('maintenance.write');
            $input = getInput();
            validateRequired($input, ['equipment_id', 'title', 'frequency', 'next_due']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO cmms_pm_schedules (equipment_id, title, description, frequency, next_due, assigned_to, estimated_hours, checklist)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $checklist = isset($input['checklist']) ? json_encode($input['checklist']) : null;
            $stmt->bind_param('isssssis',
                $input['equipment_id'], $input['title'], $input['description'] ?? null,
                $input['frequency'], $input['next_due'], $input['assigned_to'] ?? null,
                $input['estimated_hours'] ?? null, $checklist
            );
            $stmt->execute();
            log_activity('maintenance.pm.create', "Created PM schedule for equipment #{$input['equipment_id']}");
            ok(['id' => $db->insert_id, 'message' => 'PM schedule created']);
        }
        break;

    // ==================== PARTS ====================
    case 'parts':
        if ($method === 'GET') {
            can('maintenance.read');
            $db = db();
            $result = $db->query("SELECT * FROM cmms_parts ORDER BY name");
            ok(['parts' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            can('maintenance.write');
            $input = getInput();
            validateRequired($input, ['part_number', 'name']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO cmms_parts (part_number, name, description, quantity_on_hand, reorder_point, unit_cost, supplier, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssiidss',
                $input['part_number'], $input['name'], $input['description'] ?? null,
                $input['quantity_on_hand'] ?? 0, $input['reorder_point'] ?? 0,
                $input['unit_cost'] ?? 0, $input['supplier'] ?? null, $input['location'] ?? null
            );
            $stmt->execute();
            ok(['id' => $db->insert_id, 'message' => 'Part created']);
        }
        break;

    // ==================== DASHBOARD STATS ====================
    case 'stats':
        can('maintenance.read');
        $db = db();
        $stats = [];
        $stats['open_work_orders'] = $db->query("SELECT COUNT(*) as c FROM cmms_work_orders WHERE status IN ('open','assigned','in_progress')")->fetch_assoc()['c'];
        $stats['overdue_pm'] = $db->query("SELECT COUNT(*) as c FROM cmms_pm_schedules WHERE is_active = 1 AND next_due < CURDATE()")->fetch_assoc()['c'];
        $stats['equipment_down'] = $db->query("SELECT COUNT(*) as c FROM cmms_equipment WHERE status IN ('maintenance','down')")->fetch_assoc()['c'];
        $stats['completed_this_month'] = $db->query("SELECT COUNT(*) as c FROM cmms_work_orders WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE())")->fetch_assoc()['c'];
        $stats['avg_completion_hours'] = $db->query("SELECT ROUND(AVG(actual_hours),1) as avg FROM cmms_work_orders WHERE status = 'completed' AND actual_hours IS NOT NULL")->fetch_assoc()['avg'] ?? 0;
        $stats['total_cost_this_month'] = $db->query("SELECT ROUND(SUM(total_cost),2) as total FROM cmms_work_orders WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURDATE())")->fetch_assoc()['total'] ?? 0;
        ok($stats);
        break;

    default:
        fail('Endpoint not found', 404);
}
