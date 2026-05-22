# Processing Refunds

## When to Use a Refund vs. a Void

There are two ways to cancel a transaction, and they work differently:

- **Void** -- cancels an order that has **not yet been paid**. Use this when a customer changes their mind before the payment is processed. Only orders with a status of "open" can be voided.
- **Refund** -- reverses an order that **has already been paid**. Use this when a customer returns items or when a paid order needs to be cancelled. Only orders with a status of "paid" can be refunded.

## Who Can Process Refunds

Refunds are a sensitive operation, so they are restricted to certain roles:

- **Supervisors** -- can process refunds.
- **Admins** and **SuperAdmins** -- can process refunds.

If you are an Operator and a customer needs a refund, ask your supervisor to handle it.

## Step by Step: Processing a Refund

1. Open the **Point of Sale** module.
2. Click the **Orders** tab.
3. Find the order you need to refund. You can search by order number, filter by date, or browse the list.
4. Click the order to open its details.
5. Click the **Refund** button.
6. Enter the **refund amount**. By default, this is the full order total, but you can enter a smaller amount for a partial refund.
7. Enter a **reason** for the refund (for example, "Customer returned item" or "Incorrect order").
8. Click **Confirm Refund**.

## What Happens After a Refund

When a refund is processed:

1. The order status changes from **Paid** to **Refunded**.
2. The payment record is marked as refunded.
3. If the original payment was made through a payment gateway (Stripe or Square), the system will attempt to refund the charge through the gateway automatically.
4. The items from the order are **returned to inventory** -- stock quantities are increased by the refunded amounts.
5. A refund transaction is recorded in the system for audit purposes.

## Voiding an Order

If the order has not been paid yet and you need to cancel it:

1. Go to the **Orders** tab.
2. Find the open order.
3. Click the order to open its details.
4. Click the **Void** button.
5. The order status changes to **Voided**.

Voiding an order does not affect inventory because no stock was deducted (stock is only deducted when payment is processed).

> Tip: If you are unsure whether to void or refund, check the order status. If it says "open," use void. If it says "paid," use refund.

> Warning: Refunds cannot be undone. Double-check the order and amount before confirming. If you make a mistake, you will need to create a new sale to correct it.
