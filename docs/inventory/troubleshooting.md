# Inventory Troubleshooting

This page covers common inventory problems and how to fix them.

## "My stock count is wrong"

Stock counts can drift when transactions are missed or entered incorrectly. Here is how to investigate:

1. Click **Inventory**, then click **Transactions**.
2. Filter by the item in question to see all receiving and shipping entries.
3. Check for duplicate entries, incorrect quantities, or missing transactions.

If you find a mistake, you can correct it by creating a new transaction. For example, if someone accidentally received 100 units instead of 10, ship 90 units to bring the count back to the correct number.

> Tip: Look at the dates on each transaction. If the count was correct on a certain date, focus on transactions that happened after that date.

## "I can't find an item"

If an item is not showing up in the items list, try these steps:

1. Use the **search bar** at the top of the Items page.
2. Search by **SKU** first, since SKUs are unique and exact.
3. If that does not work, search by **name** using just one or two keywords.
4. Check whether a category filter is active. Clear any filters to show all items.

If you still cannot find the item, it may not have been added yet. See the [Adding Inventory Items](adding-items.md) guide to create it.

## "The system won't let me ship"

This happens when the quantity you are trying to ship is greater than the stock currently available. The error message will tell you how much stock is on hand.

To resolve this:

- Reduce the ship quantity to match the available stock.
- If you believe more stock should be available, check whether a recent shipment was received in the system. If not, receive the stock first, then try shipping again.

## "An item isn't showing in POS"

For an item to appear in the POS system, it must meet two conditions:

1. **Sell Price** — The item must have a sell price entered. Items without a sell price are not displayed in POS.
2. **Active status** — The item must be active (not disabled or archived).

To fix this:

1. Go to **Inventory**, then **Items**.
2. Find the item and click **Edit**.
3. Make sure the **Sell Price** field has a value.
4. Make sure the item is set to **Active**.
5. Click **Save**.

The item should appear in POS within a few moments.

> Tip: If the item still does not appear after saving, try refreshing the POS screen or logging out and back in.
