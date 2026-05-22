# POS Troubleshooting

## "I can't find a product"

**Problem:** You searched for a product in the Register, but nothing came up.

**What to check:**

1. Make sure you are spelling the product name correctly. Try searching by SKU or UPC code instead.
2. Check if the product exists in the **Inventory** module. If it has not been added to inventory, it will not show up in POS.
3. The product may be marked as inactive in Inventory. Ask your inventory manager to check.
4. If the product has a UPC barcode, try typing or scanning that exact number.

> Tip: Searching by SKU (the short code your business assigns to each product) is more reliable than searching by name, especially if the name has unusual spelling.

## "The barcode scanner isn't working"

**Problem:** You scan a barcode, but nothing happens in the search bar.

**What to check:**

1. Click inside the **search bar** before scanning. The scanner types characters wherever the cursor is. If the cursor is not in the search bar, the scanned data goes nowhere.
2. Check that the scanner is plugged in (USB) or connected (Bluetooth). Try unplugging and reconnecting it.
3. Test the scanner on a simple text document. If it types the barcode number in the text document but not in Phoenix, the issue is that the search bar does not have focus.
4. Some scanners need to be configured to add an "Enter" key press after the barcode. Check your scanner's manual.

## "A payment failed"

**Problem:** You tried to process a card payment but received an error.

**What to check:**

1. If the error says "declined," the customer's bank refused the charge. Ask the customer to try a different card.
2. If you are using manual card entry, make sure you typed the approval code correctly from your card terminal.
3. If you are using Stripe or Square integration, check that the payment gateway is properly configured in the **Settings** tab.
4. Try switching to **cash payment** as an alternative while the issue is being resolved.
5. If errors persist, contact your system administrator to check the payment gateway configuration.

## "I need to void an order"

**Problem:** You created an order by mistake and need to cancel it before it is paid.

**What to do:**

1. Go to the **Orders** tab.
2. Find the order you want to cancel. It must have a status of **open** (not yet paid).
3. Click the order to open its details.
4. Click the **Void** button.
5. The order will be marked as voided and no payment will be processed.

> Warning: You can only void orders that have not been paid yet. If the order has already been paid, you need to process a refund instead. See [Processing Refunds](refunds.md).

## "The register shows 'Session already open'"

**Problem:** You try to open a new register session, but you get an error saying you already have one open.

**What to do:**

1. You have an existing session that was never closed. This usually happens if you forgot to close the register at the end of your last shift.
2. Close the existing session first. Go to the Register tab and look for the **Close Session** option.
3. Enter your ending cash amount and close the session.
4. Now you can open a new session.

If the open session belongs to a different person on the same register, that person needs to close their session, or a supervisor can help.

## "My totals don't match at end of day"

**Problem:** When closing the register, the cash you counted does not match what the system says you should have.

**What to check:**

1. **Refunds:** Did you process any cash refunds during the day? Cash refunds reduce the expected cash total.
2. **Voids:** Were any cash orders voided? Voided orders should not affect cash totals, but if an order was paid in cash and then refunded (not voided), the expected total will be lower.
3. **Discounts:** Did you apply discounts after some items were already rung up? This can cause minor differences.
4. **Change errors:** You may have given incorrect change to a customer.
5. **Double-check your count:** Count the cash again carefully. Miscounting is the most common cause of small discrepancies.

If the difference is large (more than a few dollars) and you cannot explain it, report it to your supervisor before closing the session. Add a note in the **Notes** field explaining what you know.

## "I can't process a refund"

**Problem:** The Refund button is not available or you get a permission error.

**What to check:**

1. Only **Supervisors**, **Admins**, and **SuperAdmins** can process refunds. If you are an Operator, ask your supervisor to handle the refund.
2. The order must have a status of **paid**. You cannot refund an order that has not been paid yet (use void instead).
3. The order may have already been refunded. Check the order status -- if it says "refunded," the refund has already been processed.

## "The POS page is not loading"

**Problem:** The POS module shows a blank page, a spinner that never stops, or an error message.

**What to try:**

1. Refresh the page in your browser (press F5 or Ctrl+R).
2. Clear your browser cache (Ctrl+Shift+Delete in most browsers).
3. Try a different browser.
4. Check your internet or network connection.
5. If the problem continues, contact your system administrator. The server or database may need attention.
