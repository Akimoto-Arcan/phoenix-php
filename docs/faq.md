# Frequently Asked Questions

## How do I change my password?

Contact your administrator. Password changes are managed through the user management panel, which is only accessible to users with Admin or SuperAdmin roles. Your administrator can reset your password and, if needed, require you to set a new one on your next login.

## How do I switch between light and dark mode?

Click the **sun** or **moon** icon in the top-right corner of the screen (in the topbar). The theme switches instantly, and your preference is saved automatically.

## What do the different user roles mean?

Phoenix uses roles to control what each person can see and do:

- **Operator** -- can use the POS register, access production tools, and view their assigned work areas.
- **Inspection** -- can access quality control and inspection forms.
- **Lab** -- can access lab testing and inspection tools.
- **Maintenance** -- can access the CMMS module for work orders and maintenance tasks.
- **Supervisor** -- can access most modules, process refunds, and view management reports.
- **Admin** -- full access, including user management and system settings.
- **SuperAdmin** -- unrestricted access to every feature in the system.

Your role is assigned by an administrator. If you need access to something you cannot see, ask your administrator.

## How do I add a new user?

Only **Admin** and **SuperAdmin** users can add new accounts. There are two ways:

1. **Admin adds the user directly:** Go to the **Users** section under Administration in the sidebar. Click **Add User** and fill in the details.
2. **User self-registers:** The new user goes to the login page and clicks **Register**. They fill out the registration form. An administrator then approves the account from the Users panel.

## How do I install a new module?

Modules are installed through the system's install wizard. Only administrators can install or remove modules. Go to **Settings** under Administration in the sidebar, or run the install wizard if the module has not been set up yet.

Available modules include Point of Sale, Inventory Management, CMMS, Production Tracker, Shift Scheduling, Report Builder, and Internal Chat.

## How do I back up the database?

Database backups are a system administration task. If you are an administrator, you can create a backup using the MySQL command line:

1. Open a terminal on the server.
2. Run the backup command provided in your system documentation.
3. Store the backup file in a safe location.

If you are not an administrator, ask your IT team to confirm that regular backups are being performed.

## How does the POS connect to inventory?

The POS and Inventory modules are linked automatically:

- When you **sell an item**, the sold quantity is subtracted from inventory stock.
- When you **refund an order**, the refunded items are added back to inventory stock.
- When you **receive a purchase order**, the received items are added to inventory stock.
- The POS register **searches the inventory database** for product names, SKUs, UPC codes, and prices.

You do not need to update inventory manually after making a sale. The system handles it in the background.

## Can multiple people use the system at the same time?

Yes. Phoenix is a web-based application, so multiple people can be logged in and working at the same time from different computers or devices. Each person sees their own session.

In the POS module, each person can have their own register session open on a different register. Two people cannot have a session open on the same register at the same time.

## What browsers are supported?

Phoenix works on all modern web browsers:

- **Google Chrome** (recommended)
- **Mozilla Firefox**
- **Microsoft Edge**
- **Apple Safari**

Use the latest version of your browser for the best experience. Internet Explorer is not supported.

## How do I report a problem?

If something is not working as expected:

1. Note what you were trying to do and what happened instead.
2. Take a screenshot if possible (press Print Screen on Windows, or Shift+Command+4 on Mac).
3. Report the issue to your supervisor or system administrator with the details and screenshot.

The more detail you provide, the faster the problem can be fixed.

## Why did the system log me out?

Phoenix has an automatic **session timeout** for security. If you are inactive for too long, the system logs you out to protect your account.

- **Operators, Inspectors, and Lab users** have a 12-hour session, so you can stay logged in for a full shift.
- **Supervisors and Admins** have a shorter timeout (typically 10 minutes of inactivity) because their accounts have more sensitive access.

Simply log in again to continue working.

## Can I use Phoenix on my phone?

Phoenix works on mobile browsers, but mobile access must be enabled for your account. If you try to log in from a phone or tablet and see a "Mobile access is restricted" message, contact your administrator to request mobile access.

On mobile devices, tap the **hamburger menu** (three horizontal lines) in the top-left corner to open the sidebar navigation.

## What happens if the server goes down?

If the Phoenix server is unavailable, you will see an error page or your browser will say the site cannot be reached. Contact your system administrator or IT team. They can restart the server services to bring the system back online.

> Tip: If you experience frequent disconnections, it may be a network issue rather than a server problem. Check your computer's network connection first.

## How do I change the tax rate for sales?

Tax rates are managed in the POS module's **Settings** tab. Only administrators can change tax rates.

1. Open **Point of Sale**.
2. Click the **Settings** tab.
3. Find the **Tax Rates** section.
4. Add a new rate or edit the existing one.
5. Mark one rate as the **default** to use it for all new sales.
