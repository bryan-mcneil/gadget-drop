# 07 — Automated Review Videos → YouTube (drop-studio)

**Status:** planned · **Size:** L · **Depends on:** none (consumes `daily-drop/output.json`)
**Home:** new sibling project **`drop-studio`** (separate repo/folder). This doc lives here because gadget-drop is the content source and the integration contract is defined on this side.

---

## 1. Goal and strategy fit

One AI-generated **YouTube Short (45–90s, vertical)** per daily review, produced with zero manual video work, funneling viewers to gadgetdrop.tech (review + live price history) where the affiliate CTA lives. Long-form video is a later layer, not the starting point.

Why Shorts-first:

- A new channel gets discovery through the Shorts feed without needing subscribers or watch-time history.
- 45–90s is the only format that automates *well* — pacing, b-roll needs, and retention demands of 8-minute videos are an order of magnitude harder for template video.
- The video's job is **traffic + brand**, not YouTube ad revenue (see §7 monetization risk). Every video ends on the same CTA: full review + price history at gadgetdrop.tech, links in description. This is the video analog of the site's single-CTA rule.
- The price-intelligence layer is the differentiator here too: an animated 90-day sparkline + "Verdict: GOOD BUY / WAIT" is something template competitors don't have and maps 1:1 to `PriceIntel` verdicts.

## 2. Can Claude make videos? (asked and answered)

Claude cannot generate raw footage the way Veo/Seedance do. Claude **can** do everything else, which is most of the job:

| Capability | Can Claude do it? | How |
|---|---|---|
| Script/voiceover text | ✅ | Already does — the daily-drop pipeline writes the review |
| Storyboard (scenes, timing, text) | ✅ | Emits a JSON storyboard, same philosophy as `daily-drop-build.php` |
| Visuals, motion, text animation | ✅ | Deterministic Python/Pillow/FFmpeg renderer (proof-of-concept built — see §11) |
| Voice | ✅ via TTS | Kokoro/Piper self-hosted ($0) or OpenAI TTS (~$0.02/video) |
| Music bed | ✅ | Synthesized or licensed loop library (one-time) |
| Persona/avatar | ❌ natively | Third-party API (HeyGen ~$1–4/min) or self-hosted talking-head models — optional Tier 3 |
| Generative b-roll | ❌ natively | fal.ai model APIs (~$0.03–0.10/sec) or self-hosted Wan/LTX — optional Tier 2 |
| YouTube upload + metadata | ✅ | YouTube Data API (with caveats, §6) |

So the recommended architecture is **Claude as orchestrator, code as renderer** — the model never "makes the video" directly, exactly like the model never writes `output.json` directly. Deterministic script renders; model writes the creative inputs. Same reliability model that already works for the site.

## 3. The tier ladder (pros / cons / cost per ~75s video)

Start at Tier 0+1. Each tier is additive; nothing below it is thrown away.

### Tier 0 — Programmatic motion-graphics (CHOSEN start)
Python + Pillow + FFmpeg (or Remotion/Node if preferred later). Product photos with Ken Burns zoom/pan, animated headline/price/verdict cards, feature bullets, animated price sparkline, branded end card. PoC already rendered.

- **Pros:** $0/video; fully deterministic and testable; total brand control (Figtree, site palette); renders in ~2–5 min on any machine; no vendor, no quota, no API to break; storyboard JSON is reviewable in a PR like everything else.
- **Cons:** "slideshow-plus" aesthetic — motion design quality is on us; needs 2–4 good product images per post (already a publish requirement); music must come from a licensed/generated source.
- **Cost:** $0.

### Tier 1 — Real voiceover (add immediately)
- **Self-hosted, $0:** Kokoro-82M (Apache 2.0, near-human quality, 54 voices, runs on CPU or any GPU) or Piper (fast, robotic-ish). Runs on the same machine as the renderer.
- **API, ~pennies:** OpenAI `gpt-4o-mini-tts` $15/1M chars → a 90s VO (~1,300 chars) ≈ **$0.02**. ElevenLabs $0.05–0.10/1k chars ≈ $0.07–0.13/video for best-in-class voices.
- **Pros:** voice retention is dramatically better than captions-only; consistent "channel voice" persona for free (Kokoro voice pinned in config).
- **Cons:** self-hosted TTS is one more moving part; cloud TTS is a (tiny) recurring cost. Note: TTS endpoints were unreachable from the Cowork sandbox — run TTS on the PC/server, not in sandboxed sessions.
- **Cost:** $0 (Kokoro) to ~$1/mo (OpenAI, 7 videos/wk).

