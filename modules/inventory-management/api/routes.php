<?php
/**
 * Inventory Management API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 24 endpoints covering full inventory lifecycle
 */

// Items
// GET    /api/v1/inventory/items              — List items (paginated, filterable)
// GET    /api/v1/inventory/items/{id}         — Get single item with stock levels
// POST   /api/v1/inventory/items              — Create item
// PUT    /api/v1/inventory/items/{id}         — Update item
// DELETE /api/v1/inventory/items/{id}         — Deactivate item
// GET    /api/v1/inventory/items/search       — Search by SKU, UPC, or name
// GET    /api/v1/inventory/items/low-stock    — Items below reorder point

// Stock
// GET    /api/v1/inventory/stock              — All stock levels
// GET    /api/v1/inventory/stock/{item_id}    — Stock for specific item
// POST   /api/v1/inventory/stock/adjust       — Manual stock adjustment
// POST   /api/v1/inventory/stock/transfer     — Transfer between locations
// GET    /api/v1/inventory/stock/valuation     — Inventory valuation report

// Transactions
// GET    /api/v1/inventory/transactions       — Transaction history (filterable)
// POST   /api/v1/inventory/receive            — Receive inventory (with PO matching)
// POST   /api/v1/inventory/ship               — Ship/fulfill inventory

// Purchase Orders
// GET    /api/v1/inventory/po                 — List purchase orders
// GET    /api/v1/inventory/po/{id}            — Get PO with line items
// POST   /api/v1/inventory/po                 — Create purchase order
// PUT    /api/v1/inventory/po/{id}            — Update purchase order
// POST   /api/v1/inventory/po/{id}/receive    — Receive against PO

// Suppliers
// GET    /api/v1/inventory/suppliers          — List suppliers
// POST   /api/v1/inventory/suppliers          — Create supplier
// PUT    /api/v1/inventory/suppliers/{id}     — Update supplier

// Categories
// GET    /api/v1/inventory/categories         — List categories

require_once __DIR__ . '/../../api/_bootstrap.php';
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = getParam('path', '');
$segments = array_filter(explode('/', trim($path, '/')));

