---
name: build-revert-verification
description: How to review a phase that reverts public/build churn — grep the committed CSS instead of blocking on the missing rebuild
metadata:
  type: project
---

When an implementer edits Blade/CSS classes but **deliberately reverts the `public/build` rebuild** (citing Vite's non-deterministic full-bundle content-hash rehash on unchanged admin source — a real local toolchain drift here), do NOT auto-flag the missing build as a blocker. Verify instead.

**Why:** `bin/deploy.sh` does NOT run `npm run build` (it relies on a committed `public/build`; CLAUDE.md "Pre-build assets locally"). So new utility classes only reach prod if they're in the committed bundle. But Tailwind classes are frequently **already present** from other components, in which case a reverted build is fully prod-safe for that phase.

**How to apply:** grep the committed CSS for the *compiled declaration*, not the source class:
`grep -o "display:inline-block\|white-space:nowrap\|font-size:10px" public/build/assets/*.css`
(there are two committed CSS bundles — check both). If every declaration the diff introduces is already present → the reverted build is safe *for this phase*; downgrade to a WARN that says "verified safe, but the commit-the-build convention is deferred and this safety must NOT be assumed for later phases that add genuinely new classes." If a declaration is missing AND the class is actually rendered this phase → real prod risk, escalate. Confirmed safe on Plan 01 Phase 1.1 (verdict-badge: inline-block/whitespace-nowrap/font-size:10px/11px all pre-existing). See [[ci-guardrail-pint]] for the sibling "don't over-flag a known-safe deviation" calibration.
