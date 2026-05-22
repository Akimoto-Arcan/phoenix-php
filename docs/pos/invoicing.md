# Creating Invoices

## What Is an Invoice?

An **invoice** is a bill you send to a customer to pay later. Instead of paying at the time of sale, the customer receives a document listing what they owe and a due date. Invoices are common for business-to-business sales and customers with credit accounts.

## Creating an Invoice from a Sale

If a customer is buying items now but wants to pay later:

1. Add items to the cart as you would for a normal sale.
2. Review the totals.
3. Click the purple **Invoice** button at the bottom of the cart (instead of Cash or Card).
4. A window will appear asking for invoice details:
   - **Customer** -- select the customer from the dropdown list. This field is required. If the customer is not in the list, you will need to add them first in the Customers tab.
   - **Due Date** -- defaults to 30 days from today. Change it if the customer has different payment terms.
   - **Notes** -- add any optional notes (for example, "Net 15" or a purchase order reference).
5. Click **Create Invoice**.

The system will create the invoice, record the order, and clear the cart. The items will not be deducted from inventory until the invoice is paid.

## Creating a Standalone Invoice

You can also create an invoice without going through the register:

1. Click the **Invoices** tab in the POS module.
2. Click the **New Invoice** button.
3. Fill in the invoice form:
   - **Customer** -- select or search for the customer.
   - **Line items** -- add each item with a description, quantity, and unit price.
   - **Due Date** -- set when payment is expected.
   - **Notes and Terms** -- add any relevant details.
4. Click **Save** to create the invoice.

## Adding Line Items

Each invoice can have multiple line items. For each item, you will enter:

- **Description** -- what you are billing for.
- **Quantity** -- how many units.
- **Unit Price** -- the price per unit.

The system calculates the line total (quantity times unit price) and the invoice total automatically.

## Setting a Due Date

The **due date** tells the customer when payment is expected. By default, it is set to 30 days from the invoice date. You can change this to any date.

If an invoice is not paid by its due date, its status will automatically change to **Overdue**.

## Invoice Statuses

Every invoice has a status that tells you where it stands:

- **Draft** -- the invoice has been created but not sent to the customer yet. You can still edit it.
- **Sent** -- the invoice has been sent to the customer. You can still edit it.
- **Paid** -- the customer has paid the full amount.
- **Partial** -- the customer has made a payment, but the balance is not yet fully paid.
- **Overdue** -- the due date has passed and the invoice has not been fully paid.
- **Cancelled** -- the invoice has been cancelled and is no longer active.

## Recording a Payment Against an Invoice

When a customer pays an invoice (in full or in part):

1. Go to the **Invoices** tab.
2. Find the invoice in the list.
3. Click the **Record Payment** button next to the invoice.
4. Enter the **amount** the customer paid.
5. Click **Submit**.

If the payment covers the full remaining balance, the status changes to **Paid**. If the payment is less than the remaining balance, the status changes to **Partial**, and the remaining amount due is updated.

> Tip: You can record multiple partial payments on the same invoice. Each payment is tracked, and the balance updates automatically.

> Tip: To see the full details of an invoice, including all line items and payment history, click the invoice number in the list.
