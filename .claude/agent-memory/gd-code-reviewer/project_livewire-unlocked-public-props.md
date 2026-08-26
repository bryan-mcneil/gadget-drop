---
name: livewire-unlocked-public-props
description: "#[Locked] on the id does not protect the other public props — $phase/$result style properties are client-writable; check any that reach an href or a claim line"
metadata:
  type: project
---

The repo's Livewire security pattern is `#[Locked] public int $postId/$productId/$cycleSlug` plus "everything else is re-derived server-side". That sentence is only true for properties the component never *stores*. Any other public property (`$phase`, `$result`, `$votes`, …) is writable from the client: Livewire validates the snapshot checksum first, then applies the `updates` array, and `Livewire\Features\SupportLockedProperties\BaseLocked::update()` is the ONLY thing that throws.

**Why:** `App\Livewire\LivePriceCompare` (plan 11) carries `public array $result` holding model-authored `retailer`/`url` rows, and the view renders `href="{{ $row['url'] }}"`. `PriceComparison::normalize()` is what rejects `javascript:` — and it runs before the property is set, so a tampered `updates: {result: {...}}` reaches the anchor unfiltered. Impact is self-XSS only (own session, own response, CSRF-gated), which is why it is a WARN and not a BLOCKER, but the class docblock claiming "nothing but the product id crosses the wire" is factually wrong.

**How to apply:** for every Livewire diff, list the public properties, then ask which ones (a) are rendered into an attribute (`href`, `src`, `style`, `:class`) or (b) carry a factual claim string the honesty gates produce. Those need `#[Locked]` too — it costs nothing, since the server sets them freely and only client updates throw. Ask for a `->set('result', …)`/`->set('phase', …)` test asserting `CannotUpdateLockedPropertyException`. Related: [[plan11-price-compare-review]], [[watch-confirm-reader-surface]].
