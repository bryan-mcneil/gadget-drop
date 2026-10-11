---
name: raw-ip-precedent
description: Raw ip_address on form-submission PII tables is a confirmed-safe in-repo pattern — don't re-flag as a PII violation
metadata:
  type: project
---

Storing a **raw** `ip_address` (`request()->ip()`) on user-form-submission tables is an established, confirmed-safe pattern in this repo. Do not flag it as a PII violation when a plan specifies it.

**Why:** The reviewer heuristic "raw IPs are suspect — the codebase hashes identity" is only half true. The repo does BOTH: it hashes in analytics contexts (`PublicController` `ip_hash = hash('sha256', ip)`), but stores raw `ip_address` on every form-submission model — `Subscriber` (fillable `['email','token','ip_address']`, the direct analog to `PriceWatch`), `JoinTheDrop`, `DropPrice` plays, `SubscriberController`. The distinguishing factor is purpose: abuse forensics on a submission row = raw + pruned with the row; cross-request analytics = hashed.

**How to apply:** When a new form-backed table (magic-link email capture, watch signup, etc.) stores raw `ip_address` for abuse forensics AND the plan calls for it AND it's nullable + never rendered + pruned, that's consistent with the `Subscriber` precedent — affirm, don't flag. Reserve the hash-it push for IPs used as long-lived cross-request identifiers. First hit: Plan 03 §3.1 `price_watches.ip_address` (correctly raw). Related: [[fillable-state-columns]].
