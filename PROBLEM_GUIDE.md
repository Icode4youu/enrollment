# Database Problem-Solving Guide — SEAIT Online Enrollment System
**IT321 Advanced Database Systems — Final Presentation**
Pair: _______________ · Block: BSIT/BA – 3

---

## Use Case & Workflow (Rubric II.4 — 8 pts)

### Actors

| Actor | Role |
|-------|------|
| **Student (Applicant)** | Registers, submits pre-registration, uploads documents, pays via GCash, requests a class block |
| **Registrar (Admin)** | Reviews documents, verifies/rejects applications, marks applicants Enrolled |
| **Clinic** | Approves/rejects the medical form document |
| **Accounting** | Confirms/rejects the GCash payment |
| **Department** | Builds block schedules, assigns/approves student block requests |

### Main workflow — New student enrollment

1. **Register** — `register.php` creates a `users` row (`role='student'`). Email is UNIQUE.
2. **Pre-registration** — `preregistration.php` inserts an `applicants` row (`status='pending'`). Constraint: only ONE active application per account — enforced by a query checking `status NOT IN ('rejected','withdrawn')` before INSERT.
3. **Confirm** — `confirm.php` → `status='requirements'`, `current_step=1`.
4. **Registrar docs** — `step_registrar.php` — student uploads required docs → `documents` rows (`status='pending'`). Registrar approves/rejects each in `admin/view.php` (rejected docs must be re-uploaded).
5. **Medical** — `step_medical.php` — medical form upload → Clinic approves/rejects.
6. **Accounting** — `step_accounting.php` — GCash ref no + receipt → `payments` row (`status='pending'`) → `status='payment'`.
7. **School ID** — `step_id.php` — student number, emergency contact, and digital signature (canvas → PNG file) → `student_ids` row → `status='blocking'`.
8. **Blocking** — `step_blocks.php` / `schedules.php` — student browses block timetables and requests one → `requested_block_id` set, reason required for change requests.
9. **Department approval** — `admin/view.php` — approve (transaction: lock block `FOR UPDATE`, recheck capacity, `block_id` set, `status='submitted'`) or decline (request cleared, student picks again).
10. **Department decision** — `admin/view.php` — Department Head sets `status` to `enrolled` / `rejected` / back to `submitted`. `enrolled` is gated: all required docs approved + payment confirmed + block assigned, else refused. All actions write to `activity_log` (audit trail).
11. **COR** — `enrollment_slip.php` — Certificate of Registration with timetable grid.

### Status state machine

```
pending → requirements → payment → blocking → submitted → verified → enrolled
   (rejected / withdrawn are terminal side-states; withdrawn frees the slot)
```

### Database operations per step

| Step | Tables touched | DB mechanisms involved |
|------|----------------|------------------------|
| Register | `users` | UNIQUE constraint on email, bcrypt hashing |
| Apply | `applicants` | ENUM status, FK to users, duplicate-application guard |
| Uploads | `documents` | FK cascade, latest-row-per-type subquery |
| Payment | `payments` | ref_no index, status ENUM |
| Blocking | `blocks`, `applicants` | **Transaction: FOR UPDATE lock + capacity recount + COMMIT/ROLLBACK** |
| Scheduling | `schedules`, `teachers` | Conflict checks (teacher/room overlap queries), UNIQUE teacher names |
| Review | `documents`, `payments`, `applicants`, `activity_log` | Multi-table consistency, audit log |
| Reports | all | Aggregate GROUP BY queries, CSV export |

---

## Problems 1–20

> For each problem: **Applicability** (applies / partially / N/A) → **Concept** → **Solution in our system** → **Test & evidence**.
> If a problem does not apply to our workflow, we mark it **N/A**, justify why, and describe the equivalent scenario or design.

