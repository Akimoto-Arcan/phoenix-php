# Processing Card Payments

## Overview

Card payments can work in two ways depending on how your system is set up. Your business may use **manual card entry** (where you type details from an external card terminal) or an **integrated payment gateway** (where Phoenix charges the card directly through Stripe or Square).

Ask your supervisor or administrator which method your location uses.

## Manual Card Entry

This is the most common setup. You process the card on a separate card terminal (the physical machine the customer swipes or taps their card on) and then record the details in Phoenix.

1. Add items to the cart and verify the totals.
2. Click the blue **Card** button at the bottom of the cart.
3. A payment window appears showing the amount being charged.
4. Process the card on your external card terminal.
5. After the terminal approves the transaction, enter the following in Phoenix (all fields are optional but recommended):
   - **Last 4 Digits** -- the last four digits of the customer's card number (for example, 1234).
   - **Card Type** -- select **Visa**, **Mastercard**, **Amex**, **Discover**, or **Other** from the dropdown.
   - **Approval Code** -- the authorization code printed on the terminal receipt.
6. Click **Process Card**.

The sale will be recorded and the items will be deducted from inventory.

## Integrated Payment Gateway (Stripe or Square)

If your system is connected to **Stripe** or **Square**, the process is simpler. Phoenix charges the card automatically.

1. Add items to the cart and verify the totals.
2. Click the blue **Card** button.
3. The system will process the payment through your configured gateway.
4. A spinning indicator and "Processing payment..." message will appear while the charge is being processed.
5. When approved, a receipt screen appears confirming the transaction.

You do not need to enter card details manually -- the gateway handles everything.

## What to Do If a Card Is Declined

If the payment fails, you will see an error message in red.

1. Ask the customer if they have another card.
2. If they do, click **Cancel** to close the payment window, then click **Card** again and try the new card.
3. If the customer wants to pay cash instead, click **Cancel** and then click the **Cash** button.
4. If the problem persists, check with your supervisor. The issue may be with the payment gateway configuration rather than the customer's card.

> Tip: A "declined" message usually means the customer's bank refused the charge. This is not a problem with Phoenix. The customer should contact their bank.

## Checking Your Payment Gateway Setup

If you are an administrator and need to verify which payment gateway is configured:

1. Open the **Point of Sale** module.
2. Click the **Settings** tab.
3. Look for the **Payment Gateway** section.
4. The current gateway will be listed (Manual, Stripe, or Square).

Only administrators can change the payment gateway settings.

> Warning: Never write down a customer's full card number. Phoenix only records the last 4 digits for reference. Storing full card numbers is a security risk and may violate payment processing rules.
