<?php
/**
 * Shift Scheduling API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 14 endpoints for scheduling, availability, swaps, and time-off
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));
$user = \Auth::user()['username'] ?? 'system';

switch ($segments[0] ?? '') {
    case 'assignments':
        if ($method === 'GET') {
            can('scheduling.read');
            $db = db();
            $startDate = getParam('start', date('Y-m-d'));
            $endDate = getParam('end', date('Y-m-d', strtotime('+7 days')));
            $department = getParam('department_id');
            $where = ['a.schedule_date BETWEEN ? AND ?'];
            $params = [$startDate, $endDate];
            $types = 'ss';
            if ($department) { $where[] = 'a.department_id = ?'; $params[] = (int)$department; $types .= 'i'; }
            $whereClause = implode(' AND ', $where);
            $stmt = $db->prepare("
                SELECT a.*, s.name as shift_name, s.start_time, s.end_time, s.color, d.name as department_name
                FROM schedule_assignments a
                JOIN schedule_shifts s ON a.shift_id = s.id
                LEFT JOIN schedule_departments d ON a.department_id = d.id
                WHERE {$whereClause}
                ORDER BY a.schedule_date, s.start_time, a.username
            ");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            ok(['assignments' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            can('scheduling.write');
            $input = getInput();
            validateRequired($input, ['username', 'shift_id', 'schedule_date']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO schedule_assignments (username, shift_id, department_id, schedule_date, start_override, end_override, overtime, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('siisssiss',
                $input['username'], $input['shift_id'], $input['department_id'] ?? null,
                $input['schedule_date'], $input['start_override'] ?? null,
                $input['end_override'] ?? null, $input['overtime'] ?? 0,
                $input['notes'] ?? null, $user
            );
            $stmt->execute();
            log_activity('scheduling.assign', "Scheduled {$input['username']} for {$input['schedule_date']}");
            ok(['id' => $db->insert_id, 'message' => 'Assignment created']);
        }

        if ($method === 'DELETE' && !empty($segments[1])) {
            can('scheduling.write');
            $db = db();
            $id = (int)$segments[1];
            $stmt = $db->prepare("UPDATE schedule_assignments SET status = 'called_off' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            log_activity('scheduling.cancel', "Cancelled assignment #{$id}");
            ok(['message' => 'Assignment cancelled']);
        }
        break;

    case 'availability':
        if ($method === 'GET') {
            $targetUser = getParam('username', $user);
            $db = db();
            $stmt = $db->prepare("
                SELECT a.*, s.name as preferred_shift_name
                FROM schedule_availability a
                LEFT JOIN schedule_shifts s ON a.preferred_shift_id = s.id
                WHERE a.username = ?
                ORDER BY a.day_of_week
            ");
            $stmt->bind_param('s', $targetUser);
            $stmt->execute();
            ok(['availability' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            $input = getInput();
            validateRequired($input, ['day_of_week']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO schedule_availability (username, day_of_week, available, preferred_shift_id, notes)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE available = VALUES(available), preferred_shift_id = VALUES(preferred_shift_id), notes = VALUES(notes)
            ");
            $stmt->bind_param('siiis',
                $user, $input['day_of_week'], $input['available'] ?? 1,
                $input['preferred_shift_id'] ?? null, $input['notes'] ?? null
            );
            $stmt->execute();
            ok(['message' => 'Availability updated']);
        }
        break;

    case 'swaps':
        if ($method === 'GET') {
            $db = db();
            $status = getParam('status', 'pending');
            $stmt = $db->prepare("
                SELECT sr.*, a1.schedule_date as original_date, s1.name as original_shift
                FROM schedule_swap_requests sr
                JOIN schedule_assignments a1 ON sr.assignment_id = a1.id
                JOIN schedule_shifts s1 ON a1.shift_id = s1.id
                WHERE sr.status = ? AND (sr.requester = ? OR sr.target_user = ?)
                ORDER BY sr.created_at DESC
            ");
            $stmt->bind_param('sss', $status, $user, $user);
            $stmt->execute();
            ok(['swaps' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['assignment_id']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO schedule_swap_requests (requester, assignment_id, target_user, target_assignment_id, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('siiss',
                $user, $input['assignment_id'], $input['target_user'] ?? null,
                $input['target_assignment_id'] ?? null, $input['reason'] ?? null
            );
            $stmt->execute();
            log_activity('scheduling.swap.request', "Swap request for assignment #{$input['assignment_id']}");
            ok(['id' => $db->insert_id, 'message' => 'Swap request submitted']);
        }

        // PUT swaps/{id}/approve or /deny
        if ($method === 'PUT' && !empty($segments[1])) {
            can('scheduling.write');
            $input = getInput();
            $id = (int)$segments[1];
            $action = $input['action'] ?? 'approved';
            $db = db();
            $stmt = $db->prepare("UPDATE schedule_swap_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssi', $action, $user, $id);
            $stmt->execute();
            log_activity('scheduling.swap.' . $action, "Swap request #{$id} {$action}");
            ok(['message' => "Swap request {$action}"]);
        }
        break;

    case 'time-off':
        if ($method === 'GET') {
            $db = db();
            $targetUser = getParam('username', $user);
            $stmt = $db->prepare("SELECT * FROM schedule_time_off WHERE username = ? ORDER BY start_date DESC LIMIT 50");
            $stmt->bind_param('s', $targetUser);
            $stmt->execute();
            ok(['requests' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            $input = getInput();
            validateRequired($input, ['type', 'start_date', 'end_date']);
            $db = db();
            $stmt = $db->prepare("
                INSERT INTO schedule_time_off (username, type, start_date, end_date, hours, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssds',
                $user, $input['type'], $input['start_date'], $input['end_date'],
                $input['hours'] ?? null, $input['reason'] ?? null
            );
            $stmt->execute();
            log_activity('scheduling.timeoff.request', "Time-off request {$input['start_date']} to {$input['end_date']}");
            ok(['id' => $db->insert_id, 'message' => 'Time-off request submitted']);
        }
        break;

    case 'shifts':
        if ($method === 'GET') {
            $db = db();
            ok(['shifts' => $db->query("SELECT * FROM schedule_shifts WHERE is_active = 1 ORDER BY start_time")->fetch_all(MYSQLI_ASSOC)]);
        }
        break;

    case 'departments':
        if ($method === 'GET') {
            $db = db();
            ok(['departments' => $db->query("SELECT * FROM schedule_departments WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC)]);
        }
        break;

    default:
        fail('Endpoint not found', 404);
}
