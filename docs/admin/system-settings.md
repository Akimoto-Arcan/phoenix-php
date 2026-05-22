# System Settings

The **Settings** page is where administrators configure how the system behaves. Changes made here affect all users, so be careful when modifying these options.

## How to Access Settings

1. Go to **Admin** in the sidebar (or click **Settings** in the sidebar if it appears there directly).
2. Click **Settings**.

The Settings page is organized into sections. Each section is described below.

## Application Name

The **Application Name** is the title that appears in the browser tab and at the top of the login page. Change this to match your company or facility name.

1. Find the **Application Name** field.
2. Type the name you want to display.
3. Click **Save**.

## Default Theme

The **Default Theme** controls the color scheme for all users who have not set their own preference.

1. Find the **Theme** setting.
2. Choose from the available options (for example, Light or Dark).
3. Click **Save**.

Individual users may be able to override this in their personal settings.

## Session Timeout

The **Session Timeout** controls how long a user can be inactive before they are automatically logged out. This is a security feature that prevents unauthorized access when someone walks away from their computer.

1. Find the **Session Timeout** field.
2. Enter the number of minutes of allowed inactivity. The default is 5 minutes.
3. Click **Save**.

> Tip: For workstations on the factory floor, a short timeout (5 minutes) is recommended. For office computers, you may want a longer timeout (15-30 minutes).

## Security Settings

The security section helps protect user accounts from unauthorized access.

- **Brute-force protection** — Locks an account after a set number of failed login attempts. This prevents someone from guessing passwords by trying many combinations.
- **Password requirements** — Controls the minimum length, complexity (uppercase, lowercase, numbers, special characters), and expiration period for passwords.

1. Find the **Security** section.
2. Adjust the settings as needed.
3. Click **Save**.

> Warning: Do not turn off brute-force protection. It is an important security measure that protects all user accounts.

## Payment Gateway Configuration

If your system includes billing or payment features, this section is where you configure the connection to your payment processor.

1. Find the **Payment Gateway** section.
2. Enter the required credentials provided by your payment processor (such as API keys or merchant IDs).
3. Choose whether to use **Test Mode** (for testing without real charges) or **Live Mode** (for real transactions).
4. Click **Save**.

> Warning: Never share payment gateway credentials with unauthorized personnel. If you suspect credentials have been compromised, contact your payment processor immediately.
