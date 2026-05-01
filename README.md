# Advanced Excerpt - Enhanced Edition

A significantly enhanced version of the Advanced Excerpt WordPress plugin with improved HTML handling, RSS compatibility, and powerful new features.

## 🎯 New Features

### 1. **Homepage Category Filter**
- Filter homepage posts by multiple categories
- Dynamic category selection with checkboxes
- Fully compatible with pagination
- OR logic: displays posts from ANY selected category

### 2. **Smart Tag Closing & RSS Safety**
- **No more broken HTML in excerpts!**
- Automatically tracks and closes all unclosed tags
- Maintains proper nesting structure
- **RSS-safe output** - perfect for feed readers
- Removes broken/partial tags at excerpt end (fixes Slack & other readers)
- RSS-specific line break cleanup (removes ALL `<br>` tags in feeds)
- **Slack-optimized list formatting** - converts HTML lists to formatted text
  - `<ul>` → alternating bullet styles by depth (• ◦ ▪ ▫)
  - `<ol>` → alternating numbering styles (1. a) i))
  - Proper indentation for nested lists (2 spaces per level)
  - Multi-line list items properly indented
  - Browser-style formatting for familiar appearance
  - Uses size-consistent Unicode bullets (U+2022, U+25E6, U+25AA, U+25AB)
  - Works around Slack's limited HTML support
- **Additional tag conversions** for better compatibility
  - `<blockquote>` → markdown-style quoted text (> prefix)
  - `<dl>/<dt>/<dd>` → **Term:** Definition format
  - `<hr>` → text separator (───)

### 3. **Advanced List Handling**
- Track nested list depth (UL/OL)
- Set maximum list items across all lists
- Properly closes all list levels
- Prevents mid-list cutoffs

### 4. **Table Management**
- Smart table row tracking
- Proper closing of `<table>`, `<tr>`, `<td>`, `<th>`, `<tbody>`, `<thead>`, `<tfoot>`
- Clean table structure in excerpts

### 5. **Header Content Skipping**
- Option to skip H1-H6 text content (not just the tags)
- Header text not counted toward excerpt length

### 6. **Structure Limiting**
- Limit maximum top-level tables and lists
- Nested lists count as one structure
- All structures properly closed when limit reached

### 7. **List/Table Ellipsis**
- Separate ellipsis marker for truncated lists and tables
- For lists: displayed as a list item without bullet
- For tables: displayed as plain text below the table
- Customizable or can be disabled (leave empty)
- Works with item/row limits

### 8. **Block Finish Mode**
- Stop excerpt at next block-level element after length reached
- Supports 37 block-level tags: `br`, `p`, `div`, `blockquote`, `li`, `td`, `th`, `h1-h6`, `article`, `section`, `header`, `footer`, `aside`, `nav`, `ul`, `ol`, `table`, `tr`, `pre`, `form`, `fieldset`, `dl`, `dt`, `dd`, `hr`, `figure`, `figcaption`, `main`, `address`, `details`, `summary`, `dialog`
- Default finish mode for new installations
- Creates natural-looking excerpt boundaries

### 9. **Line Break Cleanup**
- Removes multiple consecutive `<br>` tags (max 1 in regular excerpts)
- **RSS feeds**: Removes ALL `<br>` tags for better readability
- Eliminates `<br>` between block elements
- Clean, professional formatting
- No awkward spacing issues in feeds or excerpts

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

## 📊 Key Improvements

| Feature | Before | After |
|---------|--------|-------|
| Tag Closing | ❌ Often broken | ✅ Always valid |
| RSS Compatibility | ❌ Poor, broken tags | ✅ Excellent, safe HTML |
| RSS Line Breaks | ❌ Multiple `<br>` | ✅ All `<br>` removed |
| Broken Tag Cleanup | ❌ Mid-tag cutoffs | ✅ Always clean tags |
| List Handling | ❌ Basic | ✅ Advanced with depth tracking |
| Table Support | ❌ Breaks mid-row | ✅ Clean row completion |
| Line Breaks | ❌ Multiple/messy | ✅ Clean & minimal |
| Homepage Filtering | ❌ None | ✅ Multi-category support |
| Content Exclusion | ❌ None | ✅ Shortcode markers |
| Finish Modes | ❌ Exact/Word/Sentence | ✅ + Block mode (37 tags) |
| List/Table Ellipsis | ❌ Generic only | ✅ Separate customizable |

