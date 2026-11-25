# Audit questions

Questions from the audit of 2026-11-24 (`docs/AUDIT_2026-11-24.md`). Each has a recommended default and what the answer changes. Answer in any order; the defaults are what the roadmap assumes.

## Business profile

| # | Question | Recommended default | What the answer changes |
|---|---|---|---|
| Q1 | Is the business discrete, batch-based manufacturing (gloves, balls and similar), made both to order and to stock, at **one** plant, by **one** company? | Yes (inferred from the data model and demo data). | If several plants or companies share one installation, multi-plant data separation becomes a Critical gap; today there is none by design (one installation per company). |
| Q2 | Do you issue **quotations** before sales orders, and do quoted prices need to be tracked (won/lost, revisions)? | Yes, a simple quotation that converts to a sales order. | Adds a missing step at the start of quote-to-cash. If no, the proforma invoice printed from a draft order is enough. |
| Q3 | Do you **import** materials (customs duty, freight, clearing charges)? | Yes. | If yes, landed cost is a Needed gap: today those charges go to expenses, so imported material, PTC cost and margins are understated. |
| Q4 | Are you **sales-tax registered**, and does FBR digital invoicing apply to you? | Registered; digital invoicing applicability unknown. | If digital invoicing applies, FBR integration becomes Needed (verify against a current FBR source; rules change). |
| Q5 | Do you need **customer credit limits** (block or warn on new orders above a limit)? | Warn above limit, manager override. | Adds a control. `AGENTS.md:71` already mentions credit limits, but no such feature exists. |

## Controls

| # | Question | Recommended default | What the answer changes |
|---|---|---|---|
| Q6 | Should a user ever **approve a document they created** (for example the Super Admin)? | No: block self-approval, with an override for a single-person office. | Today nothing in code prevents it (finding F-05). |
| Q7 | Should **production supervisors see individual piece rates** (Worker Rates), or only people with `salary.view`? | Only `salary.view`, matching Wage Work. | Finding F-04 is a defect under the default, or by design if supervisors set rates. |
| Q8 | Should **contractor stage work** wait for approval like wage work, before it is owed? | Yes. | Today contractor work posts to the payable immediately (finding F-17). |
| Q9 | Should production, store and sales staff see **reports** for their own area? | Yes, their area's reports only. | Today only Manager and Accountant have `reports.view` (finding F-11). |

## Costing

| # | Question | Recommended default | What the answer changes |
|---|---|---|---|
| Q10 | How should **rejected pieces from production** be valued in stock: at zero, at an expected scrap value, or at full cost (with the loss charged to the period rather than to the good pieces)? | Zero (their cost is already inside the good pieces). | Decides the fix for finding F-01. Any choice is fine as long as stock valuation and the Inventory account agree. |
| Q11 | Should **overheads** (electricity, rent, supervision) be absorbed into product cost? | Not yet; keep direct cost and report overheads separately. | If yes, adds overhead rates and absorption (gap G-14). |

## Operations

| # | Question | Recommended default | What the answer changes |
|---|---|---|---|
| Q12 | Where should **backups** be copied off the server? | Cloud object storage (S3-compatible) in another region. | Finding F-08; needs credentials and hosting access. |
| Q13 | Which **email service** can the system use (SMTP, Mailgun, SES…)? | Your existing business email SMTP. | Needed for any alert or notification (finding F-07). |
| Q14 | Will the **shop floor** use phones or tablets, and are barcode or QR labels acceptable on PTC cards and stock? | Yes, phones with the camera as scanner. | Decides the priority of the shop-floor screen and labels (gap G-07). |
