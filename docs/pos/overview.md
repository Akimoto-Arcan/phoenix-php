# Point of Sale -- Overview

## What the POS Module Does

The **Point of Sale (POS)** module is where you process sales, accept payments, and keep track of customer transactions. It handles everything from ringing up a quick cash sale to creating detailed invoices for business customers.

When a sale is completed, the system automatically deducts the sold items from your **Inventory** so your stock counts stay accurate without any extra work.

## The Main Areas

The POS module is divided into several sections, accessible through tabs inside the module:

### Register

The **Register** is where you ring up sales. It has a split-screen layout: product search on the left, shopping cart and payment buttons on the right. You can search for products by name, scan barcodes, or browse product cards.

### Dashboard

The **Dashboard** shows your sales performance at a glance. It displays today's sales total, number of orders, average order value, and a chart of sales over the last 7 days.

### Orders

The **Orders** tab shows a list of every order that has been placed. You can filter by status (open, paid, refunded, voided) and date range. Click any order to see its full details, including items, payment information, and timestamps.

### Customers

The **Customers** tab is your customer directory. Add new customers, update their contact information, and mark customers as tax-exempt. Customers can be linked to orders and invoices.

### Invoices

The **Invoices** tab lets you create and manage invoices -- bills that you send to customers to pay later. You can track which invoices are paid, partially paid, or overdue, and record payments as they come in.

### Purchase Orders

**Purchase Orders** are requests to buy items from your suppliers. Create a PO, send it to a supplier, and then mark items as received when they arrive. Receiving items automatically adds them to your inventory.

### Settings

The **Settings** tab lets administrators configure tax rates, payment gateways, and other POS options.

## Who Can Use It

Access to the POS module is controlled by your account role:

- **Operators** -- can access the register, view orders, and process sales.
- **Supervisors** -- can do everything Operators can, plus process refunds and view reports.
- **Admins** -- full access, including POS settings and configuration.

If you do not see the **Point of Sale** option in your sidebar, your role may not include POS access. Contact your administrator to request it.

## How It Connects to Inventory

The POS and Inventory modules work together automatically:

- When you **sell an item**, its stock quantity is reduced in Inventory.
- When you **refund an order**, the stock is added back to Inventory.
- When you **receive a purchase order**, the stock is added to Inventory.
- Product names, prices, and SKU codes come directly from the Inventory item master.

You do not need to update Inventory manually after a sale. The system handles it for you.

> Tip: If a product shows as "Out of stock" in the Register, it means the Inventory count has reached zero. Ask your supervisor or inventory manager to restock it.
