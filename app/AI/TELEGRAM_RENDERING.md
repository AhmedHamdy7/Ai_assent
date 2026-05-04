# Telegram AI Output Rendering — Complete Specification

This document describes, in full detail, how AI-generated text (markdown) is rendered into Telegram messages in this Dart codebase, so that an equivalent implementation can be built in PHP (or any other language).

The reference source file is:

```
packages/messaging_channels/lib/telegram_channel.dart
```

All line numbers below refer to that file.

---

## 1. Overview

The AI produces **standard Markdown** (the same dialect ChatGPT/Claude emit): headings with `#`, bold `**x**`, italic `*x*`/`_x_`, inline code `` `x` ``, fenced code blocks ```` ```lang ... ``` ````, links `[label](url)`, strikethrough `~~x~~`, etc.

Telegram's Bot API does **not** accept Markdown directly in a safe way. It supports two parse modes:

- `MarkdownV2` — requires escaping `_ * [ ] ( ) ~ \` > # + - = | { } . !`. AI text is full of those characters; escape bugs constantly cause `400 can't parse entities` errors.
- `HTML` — only `& < >` need escaping. Tag set is small and well-defined.

**This system uses `parse_mode: HTML`.** It converts the AI's Markdown to a small, Telegram-safe HTML subset, then sends it. If parsing still fails, it falls back to plain text so a message is never lost.

The high-level pipeline:

```
AI markdown text
   │
   ▼
[ _toHtmlChunks ]   split into ≤4096-char chunks on paragraph boundaries
   │
   ▼  (each chunk)
[ _markdownToHtml ] convert markdown → Telegram HTML subset
   │
   ▼
[ _postHtmlWithFallback ]
   ├─ try sendMessage with parse_mode=HTML
   ├─ on "message is not modified" → ignore (only for edits)
   ├─ on "can't parse" → retry without parse_mode, with HTML tags stripped
   └─ on transient error (429, 5xx, timeout, network) → retry up to 3 times with backoff
```

---

## 2. Telegram HTML Subset

Telegram only accepts a very small set of HTML tags when `parse_mode=HTML`. The full list this code uses:

| Tag                                          | Purpose                |
| -------------------------------------------- | ---------------------- |
| `<b>...</b>`                                 | bold                   |
| `<i>...</i>`                                 | italic                 |
| `<s>...</s>`                                 | strikethrough          |
| `<code>...</code>`                           | inline code            |
| `<pre><code>...</code></pre>`                | code block (no lang)   |
| `<pre><code class="language-xxx">...</code></pre>` | code block with language |
| `<a href="https://...">label</a>`            | hyperlink              |

Telegram does **not** accept `<h1>`, `<ul>`, `<ol>`, `<p>`, `<br>`, `<div>`, `<span>`, `<img>`, etc. Any of those will return `400 Bad Request: can't parse entities`.

**HTML escaping rules inside content:**

- `&` → `&amp;`
- `<` → `&lt;`
- `>` → `&gt;`

That is the **complete** escape set. Do not escape quotes; do not escape anything else.

