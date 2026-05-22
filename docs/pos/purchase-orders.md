# Purchase Orders

## What Is a Purchase Order?

A **purchase order (PO)** is a document you create when you need to buy items from a supplier. It lists what you want to order, how many of each item, and the agreed-upon price. Once the supplier delivers the goods, you mark them as received, and your inventory is updated automatically.

## Creating a Purchase Order

1. Go to the **Point of Sale** module and click the **Purchase Orders** tab.
2. Click the **New Purchase Order** button.
3. Fill in the required information:
   - **Supplier** -- select the supplier you are ordering from. If the supplier is not listed, ask your administrator to add them in the Inventory module.
   - **Items** -- for each item you want to order:
     - Select the item from the inventory list.
     - Enter the **quantity** you want to order.
     - Enter the **unit cost** (the price you are paying the supplier per unit).
   - **Notes** -- add any special instructions or references (optional).
4. Click **Save** to create the purchase order.

The PO is saved as a **Draft** so you can review and edit it before sending.

## Purchase Order Statuses

Every purchase order has a status that tells you where it is in the ordering process:

- **Draft** -- the PO has been created but not submitted yet. You can still make changes.
- **Submitted** -- the PO has been finalized and sent to the supplier. No more edits are allowed.
- **Partial** -- some of the ordered items have arrived, but the order is not complete yet.
- **Received** -- all ordered items have been delivered and checked in.
- **Cancelled** -- the PO has been cancelled and will not be fulfilled.

## Submitting a Purchase Order

After reviewing a draft PO and confirming everything is correct:

1. Open the purchase order from the list.
2. Click the **Submit** button.
3. The status changes to **Submitted**.

Submitting a PO means you have finalized the order. In practice, you would then send the PO details to your supplier (by email, fax, or phone). Phoenix records that the order has been submitted but does not send it to the supplier automatically.

> Tip: Always double-check quantities and prices before submitting. Once a PO is submitted, you cannot edit it.

## Receiving Goods

When the items from a purchase order arrive at your location:

1. Go to the **Purchase Orders** tab.
2. Find the PO in the list. It should have a status of **Submitted** or **Partial**.
3. Click the **Receive** button next to the PO.
4. The system will show you the ordered items and their quantities.
5. Confirm the quantities you are receiving. By default, all remaining items are marked as received.
6. Click **Confirm**.

## How Receiving Updates Your Inventory

When you receive goods against a purchase order, Phoenix automatically:

1. **Adds the received quantities** to your inventory stock levels.
2. **Records an inventory transaction** of type "receive" linked to the PO number.
3. **Updates the PO status** -- if all items are now received, the status changes to **Received**. If only some items arrived, the status changes to **Partial**.

You do not need to go into the Inventory module separately to update stock counts. Receiving through a PO handles everything.

> Tip: If a shipment is split across multiple deliveries, receive each delivery as it arrives. The PO will stay in "Partial" status until everything has been received.

> Warning: Count the items before confirming receipt. Once received, the stock is added to inventory. If the count is wrong, you will need to make a manual stock adjustment in the Inventory module.
