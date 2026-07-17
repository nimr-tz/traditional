# Seeded test accounts (TMSC)

Generated from `database/seeders/TmscSeeder.php` — run `php artisan db:seed --class=TmscSeeder`
to (re)create this data. Safe to re-run; it's idempotent (matches on email / abstract title).

**Every account's password is `password`.**

## Users by role

| Role | Name | Email | Payment status |
|---|---|---|---|
| super_admin | TMSC Administrator | admin@tmsc.nimr.or.tz | verified |
| admin | TMSC Admin | admin2@tmsc.nimr.or.tz | verified |
| reviewer | Amina Reviewer | reviewer1@tmsc.nimr.or.tz | verified |
| reviewer | Baraka Reviewer | reviewer2@tmsc.nimr.or.tz | verified |
| reviewer | Consolata Mushi | reviewer3@tmsc.nimr.or.tz | verified |
| reviewer | Daudi Kessy | reviewer4@tmsc.nimr.or.tz | verified |
| reviewer | Esther Mwakalinga | reviewer5@tmsc.nimr.or.tz | verified |
| reviewer | Frank Sanga | reviewer6@tmsc.nimr.or.tz | verified |
| staff | TMSC Check-in Staff | staff@tmsc.nimr.or.tz | verified |
| user | Test Participant | participant@tmsc.nimr.or.tz | pending |
| user | Grace Mwambene | author1@tmsc.nimr.or.tz | pending |
| user | Hamisi Juma | author2@tmsc.nimr.or.tz | verified |
| user | Irene Kileo | author3@tmsc.nimr.or.tz | verified |
| user | John Mgaya | author4@tmsc.nimr.or.tz | verified |
| user | Khadija Salum | author5@tmsc.nimr.or.tz | pending |
| user | Lucas Mrema | author6@tmsc.nimr.or.tz | verified |
| user | Mary Chuma | author7@tmsc.nimr.or.tz | verified |
| user | Nasra Idi | author8@tmsc.nimr.or.tz | verified |
| user | Omary Kalulu | author9@tmsc.nimr.or.tz | pending |
| user | Pendo Massawe | author10@tmsc.nimr.or.tz | verified |
| user | Qamar Athumani | author11@tmsc.nimr.or.tz | verified |
| user | Rehema Ngowi | author12@tmsc.nimr.or.tz | verified |
| user | Salum Bakari | author13@tmsc.nimr.or.tz | pending |
| user | Tumaini Shirima | author14@tmsc.nimr.or.tz | verified |
| user | Upendo Msigwa | author15@tmsc.nimr.or.tz | verified |
| user | Victor Nyoni | author16@tmsc.nimr.or.tz | verified |
| user | Winnie Kimaro | author17@tmsc.nimr.or.tz | pending |
| user | Yusuf Rashidi | author18@tmsc.nimr.or.tz | verified |
| user | Zainab Hassan | author19@tmsc.nimr.or.tz | verified |
| user | Elias Mwanri | author20@tmsc.nimr.or.tz | verified |

30 accounts total: 1 super_admin, 1 admin, 6 reviewers, 1 staff, 21 registrants/authors
(`participant@tmsc.nimr.or.tz` + `author1`–`author20`).

## Abstracts (43 total)

3 hand-picked examples (one per outcome — `submitted`, `accepted`, `revision_requested`) authored
by `participant@tmsc.nimr.or.tz`, plus **40 titled `TMSC Test #01`–`#40`** that deliberately walk
through every state the abstract review workflow can be in, so the two behaviours added most
recently — auto-accept on unanimous acceptance, and only re-notifying the reviewer(s) who didn't
already accept when an author resubmits — have real data to exercise.

| # range | State | What it's for |
|---|---|---|
| 01–04 | No reviewers assigned yet | Admin queue → assign two reviewers |
| 05–09 | Reviewers assigned, no recommendations yet | Reviewer logs in and submits a recommendation |
| 10–14 | One reviewer accepted, other still pending | Log in as the *other* reviewer, recommend **accept** → abstract should auto-accept immediately, no admin step |
| 15–17 | One reviewer requested revision, other pending | Log in as the other reviewer to complete the pair |
| 18–19 | One reviewer rejected, other pending | Same, with a reject in the mix |
| 20–25 | **Both reviewers already accepted → already auto-accepted** | Confirms the auto-accept path: `status=accepted`, decided by "System" (no admin), review history shows an automatic entry |
| 26–30 | Reviewers disagree (accept + revision/reject) | Still `submitted`, waiting on the admin — log in as admin2@tmsc.nimr.or.tz and make the final call |
| 31–32 | Both reviewers rejected | Also waits on the admin (rejection isn't auto-final) |
| 33–36 | **Revision requested, one reviewer had accepted** | Log in as the author (`author13`–`author16`), resubmit → only the reviewer who asked for changes should be asked to re-review; the one who already accepted keeps their decision and isn't re-notified |
| 37–38 | Revision requested, neither reviewer had accepted | Resubmitting re-opens both reviewers' recommendations |
| 39 | Accepted by admin override despite one rejection | Shows the admin can still override reviewer disagreement |
| 40 | Rejected (final) | Terminal state, no further action possible |

Reviewer/author assignment cycles through the 6 reviewers and 20 authors, so no single account is
overloaded. Query `AbstractSubmission::where('title', 'like', 'TMSC Test #%')` (or filter the admin
abstracts list) to see exact reviewer pairings for any given number.