### Tier 2 — Generative AI b-roll (later, selective)
Mix 2–3 five-second generated clips (product-in-lifestyle shots) into the Tier 0 edit via fal.ai (Seedance, Veo, Kling, Wan behind one API) at roughly $0.03–0.10/sec.

- **Pros:** big perceived-production-value jump; only needed for the hook scene.
- **Cons:** **models hallucinate product details** — a generated "Soundcore Space A40" won't be the real product; misleading for a *review* channel unless clips are clearly generic lifestyle b-roll (desk scenes, commute scenes, hands-with-phone) and never product close-ups. Cost scales with use; adds API dependency.
- **Cost:** ~$0.50–2.00/video → $15–60/mo at daily cadence. Skip until the channel proves out.

### Tier 3 — AI avatar presenter (optional, probably never)
HeyGen pay-as-you-go ≈ $1/min (basic avatars) to $4/min (Avatar IV class); Synthesia similar.

- **Pros:** most "human" format; a consistent host persona.
- **Cons:** $1.50–6 per Short → $45–180/mo; many viewers clock avatar content and it can *reduce* trust for a review brand; YouTube requires altered-content disclosure either way; vendor lock-in. Self-hosted talking-head models exist but are the hardest self-host category to run well.
- **Cost:** $45–180/mo. Not justified while goal is site traffic.

### Managed render APIs (alternative to Tier 0, rejected for now)
JSON2Video ($49.95/mo, 200 min, TTS included), Creatomate (~$41+/mo), Shotstack ($49/mo + 30% overage billing).

- **Pros:** less code to maintain; template editors; TTS bundled (JSON2Video).
- **Cons:** $50/mo forever for something FFmpeg does free at our volume (~9 min of video/week); template lock-in; the $0 budget rules it out. Revisit only if maintaining the renderer becomes a real time sink.

### Self-hosted generative video (deep dive, since asked)
The 2026 open-weights landscape: **Wan 2.2** (Alibaba, MoE 27B/14B-active, the practical self-host standard; the 1.3B variant runs in ~8GB VRAM at 480p), **LTX-2** (distilled FP8 variant fits 32GB, e.g. RTX 5090; full model needs 80GB), **HunyuanVideo 1.5** (~14GB with offloading; license has commercial restrictions), **CogVideoX-1.5** (40GB class). Practical baseline for comfortable 720p work: **RTX 4090 / 24GB**. A 5s 480p clip ≈ ~4 min on a 4090 (Wan 1.3B).

- **Pros:** $0 marginal cost after hardware; no content policy filter surprises; full control.
- **Cons:** a 4090/5090 is a $1,600–2,500 capital cost if not already owned (breakeven vs $30/mo of fal.ai credits: **4–7 years**); ComfyUI pipeline babysitting; slow iteration; same hallucination problem as Tier 2 — this generates *generic* b-roll, not footage of the actual product. Renting (Vast.ai 4090 ~$0.29–0.50/hr, RunPod ~$0.34–0.59/hr, serverless per-second) beats buying for occasional batches.
- **Verdict:** self-hosting the *renderer* (Tier 0) — absolutely, that's the plan. Self-hosting *generative* video — not worth it at this volume; if Tier 2 is ever wanted, rent GPU hours or use fal.ai per-clip. Cheapest is not a GPU; cheapest is FFmpeg.

### Cost summary (7 Shorts/week)

| Setup | $/video | $/month |
|---|---|---|
| **Tier 0+1 self-hosted (chosen)** | **$0** | **$0** |
| Tier 0+1 with OpenAI TTS | ~$0.02 | ~$0.60 |
| + Tier 2 b-roll (fal.ai) | +$0.50–2 | +$15–60 |
| Managed render API | — | ~$50 |
| Tier 3 avatar | $1.50–6 | $45–180 |
| Self-host gen-video GPU (rented, batch) | ~$0.15–0.50 | ~$5–15 |

