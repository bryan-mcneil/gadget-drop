---
name: host-allowlist-parse-url
description: parse_url-based host allowlists in this repo are bypassable by a backslash in the authority (https://amazon.com\@walmart.com); attack every new host re-check this way
metadata:
  type: project
---

Any PHP host allowlist built on `parse_url($url, PHP_URL_HOST)` + `str_ends_with($host, '.'.$domain)` is bypassable, and it bit `App\Support\PriceComparison::matchHost()` (plan 11, found 2026-08-25 review).

**Why:** PHP's `parse_url` follows RFC 3986 and treats `\` as an ordinary userinfo character; the WHATWG URL parser browsers use treats `\` as `/` for special schemes, so it terminates the host. Result for `https://amazon.com\@walmart.com/dp/B000`:
- PHP: `user = "amazon.com\"`, `host = "walmart.com"` → passes the whitelist.
- Every browser: `host = "amazon.com"`, `path = "/@walmart.com/dp/B000"` → navigates to amazon.com.

Verified with `php -r` against the vendored class; `https://walmart.com@evil.com/` (plain userinfo, no backslash) is handled correctly by PHP and is NOT a bypass. Tab/newline in the authority fails closed (PHP substitutes `_`, no match). `data:`/`javascript:` are caught by the scheme check. Uppercase, trailing dot, `www.`, and `sub.host` all resolve correctly.

**How to apply:** on any diff that adds or edits a URL/host allowlist (price compare, MCP responses, importer URL columns, redirect targets), test these six literally before approving: backslash-in-authority, `host@evil.com`, tab/newline in host, trailing dot, uppercase, `allowed.com.evil.com`. The fix is one line at the top of the URL validator: reject any raw URL containing `\`, whitespace, or a control character before `parse_url` ever sees it. Related: [[raw-amazon-blocker-scope]], [[plan11-price-compare-review]].
