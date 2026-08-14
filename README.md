# Advanced Excerpt - Enhanced Edition

A significantly enhanced version of the Advanced Excerpt WordPress plugin with improved HTML handling, RSS compatibility, and powerful new features.

> Looking for installation steps, FAQ, or the full version history? See [readme.txt](readme.txt).

## 🎯 New Features

### 1. **Homepage Category Filter**
- **Filter homepage posts by multiple categories**
- Dynamic category selection with checkboxes
- Fully compatible with pagination
- OR logic: displays posts from ANY selected category
- Also applies to the site's own default RSS/Atom feed (e.g. `/feed/`)

### 2. **Block Finish Mode**
- **Stop excerpt at the next block-level element after length reached**
- Supports 37 block-level tags: `br`, `p`, `div`, `blockquote`, `li`, `td`, `th`, `h1-h6`, `article`, `section`, `header`, `footer`, `aside`, `nav`, `ul`, `ol`, `table`, `tr`, `pre`, `form`, `fieldset`, `dl`, `dt`, `dd`, `hr`, `figure`, `figcaption`, `main`, `address`, `details`, `summary`, `dialog`
- Also stops as soon as the current inline element closes (`a`, `strong`, `b`, `em`, `i`, `span`, `code`, `mark`, `small`, `sub`, `sup`, `u`, `s`, `abbr`, `cite`, `q`) so a long stretch of inline content (e.g. several links in a row) can't drag the excerpt on indefinitely while waiting for a distant block boundary
- Default finish mode for new installations
- Creates natural-looking excerpt boundaries

### 3. **Smart Tag Closing**
- **No more broken HTML in excerpts!**
- Automatically tracks and closes all unclosed tags and maintains proper nesting structure
- Strips any trailing malformed tag fragment (e.g. a missing closing `>`) left over from the source content, so excerpts never end mid-tag
- Recognizes HTML5 void elements (`<hr>`, `<br>`, `<img>`, etc.) even without a trailing slash, so they're never mistaken for a tag needing a closing counterpart
- Plain text shaped like a tag (e.g. `<Free>`, `<New>`) is never mistaken for real markup
- Removes unnecessary consecutive `<br>` tags for better readability (when not stripped per settings)
- Only `<a>` tags with a real, followable `href` are kept (when anchors are not stripped per settings), while the following are unwrapped to their visible text instead:
  - Empty `href=""`
  - Same-page fragments (`href="#"`, `href="#section2"`) — meaningless once the content is lifted out of the full page into an excerpt
  - Bare anchors with no `href` at all (e.g. `<a name="section">`, an in-page jump target rather than a link)
  - `javascript:`/`data:`/`vbscript:` pseudo-schemes — not a real destination outside a browser executing script

### 4. **Header Content Skipping**
- Option to skip H1-H6 text content (not just the tags)
- Header text not counted toward excerpt length

### 5. **RSS-Safe Output**
- **RSS Max Length**: optional character cap on RSS feed excerpts, truncated safely so HTML always stays valid and properly closed
- **Slack-specific formatting** — detected automatically from the RSS request's User-Agent, no setting to toggle; other feed readers get standard HTML, unaffected by the following:
  - **Links use a minimal `<a href="URL">Text</a>` tag**, stripped down to nothing but `href` — no `target`/`rel`/`data-*` attributes, which is what let complex links leak through as raw, unparsed visible text in the first place. A literal `|` occurring naturally within link text is also replaced with a forward slash (`/`), since Slack's RSS app converts incoming HTML to its own mrkdwn internally before rendering and that conversion uses `|` as its own link delimiter
  - **Strip Links from Slack Feeds** (on by default): removes every link, leaving visible text only, even if `<a>` is otherwise allowed by Strip Tags. Also disrupts any URL-shaped plain text with an invisible Word Joiner character to stop Slack from auto-linking it — the text still reads and copies normally.
  - **Strip Empty Lines from Slack Feeds** (on by default): optionally collapses blank lines *between* top-level blocks (paragraphs, headings, blockquotes, `<hr>`, lists) down to a single newline
  - **Additional tag conversions** for better compatibility
    - `<blockquote>` → markdown-style quoted text (> prefix)
    - `<dl>/<dt>/<dd>` → *Term:* Definition format
    - `<hr>` → text separator (───)
    - `<br>` → a real line break
    - Stray `<` and `>` occurring naturally in plain text are escaped (without double-encoding any entity already present)

### 6. **Advanced List Handling**
- Set maximum list items across all lists
- Tracks nested list depth (UL/OL) and properly closes all list levels, preventing mid-list cutoffs
- **Slack-optimized list formatting** - converts HTML lists to formatted text
  - Browser-style formatting for familiar appearance
  - `<ul>` → alternating bullet styles by depth (• ◦ ▪ ▫)
  - `<ol>` → alternating numbering styles (1. a) i))
  - Proper indentation for nested lists (2 spaces per level)
  - Multi-line list items properly indented
  - Unnecessary blank lines removed between sibling list items and around a nested sublist

### 7. **Table Management**
- Smart table row tracking
- Proper closing of `<table>`, `<tr>`, `<td>`, `<th>`, `<tbody>`, `<thead>`, `<tfoot>`
- Clean table structure in excerpts

