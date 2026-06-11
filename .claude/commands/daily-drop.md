# /daily-drop — Content Pipeline Status & Router

The daily content pipeline is split into small steps so each one can run in a cheap, fresh session (use /handoff between steps). State passes through files in `daily-drop/`, never through conversation context.

| Step | Command | Input | Output |
|---|---|---|---|
| 1 | `/drop-research` | web + reviewed-products API | `daily-drop/research.md` (4 products) |
| 2 | `/drop-write N` (N = 1-4, optional voice name for one post) | `daily-drop/research.md` | `daily-drop/product-N.md` (4 voice posts) |
| 3 | `/drop-assemble` | `daily-drop/product-*.md` | `daily-drop-output.md` via `php bin/daily-drop-build.php` |
| 4 | `/drop-video N\|all` (optional) | research + product files | video/social sections appended to output |

## What to do when this command runs

1. **Detect state** (Glob `daily-drop/*`):
   - No `research.md`, or its `DATE:` is not today → next step is `/drop-research`
   - `research.md` is current, products missing among `product-1.md` … `product-4.md` → next step is `/drop-write {lowest missing N}`
   - All 4 product files exist → next step is `/drop-assemble`
   - `daily-drop-output.md` already built today → pipeline done; remind the user to paste the JSON into /admin/daily-drop and that `/drop-video` is available

2. **Report**, in 3 lines or fewer: what's done, what's next, e.g.
   `Research done (4 products) · products 1-2 written · Next: /drop-write 3 (consider /handoff first)`

3. **Default: do not run the next step.** The whole point of the split is letting the user /handoff to a cheaper model between steps. Only execute steps yourself if the user explicitly says so (e.g. "/daily-drop run all" or "run the next step"); in that case Read the step's command file from `.claude/commands/` and follow it exactly, one step at a time, in order.