Inside `href="..."`, the same three characters must be escaped (the URL coming from Markdown won't normally contain them, but a defensive escape does no harm).

Maximum message length: **4096 characters of UTF-16 code units** (Telegram limit). This implementation uses a string-length check, which is close enough in practice. If you want byte-perfect safety in PHP, use `mb_strlen($s, 'UTF-16')` — but `mb_strlen($s, 'UTF-8')` is what the Dart `String.length` (UTF-16 units) most closely resembles is *not* identical; see §9 for caveats.

---

## 3. Constants

```dart
static const _chunkDelay     = Duration(milliseconds: 1100);  // line 55
static const _maxDeleteBatchSize = 100;                       // line 57
static const _maxMessageLength   = 4096;                      // line 58
static const _maxRetries     = 3;                             // line 251
static const _fallbackMessage = 'Something went wrong, please try again.'; // line 252
```

- `_chunkDelay = 1100ms` — Telegram allows ~1 message/sec to the same chat. Sleep 1.1s between chunks of a multi-part message.
- `_maxMessageLength = 4096` — Telegram's hard cap on `text` for `sendMessage`/`editMessageText`.
- `_maxRetries = 3` — total attempts (not retries on top of the first call).

PHP equivalents: `usleep(1_100_000)` between chunks; constants in a class.

---

## 4. Chunking — `_toHtmlChunks` (line 588)

Goal: produce a `List<String>` of HTML strings, each ≤ 4096 chars, where paragraph boundaries from the original Markdown are preserved.

### 4.1 Algorithm

```text
trim text
split on /\n{2,}/   → list of paragraphs (each may still contain single \n)
chunks  = []
buffer  = ""

for each paragraph:
    html      = _markdownToHtml(paragraph)
    separator = (buffer == "") ? "" : "\n\n"

    if length(buffer) + length(separator) + length(html) > 4096:
        if buffer not empty:
            push buffer to chunks
            buffer = ""
        if length(html) > 4096:
            _hardSplitInto(html, chunks)        // see 4.2
        else:
            buffer = html
    else:
        buffer += separator + html

if buffer not empty:
    push buffer to chunks

if chunks is empty:
    chunks = [""]                                // never return empty list
return chunks
```

Notes:

- Each **paragraph is converted to HTML on its own**. Conversion is per-paragraph specifically so a chunk break can never land inside an open tag — every paragraph is self-contained.
- The separator `"\n\n"` is reinserted between paragraphs so the rendered Telegram message looks like the original.

### 4.2 Hard split — `_hardSplitInto` (line 617)

Used when a single paragraph is longer than 4096 chars (rare, usually a giant code block).

```text
while length(s) > 4096:
    splitAt = lastIndexOf('\n', upTo=4096) in s
    if splitAt <= 0: splitAt = 4096
    push s[0..splitAt] to out
    s = s[splitAt..].trimLeft()
if s not empty: push s
```

So we prefer to break at the last newline before the limit; if there is none, we cut at 4096. **Caveat:** this can split inside a `<pre><code>` block, leaving an open tag. Telegram will then reject the chunk and the fallback path (§6) strips tags and re-sends as plain text. In practice the AI almost never emits a single paragraph > 4096 chars, so this path is rarely exercised.

### 4.3 PHP sketch

```php
function toHtmlChunks(string $text, int $max = 4096): array {
    $paragraphs = preg_split('/\n{2,}/', trim($text));
    $chunks = [];
    $buffer = '';
    foreach ($paragraphs as $p) {
        $html = markdownToHtml($p);
        $sep  = $buffer === '' ? '' : "\n\n";
        if (mb_strlen($buffer) + mb_strlen($sep) + mb_strlen($html) > $max) {
            if ($buffer !== '') { $chunks[] = $buffer; $buffer = ''; }
            if (mb_strlen($html) > $max) {
                hardSplitInto($html, $chunks, $max);
            } else {
                $buffer = $html;
            }
        } else {
            $buffer .= $sep . $html;
        }
    }
    if ($buffer !== '') $chunks[] = $buffer;
    return $chunks ?: [''];
}

function hardSplitInto(string $s, array &$out, int $max = 4096): void {
    while (mb_strlen($s) > $max) {
        $head = mb_substr($s, 0, $max);
        $splitAt = mb_strrpos($head, "\n");
        if ($splitAt === false || $splitAt <= 0) $splitAt = $max;
        $out[] = mb_substr($s, 0, $splitAt);
        $s = ltrim(mb_substr($s, $splitAt));
    }
    if ($s !== '') $out[] = $s;
}
```

---

## 5. Markdown → HTML — `_markdownToHtml` (line 628)

This is the core. It runs **ten ordered passes**. Order matters; do not reorder.

### Pass 1 — Fenced code blocks (line 633)

Regex: ` ```(\w*)\n?([\s\S]*?)``` ` (multiline, non-greedy).

For each match:
1. Capture `lang` (group 1) and `body` (group 2).
2. HTML-escape the body (`& < >`).
3. Build either `<pre><code class="language-{lang}">{body}</code></pre>` if `lang` is non-empty, else `<pre><code>{body}</code></pre>`.
4. Push the tag to a `codeBlocks` array, replace the match with a placeholder `\x00{N}\x00` (NUL byte + index + NUL byte).

The placeholder uses the NUL byte (`\x00`) precisely because Markdown text never contains it, so later passes can't break it.

### Pass 2 — Inline code (line 647)

Regex: `` `([^`\n]+)` `` (single-line, no nested backticks).

Same trick: HTML-escape the captured content, wrap in `<code>...</code>`, push to `inlineCodes`, replace with `\x01{N}\x01`.

### Pass 3 — Escape HTML in remaining text (line 653)

Now the only thing left in the string is plain prose + Markdown markers + placeholders. Run `_escapeHtml` (line 704):

```dart
text.replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
```

Placeholders survive intact (they contain `\x00` and `\x01`, neither is HTML-special).

### Pass 4 — Headings (line 656)

Regex: `^#{1,3} +(.+)$` (multiline).

