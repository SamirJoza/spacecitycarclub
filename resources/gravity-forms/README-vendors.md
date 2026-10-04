# Vendors — how it works and how to set it up

## The idea

- **One Vendor record per business**, matched by email. It's never duplicated. **Vendor since** is the date the record was created.
- **Shows are MagePeople events.** Only our own shows, where *Club show: accept vendor applications* is switched on, take part. Other organizations' events never appear anywhere in the vendor feature.
- **New vendors apply once** (full application). **Returning vendors** just enter their email and get a personal link to sign up and pay for the next show.
- **Food rule.** Food vendors can't apply or sign up for a show that doesn't allow food. They get a friendly message instead, and the process ends.

## Workflow

| Step | Vendor status | Show row | What happens |
|---|---|---|---|
| New vendor applies (full form) | Pending review | Pending | Staff notified; vendor gets a receipt |
| Staff approve | Approved vendor | Awaiting payment | Email with a private payment link (fee locked in) |
| Vendor pays (Stripe) | Approved vendor | **Active** | Vendor and staff confirmation emails |
| Staff reject | Rejected | Declined | **Reason required**; the email tells the vendor why. The record can be deleted later |
| Returning vendor enters email | Approved vendor | — | Personal link emailed → choose show → pay → **Active** |
| Staff mark "Do not re-invite" | Do not re-invite | Declined | Online sign-up refused; no email |

Returning vendors are **not** reviewed again, with two exceptions: food vendors are stopped automatically at shows that don't allow food, and "Do not re-invite" vendors can't sign up.

## Where things live

| What | Where |
|---|---|
| Vendors list (color-coded, sortable, filters, exports) | wp-admin → **Vendors** |
| Vendor type colors | Vendors → **Vendor Types** → edit a type → *Color* |
| Per-show settings | Edit the MagePeople event → **Vendors** step (Modern editor) or **Vendors** box in the sidebar (Classic editor) |
| Pages, emails, messages, EmailOctopus list | Theme Settings → **Vendor Settings** |
| Form status and "Sync vendor forms from code" | Top of Theme Settings → Vendor Settings |

### Per-show settings (on the event)

In MagePeople's Modern editor they're in the **Vendors** step, after Advanced. In the Classic editor they're in the **Vendors** box in the sidebar. Both editors save the same fields.


- **Club show: accept vendor applications:** off by default. Turn on only for our own shows.
- **Vendor fee:** the price for this show.
- **Food vendors allowed:** on or off.
- **Application deadline:** optional. The show closes to vendors after this day, and it closes automatically once the show date has passed.
- **Pricing:** *Flat fee*, or *By booth type* with a price table. Booth types not listed use the flat fee.

## Gravity Forms (created automatically in code)

The theme builds these forms itself (`app/Support/Vendors/VendorForms.php`). There is nothing to import.

| Form | Used on | Purpose |
|---|---|---|
| **Vendor Application** (4 pages) | Vendor page (via the block) | New vendors: business, contact, show & booth, promotion & agreement |
| **Returning Vendor** | Vendor page (via the block) | Email → personal sign-up link |
| **Vendor Sign-up & Payment** | Sign-up & payment page | Choose show → pay with Stripe |

The code is the source of truth. **Sync vendor forms from code** rebuilds the forms and keeps their IDs, entries and feeds. Change wording in `VendorForms.php`, then bump `SCHEMA_VERSION`.

### Feeds

- **Stripe:** once Gravity Forms Stripe is active, the card field and the payment feed are added automatically.
- **EmailOctopus:** choose the vendors list in Vendor Settings and save. The feed is created and adds new vendors who tick *Stay in the loop*.

## Blocks

- **Vendor Sign-up:** place it on the public vendor page. It lists the open shows and asks "Have you been a vendor with us before?", then shows the returning or new form.
  - Links can preselect a path: `?vendor_signup=returning` or `?vendor_signup=new`, plus `#vendor-signup` to jump to the block.
- **Featured Vendors:** logos of **active** vendors who allow featuring, for the next club show (automatic) or a chosen show. Layout is a grid or a scrolling rail, with optional names, type pills and links.

## Exports (Vendors list)

Filter the list by a show, then use:

- **Export CSV:** every vendor on that show, with status, fee, contact and booth details.
- **Download logos (ZIP):** logos of active vendors who allow featuring.

## One-time setup

1. **Connect the add-ons:**
   - Gravity Forms Stripe (use test mode locally).
   - EmailOctopus: API key, plus create a vendors list in EmailOctopus.
2. **Create two pages:**
   - **Vendor page** (e.g. `/vendors/`): add the **Vendor Sign-up** block.
   - **Vendor sign-up & payment page** (e.g. `/vendor-signup/`): add the *Vendor Sign-up & Payment* form. Hide it from menus.
3. **Theme Settings → Vendor Settings:**
   - Set both pages, the staff email and the EmailOctopus list.
   - Adjust the messages if you like.
   - Check that the status box shows everything in green.
4. **Your next show:** on the event, switch on *Club show: accept vendor applications*, set the fee and the food rule.
5. **Inviting past vendors:** send an EmailOctopus campaign to the vendors list with a button linking to
   `https://spacecitycarclub.com/vendors/?vendor_signup=returning#vendor-signup`

## Testing locally

Put Stripe in test mode and use card `4242 4242 4242 4242`, any future expiry date and any CVC.

1. Create a test club show (accepting vendors, $50, food allowed) and a second one with **food not allowed**.
2. Apply as a new **food** vendor for show 1. Check that a pending Vendor appears.
3. Try the same email again as a new vendor. It should be refused with "use returning vendor".
4. Approve the vendor, open the payment link and pay. Show 1 should become **Active**.
5. Use *Returning Vendor* with the same email. Show 2 doesn't allow food, so you should get the "no food vendors" message.
6. Switch show 2 to allow food, then use *Returning Vendor* again. You should get a link, and after paying, show 2 is **Active**.
7. Reject a second test vendor with a reason. Check the rejection email.
8. Check the Featured Vendors block and both exports.