## 4. Architecture — `drop-studio`

New repo, Python (matches FFmpeg/Pillow tooling; `uv` for env). gadget-drop stays PHP-only.

```
drop-studio/
├── studio/
│   ├── storyboard.py      # output.json + images → storyboard.json (deterministic defaults)
│   ├── renderer.py        # storyboard.json → frames → FFmpeg mux (port of the PoC)
│   ├── voice.py           # script → wav (kokoro | piper | openai, pluggable)
│   ├── music.py           # licensed-loop picker or synth bed
│   ├── qa.py              # gates: duration, file size, loudness (EBU R128), text-overflow probe frames
│   └── youtube.py         # upload + metadata (Phase 4+)
├── templates/short-review-v1/   # scene layouts, palette, fonts (Figtree, synced from gadget-drop)
├── assets/music/                # cleared loops (one-time purchase or CC0)
├── work/YYYY-MM-DD/             # storyboard.json, vo.wav, final.mp4, thumb.jpg
└── docs/PLAN.md → this file governs; phase log kept here
```

**Data contract with gadget-drop** (the only coupling):

1. **In:** `daily-drop/output.json` (title, slug, product name, price, deal %, verdict, key points — already produced daily) + the post's product images (the ones Bryan adds at publish time) + `PriceIntel` series. Phase 3 adds a tiny read-only JSON endpoint or artisan export (`php artisan drop:video-feed {post}`) so drop-studio never touches the DB directly.
2. **Out:** `final.mp4` + `metadata.json` (title, description with affiliate-page URL + disclosures, tags) + `thumb.jpg`. gadget-drop stores the resulting YouTube URL on the post (optional `posts.video_url` column, later phase) to embed the Short on the review page — YouTube embeds are also a minor SEO/engagement win.

**Pipeline stages** (mirrors the daily-drop philosophy — each stage a file, resumable, model writes inputs, script validates):

```
output.json ──► storyboard.json ──► vo.wav ──► frames ──► final.mp4 ──► QA gates ──► upload queue
              (Claude drafts hook   (TTS)     (renderer)              (deterministic)  (§6)
               + captions; script
               fills the rest)
```

A `/drop-video` skill in gadget-drop's `.claude/commands/` runs the storyboard step (creative text: hook line, 3 feature captions, CTA variant) and shells out to drop-studio for the rest. The renderer never depends on a model; the model never touches FFmpeg.

## 5. Voice, music, and assets — the $0 stack

- **Voice:** Kokoro-82M via `kokoro-onnx` (CPU-friendly, Apache 2.0). Pin one male + one female voice as "channel voices." Fallback chain in `voice.py`: kokoro → piper → openai (if key set). Script text comes from the review's own copy, compressed to ~170 words for 75s.
- **Music:** buy a small pack of cleared loops once (~$20 one-time, optional) or use CC0/YouTube Audio Library tracks downloaded manually into `assets/music/`; the synth-bed generator from the PoC is the true-$0 fallback. Do **not** auto-scrape "no copyright music" channels — Content ID claims will demonetize/mute.
- **Product images:** must originate from Amazon's API per Associates policy (no screenshots/manual downloads). The site already stores compliant product images per post; the video reuses those. ⚠️ **Amazon is migrating PA-API → Creators API (PA-API deprecation reported for May 15, 2026)** — this affects `AmazonProductService` on the site regardless of video; verify current state in Associates Central and plan that migration separately.
- **Fonts/brand:** Figtree woff2 → ttf conversion at build time from gadget-drop's committed fonts (PoC does this already); palette in one `templates/.../theme.json`.

## 6. YouTube automation — the honest picture

This is where "fully automated" meets reality. Facts that shape the plan:

