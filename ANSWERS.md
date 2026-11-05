# Database Problem-Solving Guide — Answers
**SEAIT Online Enrollment System · IT321 Advanced Database Systems**

Our system enrolls students into a **block** (a section with a fixed schedule), not into
individual subjects. Workflow: apply → upload docs → medical → GCash payment → school ID →
request a block → Department approves → enrolled.

---

## Problem 1 — Incomplete Enrollment Transaction
**Partially applies.** We don't enroll per-subject, so there's no batch to fail halfway.
Our equivalent is block assignment (`admin/view.php`): it runs inside one transaction —
lock block, recount capacity, update the student. If anything fails, `ROLLBACK` undoes
everything; the student's `block_id` stays NULL.
**Show:** assign to a full block → error, nothing saved.

## Problem 2 — Last Available Slot
**Applies.** `SELECT ... FOR UPDATE` locks the block row. The first staff member commits;
the second waits, recounts, sees it's full, and gets rejected — nobody is over-enrolled.
**Show:** two sessions approve the last slot → one succeeds, one fails.

## Problem 3 — Duplicate Enrollment
**Applies.** Only one active application per account (checked before insert), and
`block_id` is a single column so a student can only be in one block. `users.email` is
UNIQUE. Honest gap: the app-level check could race, so we propose a DB-level UNIQUE
constraint as the stronger fix.
**Show:** file a second application → blocked.

## Problem 4 — Incorrect Section Capacity
**Applies.** We never store a count — it's always a live `COUNT(*)` of active applicants,
done inside the lock. The count can't drift, and withdrawing a student frees the slot
automatically. `blocks.capacity` is the only stored number.
**Show:** fill a block → next assign rejected; withdraw one → slot opens.

## Problem 5 — Simultaneous Student Updates
**Partially applies.** Each office edits different fields (Registrar=docs, Accounting=
payment, Clinic=medical, Department=block), so collisions are rare. The one contested
write — block assignment — is serialized by the row lock. Honest gap: no version check
on `updated_at`; we propose adding one (optimistic locking).
**Show:** two staff act on the same student → both logged in `activity_log`.

## Problem 6 — Enrollment Cancellation
**Applies.** Withdraw is a single UPDATE (`status='withdrawn'`, `block_id=NULL`) — atomic
by nature. The slot frees itself because the capacity count excludes `withdrawn`.
**Show:** withdraw → block count drops by one.

## Problem 7 — Database Deadlock
**Partially applies (natural N/A).** We only ever lock one row at a time, so a circular
wait can't happen in our system. InnoDB would still catch one (ERROR 1213, kills one
transaction). Prevention: keep transactions short, lock in a consistent order.
**Show:** controlled demo — two sessions update two rows in opposite order → InnoDB
detects the deadlock; `SHOW ENGINE INNODB STATUS`.

## Problem 8 — Student Search Performance
**Applies.** Staff search by name/email and filter by `status`/`course`. `status` is
already indexed (`idx_status`) so office queues are fast. Honest point: `LIKE '%name%'`
can't use a normal index — fix is prefix search or a FULLTEXT index.
**Show:** `EXPLAIN` a queue query → uses `idx_status`, not a full scan.

## Problem 9 — Enrollment History Performance
**Applies.** A student's history pulls `documents`, `payments`, `activity_log`,
`student_ids` — all joined on `applicant_id`, which is already an indexed foreign key
in each table. Queries only fetch the latest rows, not full scans.
**Show:** `EXPLAIN` on the applicant detail join → index lookups.

## Problem 10 — Concurrent Subject Registration
**Applies.** Same mechanism as Problem 2: everyone targeting one block queues on its row
lock, and capacity is re-checked after acquiring it — so N simultaneous approvals can't
exceed 40 slots.
**Show:** fire several parallel approvals at a 1-slot block → exactly one wins.

## Problem 11 — Invalid Enrollment Data
**Applies.** The schema rejects bad data: `NOT NULL` on required fields, `ENUM`s for
status/sex/day/role, `FOREIGN KEY`s so a document can't point to a missing applicant,
`UNIQUE` on email/teacher/username. (`applicants.block_id` FK + index added in
`migrate_v11.sql`.)
**Show:** insert with a bad applicant_id or bad status → SQL error.