## 🚀 Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/advanced-excerpt/`
3. Activate through WordPress admin
4. Configure via Settings → Excerpt

## ⚙️ Configuration Options

### Basic Settings
- **Excerpt Length**: Words or characters
- **Text Ellipsis**: Custom text for truncation (e.g., `&hellip;`)
- **List/Table Ellipsis** (new!): Separate ellipsis for truncated lists/tables
- **Finish**: Exact, Word, Sentence, or **Block** (new!) completion
  - **Block mode**: Stops at next block-level tag or `<br>` after length reached
  - Supports 37 block-level tags including HTML5 elements
- **Read More Link**: Customizable link text

### New Advanced Settings
- **Skip Headers**: Remove H1-H6 content from excerpts
- **Max List Items (Total)**: Limit total list items across all nesting levels (0 = unlimited)
- **Max Top-Level List Items**: Limit only top-level list items, excludes nested items (0 = unlimited)
- **Max Top-Level Structures**: Limit tables/lists (0 = unlimited)
- **RSS Max Length (chars)**: Maximum character limit for RSS feeds
  - **Recommended for Slack**: 4000 characters
  - **Slack absolute max**: 40000 characters (truncated after)
  - Ensures valid HTML even after truncation
  - Set to 0 for no limit
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

## 📝 Changelog

### Version 4.4.2-fork
**Bug Fixes:**
- Fix OOM on archive/search pages: replaced `apply_filters('the_content')` with safe core formatting functions (`wptexturize`, `wpautop`, `shortcode_unautop`) to prevent plugin hooks (do_shortcode, WP_Term_Query, etc.) exhausting memory when generating excerpts for many posts at once
- `[excerpt_cut]`, `[excerpt_only]`, and `[advanced_excerpt_text]` shortcodes continue to work correctly; all third-party shortcodes are stripped without executing

**New Shortcodes:**
- `[excerpt_cut]` with optional `text` parameter for replacement content in excerpts
- `[excerpt_only]` with optional `text` parameter for replacement content in full posts
- Smart nested shortcode handling (automatically ignored)
- Multiple use cases: hide details, show teasers, swap content between excerpt/post

**RSS Feed Improvements:**
- Removes ALL `<br>` tags in RSS feeds (not just duplicates)
- Cleans up broken/partial HTML tags at excerpt end
- **Slack-friendly list conversion** - HTML lists → formatted text
  - Converts `<ul>/<li>` to bullet points (•) with line breaks
  - Converts `<ol>/<li>` to numbered lists (1., 2., 3.)
  - Handles nested lists with indentation (basic support)
  - Workaround for Slack's limited HTML tag support
  - Prevents lists from appearing as wall of text with extra line breaks
- **RSS Max Length setting** enforces character limits (recommended: 4000 for Slack)
  - Slack recommended limit: 4000 characters for optimal display
  - Slack absolute max: 40000 characters (messages truncated after)
  - Intelligently truncates at safe points (after closing tags)
  - Automatically closes all unclosed tags after truncation
- Fixes display issues in Slack and other RSS readers with length limits
- Prevents mid-tag cutoffs that show raw HTML

**Other Features:**
- Homepage category filter with multi-select
- Smart tag closing for RSS-safe excerpts
- Advanced list/table handling with limits
- List/Table ellipsis markers
- Block finish mode with 37 block-level tags
- Header content skipping
- Enhanced line break cleanup (feed-aware)
- Version upgrade detection for existing installations

### Original Version 4.4.1
- See readme.txt for original changelog

## 📄 License

GPLv3 - Same as the original Advanced Excerpt plugin

## 👏 Credits

- **Original Plugin**: Advanced Excerpt by WPKube & basvd

## 📧 Support

For issues with the enhanced features, please open a GitHub issue.
For original plugin features, refer to the [WordPress.org plugin page](http://wordpress.org/plugins/advanced-excerpt/).

---

**⚠️ Important**: This is an enhanced, unofficial version. Always backup your site before installing any plugin.
