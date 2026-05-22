# Roles and Permissions

The system uses **roles** and **groups** to control what each user can see and do. Understanding how these work helps you set up accounts correctly and keep sensitive areas of the system secure.

## What Roles Are

A **role** is a job title that determines a user's base level of access. Every user is assigned exactly one role. The available roles are:

- **SuperAdmin** — Full access to everything in the system. Can manage all settings, users, and data. Use this role sparingly.
- **Admin** — Full access to most features including user management, reports, and system settings. Cannot change certain SuperAdmin-only settings.
- **Supervisor** — Can view and manage production data, schedules, and reports for their team. Can approve time off and shift swaps.
- **Operator** — Can log production runs, downtime, and defects. Can view their own schedule and submit time off requests.
- **Inspection** — Can access quality control forms and inspection records. Can log defects and view quality reports.
- **Lab** — Can access lab testing forms and results. Can view quality data related to lab work.
- **Maintenance** — Can access the maintenance system (CMMS), view and update work orders, and manage parts inventory.
- **Shipping** — Can access shipping and logistics features, including order tracking and delivery schedules.

## What Each Role Can Do

Here is a brief summary of what each role includes:

- **SuperAdmin** and **Admin** — Everything: user management, system settings, all reports, all production data.
- **Supervisor** — Production data for their lines, team schedules, team reports, approve requests.
- **Operator** — Log runs, downtime, and defects. View own schedule. Submit time off and swap requests.
- **Inspection** — Quality forms, defect records, quality reports.
- **Lab** — Lab testing forms, lab results, quality data.
- **Maintenance** — Work orders, parts inventory, maintenance reports.
- **Shipping** — Shipping records, delivery tracking.

## What Groups Are

A **group** is an additional layer of permissions. A user can belong to **multiple groups** at the same time. Groups let you give a user access to areas outside their normal role.

For example, an Operator might also be added to the Inspection group so they can fill out quality forms in addition to logging production data.

Groups are listed as a comma-separated list in each user's profile (for example: "Operator, Inspection").

## The Permission Matrix

The **permission matrix** is a table that shows which roles and groups can access each section of the system. You can find it in **Admin** > **Settings** > **Permissions**.

- Each row is a section of the system (Production, Scheduling, Reports, Maintenance, etc.).
- Each column is a role or group.
- A checkmark means that role or group can access that section.

Read across a row to see who can access a particular section. Read down a column to see everything a particular role can do.

## How to Change What a Role Can Access

1. Go to **Admin** in the sidebar.
2. Click **Settings**, then **Permissions**.
3. Find the section you want to change in the permission matrix.
4. Check or uncheck the box for the role or group you want to modify.
5. Click **Save Changes**.

Changes take effect immediately. Users with that role or group will see the updated access the next time they load a page.

> Warning: Be careful when changing permissions for the SuperAdmin or Admin roles. Removing too many permissions could lock administrators out of important settings.