Replaces `# Heading`, `## Heading`, `### Heading` with `<b>Heading</b>`. Telegram has no real heading tag, so we render headings as bold lines.

### Pass 5 — Links (line 662)

Regex: `\[([^\]\n]+)\]\((https?://[^\)\n]+)\)`.

Replace with `<a href="{url}">{label}</a>`. Only `http://` and `https://` schemes are matched.

### Pass 6 — Bold (line 668)

Regex: `\*\*(.+?)\*\*` (non-greedy).

Done **before** italic so that `***x***` is processed correctly: bold first eats the outer `**`, leaving `*x*` for italic.

### Pass 7 — Italic with `*` (line 674)

Regex: `(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)`.

Negative lookbehind/lookahead `(?<!\*)` and `(?!\*)` ensure we don't match a `*` adjacent to another `*` — that would have been bold and was already handled.

### Pass 8 — Italic with `_` (line 680)

Regex: `(?<!\w)_([^_\n]+)_(?!\w)`.

The `\w` boundary guards prevent matching inside `snake_case_identifiers` — important because the AI emits a lot of code-flavored prose.

### Pass 9 — Strikethrough (line 686)

Regex: `~~(.+?)~~` → `<s>...</s>`.

### Pass 10 — Restore placeholders (lines 691–699)

Replace `\x01{N}\x01` with `inlineCodes[N]`, then `\x00{N}\x00` with `codeBlocks[N]`. **Restore inline code first**, because inline-code content may itself contain `\x00` placeholders if a fenced block was somehow nested (paranoia — order is cheap, bugs aren't).

### 5.1 Why this ordering

| Step | Why this order |
| ---- | -------------- |
| Code blocks first | So their `*`, `_`, `[`, etc. are not interpreted as Markdown. |
| Inline code second | Same reason, smaller scope. |
| Escape HTML next | Now the leftover string is plain prose; safe to escape. Placeholders pass through. |
| Headings before bold | A `## **x**` line should render as bold `**x**`, not bold-of-bold. The `# +(.+)$` regex captures the rest of the line including `**x**`, then we wrap it in `<b>` — but wait, the `**` would still be there. In practice the AI rarely combines them; the trade-off is acceptable. |
| Bold before italic | `***x***` → bold first leaves `*x*` for italic. |
| `*` italic before `_` italic | Same regex shape; order between them does not matter, but `_` has the stricter `\w` guard so it's safer to run it after the looser one. |
| Strikethrough late | `~~` is unambiguous; could be earlier, but no benefit. |
| Restore placeholders last | They contain finished HTML that must not be re-processed. |

### 5.2 PHP sketch

```php
function escapeHtml(string $t): string {
    return strtr($t, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
}

function markdownToHtml(string $text): string {
    $codeBlocks  = [];
    $inlineCodes = [];

    // 1. Fenced code blocks
    $text = preg_replace_callback(
        '/```(\w*)\n?([\s\S]*?)```/m',
        function ($m) use (&$codeBlocks) {
            $lang = $m[1];
            $body = escapeHtml($m[2] ?? '');
            $tag  = $lang !== ''
                ? "<pre><code class=\"language-{$lang}\">{$body}</code></pre>"
                : "<pre><code>{$body}</code></pre>";
            $codeBlocks[] = $tag;
            return "\x00" . (count($codeBlocks) - 1) . "\x00";
        },
        $text
    );

    // 2. Inline code
    $text = preg_replace_callback(
        '/`([^`\n]+)`/',
        function ($m) use (&$inlineCodes) {
            $inlineCodes[] = '<code>' . escapeHtml($m[1]) . '</code>';
            return "\x01" . (count($inlineCodes) - 1) . "\x01";
        },
        $text
    );

    // 3. Escape remaining HTML
    $text = escapeHtml($text);

    // 4. Headings → bold line
    $text = preg_replace('/^#{1,3} +(.+)$/m', '<b>$1</b>', $text);

    // 5. Links
    $text = preg_replace(
        '/\[([^\]\n]+)\]\((https?:\/\/[^\)\n]+)\)/',
        '<a href="$2">$1</a>',
        $text
    );

    // 6. Bold
    $text = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $text);

    // 7. Italic *x*
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<i>$1</i>', $text);

    // 8. Italic _x_
    $text = preg_replace('/(?<!\w)_([^_\n]+)_(?!\w)/', '<i>$1</i>', $text);

    // 9. Strikethrough
    $text = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $text);

    // 10. Restore placeholders (inline first, then blocks)
    $text = preg_replace_callback('/\x01(\d+)\x01/', fn($m) => $inlineCodes[(int)$m[1]], $text);
    $text = preg_replace_callback('/\x00(\d+)\x00/', fn($m) => $codeBlocks[(int)$m[1]], $text);

    return $text;
}
```

PHP gotcha: PHP's PCRE supports lookbehind/lookahead and `\w` exactly like Dart's RegExp here, so the patterns transfer 1:1.

---

## 6. Sending — `_postHtmlWithFallback` (line 548)

```dart
try:
    POST {method} { ...extra, text: html, parse_mode: 'HTML' }
