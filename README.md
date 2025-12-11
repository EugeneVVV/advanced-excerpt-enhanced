# Advanced Excerpt - Enhanced Edition

A significantly enhanced version of the Advanced Excerpt WordPress plugin with improved HTML handling, RSS compatibility, and powerful new features.

## 🎯 New Features

### 1. **Homepage Category Filter**
- Filter homepage posts by multiple categories
- Dynamic category selection with checkboxes
- Fully compatible with pagination
- OR logic: displays posts from ANY selected category

### 2. **Smart Tag Closing**
- **No more broken HTML in excerpts!**
- Automatically tracks and closes all unclosed tags
- Maintains proper nesting structure
- **RSS-safe output** - perfect for feed readers

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
- Removes multiple consecutive `<br>` tags
- Eliminates `<br>` between block elements
- Clean, professional formatting
- No awkward spacing issues

### 10. **Excerpt Cut Markers**
- Mark sections of content to exclude from excerpts
- Use `[excerpt_cut]...[/excerpt_cut]` shortcodes
- Support for multiple cut sections per post
- Unpaired markers cut to end of post
- Markers invisible in rendered posts

## 📊 Key Improvements

| Feature | Before | After |
|---------|--------|-------|
| Tag Closing | ❌ Often broken | ✅ Always valid |
| RSS Compatibility | ❌ Poor | ✅ Excellent |
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

### Excerpt Cut Markers
Exclude specific sections from excerpts while keeping them in full posts:

```html
<p>This appears in excerpt.</p>

[excerpt_cut]
<h2>This heading is hidden from excerpts</h2>
<p>This content won't appear in excerpts.</p>
<ul>
  <li>Hidden list item 1</li>
  <li>Hidden list item 2</li>
</ul>
[/excerpt_cut]

<p>This also appears in excerpt.</p>

[excerpt_cut]
<p>Another hidden section.</p>
[/excerpt_cut]

<p>Final visible paragraph.</p>
```

**Features:**
- Multiple cut sections supported
- Unpaired `[excerpt_cut]` cuts everything to end of post
- Markers are invisible in rendered posts (no visual impact)
- Works with all other excerpt features (lists, tables, headers, etc.)

**Important Notes:**
- Nested `[excerpt_cut]` markers are ignored (treated as literal text within the cut section)
- Orphaned `[/excerpt_cut]` markers without opening tags are ignored
- This ensures predictable behavior and prevents malformed markup

## 📝 Changelog

### Version 4.4.2-fork
- Initial release

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