| # | Applicability | Concept(s) | Solution / Design | Test & Evidence |
|---|---------------|------------|-------------------|-----------------|
| 1 | Partially | Transactions, atomicity | Our enrollment is per-**block**, not per-subject, so there is no multi-subject batch to fail mid-way. The equivalent multi-step operation is **block assignment**: `begin_transaction → SELECT blocks FOR UPDATE → COUNT(*) capacity recount → UPDATE applicants → COMMIT`; any failure throws → `ROLLBACK`, leaving no partial state (`admin/view.php:48-70`). | Force a full-block assignment → exception → rollback; `block_id` stays NULL. Screenshot + code. |
| 2 | Applies | Concurrency control, row locking | Two Department staff approving the last slot of the same block: `SELECT ... FOR UPDATE` locks the block row; the first txn commits, the second waits, then recounts `COUNT(*)` and throws "already full" → rollback. Loser gets an error, not a slot. | Two concurrent mysql sessions / curl POSTs on the same last slot → one commits, one fails. SQL output. |
| 3 | Applies | Constraints, uniqueness | One active application per user enforced by a pre-INSERT check (`status NOT IN ('rejected','withdrawn')`); a student can only occupy one block because `applicants.block_id` is a single column. Simultaneous submits hit the same guard inside the locked read. **Honest gap:** the guard is application-level; a UNIQUE index would be stronger (e.g., a generated "active flag" column + UNIQUE on `(user_id, flag)`). | Submit two applications back-to-back → second blocked. Show the check + result. |
| 4 | Applies | Derived data, consistency | Enrolled count is **never stored** — always computed by `COUNT(*) WHERE block_id=? AND status NOT IN ('withdrawn','rejected')` inside the locked transaction, so the counter can't drift from reality. `blocks.capacity` is the only stored limit. | Fill a block to capacity → next assign rejected; withdraw one → slot frees (COUNT drops). |
| 5 | Partially | Concurrency, lost updates | Offices touch different columns (docs / medical / payments / block), so true same-field collisions are rare. For the contested write — block assignment — `FOR UPDATE` serializes it. **Honest gap:** we use last-write-wins for independent fields; no optimistic `updated_at` version check. Proposed: compare `updated_at` on save and reject stale writes. | Two staff act on one applicant in different offices → both logged in `activity_log`, no clobbering. |
| 6 | Applies | Transactions, consistency | Withdraw = single atomic UPDATE: `status='withdrawn'`. The slot frees automatically because capacity COUNT excludes `withdrawn`. No counter to desync. | Withdraw an assigned applicant → block count drops by one; re-request works. |
| 7 | Partially (natural N/A) | Deadlock detection | Our only lock is a **single row** (`blocks.id`), so a circular wait can't arise in normal use — honest N/A for the natural case. InnoDB would still detect one (`ERROR 1213`, rolls back victim txn). | Controlled demo: two sessions `UPDATE applicants` on two rows in opposite order → InnoDB kills one. `SHOW ENGINE INNODB STATUS`. |
| 8 | Applies | Indexing, query opt. | Queue/dashboards filter by `status`, `course`, name search. Indexed: `idx_status`, `idx_req_block`, PK/`user_id` FK. Proposed extra: `INDEX(last_name)`, `INDEX(course)` — justify with EXPLAIN. | `EXPLAIN SELECT ... WHERE status='blocking'` → uses idx_status; before/after timing. |
| 9 | Partially | Indexing | Same pattern at smaller scale: history queries hit `applicants.user_id`, `documents.applicant_id`, `activity_log.applicant_id`, `payments.applicant_id` — all FK-indexed. JOIN+WHERE optimized by covering the filter column. | `EXPLAIN` on applicant history join → shows index usage instead of full scan. |
| 10 | Applies | Concurrency, locking | Same mechanism as #2: all assignments to a block serialize on its row lock; capacity is re-checked post-lock, so N simultaneous approvals can't overshoot 40. | Script N parallel assignment requests on one slot-remaining block → exactly one succeeds. |
| 11 | Applies | Constraints, integrity | `NOT NULL`, ENUMs (`status`, `sex`, doc/payment status), FKs (`applicants.user_id`, `documents.applicant_id`, `schedules.block_id`, `payments.applicant_id`, `applicants.requested_block_id`) with CASCADE/SET NULL, UNIQUE (`users.email`, `teachers.name`, `admins.username`). **Honest gap:** `applicants.block_id` has no FK — proposed fix: `ADD CONSTRAINT fk_app_block FOREIGN KEY (block_id) REFERENCES blocks(id)`. | Try INSERT with bad applicant_id / bad status value → rejected with error. Screenshot. |
| 12 | Mostly N/A | Preconditions, integrity | Our "prerequisites" are **document + payment + block requirements**, not academic course prerequisites: `Mark Enrolled` is blocked unless all `required_docs(type)` are approved, payment confirmed, and a block assigned (`admin/view.php:90-101`). Proposed if asked: `subjects` + `subject_prereq` + `student_grades` tables checked in the same gate. | Mark Enrolled with missing docs → refused, missing items listed. Screenshot. |
| 13 | N/A | Validation | Students never select subjects — the Department assigns a fixed-load block, so unit overload can't occur. Equivalent guard = block capacity check. Proposed design if needed: `units` column on `schedules`, `SUM(units)` validated against a max before assignment. | Explain workflow; show that the request form has no subject picker. |
| 14 | Applies | Load tracking, conflict detection | We do manage teachers: `teachers` table + `instructor` column on `schedules`; `includes/scheduler.php` computes per-teacher weekly load, caps at **18 h**, prefers one teacher for both sessions of a subject, and falls back to TBA if none fits. Load recomputed live — no stored counter to drift. | Generate a schedule → query `SUM(time)` per instructor; show nobody exceeds cap; manual assignment in `block_sched.php`. |
| 15 | Partially | Overlap detection | Student-side conflicts can't occur (no self-scheduling). The scheduler prevents the real conflicts: `time_start < ? AND time_end > ?` per day for **teacher** and **room** before inserting any meeting. | Try adding a meeting overlapping an existing teacher/room → rejected with the clash named. |
| 16 | Applies | COMMIT/ROLLBACK | Same txn as #1: lock → verify → update → commit; exception → rollback restores pre-state. This is the demonstrated all-or-nothing path. | Fail mid-transaction (full block) → `block_id` unchanged, error shown. Code + output. |
| 17 | Applies (concept) | Recovery, durability | InnoDB redo log: committed txns survive a crash; uncommitted are rolled back on restart — we can kill mysqld mid-transaction to show it. Backups = `mysqldump seait_enrollment > backup.sql` export + `database.sql`/`migrate_v*.sql` replay for rebuild. **Honest gap:** no automated/scheduled backup job — propose nightly cron `mysqldump`. | Restart test + dump/restore demo; show `INNODB` recovery in error log. |
| 18 | Applies | Security, authorization | Six roles (`student`,`registrar`,`medical`,`accounting`,`department`,`admin`); server-side gates `require_office()`/`admin_can()` + per-document office mapping; decision restricted to `admin_role()==='department'`; nav hides unauthorized pages; bcrypt `password_hash`/`password_verify`; prepared statements; upload type/size checks. **Honest gap:** app uses one MySQL user — propose per-role DB accounts with `GRANT SELECT/UPDATE` only on needed tables. | Medical login → `blocks.php` = 403; dept → `settings.php` = 403; student → `/admin` = redirect. Tested already. |
| 19 | Applies | Indexing, concurrency, throughput | Hot spots on opening day: office queue lists (status-filtered → `idx_status`), capacity COUNTs (FK-indexed), payment lookup (`idx_ref`). Lock scope is one row for milliseconds, so throughput isn't serialized globally. Proposed scaling: connection reuse, `LIMIT`ed queues (already), covering index `(status, course)` for the biggest filter. | `EXPLAIN` hot queries; concurrent-curl timing on queue pages. |
| 20 | Applies | All of the above | Synthesis: ENUMs+FKs+NOT NULL keep records valid; one active application per user stops duplicates; `FOR UPDATE` + live `COUNT(*)` stops over-assignment; single-row locks avoid deadlock cycles (InnoDB kills any that occur); `idx_status`/FK indexes keep queues fast; bcrypt+RBAC+prepared statements keep it secure; InnoDB redo + mysqldump keep it recoverable. | Re-run all evidence tests on opening-day scale data; table of results. |

