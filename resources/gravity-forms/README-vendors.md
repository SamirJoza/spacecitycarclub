# Vendor applications — setup guide

This folder holds the Gravity Forms used by the vendor workflow:

| File | Form | Purpose |
|---|---|---|
| `vendor-application.json` | **Vendor Application** | Public form. Creates one *pending* Vendor application per selected show and adds the vendor to EmailOctopus |
| `vendor-payment.json` | **Vendor Payment** | Private payment form (Stripe), reached only through the link in the approval email |

## How the workflow runs

1. **Apply.** A vendor fills in the Vendor Application form and picks one or more upcoming shows.
2. **Record.** The theme creates one **Vendor** post per show, with status *Pending review*. Gravity Forms emails staff and sends the vendor a receipt.
3. **Review.** Staff open **Vendors** in wp-admin, review each application and set **Application status** on the *Review* tab:
   - **Approved:** the fee is locked in (the show's default fee, or the *Fee override*). The vendor is emailed a private **Pay now** link.
   - **Declined:** the vendor is optionally emailed, including the *Reason* if you entered one.
4. **Pay.** The vendor pays through the Vendor Payment form (Stripe). The application becomes **Paid — confirmed**, and both the vendor and staff get a confirmation email.

**No payment is ever requested before a person approves the application.**

A food vendor who picks a show whose venue doesn't allow food is flagged with a **Food not allowed** tag in the Vendors list.

---

## One-time setup

### 1. Configure the shows

On each MagePeople event that should take vendors, use the **Vendors** box in the right sidebar:

- **Accepting vendor applications:** turn on. The show then appears in the form while it is upcoming.
- **Vendor fee:** the default fee for this show.
- **Food vendors allowed:** turn off if the venue doesn't allow outside food.

### 2. Import the forms

In **Forms → Import/Export → Import Forms**, import both JSON files.

The theme recognizes the forms by their **CSS Class Name**: `sccc-vendor-application` and `sccc-vendor-payment`. It recognizes fields by their **Admin Field Label**. Don't change these; everything else (labels, wording, layout) is safe to edit.

### 3. Vendor Payment form: add Stripe

Form JSON exports can't carry payment add-on pieces, so these steps are done by hand:

1. Open **Vendor Payment** in the form editor.
2. Add **Pricing Fields → Stripe** (the card field) below *Total*. Save.
3. Go to **Settings → Stripe → Add New**:
   - **Transaction type:** Products and Services
   - **Payment amount:** Form Total
   - **Billing information → Email:** *Email for your receipt*
   - Save.

The price is always set by the theme from the approved fee, so vendors can't change it.

### 4. Create the payment page

1. Create a page such as **Vendor Payment** (`/vendor-payment/`) and add the Vendor Payment form to it.
2. Consider hiding the page from menus and search engines.
3. Go to **Theme Settings → Vendor Settings** and set:
   - **Vendor payment page:** the page you just created (required before anyone can be approved).
   - **Staff notification email:** who gets "vendor paid" notices.
   - **Extra text for the approval email:** optional, e.g. load-in times or permit reminders.
   - **Message when no shows are open:** optional.

### 5. Create the application page

Create a page such as **Become a Vendor** (`/vendors/`) and add the Vendor Application form. When no show is open, the form is replaced by the "closed" message automatically.

### 6. Vendor Application form: add the EmailOctopus feed

Form exports don't include add-on feeds, so set this up once:

1. Open **Vendor Application → Settings → EmailOctopus → Add New**.
2. Set:
   - **Name:** Vendors
   - **List:** your vendors list in EmailOctopus (create one there first, e.g. *SCCC Vendors*)
   - **Map fields:** Email → *Email*, First Name → *First name*, Last Name → *Last name*
   - **Conditional logic:** enable, and process this feed if *Stay in the loop* **is** `yes`
3. Save.

The opt-in box is ticked by default. Remove the conditional logic if every vendor should be added regardless.

### 7. Check the notification addresses

The two notifications on the application form use `{admin_email}`. Change *Staff: new vendor application* if a different person should get new applications.

---

## Testing locally

The **Gravity Forms Stripe** add-on must be installed and in **test mode**. Use Stripe's test card `4242 4242 4242 4242`, any future expiry date and any CVC.

1. Turn on *Accepting vendor applications* on an upcoming event and set a fee.
2. Submit the application form, choosing two shows. Check that two Vendor applications appear under **Vendors**.
3. Approve one. Check the admin notice and the vendor email, then open the payment link.
4. Pay with the test card. The application should switch to **Paid — confirmed**.
5. Open the same link again. It should say the fee is already paid.
6. Decline the other application and check the decline email.