## Problem 12 — Prerequisite Checking
**Mostly N/A.** We don't have subject prerequisites because students don't pick subjects.
Our equivalent is the "Mark Enrolled" gate: enrollment is refused unless all required
documents are approved, payment is confirmed, and a block is assigned — the same
"check preconditions before the write" pattern. If asked to extend: add
`subject_prereq` + `student_grades` tables checked in the same gate.
**Show:** mark enrolled with a missing doc → refused, missing item named.

## Problem 13 — Maximum Academic Load
**N/A.** Students can't overload — the Department assigns a fixed-load block; there is
no subject picker in the system. Our equivalent numeric limit is block capacity (40),
enforced inside the locked transaction.
**Show:** the request UI has no subject selection.

## Problem 14 — Faculty Teaching Load
**Applies.** `schedules.instructor` links teachers to meetings. The scheduler sums each
teacher's weekly minutes live and caps at 18 hours; it also refuses to double-book a
teacher in overlapping slots.
**Show:** `SUM` query per instructor → nobody over 18h.

## Problem 15 — Conflicting Class Schedules
**Partially applies.** Students can't create conflicts (no self-scheduling). The real
conflicts — teacher and room double-booking — are prevented by an overlap check:
`existing.start < new.end AND existing.end > new.start` on the same day.
**Show:** try to schedule a teacher at an overlapping time → rejected.

## Problem 16 — Transaction Failure During Enrollment
**Applies.** Same transaction as Problem 1: lock + capacity check + update belong
together. One failure → `ROLLBACK` → zero partial records. `COMMIT` only runs when all
checks pass.
**Show:** fail mid-transaction → student unchanged.

## Problem 17 — Database Recovery
**Applies.** InnoDB rolls back uncommitted transactions on crash and replays committed
ones from the redo log — durability. Backups are automated: `backup.sh` produces a
timestamped, gzip-compressed `mysqldump` and keeps the last 7 dumps in `backups/`;
scheduling it in cron (`0 2 * * *`) gives a nightly backup. Rebuild path: restore a
dump or replay `database.sql` + all `migrate_v*.sql`.
**Show:** `backups/seait_enrollment_*.sql.gz` exists (real dump produced); dump +
restore demo; InnoDB recovery in the error log.

## Problem 18 — Unauthorized Database Access
**Applies.** Six roles (student, registrar, medical, accounting, department, admin), each
restricted to its own pages and data — server-side `require_office()`/`admin_can()`
checks return 403; students only ever see their own application. Passwords are bcrypt,
queries use prepared statements, uploads are type/size-checked, `activity_log` audits
staff actions. Honest gap: one shared MySQL account — propose per-role DB users.
**Show:** medical login → schedules page = 403; student → /admin = redirect.

## Problem 19 — Heavy Enrollment Traffic
**Applies.** Hot spots: status-filtered queues (`idx_status`), capacity counts
(`idx_block`), payment lookups (`idx_ref`), and the combined `status + course` filter
used by the admin dashboard (`idx_status_course`). `migrate_v12.sql` also indexes the
queue subqueries: `documents(status, doc_type)` and `payments(status)`. The lock covers
one row for milliseconds, so different blocks process in parallel — only same-block
requests serialize, which is what correctness requires.
**Show:** EXPLAIN on 30k applicants — `status='enrolled' AND course='BSIT'` drops from
14,898 rows scanned (`idx_status` only) to 1,998 with `idx_status_course` (covering);
documents queue subquery goes from full scan (102) to 3 rows (`idx_status_doctype`).

## Problem 20 — Complete Enrollment-Day Challenge
**Applies — summary of everything.** Consistency: ENUMs + FKs + NOT NULL + requirement
gate. Concurrency: `FOR UPDATE` serializes contested writes. ACID: all-or-nothing
assignments, committed data survives crashes. Duplicates/over-capacity: one active
application per user + recount under lock. Deadlocks: impossible by design, caught by
InnoDB if they occur. Indexes: `status`, `status+course`, `block_id`, `ref_no`,
`documents(status,doc_type)`, `payments(status)`, all FK columns. Recovery: automated
`backup.sh` dumps + InnoDB redo. Performance: derived counts on indexed columns,
`EXPLAIN` verified on 30k rows.
**Show:** the full test matrix — every test with expected vs. actual result, run on the
scale dataset (30k applicants / 900 blocks / 14k schedules).

---

## Honest gaps to mention if asked
- Duplicate-application guard is app-level → propose DB UNIQUE.
- Mark-Enrolled does several writes → propose wrapping in a transaction.
- `LIKE '%x%'` search can't use an index → propose FULLTEXT.
- One MySQL `root` account for the app → propose per-role DB users.
