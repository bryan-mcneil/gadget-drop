# /drop-assemble — Daily Drop Step 3: Build the Import JSON

## Context
GadgetDrop daily content pipeline, **step 3**. The day's posts already exist as structured markdown in `daily-drop/` (`product-*.md` from `/drop-write`, plus `tip-1.md` or `news-1.md` on their cadence days). A PHP script converts them into the JSON array the importer expects. You run the script and relay its result; you do NOT write or escape any JSON yourself. This command needs no prior conversation context.

## Steps

1. **Preflight**: list `daily-drop/product-*.md`, `tip-*.md`, `news-*.md` and check against today's expected mix:

   | Day | Expected files |
   |---|---|
   | Mon | product-N.md + news-1.md |
   | Tue | product-N.md + tip-1.md |
   | Wed / Fri / Sun | product-N.md only |
   | Thu | product-N.md + news-1.md |
   | Sat | product-N.md + tip-1.md |

   (One review file per day — product-1, or product-2 if the backup was used.) If something expected is missing, tell the user which step to run (`/drop-write`, `/drop-tip`, `/drop-news`) and ask whether to build a partial drop or stop.

2. **Build**: run with the Bash tool from the project root:
   ```bash
   php bin/daily-drop-build.php
   ```
   The script parses all content files, validates every post per its TYPE, and writes `daily-drop/output.json` (raw array).

3. **Handle the result**:
   - **Hard error** (exit code 1 — missing TITLE/BODY, unknown TYPE, tip/news missing SOURCE_URL, news missing `## Buy or Wait?`): the script names the file and post block. Fix exactly what it names with the Edit tool, then re-run.
   - **Warnings** (title length, banned phrase, em dash, word count): make a targeted Edit to only the flagged field or sentence, then re-run. Never rewrite whole posts. If a warning is a judgment call, report it to the user instead of editing.
   - Repeat until clean or only user-acknowledged warnings remain.

4. **Done**: print the script's per-post summary lines, then:
   ```
   ✅ Drop assembled — daily-drop/output.json is ready ({N} posts).
   Import: php artisan posts:import        (local preview)
   Publish path: /morning handles pull + prod import; or paste output.json into the prod /admin/daily-drop Import page.
   ```