catch TelegramApiException e:
    if e.isNotModified: return {}              // safe-to-ignore (only for editMessageText)
    if not e.isParseError: rethrow              // bubble up to retry layer
    POST {method} { ...extra, text: stripHtmlTags(html) }   // last-ditch plain text
```

Definitions (line 29-35):

- `isParseError`  = `errorCode == 400 && description.contains("can't parse")`
- `isNotModified` = `errorCode == 400 && description.contains("message is not modified")`

`_stripHtmlTags` (line 777):

```dart
html.replaceAll(RegExp(r'<[^>]+>'), '')
    .replaceAll('&amp;', '&')
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    // ... and so on for &quot; / &#39; if you have them
```

PHP equivalent: `strip_tags($html)` followed by `html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')`. Note PHP's `strip_tags` is slightly different from the regex — it understands HTML structure better, which is fine for this purpose.

### 6.1 Calling the API

The HTTP target is:

```
POST https://api.telegram.org/bot{TOKEN}/{method}
Content-Type: application/json
Body: { "chat_id": "...", "text": "...", "parse_mode": "HTML", ... }
```

Successful response: `{ "ok": true, "result": { "message_id": 123, ... } }`.

Failure: `{ "ok": false, "error_code": 400, "description": "..." }` — wrap in your own exception class so the caller can match on `error_code` + `description` substrings.

### 6.2 Retry — `_withRetry` (line 716)

Wraps `_sendHtml` / `_editHtml`. Behavior:

- Up to **3 attempts** total.
- Retries on: `TimeoutException`, `SocketException`, and `TelegramApiException` where `errorCode == 429 || errorCode >= 500`.
- Backoff: `attempt * 2` seconds. So sleep **2s before attempt 2**, **4s before attempt 3**.
- Parse errors (400 with "can't parse") are NOT retried by `_withRetry` — they're handled inside `_postHtmlWithFallback` by stripping tags. The retry layer only sees parse errors if `_postHtmlWithFallback` re-throws something we don't expect, in which case retry can't help anyway and the call fails through to the final `_sendPlain` fallback in the caller.

PHP retry pattern:

```php
function withRetry(callable $fn, int $max = 3): mixed {
    $last = null;
    for ($attempt = 1; $attempt <= $max; $attempt++) {
        try { return $fn(); }
        catch (TelegramApiException $e) {
            if (!($e->code === 429 || $e->code >= 500)) throw $e;
            $last = $e;
        }
        catch (RuntimeException $e) { $last = $e; }
        if ($attempt === $max) break;
        sleep($attempt * 2);
    }
    throw $last;
}
```

### 6.3 Final fallback

If `_withRetry` exhausts retries, the public methods (`sendWithMessageIds` line 316, `editMessageWithMessageIds` line 435) catch and call `_sendPlain` (line 763) with the constant `_fallbackMessage = 'Something went wrong, please try again.'`. `_sendPlain` posts with **no `parse_mode`** and swallows all errors. This guarantees the user gets *some* signal that the turn ran, even if the actual content can't be delivered.

---

## 7. Public API surface

### 7.1 Send a new message

`sendWithMessageIds(userId, text)` — line 316:

1. `chunks = _toHtmlChunks(text)`
2. For each chunk index `i`:
   - if `i > 0`: sleep `_chunkDelay` (1100 ms)
   - call `_withRetry(...)` → `_sendHtml(userId, chunk)` → `_postHtmlWithFallback('sendMessage', ...)`
   - on permanent failure: `_sendPlain(userId, _fallbackMessage)`, push id, return.
