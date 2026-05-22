# Glossary

This glossary defines common terms used throughout Phoenix. Terms are listed in alphabetical order.

---

**Admin** -- A user role with full access to all modules, user management, and system settings. Admins can manage other users, configure the system, and access every feature.

**API (Application Programming Interface)** -- A way for different parts of the system to communicate with each other behind the scenes. You do not interact with APIs directly, but they power features like the POS register, inventory lookups, and data syncing between modules.

**Barcode** -- A pattern of lines or squares printed on a product label. When scanned with a barcode reader, it identifies the product automatically. Phoenix supports both traditional barcodes and QR codes.

**Cart** -- The list of items a customer is buying during a POS transaction. Items are added to the cart, quantities can be adjusted, and the cart calculates the total before payment.

**CMMS (Computerized Maintenance Management System)** -- The maintenance module in Phoenix. It tracks work orders, equipment, preventive maintenance schedules, and parts inventory for maintenance teams.

**Credit Card Gateway** -- A service that processes credit and debit card payments. Phoenix supports manual card entry, Stripe, and Square as payment gateways. The gateway is configured by an administrator.

**Dashboard** -- The main home screen you see after logging in. It shows an overview of your system, including installed modules and system status. Each module (like POS) may also have its own dashboard with module-specific information.

**Discount** -- A reduction in price applied to a sale. In the POS register, you can enter a dollar amount in the discount field to lower the total the customer pays.

**Draft** -- A status for invoices and purchase orders that have been created but not yet finalized. Drafts can still be edited. Once submitted or sent, they can no longer be changed freely.

**Due Date** -- The date by which an invoice must be paid. If payment is not received by the due date, the invoice status changes to Overdue.

**Inventory** -- The stock of items your business has available for sale or use. The Inventory module tracks quantities, locations, and values of all items.

**Invoice** -- A document that bills a customer for goods or services, to be paid at a later date. Invoices include line items, amounts, tax, and a due date.

**Module** -- A self-contained feature set that can be installed into Phoenix. Examples include Point of Sale, Inventory Management, and CMMS. Each module adds specific capabilities to the system.

**Operator** -- A user role for front-line workers. Operators can use the POS register, access production tools, and perform day-to-day tasks. They have limited access to administrative functions.

**Overdue** -- An invoice status meaning the due date has passed without full payment being received.

**Partial** -- A status indicating that some but not all of an expected action is complete. For invoices, it means some payment has been received but a balance remains. For purchase orders, it means some items have been delivered but others are still pending.

**Permission** -- A rule that controls whether a user can access a specific feature or perform a specific action. Permissions are assigned through roles and groups.

**PO (Purchase Order)** -- A document requesting items from a supplier. It specifies what to buy, how much, and at what price. When the items arrive, they are received against the PO and added to inventory.

**POS (Point of Sale)** -- The module used to process sales transactions. It includes the register, order management, customer directory, invoicing, and purchase orders.

**Received** -- A status for purchase orders indicating that all ordered items have been delivered and checked into inventory.

**Refund** -- The process of returning money to a customer for a previously paid order. When a refund is processed, the items are added back to inventory stock.

**Register** -- The screen where sales are processed in the POS module. It includes product search, the shopping cart, and payment buttons. A register session must be opened before processing sales.

**Role** -- A label assigned to a user account that determines what the user can see and do in Phoenix. Common roles include Operator, Supervisor, Admin, and SuperAdmin.

**Session** -- A period of time during which you are logged into Phoenix. Also refers to a register session in POS, which tracks your sales activity from the time you open the register until you close it.

**SKU (Stock Keeping Unit)** -- A unique code your business assigns to each product for tracking purposes. SKUs are used to identify products in inventory and POS searches.

**Submitted** -- A status for purchase orders that have been finalized and sent to the supplier. Submitted POs cannot be edited.

**SuperAdmin** -- The highest user role. SuperAdmins have unrestricted access to every feature in the system, including all modules, all users, and all settings.

**Supervisor** -- A user role with elevated access. Supervisors can access most modules, process refunds, view management reports, and oversee day-to-day operations.

**Tax Exempt** -- A flag on a customer record indicating that the customer does not pay sales tax. This is typically used for government agencies, nonprofits, or resellers.

**Tax Rate** -- The percentage of sales tax applied to transactions. Tax rates are configured in POS Settings and automatically applied to sales totals.

**UPC (Universal Product Code)** -- A standardized barcode number found on most retail products. Phoenix can search for products by their UPC code, either by typing it or scanning it with a barcode reader.

**Void** -- The process of cancelling an order that has not yet been paid. Voided orders are marked as cancelled and no payment is processed. Unlike refunds, voids do not affect inventory because stock was never deducted.

**Work Order** -- A maintenance task or repair request tracked in the CMMS module. Work orders include a description of the problem, priority level, assigned technician, and status.
