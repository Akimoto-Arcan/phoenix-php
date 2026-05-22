<?php
/**
 * Point of Sale API Routes
 * PhoenixPHP Module — CDAC Programming
 *
 * 28 endpoints covering registers, sessions, orders, payments,
 * products, customers, invoices, purchase orders, tax rates, and stats.
 *
 * All pos_* and inventory_* tables reside in the same database.
 * Payment processing uses PaymentGatewayFactory from includes/PaymentGateway.php.
 */

require_once __DIR__ . '/../../api/_bootstrap.php';
require_once __DIR__ . '/../../../includes/PaymentGateway.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$path   = getParam('path', '');
$segments = array_values(array_filter(explode('/', trim($path, '/'))));
$user   = \Auth::user();
$operator = $user['username'] ?? 'system';

switch ($segments[0] ?? '') {

    // ==================== REGISTERS ====================
    case 'registers':
        if ($method === 'GET' && empty($segments[1])) {
            $db = db();
            $result = $db->query("SELECT * FROM pos_registers ORDER BY name");
            ok(['registers' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['name']);

            $db = db();
            $stmt = $db->prepare("INSERT INTO pos_registers (name, location) VALUES (?, ?)");
            $name = $input['name'];
            $location = $input['location'] ?? null;
            $stmt->bind_param('ss', $name, $location);
            $stmt->execute();

            log_activity('pos.register.create', "Created register '{$name}'");
            ok(['id' => $db->insert_id, 'message' => 'Register created']);
        }
        break;

    // ==================== SESSIONS ====================
    case 'sessions':
        $action = $segments[1] ?? '';

        if ($method === 'GET' && $action === 'current') {
            // Get the current user's open session
            $db = db();
            $stmt = $db->prepare("
                SELECT s.*, r.name as register_name, r.location as register_location
                FROM pos_sessions s
                JOIN pos_registers r ON s.register_id = r.id
                WHERE s.operator = ? AND s.status = 'open'
                LIMIT 1
            ");
            $stmt->bind_param('s', $operator);
            $stmt->execute();
            $session = $stmt->get_result()->fetch_assoc();

            if (!$session) {
                ok(null);
            }
            ok($session);
        }

        if ($method === 'POST' && $action === 'open') {
            $input = getInput();
            validateRequired($input, ['register_id', 'starting_cash']);

            $db = db();

            // Check no other open session for this operator
            $stmt = $db->prepare("SELECT id FROM pos_sessions WHERE operator = ? AND status = 'open' LIMIT 1");
            $stmt->bind_param('s', $operator);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                fail('You already have an open session. Close it before opening a new one.');
            }

            $stmt = $db->prepare("
                INSERT INTO pos_sessions (register_id, operator, starting_cash)
                VALUES (?, ?, ?)
            ");
            $registerId = (int) $input['register_id'];
            $startingCash = (float) $input['starting_cash'];
            $stmt->bind_param('isd', $registerId, $operator, $startingCash);
            $stmt->execute();

            log_activity('pos.session.open', "Opened session on register #{$registerId}");
            ok(['id' => $db->insert_id, 'message' => 'Session opened']);
        }

        if ($method === 'POST' && $action === 'close') {
            $input = getInput();
            validateRequired($input, ['ending_cash']);

            $db = db();

            // Find the operator's open session
            $stmt = $db->prepare("SELECT id FROM pos_sessions WHERE operator = ? AND status = 'open' LIMIT 1");
            $stmt->bind_param('s', $operator);
            $stmt->execute();
            $session = $stmt->get_result()->fetch_assoc();
            if (!$session) {
                fail('No open session found');
            }
            $sessionId = (int) $session['id'];

            // Calculate totals from orders in this session
            $totals = $db->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_sales,
                    COALESCE(SUM(CASE WHEN payment_method != 'cash' THEN total ELSE 0 END), 0) as card_sales,
                    COALESCE(SUM(total), 0) as total_sales,
                    COUNT(*) as order_count
                FROM pos_orders
                WHERE session_id = ? AND status = 'paid'
            ");
            $totals->bind_param('i', $sessionId);
            $totals->execute();
            $sums = $totals->get_result()->fetch_assoc();

            $stmt = $db->prepare("
                UPDATE pos_sessions
                SET ending_cash = ?, cash_sales = ?, card_sales = ?,
                    total_sales = ?, order_count = ?, status = 'closed',
                    closed_at = NOW(), notes = ?
                WHERE id = ?
            ");
            $endingCash  = (float) $input['ending_cash'];
            $cashSales   = (float) $sums['cash_sales'];
            $cardSales   = (float) $sums['card_sales'];
            $totalSales  = (float) $sums['total_sales'];
            $orderCount  = (int) $sums['order_count'];
            $notes       = $input['notes'] ?? null;
            $stmt->bind_param('ddddisi', $endingCash, $cashSales, $cardSales, $totalSales, $orderCount, $notes, $sessionId);
            $stmt->execute();

            log_activity('pos.session.close', "Closed session #{$sessionId}");
            ok([
                'message'     => 'Session closed',
                'cash_sales'  => $cashSales,
                'card_sales'  => $cardSales,
                'total_sales' => $totalSales,
                'order_count' => $orderCount
            ]);
        }
        break;

    // ==================== ORDERS ====================
    case 'orders':
        // POST orders — create order
        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['items']);

            if (!is_array($input['items']) || count($input['items']) === 0) {
                fail('At least one item is required');
            }

            $db = db();
            $db->begin_transaction();
            try {
                // Generate order number
                $year = date('Y');
                $maxStmt = $db->query("SELECT MAX(CAST(SUBSTRING(order_number, 10) AS UNSIGNED)) as max_num FROM pos_orders WHERE order_number LIKE 'POS-{$year}-%'");
                $row = $maxStmt->fetch_assoc();
                $next = ($row['max_num'] ?? 0) + 1;
                $orderNumber = sprintf('POS-%s-%04d', $year, $next);

                // Get current session if exists
                $sessionStmt = $db->prepare("SELECT id, register_id FROM pos_sessions WHERE operator = ? AND status = 'open' LIMIT 1");
                $sessionStmt->bind_param('s', $operator);
                $sessionStmt->execute();
                $session = $sessionStmt->get_result()->fetch_assoc();
                $sessionId  = $session ? (int) $session['id'] : null;
                $registerId = $session ? (int) $session['register_id'] : null;

                $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
                $taxRate    = (float) ($input['tax_rate'] ?? 0);
                $discount   = (float) ($input['discount_amount'] ?? 0);
                $notes      = $input['notes'] ?? null;

                // Calculate subtotal from items
                $subtotal = 0;
                foreach ($input['items'] as $item) {
                    $qty   = (float) ($item['quantity'] ?? 1);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $disc  = (float) ($item['discount'] ?? 0);
                    $subtotal += ($qty * $price) - $disc;
                }

                $taxAmount = round($subtotal * ($taxRate / 100), 2);
                $total     = round($subtotal + $taxAmount - $discount, 2);

                // Insert order
                $stmt = $db->prepare("
                    INSERT INTO pos_orders
                        (order_number, register_id, session_id, customer_id, operator,
                         subtotal, tax_rate, tax_amount, discount_amount, total, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('siiisddddds',
                    $orderNumber, $registerId, $sessionId, $customerId, $operator,
                    $subtotal, $taxRate, $taxAmount, $discount, $total, $notes
                );
                $stmt->execute();
                $orderId = $db->insert_id;

                // Insert order items
                $itemStmt = $db->prepare("
                    INSERT INTO pos_order_items (order_id, item_id, sku, name, quantity, unit_price, discount)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($input['items'] as $item) {
                    $itemId    = isset($item['item_id']) ? (int) $item['item_id'] : null;
                    $sku       = $item['sku'] ?? null;
                    $itemName  = $item['name'];
                    $qty       = (float) ($item['quantity'] ?? 1);
                    $unitPrice = (float) $item['unit_price'];
                    $itemDisc  = (float) ($item['discount'] ?? 0);
                    $itemStmt->bind_param('iissddd', $orderId, $itemId, $sku, $itemName, $qty, $unitPrice, $itemDisc);
                    $itemStmt->execute();
                }

                $db->commit();
                log_activity('pos.order.create', "Created order {$orderNumber}");
                ok(['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Failed to create order: ' . $e->getMessage());
            }
        }

        // GET orders — list orders
        if ($method === 'GET' && empty($segments[1])) {
            $page    = (int) getParam('page', 1);
            $perPage = min((int) getParam('per_page', 20), 100);
            $offset  = ($page - 1) * $perPage;
            $status  = getParam('status');
            $start   = getParam('start');
            $end     = getParam('end');
            $regId   = getParam('register_id');

            $where  = ['1=1'];
            $params = [];
            $types  = '';

            if ($status)  { $where[] = 'o.status = ?';      $params[] = $status;       $types .= 's'; }
            if ($start)   { $where[] = 'o.created_at >= ?';  $params[] = $start;        $types .= 's'; }
            if ($end)     { $where[] = 'o.created_at <= ?';  $params[] = $end . ' 23:59:59'; $types .= 's'; }
            if ($regId)   { $where[] = 'o.register_id = ?';  $params[] = (int) $regId;  $types .= 'i'; }

            $whereClause = implode(' AND ', $where);
            $db = db();

            // Count
            $countStmt = $db->prepare("SELECT COUNT(*) FROM pos_orders o WHERE {$whereClause}");
            if ($types) $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];

            // Fetch
            $stmt = $db->prepare("
                SELECT o.*,
                    (SELECT COUNT(*) FROM pos_order_items WHERE order_id = o.id) as item_count
                FROM pos_orders o
                WHERE {$whereClause}
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $types  .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            ok([
                'orders'     => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => (int) $total, 'pages' => ceil($total / $perPage)]
            ]);
        }

        // GET orders/{id} — order detail
        if ($method === 'GET' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $db = db();
            $id = (int) $segments[1];

            $stmt = $db->prepare("SELECT * FROM pos_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) fail('Order not found', 404);

            // Items
            $itemStmt = $db->prepare("SELECT * FROM pos_order_items WHERE order_id = ?");
            $itemStmt->bind_param('i', $id);
            $itemStmt->execute();
            $order['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Payments
            $payStmt = $db->prepare("SELECT * FROM pos_payments WHERE order_id = ?");
            $payStmt->bind_param('i', $id);
            $payStmt->execute();
            $order['payments'] = $payStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            ok($order);
        }

        // PUT orders/{id} — update order (only if open)
        if ($method === 'PUT' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            // Verify order exists and is open
            $stmt = $db->prepare("SELECT status FROM pos_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) fail('Order not found', 404);
            if ($order['status'] !== 'open') fail('Only open orders can be updated');

            $fields = [];
            $params = [];
            $types  = '';
            $allowed = ['customer_id', 'tax_rate', 'discount_amount', 'notes'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= 's';
                }
            }

            // If items are provided, replace them and recalculate
            if (isset($input['items']) && is_array($input['items'])) {
                $db->begin_transaction();
                try {
                    // Delete existing items
                    $delStmt = $db->prepare("DELETE FROM pos_order_items WHERE order_id = ?");
                    $delStmt->bind_param('i', $id);
                    $delStmt->execute();

                    // Insert new items
                    $itemStmt = $db->prepare("
                        INSERT INTO pos_order_items (order_id, item_id, sku, name, quantity, unit_price, discount)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $subtotal = 0;
                    foreach ($input['items'] as $item) {
                        $itemId    = isset($item['item_id']) ? (int) $item['item_id'] : null;
                        $sku       = $item['sku'] ?? null;
                        $itemName  = $item['name'];
                        $qty       = (float) ($item['quantity'] ?? 1);
                        $unitPrice = (float) $item['unit_price'];
                        $itemDisc  = (float) ($item['discount'] ?? 0);
                        $subtotal += ($qty * $unitPrice) - $itemDisc;
                        $itemStmt->bind_param('iissddd', $id, $itemId, $sku, $itemName, $qty, $unitPrice, $itemDisc);
                        $itemStmt->execute();
                    }

                    // Recalculate totals
                    $taxRate   = (float) ($input['tax_rate'] ?? 0);
                    $discount  = (float) ($input['discount_amount'] ?? 0);

                    // If tax_rate/discount not in input, fetch from existing order
                    if (!isset($input['tax_rate'])) {
                        $tStmt = $db->prepare("SELECT tax_rate FROM pos_orders WHERE id = ?");
                        $tStmt->bind_param('i', $id);
                        $tStmt->execute();
                        $taxRate = (float) $tStmt->get_result()->fetch_assoc()['tax_rate'];
                    }
                    if (!isset($input['discount_amount'])) {
                        $dStmt = $db->prepare("SELECT discount_amount FROM pos_orders WHERE id = ?");
                        $dStmt->bind_param('i', $id);
                        $dStmt->execute();
                        $discount = (float) $dStmt->get_result()->fetch_assoc()['discount_amount'];
                    }

                    $taxAmount = round($subtotal * ($taxRate / 100), 2);
                    $total     = round($subtotal + $taxAmount - $discount, 2);

                    $upd = $db->prepare("
                        UPDATE pos_orders SET subtotal = ?, tax_rate = ?, tax_amount = ?, discount_amount = ?, total = ?
                        WHERE id = ?
                    ");
                    $upd->bind_param('dddddi', $subtotal, $taxRate, $taxAmount, $discount, $total, $id);
                    $upd->execute();

                    // Apply any other field updates
                    if (!empty($fields)) {
                        $params[] = $id;
                        $types .= 'i';
                        $updFields = $db->prepare("UPDATE pos_orders SET " . implode(', ', $fields) . " WHERE id = ?");
                        $updFields->bind_param($types, ...$params);
                        $updFields->execute();
                    }

                    $db->commit();
                    log_activity('pos.order.update', "Updated order #{$id} with new items");
                    ok(['message' => 'Order updated', 'total' => $total]);
                } catch (\Exception $e) {
                    $db->rollback();
                    fail('Failed to update order: ' . $e->getMessage());
                }
            } else {
                // Simple field update (no item changes)
                if (empty($fields)) fail('No fields to update');
                $params[] = $id;
                $types .= 'i';
                $stmt = $db->prepare("UPDATE pos_orders SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->bind_param($types, ...$params);
                $stmt->execute();

                log_activity('pos.order.update', "Updated order #{$id}");
                ok(['message' => 'Order updated']);
            }
        }

        // POST orders/{id}/pay — process payment (KEY endpoint)
        if ($method === 'POST' && !empty($segments[1]) && is_numeric($segments[1]) && ($segments[2] ?? '') === 'pay') {
            $input = getInput();
            validateRequired($input, ['method', 'amount']);

            $id = (int) $segments[1];
            $db = db();

            // 1. Validate order exists and is open
            $stmt = $db->prepare("SELECT * FROM pos_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) fail('Order not found', 404);
            if ($order['status'] !== 'open') fail('Order is not open for payment');

            // 2. Get payment method and amount
            $paymentMethod = $input['method'];
            $amount        = (float) $input['amount'];

            if ($amount < $order['total']) {
                fail('Payment amount is less than order total');
            }

            // 3. Load payment gateway
            try {
                $gateway = \Phoenix\PaymentGatewayFactory::create($paymentMethod);
            } catch (\Exception $e) {
                fail('Invalid payment method: ' . $e->getMessage());
            }

            // 4. Charge via gateway
            $metadata = [
                'order_id'     => $id,
                'order_number' => $order['order_number'],
                'operator'     => $operator,
            ];
            // Forward gateway-specific metadata
            foreach (['amount_tendered', 'last4', 'card_type', 'approval_code', 'payment_method', 'receipt_email'] as $key) {
                if (isset($input[$key])) $metadata[$key] = $input[$key];
            }

            $result = $gateway->charge($order['total'], 'USD', $metadata);
            if (!$result->success) {
                fail('Payment declined: ' . $result->error);
            }

            // 5. Wrap DB changes in transaction
            $db->begin_transaction();
            try {
                // Insert payment record
                $changeDue = isset($result->raw['change_due']) ? (float) $result->raw['change_due'] : null;
                $amountTendered = isset($result->raw['amount_tendered']) ? (float) $result->raw['amount_tendered'] : null;
                $txId = $result->transactionId;
                $gwResponse = json_encode($result->raw);
                $orderTotal = (float) $order['total'];

                $payStmt = $db->prepare("
                    INSERT INTO pos_payments (order_id, method, amount, amount_tendered, change_due, transaction_id, gateway_response)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $payStmt->bind_param('isdddss',
                    $id, $paymentMethod, $orderTotal, $amountTendered, $changeDue, $txId, $gwResponse
                );
                $payStmt->execute();
                $paymentId = $db->insert_id;

                // Update order status
                $updOrder = $db->prepare("UPDATE pos_orders SET status = 'paid', payment_method = ? WHERE id = ?");
                $updOrder->bind_param('si', $paymentMethod, $id);
                $updOrder->execute();

                // 6. INVENTORY INTEGRATION: deduct stock for each order item with an item_id
                $itemsStmt = $db->prepare("SELECT * FROM pos_order_items WHERE order_id = ?");
                $itemsStmt->bind_param('i', $id);
                $itemsStmt->execute();
                $orderItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($orderItems as $oi) {
                    if (!empty($oi['item_id'])) {
                        $qty    = (float) $oi['quantity'];
                        $itemId = (int) $oi['item_id'];

                        // Deduct from inventory_stock
                        $deduct = $db->prepare("UPDATE inventory_stock SET quantity = quantity - ? WHERE item_id = ?");
                        $deduct->bind_param('di', $qty, $itemId);
                        $deduct->execute();

                        // Record inventory transaction
                        $orderNum = $order['order_number'];
                        $txType   = 'ship';
                        $invTx = $db->prepare("
                            INSERT INTO inventory_transactions (item_id, type, quantity, reference_number, created_by)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $invTx->bind_param('isdss', $itemId, $txType, $qty, $orderNum, $operator);
                        $invTx->execute();
                    }
                }

                // 7. Update session totals if order belongs to a session
                if ($order['session_id']) {
                    $sessId = (int) $order['session_id'];
                    $isCash = ($paymentMethod === 'cash') ? 1 : 0;

                    if ($isCash) {
                        $sessUpd = $db->prepare("
                            UPDATE pos_sessions
                            SET cash_sales = cash_sales + ?, total_sales = total_sales + ?, order_count = order_count + 1
                            WHERE id = ?
                        ");
                        $sessUpd->bind_param('ddi', $orderTotal, $orderTotal, $sessId);
                    } else {
                        $sessUpd = $db->prepare("
                            UPDATE pos_sessions
                            SET card_sales = card_sales + ?, total_sales = total_sales + ?, order_count = order_count + 1
                            WHERE id = ?
                        ");
                        $sessUpd->bind_param('ddi', $orderTotal, $orderTotal, $sessId);
                    }
                    $sessUpd->execute();
                }

                $db->commit();
                log_activity('pos.order.pay', "Payment {$paymentMethod} on order {$order['order_number']}");

                ok([
                    'message'        => 'Payment processed',
                    'payment_id'     => $paymentId,
                    'transaction_id' => $txId,
                    'change_due'     => $changeDue,
                    'order_number'   => $order['order_number'],
                    'total'          => $orderTotal
                ]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Payment failed: ' . $e->getMessage());
            }
        }

        // POST orders/{id}/refund
        if ($method === 'POST' && !empty($segments[1]) && is_numeric($segments[1]) && ($segments[2] ?? '') === 'refund') {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT * FROM pos_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) fail('Order not found', 404);
            if ($order['status'] !== 'paid') fail('Only paid orders can be refunded');

            $refundAmount = (float) ($input['amount'] ?? $order['total']);
            $reason       = $input['reason'] ?? null;

            // Get the original payment
            $payStmt = $db->prepare("SELECT * FROM pos_payments WHERE order_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1");
            $payStmt->bind_param('i', $id);
            $payStmt->execute();
            $payment = $payStmt->get_result()->fetch_assoc();

            $db->begin_transaction();
            try {
                $refundTxId = null;

                // If there was a gateway payment, attempt gateway refund
                if ($payment && $payment['transaction_id']) {
                    try {
                        $gateway = \Phoenix\PaymentGatewayFactory::create($payment['method']);
                        $refundResult = $gateway->refund($payment['transaction_id'], $refundAmount);
                        $refundTxId = $refundResult->transactionId;
                    } catch (\Exception $e) {
                        // Log but don't fail — record the refund anyway
                        error_log("Gateway refund failed for order #{$id}: " . $e->getMessage());
                    }
                }

                // Insert refund record
                $paymentIdVal = $payment ? (int) $payment['id'] : null;
                $refStmt = $db->prepare("
                    INSERT INTO pos_refunds (order_id, payment_id, amount, reason, transaction_id, processed_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $refStmt->bind_param('iidsss', $id, $paymentIdVal, $refundAmount, $reason, $refundTxId, $operator);
                $refStmt->execute();

                // Update order status
                $updOrder = $db->prepare("UPDATE pos_orders SET status = 'refunded' WHERE id = ?");
                $updOrder->bind_param('i', $id);
                $updOrder->execute();

                // Update payment status
                if ($payment) {
                    $updPay = $db->prepare("UPDATE pos_payments SET status = 'refunded' WHERE id = ?");
                    $payId = (int) $payment['id'];
                    $updPay->bind_param('i', $payId);
                    $updPay->execute();
                }

                // Reverse inventory: add stock back for each order item with an item_id
                $itemsStmt = $db->prepare("SELECT * FROM pos_order_items WHERE order_id = ?");
                $itemsStmt->bind_param('i', $id);
                $itemsStmt->execute();
                $orderItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($orderItems as $oi) {
                    if (!empty($oi['item_id'])) {
                        $qty    = (float) $oi['quantity'];
                        $itemId = (int) $oi['item_id'];

                        $addBack = $db->prepare("UPDATE inventory_stock SET quantity = quantity + ? WHERE item_id = ?");
                        $addBack->bind_param('di', $qty, $itemId);
                        $addBack->execute();

                        $txType   = 'receive';
                        $orderNum = $order['order_number'];
                        $refNote  = 'Refund: ' . $order['order_number'];
                        $invTx = $db->prepare("
                            INSERT INTO inventory_transactions (item_id, type, quantity, reference_number, notes, created_by)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $invTx->bind_param('isdsss', $itemId, $txType, $qty, $orderNum, $refNote, $operator);
                        $invTx->execute();
                    }
                }

                $db->commit();
                log_activity('pos.order.refund', "Refunded {$refundAmount} on order {$order['order_number']}");
                ok(['message' => 'Refund processed', 'refund_amount' => $refundAmount]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Refund failed: ' . $e->getMessage());
            }
        }

        // DELETE orders/{id} — void order (only if open)
        if ($method === 'DELETE' && !empty($segments[1]) && is_numeric($segments[1])) {
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT status, order_number FROM pos_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) fail('Order not found', 404);
            if ($order['status'] !== 'open') fail('Only open orders can be voided');

            $updStmt = $db->prepare("UPDATE pos_orders SET status = 'voided' WHERE id = ?");
            $updStmt->bind_param('i', $id);
            $updStmt->execute();

            log_activity('pos.order.void', "Voided order {$order['order_number']}");
            ok(['message' => 'Order voided']);
        }
        break;

    // ==================== PRODUCTS (inventory lookup for POS) ====================
    case 'products':
        if ($method === 'GET' && empty($segments[1])) {
            // Search inventory_items by SKU, UPC, or name
            $q = getParam('q', '');
            $db = db();

            if ($q === '') {
                // Return recent/popular items
                $result = $db->query("
                    SELECT i.id, i.sku, i.upc, i.name, i.sell_price, i.cost_price, i.unit_of_measure,
                           COALESCE(st.available_qty, COALESCE(st.quantity, 0)) as available_qty
                    FROM inventory_items i
                    LEFT JOIN inventory_stock st ON i.id = st.item_id
                    WHERE i.is_active = 1
                    ORDER BY i.name
                    LIMIT 50
                ");
                ok(['products' => $result->fetch_all(MYSQLI_ASSOC)]);
            }

            $searchTerm = "%{$q}%";
            $stmt = $db->prepare("
                SELECT i.id, i.sku, i.upc, i.name, i.sell_price, i.cost_price, i.unit_of_measure,
                       COALESCE(st.available_qty, COALESCE(st.quantity, 0)) as available_qty
                FROM inventory_items i
                LEFT JOIN inventory_stock st ON i.id = st.item_id
                WHERE i.is_active = 1
                  AND (i.sku LIKE ? OR i.upc LIKE ? OR i.name LIKE ?)
                ORDER BY
                    CASE WHEN i.sku = ? THEN 0 WHEN i.upc = ? THEN 1 ELSE 2 END,
                    i.name
                LIMIT 50
            ");
            $exactQ = $q;
            $stmt->bind_param('sssss', $searchTerm, $searchTerm, $searchTerm, $exactQ, $exactQ);
            $stmt->execute();
            ok(['products' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        // GET products/{sku} — exact SKU or UPC lookup
        if ($method === 'GET' && !empty($segments[1])) {
            $lookup = $segments[1];
            $db = db();
            $stmt = $db->prepare("
                SELECT i.id, i.sku, i.upc, i.name, i.sell_price, i.cost_price, i.unit_of_measure,
                       COALESCE(st.available_qty, COALESCE(st.quantity, 0)) as available_qty
                FROM inventory_items i
                LEFT JOIN inventory_stock st ON i.id = st.item_id
                WHERE i.is_active = 1 AND (i.sku = ? OR i.upc = ?)
                LIMIT 1
            ");
            $stmt->bind_param('ss', $lookup, $lookup);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            if (!$product) fail('Product not found', 404);
            ok($product);
        }
        break;

    // ==================== CUSTOMERS ====================
    case 'customers':
        if ($method === 'GET' && empty($segments[1])) {
            $q = getParam('q', '');
            $db = db();

            if ($q === '') {
                $result = $db->query("SELECT * FROM pos_customers WHERE is_active = 1 ORDER BY name LIMIT 100");
                ok(['customers' => $result->fetch_all(MYSQLI_ASSOC)]);
            }

            $searchTerm = "%{$q}%";
            $stmt = $db->prepare("
                SELECT * FROM pos_customers
                WHERE is_active = 1
                  AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)
                ORDER BY name
                LIMIT 100
            ");
            $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            ok(['customers' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['name']);

            $db = db();
            $stmt = $db->prepare("
                INSERT INTO pos_customers (name, email, phone, company, address, city, state, zip, tax_exempt, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $name       = $input['name'];
            $email      = $input['email'] ?? null;
            $phone      = $input['phone'] ?? null;
            $company    = $input['company'] ?? null;
            $address    = $input['address'] ?? null;
            $city       = $input['city'] ?? null;
            $state      = $input['state'] ?? null;
            $zip        = $input['zip'] ?? null;
            $taxExempt  = (int) ($input['tax_exempt'] ?? 0);
            $notes      = $input['notes'] ?? null;
            $stmt->bind_param('ssssssssis',
                $name, $email, $phone, $company, $address, $city, $state, $zip, $taxExempt, $notes
            );
            $stmt->execute();

            log_activity('pos.customer.create', "Created customer '{$name}'");
            ok(['id' => $db->insert_id, 'message' => 'Customer created']);
        }

        if ($method === 'PUT' && !empty($segments[1]) && is_numeric($segments[1])) {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $fields  = [];
            $params  = [];
            $types   = '';
            $allowed = ['name', 'email', 'phone', 'company', 'address', 'city', 'state', 'zip', 'tax_exempt', 'notes', 'is_active'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= ($field === 'tax_exempt' || $field === 'is_active') ? 'i' : 's';
                }
            }

            if (empty($fields)) fail('No fields to update');

            $params[] = $id;
            $types .= 'i';
            $stmt = $db->prepare("UPDATE pos_customers SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            log_activity('pos.customer.update', "Updated customer #{$id}");
            ok(['message' => 'Customer updated']);
        }
        break;

    // ==================== INVOICES ====================
    case 'invoices':
        // POST invoices — create invoice
        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            $db = db();

            $db->begin_transaction();
            try {
                // Generate invoice number
                $year = date('Y');
                $maxStmt = $db->query("SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) as max_num FROM pos_invoices WHERE invoice_number LIKE 'INV-{$year}-%'");
                $row = $maxStmt->fetch_assoc();
                $next = ($row['max_num'] ?? 0) + 1;
                $invoiceNumber = sprintf('INV-%s-%04d', $year, $next);

                $orderId    = isset($input['order_id']) ? (int) $input['order_id'] : null;
                $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
                $issueDate  = $input['issue_date'] ?? date('Y-m-d');
                $dueDate    = $input['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $notes      = $input['notes'] ?? null;
                $terms      = $input['terms'] ?? null;
                $discount   = (float) ($input['discount_amount'] ?? 0);

                $items = [];

                // If from an order, copy items
                if ($orderId) {
                    $orderStmt = $db->prepare("SELECT * FROM pos_orders WHERE id = ?");
                    $orderStmt->bind_param('i', $orderId);
                    $orderStmt->execute();
                    $order = $orderStmt->get_result()->fetch_assoc();
                    if (!$order) fail('Order not found', 404);

                    $customerId = $customerId ?: ($order['customer_id'] ? (int) $order['customer_id'] : null);

                    $oiStmt = $db->prepare("SELECT * FROM pos_order_items WHERE order_id = ?");
                    $oiStmt->bind_param('i', $orderId);
                    $oiStmt->execute();
                    $orderItems = $oiStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                    foreach ($orderItems as $oi) {
                        $items[] = [
                            'item_id'     => $oi['item_id'],
                            'description' => $oi['name'],
                            'quantity'    => (float) $oi['quantity'],
                            'unit_price'  => (float) $oi['unit_price'],
                            'tax_rate'    => (float) $order['tax_rate'],
                        ];
                    }
                } elseif (isset($input['items']) && is_array($input['items'])) {
                    $items = $input['items'];
                } else {
                    fail('Either order_id or items array is required');
                }

                // Calculate totals
                $subtotal  = 0;
                $taxAmount = 0;
                foreach ($items as $item) {
                    $lineTotal = (float) ($item['quantity'] ?? 1) * (float) $item['unit_price'];
                    $subtotal += $lineTotal;
                    $taxAmount += round($lineTotal * ((float) ($item['tax_rate'] ?? 0) / 100), 2);
                }
                $total = round($subtotal + $taxAmount - $discount, 2);

                // Insert invoice
                $invStmt = $db->prepare("
                    INSERT INTO pos_invoices
                        (invoice_number, order_id, customer_id, issue_date, due_date,
                         subtotal, tax_amount, discount_amount, total, notes, terms, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $invStmt->bind_param('siissddddsss',
                    $invoiceNumber, $orderId, $customerId, $issueDate, $dueDate,
                    $subtotal, $taxAmount, $discount, $total, $notes, $terms, $operator
                );
                $invStmt->execute();
                $invoiceId = $db->insert_id;

                // Insert invoice items
                $iiStmt = $db->prepare("
                    INSERT INTO pos_invoice_items (invoice_id, item_id, description, quantity, unit_price, tax_rate)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($items as $item) {
                    $iiItemId = isset($item['item_id']) ? (int) $item['item_id'] : null;
                    $desc     = $item['description'] ?? $item['name'] ?? '';
                    $qty      = (float) ($item['quantity'] ?? 1);
                    $price    = (float) $item['unit_price'];
                    $tr       = (float) ($item['tax_rate'] ?? 0);
                    $iiStmt->bind_param('iisddd', $invoiceId, $iiItemId, $desc, $qty, $price, $tr);
                    $iiStmt->execute();
                }

                $db->commit();
                log_activity('pos.invoice.create', "Created invoice {$invoiceNumber}");
                ok(['id' => $invoiceId, 'invoice_number' => $invoiceNumber, 'total' => $total]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Failed to create invoice: ' . $e->getMessage());
            }
        }

        // GET invoices — list invoices
        if ($method === 'GET' && empty($segments[1])) {
            $page    = (int) getParam('page', 1);
            $perPage = min((int) getParam('per_page', 20), 100);
            $offset  = ($page - 1) * $perPage;
            $status  = getParam('status');
            $custId  = getParam('customer_id');
            $start   = getParam('start');
            $end     = getParam('end');

            $where  = ['1=1'];
            $params = [];
            $types  = '';

            if ($status) { $where[] = 'inv.status = ?';      $params[] = $status;       $types .= 's'; }
            if ($custId) { $where[] = 'inv.customer_id = ?';  $params[] = (int) $custId; $types .= 'i'; }
            if ($start)  { $where[] = 'inv.issue_date >= ?';  $params[] = $start;        $types .= 's'; }
            if ($end)    { $where[] = 'inv.issue_date <= ?';  $params[] = $end;           $types .= 's'; }

            $whereClause = implode(' AND ', $where);
            $db = db();

            $countStmt = $db->prepare("SELECT COUNT(*) FROM pos_invoices inv WHERE {$whereClause}");
            if ($types) $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];

            $stmt = $db->prepare("
                SELECT inv.*, c.name as customer_name
                FROM pos_invoices inv
                LEFT JOIN pos_customers c ON inv.customer_id = c.id
                WHERE {$whereClause}
                ORDER BY inv.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $types  .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            ok([
                'invoices'   => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => (int) $total, 'pages' => ceil($total / $perPage)]
            ]);
        }

        // GET invoices/{id} — invoice detail with items
        if ($method === 'GET' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("
                SELECT inv.*, c.name as customer_name, c.email as customer_email,
                       c.phone as customer_phone, c.company as customer_company, c.address as customer_address
                FROM pos_invoices inv
                LEFT JOIN pos_customers c ON inv.customer_id = c.id
                WHERE inv.id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            if (!$invoice) fail('Invoice not found', 404);

            $iiStmt = $db->prepare("SELECT * FROM pos_invoice_items WHERE invoice_id = ?");
            $iiStmt->bind_param('i', $id);
            $iiStmt->execute();
            $invoice['items'] = $iiStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            ok($invoice);
        }

        // PUT invoices/{id} — update invoice (only draft/sent)
        if ($method === 'PUT' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT status FROM pos_invoices WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            if (!$invoice) fail('Invoice not found', 404);
            if (!in_array($invoice['status'], ['draft', 'sent'])) {
                fail('Only draft or sent invoices can be updated');
            }

            $fields  = [];
            $params  = [];
            $types   = '';
            $allowed = ['customer_id', 'due_date', 'status', 'notes', 'terms', 'discount_amount'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= ($field === 'customer_id') ? 'i' : 's';
                }
            }

            if (empty($fields)) fail('No fields to update');

            $params[] = $id;
            $types .= 'i';
            $stmt = $db->prepare("UPDATE pos_invoices SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            log_activity('pos.invoice.update', "Updated invoice #{$id}");
            ok(['message' => 'Invoice updated']);
        }

        // POST invoices/{id}/pay — record payment against invoice
        if ($method === 'POST' && !empty($segments[1]) && is_numeric($segments[1]) && ($segments[2] ?? '') === 'pay') {
            $input = getInput();
            validateRequired($input, ['amount']);

            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT * FROM pos_invoices WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            if (!$invoice) fail('Invoice not found', 404);
            if (in_array($invoice['status'], ['paid', 'cancelled'])) {
                fail('Invoice cannot accept payments in its current status');
            }

            $payAmount   = (float) $input['amount'];
            $newPaid     = (float) $invoice['amount_paid'] + $payAmount;
            $invoiceTotal = (float) $invoice['total'];

            // Determine new status
            if ($newPaid >= $invoiceTotal) {
                $newStatus = 'paid';
                $newPaid   = $invoiceTotal; // Cap at total
            } else {
                $newStatus = 'partial';
            }

            $updStmt = $db->prepare("UPDATE pos_invoices SET amount_paid = ?, status = ? WHERE id = ?");
            $updStmt->bind_param('dsi', $newPaid, $newStatus, $id);
            $updStmt->execute();

            log_activity('pos.invoice.pay', "Recorded payment of {$payAmount} on invoice {$invoice['invoice_number']}");
            ok([
                'message'      => 'Payment recorded',
                'amount_paid'  => $newPaid,
                'amount_due'   => max(0, $invoiceTotal - $newPaid),
                'status'       => $newStatus
            ]);
        }
        break;

    // ==================== PURCHASE ORDERS ====================
    case 'po':
        // GET po — list purchase orders
        if ($method === 'GET' && empty($segments[1])) {
            $db = db();
            $page    = (int) getParam('page', 1);
            $perPage = min((int) getParam('per_page', 20), 100);
            $offset  = ($page - 1) * $perPage;

            $countStmt = $db->query("SELECT COUNT(*) FROM inventory_purchase_orders");
            $total = $countStmt->fetch_row()[0];

            $stmt = $db->prepare("
                SELECT po.*, s.name as supplier_name
                FROM inventory_purchase_orders po
                LEFT JOIN inventory_suppliers s ON po.supplier_id = s.id
                ORDER BY po.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('ii', $perPage, $offset);
            $stmt->execute();

            ok([
                'purchase_orders' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
                'pagination'      => ['page' => $page, 'per_page' => $perPage, 'total' => (int) $total, 'pages' => ceil($total / $perPage)]
            ]);
        }

        // POST po — create purchase order
        if ($method === 'POST' && empty($segments[1])) {
            $input = getInput();
            validateRequired($input, ['supplier_id', 'items']);

            if (!is_array($input['items']) || count($input['items']) === 0) {
                fail('At least one line item is required');
            }

            $db = db();
            $db->begin_transaction();
            try {
                // Generate PO number
                $year = date('Y');
                $maxStmt = $db->query("SELECT MAX(CAST(SUBSTRING(po_number, 9) AS UNSIGNED)) as max_num FROM inventory_purchase_orders WHERE po_number LIKE 'PO-{$year}-%'");
                $row = $maxStmt->fetch_assoc();
                $next = ($row['max_num'] ?? 0) + 1;
                $poNumber = sprintf('PO-%s-%04d', $year, $next);

                $supplierId = (int) $input['supplier_id'];
                $notes      = $input['notes'] ?? null;

                // Calculate total
                $total = 0;
                foreach ($input['items'] as $item) {
                    $total += (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0);
                }

                $poStmt = $db->prepare("
                    INSERT INTO inventory_purchase_orders (po_number, supplier_id, total, notes, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $poStmt->bind_param('sidss', $poNumber, $supplierId, $total, $notes, $operator);
                $poStmt->execute();
                $poId = $db->insert_id;

                // Insert line items
                $lineStmt = $db->prepare("
                    INSERT INTO inventory_po_lines (po_id, item_id, quantity, unit_cost)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($input['items'] as $item) {
                    $itemId   = (int) $item['item_id'];
                    $qty      = (float) $item['quantity'];
                    $unitCost = (float) ($item['unit_cost'] ?? 0);
                    $lineStmt->bind_param('iidd', $poId, $itemId, $qty, $unitCost);
                    $lineStmt->execute();
                }

                $db->commit();
                log_activity('pos.po.create', "Created PO {$poNumber}");
                ok(['id' => $poId, 'po_number' => $poNumber, 'total' => $total]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Failed to create purchase order: ' . $e->getMessage());
            }
        }

        // GET po/{id} — PO detail with line items
        if ($method === 'GET' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("
                SELECT po.*, s.name as supplier_name, s.email as supplier_email, s.phone as supplier_phone
                FROM inventory_purchase_orders po
                LEFT JOIN inventory_suppliers s ON po.supplier_id = s.id
                WHERE po.id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $po = $stmt->get_result()->fetch_assoc();
            if (!$po) fail('Purchase order not found', 404);

            $lineStmt = $db->prepare("
                SELECT pl.*, i.sku, i.name as item_name, i.unit_of_measure
                FROM inventory_po_lines pl
                LEFT JOIN inventory_items i ON pl.item_id = i.id
                WHERE pl.po_id = ?
            ");
            $lineStmt->bind_param('i', $id);
            $lineStmt->execute();
            $po['items'] = $lineStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            ok($po);
        }

        // PUT po/{id} — update PO (draft only)
        if ($method === 'PUT' && !empty($segments[1]) && is_numeric($segments[1]) && empty($segments[2])) {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT status FROM inventory_purchase_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $po = $stmt->get_result()->fetch_assoc();
            if (!$po) fail('Purchase order not found', 404);
            if ($po['status'] !== 'draft') fail('Only draft purchase orders can be updated');

            $fields  = [];
            $params  = [];
            $types   = '';
            $allowed = ['supplier_id', 'notes'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= ($field === 'supplier_id') ? 'i' : 's';
                }
            }

            if (empty($fields)) fail('No fields to update');

            $params[] = $id;
            $types .= 'i';
            $stmt = $db->prepare("UPDATE inventory_purchase_orders SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            log_activity('pos.po.update', "Updated PO #{$id}");
            ok(['message' => 'Purchase order updated']);
        }

        // POST po/{id}/submit — change status to submitted
        if ($method === 'POST' && !empty($segments[1]) && is_numeric($segments[1]) && ($segments[2] ?? '') === 'submit') {
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT status, po_number FROM inventory_purchase_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $po = $stmt->get_result()->fetch_assoc();
            if (!$po) fail('Purchase order not found', 404);
            if ($po['status'] !== 'draft') fail('Only draft purchase orders can be submitted');

            $updStmt = $db->prepare("UPDATE inventory_purchase_orders SET status = 'submitted' WHERE id = ?");
            $updStmt->bind_param('i', $id);
            $updStmt->execute();

            log_activity('pos.po.submit', "Submitted PO {$po['po_number']}");
            ok(['message' => 'Purchase order submitted']);
        }

        // POST po/{id}/receive — receive goods
        if ($method === 'POST' && !empty($segments[1]) && is_numeric($segments[1]) && ($segments[2] ?? '') === 'receive') {
            $input = getInput();
            $id = (int) $segments[1];
            $db = db();

            $stmt = $db->prepare("SELECT * FROM inventory_purchase_orders WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $po = $stmt->get_result()->fetch_assoc();
            if (!$po) fail('Purchase order not found', 404);
            if (!in_array($po['status'], ['submitted', 'partial'])) {
                fail('Purchase order is not in a receivable status');
            }

            // Get line items
            $lineStmt = $db->prepare("SELECT * FROM inventory_po_lines WHERE po_id = ?");
            $lineStmt->bind_param('i', $id);
            $lineStmt->execute();
            $lines = $lineStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Input: array of received items [{line_id, quantity_received}] or receive all
            $receivedItems = $input['items'] ?? null;

            $db->begin_transaction();
            try {
                $allFullyReceived = true;

                foreach ($lines as $line) {
                    $recvQty = 0;

                    if ($receivedItems) {
                        // Find matching line in input
                        foreach ($receivedItems as $ri) {
                            if ((int) ($ri['line_id'] ?? 0) === (int) $line['id']) {
                                $recvQty = (float) $ri['quantity_received'];
                                break;
                            }
                        }
                    } else {
                        // Receive all remaining
                        $ordered  = (float) $line['quantity'];
                        $already  = (float) ($line['quantity_received'] ?? 0);
                        $recvQty  = $ordered - $already;
                    }

                    if ($recvQty <= 0) {
                        // Check if this line is already fully received
                        $ordered = (float) $line['quantity'];
                        $already = (float) ($line['quantity_received'] ?? 0);
                        if ($already < $ordered) $allFullyReceived = false;
                        continue;
                    }

                    $lineId = (int) $line['id'];
                    $itemId = (int) $line['item_id'];

                    // Update quantity_received on the PO line
                    $updLine = $db->prepare("UPDATE inventory_po_lines SET quantity_received = COALESCE(quantity_received, 0) + ? WHERE id = ?");
                    $updLine->bind_param('di', $recvQty, $lineId);
                    $updLine->execute();

                    // Add to inventory_stock
                    $addStock = $db->prepare("
                        INSERT INTO inventory_stock (item_id, quantity)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                    ");
                    $addStock->bind_param('id', $itemId, $recvQty);
                    $addStock->execute();

                    // Record inventory transaction
                    $txType = 'receive';
                    $poNum  = $po['po_number'];
                    $invTx = $db->prepare("
                        INSERT INTO inventory_transactions (item_id, type, quantity, reference_number, po_number, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $invTx->bind_param('isdsss', $itemId, $txType, $recvQty, $poNum, $poNum, $operator);
                    $invTx->execute();

                    // Check if line is now fully received
                    $newReceived = (float) ($line['quantity_received'] ?? 0) + $recvQty;
                    if ($newReceived < (float) $line['quantity']) {
                        $allFullyReceived = false;
                    }
                }

                // Update PO status
                $newStatus = $allFullyReceived ? 'received' : 'partial';
                $updPo = $db->prepare("UPDATE inventory_purchase_orders SET status = ? WHERE id = ?");
                $updPo->bind_param('si', $newStatus, $id);
                $updPo->execute();

                $db->commit();
                log_activity('pos.po.receive', "Received goods on PO {$po['po_number']}");
                ok(['message' => 'Goods received', 'status' => $newStatus]);
            } catch (\Exception $e) {
                $db->rollback();
                fail('Receiving failed: ' . $e->getMessage());
            }
        }
        break;

    // ==================== TAX RATES ====================
    case 'tax-rates':
        if ($method === 'GET') {
            $db = db();
            $result = $db->query("SELECT * FROM pos_tax_rates WHERE is_active = 1 ORDER BY name");
            ok(['tax_rates' => $result->fetch_all(MYSQLI_ASSOC)]);
        }

        if ($method === 'POST') {
            $input = getInput();
            validateRequired($input, ['name', 'rate']);

            $db = db();
            $stmt = $db->prepare("INSERT INTO pos_tax_rates (name, rate, is_default) VALUES (?, ?, ?)");
            $name      = $input['name'];
            $rate      = (float) $input['rate'];
            $isDefault = (int) ($input['is_default'] ?? 0);
            $stmt->bind_param('sdi', $name, $rate, $isDefault);
            $stmt->execute();

            log_activity('pos.tax.create', "Created tax rate '{$name}' at {$rate}%");
            ok(['id' => $db->insert_id, 'message' => 'Tax rate created']);
        }
        break;

    // ==================== STATS ====================
    case 'stats':
        $db = db();
        $period = getParam('period', 'today');

        switch ($period) {
            case 'week':
                $dateFilter = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateFilter = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            default: // today
                $dateFilter = "AND DATE(o.created_at) = CURDATE()";
                break;
        }

        // Sales totals
        $result = $db->query("
            SELECT
                COALESCE(SUM(o.total), 0) as total_sales,
                COUNT(*) as order_count,
                COALESCE(ROUND(AVG(o.total), 2), 0) as avg_order_value
            FROM pos_orders o
            WHERE o.status = 'paid' {$dateFilter}
        ");
        $stats = $result->fetch_assoc();

        // Payment method breakdown
        $pmResult = $db->query("
            SELECT
                o.payment_method,
                COUNT(*) as count,
                COALESCE(SUM(o.total), 0) as total
            FROM pos_orders o
            WHERE o.status = 'paid' {$dateFilter}
            GROUP BY o.payment_method
        ");
        $stats['payment_methods'] = $pmResult->fetch_all(MYSQLI_ASSOC);

        ok($stats);
        break;

    default:
        fail('Endpoint not found', 404);
}
