# Car shows: how registration works and how to set it up

## The idea

- **Which events are car shows:** an event is a car show when its **Category** is *Car Show* **and** its **Organizer** is *Space City Car Club*. Every other event stays exactly as MagePeople shows it.
- **On a car show page:**
  - MagePeople's ticket box is replaced by our **Car Registration** form.
  - The seat box shows **Cars registered** instead of Total/Available Seats.
- **The form:**
  - Owner details are entered once. Logged-in users get them pre-filled from their account, WooCommerce billing or PMPro billing.
  - Cars come next: **Add another car** adds up to 10.
  - **Prices come from the event's own tickets** (MagePeople → Ticket & Pricing). Each ticket type is a registration option, for example "Early Bird" $30 with a sale end date, and "Regular" $35.
  - The visitor picks from the tickets on sale right now, and the total is cars × that ticket's price. The server enforces it.
- **After payment:**
  - Every car becomes a **Registered Car** record with its number for that show (1, 2, 3… in payment order). Numbers are never reused.
  - The owner gets a confirmation email with the car numbers, and staff get a copy.
- **Vendors:** if the show accepts vendors, a **Vendors wanted** card on the event page links to the vendor application with that show pre-selected. Vendors never register a car.

## Where things live

| What | Where |
|---|---|
| Prices, early bird, number of cars | Event → **Ticket & Pricing** (MagePeople's own tickets) |
| Vendor settings for the show | Event → **Club Show** step (Modern editor) or **Club Show** box (Classic editor) |
| Staff email, email note, closed/full messages | Theme Settings → **Car Show Settings** |
| Form status and "Sync car registration form from code" | Top of Car Show Settings |
| Registered cars (filter by show, CSV export, add by hand) | wp-admin → **Registered Cars** |

## Prices and early bird (Ticket & Pricing)

- **One registration option per ticket type.**
  - Name: shown to the visitor, for example "Early Bird", "Regular" or "Club Member".
  - Price: the price per car.
  - Quantity: how many cars this option allows. 0 means no limit.
  - Sale end date: when this option stops being offered. Use it for early bird. Once it passes, the next ticket (e.g. Regular) is the only choice.
- **Hiding an option:** disable its ticket type.
- **Counting:** cars are counted from Registered Cars, per ticket type, not from MagePeople's seat counters.
- **Closing:** registration closes when the show starts, or when every ticket has ended or is full.

## Registered Cars (admin)

- **Filtering:** filter by show. The list then sorts by car number and offers **Export CSV**.
- **Walk-ups:** **Add car** creates a car by hand. Choose the show, fill in the details and save; it gets the next number automatically.
- **Cancelling:** trash the car. It stops counting as registered, and its number is not reused.
- **Links:** each car links back to its Gravity Forms entry, where the payment details are.

## Gravity Form (built in code)

`app/Support/CarShows/CarRegistrationForm.php` creates the **Car Registration** form (CSS class `sccc-car-registration`). Once Gravity Forms Stripe is active, it also adds the Stripe card field and feed.

The code is the source of truth. To change wording, edit the file and bump `SCHEMA_VERSION`, or click **Sync car registration form from code**.

## Testing locally

Put Stripe in test mode and use card `4242 4242 4242 4242` with any future date and any CVC.

1. Clear caches: `wp acorn optimize:clear` (and `wp acorn acf:clear` if fields don't appear).
2. Open Theme Settings → Car Show Settings and check the status box.
3. On the test event:
   - set Category **Car Show** and Organizer **Space City Car Club**;
   - in **Ticket & Pricing**, add "Early Bird" $30 (sale end date next week) and "Regular" $35, then save.
4. Open the event page:
   - Total/Available Seats should now read **Cars registered: 0**;
   - the ticket box should be the Car Registration form.
5. While logged in, check that your name and email are pre-filled.
6. Choose Early Bird, add 2 cars and check that the total is $60. Pay.
7. Check the confirmation (cars #1 and #2), the owner email, the staff email, and **Registered Cars**.
8. Register 1 more car and check that it gets #3.
9. Set the Early Bird sale end date to yesterday. Only Regular should be offered.
