# Creating Custom Reports (Admin)

The **Report Builder** lets administrators create custom reports tailored to your facility's specific needs. If the pre-built reports do not cover what you need, you can build your own.

## What the Report Builder Is

The Report Builder is a tool that lets you choose what data to include in a report, how to filter it, and how to display it. You do not need to write any code — everything is done through drop-down menus and checkboxes.

Only users with **Admin** or **SuperAdmin** roles can access the Report Builder.

## How to Create a Custom Report

1. Go to **Reports** in the sidebar.
2. Click **New Report**.
3. Give your report a **Name** and an optional **Description** so others know what it is for.
4. Choose a **Data Source** — this is the type of data the report will pull from (for example, Production Runs, Downtime Events, Defects, or Scheduling).
5. Select the **Columns** you want to include by checking the boxes next to each field name. These become the columns in your report's output table.
6. Add **Filters** to narrow down the results. For example, you might filter by date range, production line, or shift. Click **Add Filter**, choose the field, set the condition (equals, greater than, between, etc.), and enter the value.
7. Choose a **Sort Order** for the results.
8. Click **Preview** to see a sample of what the report will look like.
9. Click **Save** when you are satisfied with the results.

## Saving a Report to the Library

When you save a custom report, it is added to the **report library** where other users can find and run it. You can control who has access:

- Choose **Everyone** to make the report available to all users with report permissions.
- Choose **My Team** to limit it to users in your department.
- Choose **Only Me** to keep it private.

You can change these settings later by editing the report.

## Scheduling Reports to Run Automatically

Administrators can set reports to run on a schedule and deliver results automatically.

1. Open the report you want to schedule.
2. Click **Schedule**.
3. Choose how often the report should run: **Daily**, **Weekly**, or **Monthly**.
4. Set the **time** the report should run.
5. Enter the **email addresses** that should receive the report results.
6. Click **Save Schedule**.

The system will run the report at the scheduled time and email the results as a PDF attachment.

> Tip: Schedule important reports to run early in the morning so the results are waiting in your inbox when you start your day.
