# Installing and Managing Modules

**Modules** are add-on features that you can enable or disable in the system. Each module adds a specific set of tools and pages. You only need to install the modules your facility actually uses, which keeps the system simple and focused.

## What Modules Are

Think of modules like apps on a phone. The core system provides the basics — login, user management, and the dashboard. Modules add extra capabilities on top of that, like production tracking, scheduling, or chat.

Each module is independent. Installing one module does not affect the others.

## How to See Available Modules

1. Go to **Admin** in the sidebar.
2. Click **Modules**.
3. The module list shows all available modules along with their current status (Installed or Not Installed).

You can also see which modules are installed during the **initial setup wizard** when the system is first configured.

## Enabling a Module During Initial Setup

When you first set up the system, the **Install Wizard** walks you through choosing which modules to enable.

1. On the **Modules** step of the wizard, review the list of available modules.
2. Check the box next to each module you want to install.
3. Click **Next** to continue the setup process.

The wizard will create the necessary database tables and add the module's pages to the navigation menu.

## What Happens When a Module Is Installed

When you install a module, the system does the following automatically:

- **Database tables** are created to store the module's data.
- **Navigation links** appear in the sidebar so users can access the new features.
- **Permissions** for the module are added to the permission matrix so you can control who has access.

No manual configuration is needed beyond enabling the module. Everything is set up automatically.

> Tip: After installing a new module, go to **Admin** > **Settings** > **Permissions** to review who can access it. By default, only Admins and SuperAdmins may have access.

## The 7 Available Modules

The system offers these modules:

1. **Production** — Track production lines, runs, downtime, and defects. Includes the production dashboard with live status and efficiency metrics.
2. **Scheduling** — Manage work schedules, shifts, time off requests, and shift swaps. Includes the calendar view.
3. **Chat** — Built-in messaging with channels for team communication. Includes @mentions and presence indicators.
4. **Reports** — Run pre-built reports and create custom reports from your data. Includes export to PDF and CSV.
5. **Maintenance** — Computerized Maintenance Management System (CMMS) for work orders, parts inventory, and maintenance requests.
6. **Quality** — Quality control forms, inspection checklists, and quality trend tracking. Works alongside the Production module's defect tracking.
7. **Inventory** — Track raw materials, finished goods, and parts. Includes barcode and QR code scanner support.

You do not need to install all modules at once. Start with the modules you need most and add more later as your needs grow.

> Tip: If you are not sure which modules to install, start with **Production** and **Scheduling** — these cover the most common needs for a manufacturing facility.