3. Return list of all message IDs created.

### 7.2 Edit an existing message (used for streaming output)

`editMessageWithMessageIds(userId, messageId, text)` — line 435:

1. `chunks = _toHtmlChunks(text)`
2. Edit the original message with `chunks[0]` via `editMessageText`.
3. For chunks `[1..]` (overflow), **send them as new messages** (sleep 1100 ms between each). Telegram has no concept of editing into multiple messages; an edit only changes one message, so overflow becomes new sends. The returned list starts with the original `messageId` plus any new ones.

This is critical for streaming: while the AI is producing tokens, the bot edits a single "Thinking..." placeholder message in place. When the streamed content grows past 4096 chars, the system spills into additional messages.

### 7.3 The streaming entry point

`sendLoading(userId)` (line 343) posts the literal text `"Thinking..."` (no parse_mode) and returns its `message_id`. The AI runtime then repeatedly calls `editMessage(userId, messageId, currentBuffer)` as new tokens arrive. There is no special "streaming" API — it's just `editMessageText` called many times. Telegram's "message is not modified" 400 is the safe-to-ignore signal that the buffer didn't change since last edit.

> **Rate-limit note:** edits to the same message also count against Telegram's per-chat rate limit. The runtime that drives the streaming throttles edits (typically every 700–1500 ms in production AI bots) to avoid 429s. This file only handles the per-call retry; the calling layer is responsible for not spamming `editMessage`.

---

## 8. Worked examples

### 8.1 Plain bold

Input (from AI):

```
Hello **world**!
```

After `_markdownToHtml`:

```
Hello <b>world</b>!
```

Sent as: `{"chat_id":..., "text":"Hello <b>world</b>!", "parse_mode":"HTML"}`.

### 8.2 Code block with language

Input:

```
Here is the fix:

​```dart
void main() => print('hi <world>');
​```
```

After Pass 1 (code block extracted to `codeBlocks[0]`):

```
Here is the fix:

\x000\x00
```

Where `codeBlocks[0]` = `<pre><code class="language-dart">void main() =&gt; print('hi &lt;world&gt;');</code></pre>` (note `>` and `<` already escaped).

After Pass 3 (HTML-escape the rest): unchanged (no special chars left).

After Pass 10 (restore): final HTML is

```
Here is the fix:

<pre><code class="language-dart">void main() =&gt; print('hi &lt;world&gt;');</code></pre>
```

### 8.3 Edge case — heading with bold

Input:

```
## **Important**
Read this.
```

After Pass 4 (heading): `<b>**Important**</b>\nRead this.`
After Pass 6 (bold): `<b><b>Important</b></b>\nRead this.`