- **Unaudited API apps can only upload PRIVATE videos.** Any Google Cloud project created after mid-2020 that hasn't passed YouTube's **API compliance audit** has `videos.insert` restricted to private visibility. The audit (form + use-case demo) reportedly takes 2–4+ weeks and denies vague/bulk-looking use cases.
- **Quota:** default 10,000 units/day; `videos.insert` historically costs 1,600 units (~6 uploads/day) — recent 2026 write-ups report uploads moving to a dedicated ~100/day bucket. Either way, 1/day is far inside limits. Quota extensions are a separate slow form.
- **Disclosure:** YouTube requires flagging **altered/synthetic content** where realistic; for motion-graphics + TTS this is a checkbox ("synthetic voice") — set `containsSyntheticMedia` honestly via API/Studio. FTC affiliate disclosure goes in every description ("As an Amazon Associate I earn from qualifying purchases") alongside the gadgetdrop.tech link.
- **Monetization risk (accepted):** YPP routinely rejects/demonetizes "reused/repetitious" template content. Irrelevant to us — revenue is affiliate, not AdSense. Do not chase YPP early; it removes a failure mode.
- **Affiliate links in YouTube descriptions:** link to the **review page**, not Amazon. Keeps click tracking (`/out/{product}`), keeps the site as the conversion surface, and sidesteps Amazon's rules about link cloaking in contexts you don't control.