### 8. **Top Level Structure Limiting**
- Limit maximum top-level tables and lists
- Nested lists count as one structure
- All structures properly closed when limit reached

### 9. **List/Table Ellipsis**
- Separate ellipsis marker for truncated lists and tables
- For lists: displayed as a list item without bullet
- For tables: displayed as plain text below the table
- Customizable or can be disabled (leave empty)
- Works with item/row limits

### 10. **Excerpt Cut & Excerpt Only Shortcodes**
- **[excerpt_cut]**: Hide content from excerpts, show in full posts
  - Optional `text` parameter for replacement text in excerpts
  - `[excerpt_cut]hidden content[/excerpt_cut]` - removes from excerpt
  - `[excerpt_cut text="Summary..."]detailed content[/excerpt_cut]` - shows summary in excerpt
- **[excerpt_only]**: Show content only in excerpts, hide from full posts
  - Optional `text` parameter for replacement text in full posts
  - `[excerpt_only]teaser text[/excerpt_only]` - only in excerpts
  - `[excerpt_only text="Full details..."]teaser[/excerpt_only]` - swap content
- Multiple sections supported
- Nested shortcodes automatically ignored
- Unpaired `[excerpt_cut]` cuts to end of post

## ⚙️ Configuration Options

### New Advanced Settings
- **Finish**: now also offers **Block** mode alongside the original Exact/Word/Sentence options — see Block Finish Mode above
- **Skip Headers**: Remove H1-H6 content from excerpts
- **Max List Items (Total)**: Limit total list items across all nesting levels (0 = unlimited)
- **Max Top-Level List Items**: Limit only top-level list items, excludes nested items (0 = unlimited)
- **Max Top-Level Structures**: Limit tables/lists (0 = unlimited)
- **RSS Max Length (chars)**: Maximum character limit for RSS feeds (0 = unlimited) - ensures valid, properly-closed HTML even after truncation 
- **Strip Links from Slack Feeds** (on by default): removes every link from Slack's output — see RSS-Safe Output above
- **Strip Empty Lines from Slack Feeds** (on by default): collapses blank lines between top-level blocks in Slack's plain-text conversion — see RSS-Safe Output above
- **Homepage Category Filter**: Multi-select category filtering

## 💡 Usage Examples

### Basic Usage
The plugin automatically enhances all excerpts. No code changes needed!

### Advanced Template Tag
```php
<?php
the_advanced_excerpt('length=50&length_type=words&skip_headers=1&max_list_items=5');
?>
```

### Homepage Category Filtering
1. Go to Settings → Excerpt
2. Enable "Show only posts from specific categories on the homepage"
3. Select desired categories
4. Ensure Settings → Reading is set to "Your latest posts"

### Excerpt Shortcodes
Control what appears in excerpts vs full posts:

#### Basic Usage - Hide from Excerpts

```html
<p>This appears everywhere.</p>

[excerpt_cut]
<h2>This heading only appears in full post</h2>
<p>This detailed content is hidden from excerpts.</p>
[/excerpt_cut]

<p>This also appears everywhere.</p>
```

#### Advanced - Replacement Text

```html
<p>Introduction text...</p>

[excerpt_cut text="Read the full analysis in the post..."]
<h2>Detailed Analysis</h2>
<p>Five paragraphs of in-depth analysis...</p>
<ul>
  <li>Complex point 1</li>
  <li>Complex point 2</li>
</ul>
[/excerpt_cut]

<p>Conclusion...</p>
```

**Excerpt shows:** "Introduction text... Read the full analysis in the post... Conclusion..."
**Full post shows:** All content including the detailed analysis

#### Excerpt-Only Content

```html
<p>Article introduction...</p>

[excerpt_only]
<p><strong>Click to read more about this fascinating topic!</strong></p>
[/excerpt_only]

<p>Main article content continues...</p>
```

**Excerpt shows:** The teaser call-to-action
**Full post shows:** Just the main content (teaser hidden)

#### Swapping Content

```html
[excerpt_only text="<p>This article explores advanced techniques...</p>"]
<p><strong>Subscribe to read this exclusive content!</strong></p>
[/excerpt_only]

<p>Article content here...</p>
```

**Excerpt shows:** "Subscribe to read this exclusive content!"
**Full post shows:** "This article explores advanced techniques..."

**Features:**
- Multiple sections supported in one post
- `[excerpt_cut]` - hide from excerpts (with optional replacement)
- `[excerpt_only]` - show only in excerpts (with optional post replacement)
- Nested shortcodes automatically ignored
- Unpaired `[excerpt_cut]` cuts to end of post
- Works with all other excerpt features

## 📄 License

GPLv3 - Same as the original Advanced Excerpt plugin

## 👏 Credits

- **Original Plugin**: Advanced Excerpt by WPKube & basvd

## 📧 Support

For issues with the enhanced features, please open a GitHub issue.
For original plugin features, refer to the [WordPress.org plugin page](http://wordpress.org/plugins/advanced-excerpt/).

---

**⚠️ Important**: This is an enhanced, unofficial version. Always backup your site before installing any plugin.
