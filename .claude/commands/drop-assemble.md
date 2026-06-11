# /drop-assemble — Daily Drop Step 3: Build the Import JSON

## Context
GadgetDrop daily content pipeline, **step 3**. The posts already exist as structured markdown in `daily-drop/product-*.md` (written by `/drop-write`). A PHP script converts them into the JSON array the admin importer expects. You run the script and relay its result; you do NOT write or escape any JSON yourself. This command needs no prior conversation context.

## Steps

1. **Preflight**: list `daily-drop/product-*.md`. The full daily drop has products 1 through 4. If some are missing, tell the user which ones and ask whether to build a partial drop or stop so they can run `/drop-write N` first.

2. **Build**: run with the Bash tool from the project root:
   ```bash
   php bin/daily-drop-build.php
   ```
   The script parses the product files, validates every post, and writes:
   - `daily-drop-output.md` (date header + fenced json block, for the admin paste)
   - `daily-drop/output.json` (raw array)

3. **Handle the result**:
   - **Hard error** (exit code 1): the script names the file and post block. Fix exactly what it names in that `daily-drop/product-*.md` file with the Edit tool, then re-run the script.
   - **Warnings** (e.g. title length, banned phrase, em dash): make a targeted Edit to only the flagged field or sentence in the product file, then re-run the script. Never rewrite whole posts. If a warning is a judgment call (e.g. word count slightly off), report it to the user instead of editing.
   - Repeat until clean or only user-acknowledged warnings remain.

4. **Done**: print the script's per-post summary lines, then:
   ```
   ✅ Daily drop complete — daily-drop-output.md is ready.
   {N} posts · JSON array saved.
   Go to /admin/daily-drop, paste the JSON array, preview, then import all as drafts.
   Optional: run /drop-video all (or /drop-video N) for YouTube scripts and social captions.
   ```