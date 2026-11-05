# SEAIT Online Enrollment System — Use Case Matrix

Six actors: **Student**, **Registrar**, **Medical (Clinic)**, **Accounting**, **Department Head**, **Admin**.
Each office owns exactly one stage of the pipeline — a student can only be *Enrolled* when all offices have signed off.

## Matrix

| # | Use Case | Student | Registrar | Medical | Accounting | Department | Admin |
|---|----------|:-------:|:---------:|:-------:|:----------:|:----------:|:-----:|
| 1 | Register / Login / Logout | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 2 | Reset password (forgot password) | ✔ | — | — | — | — | — |
| 3 | Submit pre-registration / application form | ✔ | — | — | — | — | — |
| 4 | Upload registrar documents (PSA, Report Card, 2×2, School ID, Clearance) | ✔ | — | — | — | — | — |
| 5 | Upload medical form | ✔ | — | — | — | — | — |
| 6 | Pay enrollment fee via GCash (receipt + ref no.) | ✔ | — | — | — | — | — |
| 7 | Track application status + activity timeline | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 8 | View announcements | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| 9 | Browse class schedules (timetable grid) | ✔ | — | — | — | ✔ | ✔ |
| 10 | Request block / block change (with reason) | ✔ | — | — | — | — | — |
| 11 | View my schedule | ✔ | — | — | — | — | — |
| 12 | Download / print enrollment slip | ✔ | — | — | — | — | — |
| 13 | Withdraw application | ✔ | — | — | — | — | — |
| 14 | Manage account settings | ✔ | — | — | — | — | — |
| 15 | View office queue dashboard | — | ✔ docs | ✔ forms | ✔ payments | ✔ blocks + decisions | ✔ all |
| 16 | Approve / reject registrar documents (with remarks) | — | ✔ | — | — | — | — |
| 17 | Approve / reject medical forms | — | — | ✔ | — | — | — |
| 18 | Confirm / reject payments | — | — | — | ✔ | — | — |
| 19 | Assign / reassign class blocks | — | — | — | — | ✔ | — |
| 20 | Approve / decline block change requests | — | — | — | — | ✔ | — |
| 21 | Decide application (Enrolled / Rejected / Back to Review) | — | — | — | — | ✔ | — |
| 22 | Manage blocks (add / edit / capacity) | — | — | — | — | ✔ | — |
| 23 | Manage block schedules (add / remove / randomize) | — | — | — | — | ✔ | — |
| 24 | Manage teachers + teaching load | — | — | — | — | ✔ | — |
| 25 | Post / delete announcements | — | ✔ | — | — | — | ✔ |
| 26 | View reports / export CSV | — | ✔ | — | — | — | ✔ |
| 27 | Manage user accounts (create / reset / delete) | — | — | — | — | — | ✔ |
| 28 | Configure enrollment period & school year | — | — | — | — | — | ✔ |

## Office responsibilities

| Office | Owns | Dashboard queue shows |
|--------|------|----------------------|
| **Registrar** | Registrar document review, announcements, reports | Applicants with pending registrar docs |
| **Medical** | Medical form approval | Applicants with a pending medical form |
| **Accounting** | GCash payment confirmation | Applicants with a pending payment |
| **Department** | Blocks, schedules, teachers, block requests, final decision | `blocking` + `submitted` + `verified` + change requests |
| **Admin** | User accounts, enrollment settings | All applications |

## Enrollment decision chain (preconditions)

`Mark Enrolled` is enabled only when **all** of these hold:

1. Every required document is `approved` (Registrar + Medical offices)
2. Latest payment is `confirmed` (Accounting)
3. A class block is assigned (Department, transaction + `FOR UPDATE` + capacity check)

Every use case action is written to `activity_log` (audit trail, shown to the student as a timeline).
