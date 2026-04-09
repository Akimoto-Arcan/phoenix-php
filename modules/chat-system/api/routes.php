<?php
/**
 * Internal Chat API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 16 endpoints for channels, messages, presence, and mentions
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));
$user = \Auth::user()['username'] ?? 'system';

switch ($segments[0] ?? '') {
    case 'channels':
        if ($method === 'GET' && empty($segments[1])) {
            $db = db();
            $stmt = $db->prepare("
                SELECT c.*, cm.last_read_at,
                    (SELECT COUNT(*) FROM chat_messages m WHERE m.channel_id = c.id AND m.created_at > COALESCE(cm.last_read_at, '1970-01-01')) as unread_count
                FROM chat_channels c
                LEFT JOIN chat_channel_members cm ON c.id = cm.channel_id AND cm.username = ?
                WHERE c.is_active = 1 AND (c.type = 'public' OR cm.username IS NOT NULL)
                ORDER BY c.name
            ");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            ok(['channels' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['name']);
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $input['name'])));
            $db = db();
            $stmt = $db->prepare("INSERT INTO chat_channels (name, slug, description, type, created_by) VALUES (?, ?, ?, ?, ?)");
            $type = $input['type'] ?? 'public';
            $stmt->bind_param('sssss', $input['name'], $slug, $input['description'] ?? null, $type, $user);
            $stmt->execute();
            $channelId = $db->insert_id;
            $db->query("INSERT INTO chat_channel_members (channel_id, username, role) VALUES ({$channelId}, '{$db->real_escape_string($user)}', 'owner')");
            log_activity('chat.channel.create', "Created channel #{$slug}");
            ok(['id' => $channelId, 'slug' => $slug, 'message' => 'Channel created']);
        }

        // POST channels/{id}/join
        if ($method === 'POST' && !empty($segments[1]) && ($segments[2] ?? '') === 'join') {
            $db = db();
            $channelId = (int)$segments[1];
            $stmt = $db->prepare("INSERT IGNORE INTO chat_channel_members (channel_id, username) VALUES (?, ?)");
            $stmt->bind_param('is', $channelId, $user);
            $stmt->execute();
            ok(['message' => 'Joined channel']);
        }

        // POST channels/{id}/leave
        if ($method === 'POST' && !empty($segments[1]) && ($segments[2] ?? '') === 'leave') {
            $db = db();
            $channelId = (int)$segments[1];
            $stmt = $db->prepare("DELETE FROM chat_channel_members WHERE channel_id = ? AND username = ? AND role != 'owner'");
            $stmt->bind_param('is', $channelId, $user);
            $stmt->execute();
            ok(['message' => 'Left channel']);
        }
        break;

    case 'messages':
        // GET messages?channel_id=X&before=ID&limit=50
        if ($method === 'GET') {
            $channelId = (int) getParam('channel_id');
            if (!$channelId) fail('channel_id required');
            $limit = min((int) getParam('limit', 50), 100);
            $before = getParam('before');

            $db = db();
            $where = 'm.channel_id = ? AND m.is_deleted = 0';
            $params = [$channelId];
            $types = 'i';
            if ($before) { $where .= ' AND m.id < ?'; $params[] = (int)$before; $types .= 'i'; }

            $stmt = $db->prepare("SELECT m.* FROM chat_messages m WHERE {$where} ORDER BY m.created_at DESC LIMIT ?");
            $params[] = $limit; $types .= 'i';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Mark as read
            $readStmt = $db->prepare("UPDATE chat_channel_members SET last_read_at = NOW() WHERE channel_id = ? AND username = ?");
            $readStmt->bind_param('is', $channelId, $user);
            $readStmt->execute();

            ok(['messages' => array_reverse($messages)]);
        }

        // POST messages — send a message
        if ($method === 'POST') {
            $input = getInput();
            validateRequired($input, ['channel_id', 'message']);
            $db = db();
            $stmt = $db->prepare("INSERT INTO chat_messages (channel_id, username, message, type) VALUES (?, ?, ?, ?)");
            $type = $input['type'] ?? 'text';
            $channelId = (int)$input['channel_id'];
            $stmt->bind_param('isss', $channelId, $user, $input['message'], $type);
            $stmt->execute();
            $msgId = $db->insert_id;

            // Parse @mentions
            if (preg_match_all('/@(\w+)/', $input['message'], $matches)) {
                $mentionStmt = $db->prepare("INSERT IGNORE INTO chat_mentions (message_id, mentioned_user) VALUES (?, ?)");
                foreach ($matches[1] as $mentioned) {
                    $mentionStmt->bind_param('is', $msgId, $mentioned);
                    $mentionStmt->execute();
                }
            }

            ok(['id' => $msgId, 'message' => 'Message sent']);
        }

        // DELETE messages/{id}
        if ($method === 'DELETE' && !empty($segments[1])) {
            $db = db();
            $msgId = (int)$segments[1];
            $stmt = $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = ? AND username = ?");
            $stmt->bind_param('is', $msgId, $user);
            $stmt->execute();
            ok(['message' => 'Message deleted']);
        }
        break;

    case 'presence':
        if ($method === 'POST') {
            $input = getInput();
            $status = $input['status'] ?? 'online';
            $db = db();
            $stmt = $db->prepare("INSERT INTO chat_user_presence (username, status, status_message) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), status_message = VALUES(status_message), last_seen = NOW()");
            $stmt->bind_param('sss', $user, $status, $input['status_message'] ?? null);
            $stmt->execute();
            ok(['message' => 'Presence updated']);
        }
        if ($method === 'GET') {
            $db = db();
            $result = $db->query("SELECT * FROM chat_user_presence WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY username");
            ok(['users' => $result->fetch_all(MYSQLI_ASSOC)]);
        }
        break;

    case 'mentions':
        if ($method === 'GET') {
            $db = db();
            $stmt = $db->prepare("
                SELECT mn.*, m.message, m.channel_id, m.username as from_user, m.created_at as message_time
                FROM chat_mentions mn
                JOIN chat_messages m ON mn.message_id = m.id
                WHERE mn.mentioned_user = ? AND mn.is_read = 0
                ORDER BY m.created_at DESC LIMIT 20
            ");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            ok(['mentions' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }
        if ($method === 'POST' && ($segments[1] ?? '') === 'read') {
            $db = db();
            $stmt = $db->prepare("UPDATE chat_mentions SET is_read = 1 WHERE mentioned_user = ?");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            ok(['message' => 'Mentions marked as read']);
        }
        break;

    case 'unread':
        $db = db();
        $stmt = $db->prepare("
            SELECT c.id, c.name, c.slug,
                COUNT(m.id) as unread_count
            FROM chat_channels c
            JOIN chat_channel_members cm ON c.id = cm.channel_id AND cm.username = ?
            LEFT JOIN chat_messages m ON m.channel_id = c.id AND m.created_at > COALESCE(cm.last_read_at, '1970-01-01') AND m.is_deleted = 0
            GROUP BY c.id
            HAVING unread_count > 0
        ");
        $stmt->bind_param('s', $user);
        $stmt->execute();
        ok(['unread' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    default:
        fail('Endpoint not found', 404);
}
