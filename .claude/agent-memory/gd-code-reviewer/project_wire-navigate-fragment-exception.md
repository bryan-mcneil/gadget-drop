---
name: wire-navigate-fragment-exception
description: Internal public links that target a #fragment intentionally omit wire:navigate — don't re-flag; candidate for CLAUDE.md promotion
metadata:
  type: project
---

Internal public links whose href targets a `#fragment` on another page (e.g. the Truth Report methodology box linking `/how-we-review#deal-verdicts`, plan 05 §5.2) intentionally OMIT `wire:navigate` and use plain native navigation.

**Why:** wire:navigate morphs the body but fragment/anchor scrolling after the swap is unreliable; native navigation reliably scrolls to the anchor. Implementer flagged this deliberately (view comment + session contract).

**How to apply:** CLAUDE.md's "every internal public link carries wire:navigate" has fragment-targeting links as an unwritten sanctioned exception (alongside the listed hard exclusions: /out, /admin, external/mailto, /tools/*). Do NOT raise a BLOCKER/WARN when a `#anchor` link lacks wire:navigate AND the omission is commented. This is a promotion candidate for CLAUDE.md — suggest, don't edit. See also [[raw-amazon-blocker-scope]] for other calibration notes on this surface.