**Rollout (matches effort to what's proven):**

1. **Phase 4 — semi-auto (start here):** pipeline drops `final.mp4` + ready-to-paste `metadata.json` into `work/` (or the morning PR). Bryan uploads via YouTube Studio in ~2 min/day inside the existing `/morning` routine. Zero API risk, zero audit dependency, full quality eyeball before anything goes public.
2. **Phase 5a — API as private:** register the Cloud project, upload as *private* automatically, Bryan flips to public in Studio (one click) after review. Apply for the compliance audit in parallel (use case: "publishing our own original review videos to our own channel" — specific, non-bulk, likely approvable).
3. **Phase 5b — full auto:** after audit approval, upload public + scheduled (`status.publishAt`), triggered by the same scheduler mindset as the site (a cron on the PC/server — note Hostinger shared hosting has no FFmpeg guarantee; rendering runs on the local PC or a $5 VPS, *not* on Hostinger).

## 7. Risks and mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Channel looks like AI spam → no reach | High | Price-history angle + real product photos + honest verdicts = differentiated substance; hook copy written per-video by the model, not a fill-in template sentence |
| Amazon Associates image/policy violation | High (account termination) | Images only from API-sourced site assets; never scrape; PA-API→Creators API migration tracked as its own task |
| YouTube audit denial | Medium | Semi-auto flow works forever without it; reapply with narrower wording |
| Content ID music claim | Medium | Only cleared/CC0/self-generated audio, manifest of source per track |
| Renderer bit-rot / one more system to maintain | Medium | Deterministic + tested (golden-frame tests, QA gates); no external services in Tier 0/1 |
| Hallucinated product claims in VO | Medium | VO text is extracted from the already-fact-checked review, not freshly generated; build-script style validation (banned superlatives, claim source required) like `daily-drop-build.php` |
| Time sink vs return | Medium | Phase gate after 30 published Shorts: check YouTube→site referrals in GSC/analytics before investing past Phase 4 |

## 8. Phased implementation

Same working agreement as the other plans (one phase per session, tests are part of the phase, review before commit). Phases 1–2 live entirely in drop-studio; 3+ touch gadget-drop lightly.

- **Phase 1 — Renderer + storyboard schema (S–M).** Port the PoC into `renderer.py` driven by `storyboard.json` (scene list: hook, product, features×3, price-check, cta; all text/images/timing data-driven). Golden-frame tests + QA gates (duration, resolution, loudness placeholder, text-overflow probe). Deliverable: `make video SLUG=...` renders from a hand-written storyboard.
- **Phase 2 — Voice + music (S).** `voice.py` with kokoro/piper/openai backends; ffmpeg mix with ducking under VO; loudness normalization (EBU R128, −14 LUFS target); captions burned in (Shorts are watched muted ~half the time — captions from VO text, timed by TTS phoneme/word timestamps or simple per-scene chunks).
- **Phase 3 — gadget-drop integration (M).** `php artisan drop:video-feed {post}` export (post JSON + image paths + PriceIntel series); `storyboard.py` builds storyboard from it; `/drop-video` skill for the creative fields; hook into `/morning` as an optional step. Video renders the *real* sparkline from real snapshots — honesty gates apply (no verdict shown unless `PriceIntel` has one).
- **Phase 4 — Semi-auto publish (S).** `metadata.json` generator (title ≤100 chars with product + hook, description with review URL, disclosures, tags; `#shorts`), thumbnail frame export. Bryan uploads via Studio. Channel setup checklist: brand channel, handle, banner, "About" with site link, default upload settings with synthetic-media disclosure ON.
- **Phase 5 — API upload (M).** OAuth setup, private-visibility uploads, then compliance audit → scheduled public uploads. Retry/backoff, upload ledger (`work/uploads.sqlite`) so re-runs never double-post.
- **Phase 6 — Iterate on data (ongoing).** After 30 Shorts: retention graphs → hook length/style A/B (two hook variants per video is one storyboard field); consider Tier 2 b-roll for the hook scene only; consider long-form monthly "5 best drops" compilation (cheap: stitch existing Shorts scenes horizontally re-laid-out).

## 9. What stays out of scope (explicitly)

- Long-form (8+ min) automated reviews — quality floor not reachable with Tier 0/1; revisit only with human editing.
- TikTok/Instagram cross-posting — trivial to add later (same file), but each platform is its own policy surface; YouTube first.
- Buying a GPU for generative video — see §3; rent-per-batch if ever needed.
- Running renders on Hostinger — shared hosting; renders run locally or on a cheap VPS.

## 10. Success metrics (checked at the Phase 6 gate)

- ≥25 of first 30 Shorts pass QA with zero manual edit.
- Pipeline time ≤10 min/video unattended; Bryan time ≤3 min/day (review + click publish).
- YouTube → gadgetdrop.tech referral clicks visible in analytics; any affiliate click attributable to video traffic is upside on $0 cost.
- Zero policy strikes (Amazon, YouTube, Content ID).

## 11. Proof of concept (already rendered)

Built during planning, in the Cowork sandbox, $0: a 22s 1080×1920 Short — animated hook, product sprite with Ken Burns + float, price badge with strike-through/deal pill, three feature cards, **self-drawing 90-day price sparkline + verdict chip**, branded CTA end card, synthesized music bed, Figtree brand font converted from the site's own woff2 files. ~530 frames rendered in ~90s with Pillow, muxed with FFmpeg. Known PoC gaps (all Phase 1/2 work): checkmark glyph missing from Figtree subset (use drawn vector tick), no VO (TTS endpoints unreachable from sandbox — works locally), placeholder product art instead of real photos.

## Phase Log

- [x] Phase 1 — 2026-07-19: drop-studio repo created (`../drop-studio`, github.com/bryan-mcneil/drop-studio, private). Storyboard schema v1 + validation, deterministic builder from `output.json`, Pillow renderer (hook/product/feature×3/price/cta, Ken Burns, animated sparkline with honesty gates, auto-fit text shared with QA), QA gates, golden-frame tests. See `drop-studio/MORNING-REVIEW.md`.
- [x] Phase 2 — 2026-07-19 (same session): Kokoro-82M per-scene TTS (kokoro→piper→openai→none chain) driving scene duration + caption timing; synth music bed + licensed-track manifest; VO ducking; two-pass loudnorm to −14 LUFS. Demo Short renders end-to-end, 37 tests green.
- [ ] Phase 3 — gadget-drop integration (M): `php artisan drop:video-feed {post}` export + `storyboard.py` + `/drop-video` skill + optional `/morning` step. **Blocking dependency for Plan 10 §10.7's honesty gate** — 10.7 ships its site-side wiring first with a null-safe fallback, but no real recap may be published until this export feeds it real price data.
- [ ] Phase 4 — Semi-auto publish (S): `metadata.json` generator + thumbnail frame export + channel setup checklist; Bryan uploads via Studio.
- [ ] Phase 5 — API upload (M): OAuth, private-visibility uploads → compliance audit → scheduled public uploads, with retry/backoff and an upload ledger.
- [ ] Phase 6 — Iterate on data (ongoing): after 30 Shorts, retention-driven hook A/B, selective Tier 2 b-roll, possible long-form compilation.
