# CMMS Troubleshooting

This page covers common CMMS problems and how to fix them.

## "I can't create a work order"

Only users with the right permissions can create work orders. If the **New Work Order** button is missing or grayed out, your account may not have access.

Ask your administrator to check your role and group permissions. Typically, members of the **Maintenance**, **Supervisor**, **Admin**, or **SuperAdmin** groups can create work orders.

If you are an operator and need to report a problem, use the **Request Maintenance** button in the top navigation bar instead. This submits a request that the maintenance team can convert into a work order.

## "I need to assign a work order to someone"

You can change who is responsible for a work order at any time.

1. Go to **Work Orders** and click on the work order.
2. Click **Edit**.
3. Change the **Assigned To** field to the correct technician.
4. Click **Save**.

The newly assigned person will see the work order on their task list.

> Tip: You can also change the priority at the same time if the urgency has changed since the work order was created.

## "A PM shows as overdue"

A PM task shows as **overdue** when its due date has passed and it has not been marked as completed. The red highlight is there to draw your attention.

You have two options:

1. **Complete it** — If the work has been done (or you can do it now), open the PM task and click **Complete**.
2. **Reschedule it** — If the PM needs to be postponed, open the PM schedule, adjust the frequency or next due date, and save. Only do this if there is a valid reason to delay.

Do not ignore overdue PMs. Skipping scheduled maintenance increases the chance of unplanned equipment failures.

## "Equipment shows as Down but it's running"

Equipment status does not update automatically. If a repair has been completed but no one changed the status, the equipment will still show as **Down** in the system.

To fix this:

1. Go to **Equipment** and click on the machine.
2. Click **Edit**.
3. Change the **Status** to **Operational**.
4. Click **Save**.

> Tip: Make it a habit to update equipment status as part of closing out a work order. When you complete a repair, change the equipment status back to Operational before marking the work order as Completed.