switch ($segments[0] ?? '') {
    case 'items':
        if ($method === 'GET' && empty($segments[1])) {
            // List items with pagination
            can('inventory.read');
            $page = (int) getParam('page', 1);
            $perPage = min((int) getParam('per_page', 20), 100);
            $offset = ($page - 1) * $perPage;
            $category = getParam('category');
            $search = getParam('search');
            $active = getParam('active', '1');

            $where = ['i.is_active = ?'];
            $params = [(int) $active];
            $types = 'i';

            if ($category) {
                $where[] = 'i.category_id = ?';
                $params[] = (int) $category;
                $types .= 'i';
            }

            if ($search) {
                $where[] = '(i.name LIKE ? OR i.sku LIKE ? OR i.upc LIKE ?)';
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
                $types .= 'sss';
            }

            $whereClause = implode(' AND ', $where);

            $db = db();
            $countStmt = $db->prepare("SELECT COUNT(*) FROM inventory_items i WHERE {$whereClause}");
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];

            $stmt = $db->prepare("
                SELECT i.*, c.name as category_name, s.name as supplier_name,
                       COALESCE(st.quantity, 0) as stock_qty,
                       COALESCE(st.available_qty, 0) as available_qty
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                LEFT JOIN inventory_suppliers s ON i.supplier_id = s.id
                LEFT JOIN inventory_stock st ON i.id = st.item_id
                WHERE {$whereClause}
                ORDER BY i.name
                LIMIT ? OFFSET ?
            ");
            $types .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            ok([
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => (int) $total,
                    'pages' => ceil($total / $perPage)
                ]
            ]);
        }

        if ($method === 'GET' && ($segments[1] ?? '') === 'low-stock') {
            can('inventory.read');
            $db = db();
            $result = $db->query("
                SELECT i.*, COALESCE(st.available_qty, 0) as available_qty
                FROM inventory_items i
                LEFT JOIN inventory_stock st ON i.id = st.item_id
                WHERE i.is_active = 1
                AND COALESCE(st.available_qty, 0) <= i.reorder_point
                AND i.reorder_point > 0
                ORDER BY (COALESCE(st.available_qty, 0) / i.reorder_point) ASC
            ");
            ok(['items' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            can('inventory.write');
            $input = getInput();
            $required = ['sku', 'name'];
            validateRequired($input, $required);

            $db = db();
            $stmt = $db->prepare("
                INSERT INTO inventory_items (sku, upc, name, description, category_id, unit_of_measure,
                    cost_price, sell_price, reorder_point, reorder_qty, lead_time_days, supplier_id,
                    location, bin_number, weight, weight_unit, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssissddiiiissds',
                $input['sku'], $input['upc'] ?? null, $input['name'],
                $input['description'] ?? null, $input['category_id'] ?? null,
                $input['unit_of_measure'] ?? 'each', $input['cost_price'] ?? 0,
                $input['sell_price'] ?? 0, $input['reorder_point'] ?? 0,
                $input['reorder_qty'] ?? 0, $input['lead_time_days'] ?? 0,
                $input['supplier_id'] ?? null, $input['location'] ?? null,
                $input['bin_number'] ?? null, $input['weight'] ?? null,
                $input['weight_unit'] ?? 'lb', $input['notes'] ?? null
            );
            $stmt->execute();

            // Create initial stock record
            $itemId = $db->insert_id;
            $db->query("INSERT INTO inventory_stock (item_id, quantity) VALUES ({$itemId}, 0)");

            log_activity('inventory.create', "Created item {$input['sku']}");
            ok(['id' => $itemId, 'message' => 'Item created']);
        }
        break;

    case 'receive':
        if ($method === 'POST') {
            can('inventory.write');
            $input = getInput();
            validateRequired($input, ['item_id', 'quantity']);

            $db = db();
            $db->begin_transaction();
            try {
                // Update stock
                $stmt = $db->prepare("
                    INSERT INTO inventory_stock (item_id, quantity)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmt->bind_param('id', $input['item_id'], $input['quantity']);
                $stmt->execute();

                // Log transaction
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions (item_id, type, quantity, reference_number,
                        po_number, supplier_id, cost_per_unit, notes, created_by)
                    VALUES (?, 'receive', ?, ?, ?, ?, ?, ?, ?)
                ");
                $user = \Auth::user()['username'] ?? 'system';
                $stmt->bind_param('idssidss',
                    $input['item_id'], $input['quantity'],
                    $input['reference_number'] ?? null, $input['po_number'] ?? null,
                    $input['supplier_id'] ?? null, $input['cost_per_unit'] ?? null,
                    $input['notes'] ?? null, $user
                );
                $stmt->execute();

                $db->commit();
                log_activity('inventory.receive', "Received {$input['quantity']} units for item #{$input['item_id']}");
                ok(['message' => 'Inventory received', 'transaction_id' => $db->insert_id]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Receive failed: ' . $e->getMessage());
            }
        }
        break;

    case 'ship':
        if ($method === 'POST') {
            can('inventory.write');
            $input = getInput();
            validateRequired($input, ['item_id', 'quantity']);

            $db = db();
            $db->begin_transaction();
            try {
                // Check available stock
                $stmt = $db->prepare("SELECT available_qty FROM inventory_stock WHERE item_id = ?");
                $stmt->bind_param('i', $input['item_id']);
                $stmt->execute();
                $stock = $stmt->get_result()->fetch_assoc();

                if (!$stock || $stock['available_qty'] < $input['quantity']) {
                    fail('Insufficient stock. Available: ' . ($stock['available_qty'] ?? 0));
                }

                // Deduct stock
                $stmt = $db->prepare("UPDATE inventory_stock SET quantity = quantity - ? WHERE item_id = ?");
                $stmt->bind_param('di', $input['quantity'], $input['item_id']);
                $stmt->execute();

                // Log transaction
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions (item_id, type, quantity, reference_number, notes, created_by)
                    VALUES (?, 'ship', ?, ?, ?, ?)
                ");
                $user = \Auth::user()['username'] ?? 'system';
                $stmt->bind_param('idsss',
                    $input['item_id'], $input['quantity'],
                    $input['reference_number'] ?? null,
                    $input['notes'] ?? null, $user
                );
                $stmt->execute();

                $db->commit();
                log_activity('inventory.ship', "Shipped {$input['quantity']} units for item #{$input['item_id']}");
                ok(['message' => 'Inventory shipped', 'transaction_id' => $db->insert_id]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Ship failed: ' . $e->getMessage());
            }
        }
        break;

    default:
        fail('Endpoint not found', 404);
}