Telegram will accept nested `<b>` (it's just bold-of-bold = bold). Visual result is fine. This is a known minor cosmetic quirk; not a bug.

### 8.4 Edge case — snake_case must not become italic

Input:

```
Call my_function_name(x).
```

After Pass 8: regex `(?<!\w)_([^_\n]+)_(?!\w)` does NOT match because both underscores have a word-character on both sides. Output: unchanged. Correct.

### 8.5 Edge case — content with `<` in prose

Input:

```
If x < 5 and y > 3, do this.
```

After Pass 3 (HTML escape):

```
If x &lt; 5 and y &gt; 3, do this.
```

Sent. Telegram renders it as `If x < 5 and y > 3, do this.` Correct.

### 8.6 Edge case — malformed Markdown that produces invalid HTML

Input (AI emits unmatched bold):

```
Look at **this and this.
```

After Pass 6: `**` non-greedy with `+?` requires a closing `**`, so no match. Output: `Look at **this and this.` (raw `**` survives). Telegram renders the literal asterisks. No error.

But if the AI emits something like `<not-a-tag>` in prose, Pass 3 escapes it to `&lt;not-a-tag&gt;`, so it becomes literal text in Telegram. Safe.

The only way to actually trigger a 400 parse error is for the converter itself to produce invalid HTML (e.g. an unclosed `<b>` due to a regex bug). When that happens, `_postHtmlWithFallback` strips tags and re-sends as plain text. The user sees ungroomed text, but the message lands.

---

## 9. PHP-specific implementation notes

1. **String length.** Dart's `String.length` returns UTF-16 code units. PHP's `strlen` returns bytes. Use `mb_strlen($s, 'UTF-8')` to count characters; this is close enough to Dart's behavior for chunking. The 4096 cap is Telegram's UTF-16 character limit, so emoji (which are 2 UTF-16 units) count as 2; `mb_strlen` will under-count them. To be safe, use:

   ```php
   function tgLen(string $s): int {
       return mb_strlen(mb_convert_encoding($s, 'UTF-16', 'UTF-8'), '8bit') / 2;
   }
   ```

   In practice an 80-char buffer (use 4000 instead of 4096) avoids edge cases without the conversion cost.

2. **Regex flavors.** PHP PCRE is a strict superset of what these patterns use. Add the `u` flag (`/.../u`) to all patterns so multibyte content doesn't break character classes:

   ```php
   preg_replace('/^#{1,3} +(.+)$/mu', '<b>$1</b>', $text);
   ```

3. **HTTP client.** `curl_multi` or Guzzle. Set a connect timeout of 5s and a request timeout of 30s. Always parse the response as JSON and check `ok === true` before trusting `result`.

4. **Chunk delay.** `usleep(1_100_000)` (1.1 s). Don't use `sleep(1)` — that's only 1.0 s and you'll occasionally hit 429.

5. **NUL byte placeholders.** `\x00` is fine in PHP strings — they're binary-safe. Don't use string functions that misinterpret NUL (none of the PCRE / `strtr` / concat ops do).

6. **Retry on 429.** Telegram's 429 response includes `parameters.retry_after` (seconds). Honor it if present:

   ```php
   if ($e->code === 429 && isset($e->retryAfter)) {
       sleep($e->retryAfter);
       continue;
   }
   ```

   The Dart code uses a fixed `attempt * 2` backoff and ignores `retry_after`. A PHP port can be smarter — strictly an improvement.

7. **Edit-vs-send semantics.** A streaming UI calls `editMessageText` repeatedly. To avoid burning rate limit, debounce: only edit if the buffer has grown by ≥N chars or M ms have passed since the last edit, whichever is first. Typical values: 50 chars OR 700 ms.

---

## 10. Test recipe

Implementing `markdownToHtml` correctly is the only hard part. A minimal test matrix:

| Input | Expected output |
| ----- | --------------- |
| `Hello **world**` | `Hello <b>world</b>` |
| `*hi*` | `<i>hi</i>` |
| `***bold-italic***` | `<b><i>bold-italic</i></b>` |
| `` `code` `` | `<code>code</code>` |
| `` `<x>` `` | `<code>&lt;x&gt;</code>` |
| ` ```py\nprint(1)\n``` ` | `<pre><code class="language-py">print(1)\n</code></pre>` |
| `[Google](https://google.com)` | `<a href="https://google.com">Google</a>` |
| `~~old~~` | `<s>old</s>` |
| `## Title` | `<b>Title</b>` |
| `snake_case_var` | `snake_case_var` (unchanged) |
| `2 < 3` | `2 &lt; 3` |
| `2 & 3` | `2 &amp; 3` |
| `**a* b*` | `**a* b*` (no match — defensive) |

Run all of these against your PHP port; if any disagree with the Dart reference, fix the regex or the ordering before shipping.

---

## 11. Summary checklist for the PHP agent

- [ ] Implement `escapeHtml($s)` for `& < >` only.
- [ ] Implement `markdownToHtml($s)` with the **exact 10-pass order** in §5.
- [ ] Use `\x00`/`\x01` NUL-bracketed placeholders for code blocks / inline code.
- [ ] Implement `toHtmlChunks($s, 4096)` splitting on `\n{2,}`, converting per-paragraph, packing with `\n\n` separator. Hard-split paragraphs > 4096 on the last `\n`.
- [ ] Wrap `sendMessage`/`editMessageText` in a `postHtmlWithFallback` that catches 400 "can't parse" and retries with `strip_tags`, and ignores 400 "message is not modified".
- [ ] Wrap that in a retry helper for 429 / 5xx / network errors. 3 attempts, 2s × attempt backoff. Honor `retry_after`.
- [ ] On total failure, send the plain string `"Something went wrong, please try again."` with no `parse_mode`.
- [ ] Sleep 1100 ms between chunks of the same multi-part reply.
- [ ] For streaming, edit chunk 0 with `editMessageText`; send overflow chunks as new messages.
- [ ] Cap edit frequency (e.g. ≥50 chars new content OR ≥700 ms elapsed) to avoid 429 from streaming.

That is the full system. Anything not covered here (file uploads, inline keyboards, callback queries) is unrelated to AI text rendering.
