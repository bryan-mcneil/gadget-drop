---
name: fillable-state-columns
description: Repo convention — keep verification/state stamp columns OUT of $fillable; enforce on new form-backed models (CLAUDE.md promotion candidate)
metadata:
  type: project
---

Enforce on every new form-backed model: **state-transition / verification columns must NOT be in `$fillable`.** They are set by internal flows (booted() hooks, explicit `->update()`, commands), never mass-assigned from client-adjacent data.

**Why:** The repo's own exemplar `Subscriber` codifies this — fillable is `['email','token','ip_address']` with a comment that streak counters "are intentionally NOT fillable — updated via explicit update()/increment(), never mass-assigned from client-supplied values." The sharp edge is auth-adjacent stamps: a fillable `verified_at` lets a bot self-verify by posting the field, bypassing the email round-trip (privilege escalation the moment a later phase wires `create()` from request-adjacent input).

**How to apply:** When reviewing a new model, diff its `$fillable` against `Subscriber`'s discipline. Flag (WARN) any `verified_at` / `notified_at` / `*_sent_at` / status columns in fillable; concrete fix = drop them, set via booted()/explicit update, and have tests use `forceCreate`/`forceFill` for those states. First raised: Plan 03 §3.1 `PriceWatch` fillable included `verified_at`/`notified_at`/`closing_mail_sent_at`. **If this recurs in 3.2/3.3 or another plan, suggest promoting to CLAUDE.md** (don't edit it yourself). Related: [[raw-ip-precedent]].
