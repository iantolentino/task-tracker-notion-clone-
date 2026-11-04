# MMTS admin requests — to-do list

Source: `TICKETING.pdf` ("Multimedia and Marketing Ticketing System (MMTS): For Improvements"), 19 requests from the admins who use the system.

Each item is tagged by what it touches, because uploads to the live site are meant to change the design only:

- **Design** — page text, layout or styles only. Safe to upload over the live site.
- **Server** — PHP rules change (allowed values, checks), but no new database column.
- **Database** — needs a new column, table or a data update on the live database. Back up the live database before uploading these.

## Batch 1 — Design only (safe to upload) — done 2026-11-05, not yet uploaded

- [x] **1. Rename the system** to "Multimedia and Marketing Ticketing System" (page titles, sign-in page, browser tab names, staff portal).
- [x] **3. Add dark mode.** Theme toggle in the sidebar and top bar; remembers each person's choice in their browser.
- [x] **7. New status colors:** Not started = gray, In progress = blue, On hold = yellow, Waiting for approval = violet, For revision = red, Done = green. Applies to the Tasks dashboard, ticket list, My tickets and the database page. Violet and red are ready and will show once Batch 2 adds those two statuses.
- [x] **11. Hide "Estimated cost"** from the request form, ticket edit form, My tickets and the database page. The column stays in the database, so old values aren't lost.
- [x] **13. Hide "Business impact"** ("Why it matters") the same way.
- [x] **14. Attachments helper text:** "If you have any design references or suggestions, feel free to add them here." Keep the field optional.
- [x] **15. Change status from the ticket inbox** without opening Edit: a status dropdown on each row that saves straight away. Uses the existing save action, so no server change.
- [x] **16. Show "Ticket no."** Each ticket already has a number (`TCK-000001`). Label it "Ticket no." and show it more prominently on the inbox, My tickets and the confirmation screen.
- [x] **17. Rename "Create a creative request" → "Request Form"** (portal button, form heading, page title).
- [x] **18. Request form description:** "Complete the form and provide the details and materials needed to get started."
- [x] **19. Disclaimer at the end of the Request Form:** "Please note: Turnaround times may vary depending on the request's complexity, scope, priority, and current workload. Submission of a request does not guarantee immediate completion."

## Batch 2 — Server rules (no new columns) — done 2026-11-05, not yet uploaded

- [x] **5. Add status "Waiting for Approval".** Add it to the allowed status lists in `tickets.php`, `db-view.php` and the Tasks dashboard (status is stored as text, so no column change).
- [x] **6. Add status "For Revision".** Same as above.
- [x] **9. Remove effort level.** Tickets now move into Tasks once a priority is set (`api.php`). Effort is hidden from every screen; existing values are kept in the database (and still appear in the CSV export) in case the admins want them back.

## Batch 3 — Database changes (back up first)

- [ ] **2. Correct "Aiko" → "Akio".** The name is stored in existing tickets and is the column default (`db.php`). Needs: update the assignee lists in `ticket.php`, `tickets.php`, `db-view.php`; change the column default; and run a one-time update on the live data (`Aiko` → `Akio`, `Aiko/Ivan` → `Akio/Ivan`).
- [ ] **4. Comments on tickets** (if possible). New `ticket_comments` table (ticket, author, text, time) plus a comment box in the ticket details for admins. Decide: should requesters see or add comments on My tickets too?
- [ ] **8. Show completion date when marked Done.** Add a `completed_at` column, set it when status changes to Done/Resolved/Closed, and clear it if reopened. Older finished tickets won't have a date unless we backfill from their last-updated time.
- [ ] **10. Add "Department of Requester"** to the request form. New `department` column. Decide: free text or a fixed list of departments? Required or optional?
- [ ] **12. Optional time on the deadline.** The deadline is stored as a date only. Add an optional `due_time` column (or change to date-time) and a time picker next to the date.

## Questions for the admins

1. Department (item 10): which departments should be in the list, or should it be free text?
2. Comments (item 4): admins only, or can requesters reply too?
3. ~~Effort (item 9)~~ — hidden and old values kept; can be fully removed later if the admins prefer.
4. Should "Waiting for Approval" and "For Revision" also appear on the requester's My tickets page?