---

## Testing Plan & Evidence (Rubric II.5 — 10 pts)

| Test | Scenario | Expected | Actual | Evidence |
|------|----------|----------|--------|----------|
| Normal enrollment | Complete flow end-to-end | status → enrolled, COR shows block | | screenshot |
| Duplicate application | File 2nd application while one is active | Blocked with message | | |
| Rejected doc | Registrar rejects PSA | Student sees rejection + reason, can re-upload | | |
| Re-upload | Student re-uploads rejected doc | Status back to submitted, old row replaced | | |
| Payment reject | Accounting rejects receipt | Student re-uploads, payment resets to pending | | |
| Block full | Request block at capacity | Rejected "already full" | | |
| Last-slot race | 2 admins approve same last slot simultaneously | One commits, one rolls back | | SQL output |
| Change block | Enrolled/submitted student requests change | Pending approval; keeps current block until approved | | |
| Withdraw | Student withdraws | status=withdrawn, block freed, slot released | | |
| Schedule conflict | Add teacher at overlapping time | Conflict error naming the clash | | |
| Room conflict | Book same room+time twice | Conflict error | | |
| Off-window | Apply outside enrollment dates | Blocked | | |

---

## Concept Coverage Checklist (Rubric II.2 — 10 pts)

| Concept | Where it lives in our system | Status |
|---------|------------------------------|--------|
| Transactions (COMMIT/ROLLBACK) | `admin/view.php` assign_block: `begin_transaction → FOR UPDATE → recount → UPDATE → commit/rollback` | ✅ |
| Atomicity | Block assignment is all-or-nothing | ✅ |
| Consistency | FKs, ENUMs, UNIQUE(email, teacher name), capacity invariant | ✅ |
| Isolation | `SELECT ... FOR UPDATE` serializes last-slot assignments | ✅ |
| Durability | InnoDB committed writes survive restart | ✅ |
| Concurrency control | Row lock on block; request/approve two-phase for student picks | ✅ |
| Deadlocks | N/A — single-row lock only (explain why) | |
| Constraints | PKs, FKs w/ CASCADE/SET NULL, ENUMs, UNIQUE, NOT NULL | ✅ |
| Indexing | `idx_status`, `idx_ref`, `idx_req_block`, PK/FK indexes — explain column choices | ✅ |
| Optimization | Latest-doc-per-type subquery, `INSERT ... ON DUPLICATE KEY`, `INSERT IGNORE` | ✅ |
| Security | bcrypt hashes, session guards (`require_user`/`require_admin`), prepared statements, upload type/size validation | ✅ |
| Recovery | `database.sql` + `migrate_v*.sql` replay; backup via mysqldump (describe) | |

*Checklist maps each rubric concept to a concrete artifact — use it to verify coverage once the 20 problems are filled in.*
