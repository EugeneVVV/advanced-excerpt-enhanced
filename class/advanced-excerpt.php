<?php
class Advanced_Excerpt {
	
	private $plugin_version;
	private $plugin_file_path;
	private $plugin_dir_path;
	private $plugin_folder_name;
	private $plugin_basename;
	private $plugin_base;

	public $options;

	/*
	 * Some of the following options below are linked to checkboxes on the plugin's option page.
	 * If any checkbox options are added/removed/modified in the future please ensure you also update
	 * the $checkbox_options variable in the update_options() method.
	 */ 
	public $default_options = array(
		'length' => 40,
		'length_type' => 'words',
		'no_custom' => 1,
		'no_custom_from_custom' => 0,
		'link_excerpt' => 0,
		'no_shortcode' => 1,
		'finish' => 'block',
		'ellipsis' => '&hellip;',
		'list_ellipsis' => '',
		'read_more' => 'Read the rest',
		'add_link' => 0,
		'link_new_tab' => 0,
		'link_screen_reader' => 0,
		'link_exclude_length' => 0,
		'link_on_custom_excerpt' => 0,
		'allowed_tags' => array(),
		'the_excerpt' => 1,
		'the_content' => 1,
		'the_content_no_break' => 0,
		'exclude_pages' => array(),
		'allowed_tags_option' => 'dont_remove_any',
		'homepage_categories' => array(),
		'enable_homepage_category_filter' => 0,
		'max_list_items' => 0,
		'max_top_level_list_items' => 0,
		'max_top_level_structures' => 0,
		'skip_headers' => 0,
		'rss_max_length' => 0,
		'strip_links_slack' => 1,
		'strip_empty_lines_slack' => 1,
	);

	public $options_basic_tags; // Basic HTML tags (determines which tags are in the checklist by default)
	public $options_all_tags; // Almost all HTML tags (extra options)
	public $filter_type; // Determines wether we're filtering the_content or the_excerpt at any given time

	// HTML5 void elements: never need (or permit) a closing tag, even when
	// written without a trailing "/>" (e.g. plain <hr>, as Gutenberg emits
	// it). Shared by the main tag-balancing loop in text_excerpt() and the
	// truncation logic in enforce_rss_max_length() so the two can't drift
	// out of sync with each other again.
	public $void_elements = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' );

	function __construct( $plugin_file_path ) {
		$this->load_options();

		$this->plugin_version = $GLOBALS['advanced_excerpt_version'];
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_dir_path = plugin_dir_path( $plugin_file_path );
		$this->plugin_folder_name = basename( $this->plugin_dir_path );
		$this->plugin_basename = plugin_basename( $plugin_file_path );
		$this->plugin_base ='options-general.php?page=advanced-excerpt';

		if ( isset($_SERVER['REQUEST_METHOD']) && 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_REQUEST['page'] ) && 'advanced-excerpt' === $_REQUEST['page'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to manage options for this site.', 'advanced-excerpt' ) );
			}
			check_admin_referer( 'advanced_excerpt_update_options' );
			$this->update_options();
		}

		$this->options_basic_tags = apply_filters( 'advanced_excerpt_basic_tags', array(
			'a', 'abbr', 'acronym', 'address', 'article', 'aside', 'audio', 'b', 'big',
			'blockquote', 'br', 'canvas', 'center', 'cite', 'code', 'dd', 'del', 'div', 'dl', 'dt',
			'em', 'embed', 'form', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img', 'ins',
			'li', 'nav', 'ol', 'p', 'pre', 'q', 's', 'section', 'small', 'span', 'strike', 'strong', 'sub',
			'sup', 'svg', 'table', 'td', 'template', 'th', 'time', 'tr', 'u', 'ul', 'video'
		) );

		$this->options_all_tags = apply_filters( 'advanced_excerpt_all_tags', array(
			'a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'bdi', 'bdo', 'big',
			'blockquote', 'br', 'button', 'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'data',
			'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption',
			'figure', 'font', 'footer', 'form', 'frame', 'frameset', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr',
			'i', 'iframe', 'img', 'input', 'ins', 'isindex', 'kbd', 'keygen', 'label', 'legend', 'li', 'main', 'map',
			'mark', 'math', 'menu', 'menuitem', 'meter', 'nav', 'noframes', 'noscript', 'object', 'ol', 'optgroup',
			'option', 'output', 'p', 'param', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script', 'section',
			'select', 'small', 'source', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'svg', 'table',
			'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'tr', 'track', 'tt', 'u', 'ul', 'var',
			'video', 'wbr'
		) );

		if ( is_admin() ) {
			$this->admin_init();
		}

		add_action( 'loop_start', array( $this, 'hook_content_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_homepage_category' ) );

		// Register excerpt shortcodes (return empty string so markers don't appear in post display)
		add_shortcode( 'excerpt_cut', array( $this, 'excerpt_cut_shortcode' ) );
		add_shortcode( 'excerpt_only', array( $this, 'excerpt_only_shortcode' ) );
	}

	function hook_content_filters() {
		/*
		 * Allow developers to skip running the advanced excerpt filters on certain page types.
		 * They can do so by using the "Disable On" checkboxes on the options page or 
		 * by passing in an array of page types they'd like to skip
		 * e.g. array( 'search', 'author' );
		 * The filter, when implemented, takes precedence over the options page selection.
		 *
		 * WordPress default themes (and others) do not use the_excerpt() or get_the_excerpt()
		 * and instead use the_content(). As such, we also need to hook into the_content().
		 * To ensure we're not changing the content of single posts / pages we automatically exclude 'singular' page types.
		 */

        add_filter( 'wppsac_excerpt', array( $this, 'filter_content' ) );

		$page_types = $this->get_current_page_types();
		$skip_page_types = array_unique( array_merge( array( 'singular' ), $this->options['exclude_pages'] ) );
		$skip_page_types = apply_filters( 'advanced_excerpt_skip_page_types', $skip_page_types ); 
		$page_type_matches = array_intersect( $page_types, $skip_page_types );
		if ( !empty( $page_types ) && !empty( $page_type_matches ) ) return;

		// skip woocommerce products
		if ( in_array( 'woocommerce', $skip_page_types ) && get_post_type( get_the_ID() ) == 'product' ) {
			return;
		}

        // conflict with WPTouch
        if ( function_exists( 'wptouch_is_mobile_theme_showing' ) && wptouch_is_mobile_theme_showing() ) {
            return;
        }

        // skip bbpress
        if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
            return;
        }

		if ( 1 == $this->options['the_excerpt'] ) {
			remove_all_filters( 'get_the_excerpt' );
			remove_all_filters( 'the_excerpt' );
			add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ) );
		}

		if ( 1 == $this->options['the_content'] ) {
			add_filter( 'the_content', array( $this, 'filter_content' ) );
		}
	}

	function admin_init() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'plugin_action_links' ) );
	}

	function load_options() {
		/* 
		 * An older version of this plugin used to individually store each of it's options as a row in wp_options (1 row per option).
		 * The code below checks if their installations once used an older version of this plugin and attempts to update
		 * the option storage to the new method (all options stored in a single row in the DB as an array)
		*/
		$update_options = false;
		$update_from_legacy = false;
		if ( false !== get_option( 'advancedexcerpt_length' ) ) {
			$legacy_options = array( 'length', 'use_words', 'no_custom', 'no_shortcode', 'finish_word', 'finish_sentence', 'ellipsis', 'read_more', 'add_link', 'allowed_tags' );

			foreach ( $legacy_options as $legacy_option ) {
				$option_name = 'advancedexcerpt_' . $legacy_option;
				$this->options[$legacy_option] = get_option( $option_name );
				delete_option( $option_name );
			}

			// filtering the_content() is disabled by default when migrating from version 4.1.1 of the plugin
			$this->options['the_excerpt'] = 1;
			$this->options['the_content'] = 0;

			$update_options = true;
			$update_from_legacy = true;
		} else {
			$this->options = get_option( 'advanced_excerpt' );
		}

		// convert legacy option use_words to it's udpated equivalent
		if ( isset( $this->options['use_words'] ) ) {
			$this->options['length_type'] = ( 1 == $this->options['use_words'] ) ? 'words' : 'characters';
			unset( $this->options['use_words'] );
			$update_options = true;
		}

		// convert legacy options finish_word & finish_sentence to their udpated equivalents
		if ( isset( $this->options['finish_sentence'] ) ) {
			if ( 0 == $this->options['finish_word'] && 0 == $this->options['finish_sentence'] ) {
				$this->options['finish'] = 'exact';
			} else if ( 1 == $this->options['finish_word'] && 1 == $this->options['finish_sentence'] ) {
				$this->options['finish'] = 'sentence';
			} else if ( 0 == $this->options['finish_word'] && 1 == $this->options['finish_sentence'] ) {
				$this->options['finish'] = 'sentence';
			} else {
				$this->options['finish'] = 'word';
			}
			unset( $this->options['finish_word'] );
			unset( $this->options['finish_sentence'] );
			$update_options = true;
		}

		// convert legacy option '_all' in the allowed_tags option to it's updated equivalent
		if ( isset( $this->options['allowed_tags'] ) ) {
			if ( false !== ( $all_key = array_search( '_all', $this->options['allowed_tags'] ) ) ) {
				unset( $this->options['allowed_tags'][$all_key] );
				$this->options['allowed_tags_option'] = 'dont_remove_any';
			} elseif( $update_from_legacy ) {
				$this->options['allowed_tags_option'] = 'remove_all_tags_except';
			}
		}

		// if no options exist then this is a fresh install, set up some default options
		if ( empty( $this->options ) ) {
			$this->options = $this->default_options;
			$update_options = true;
		}

		$this->options = wp_parse_args( $this->options, $this->default_options );

		// Check if we need to upgrade from an older version
		// This ensures new options from fork versions are added to existing installations
		$saved_version = get_option( 'advanced_excerpt_version' );
		if ( $saved_version !== $GLOBALS['advanced_excerpt_version'] ) {
			// Version changed - save merged options to include any new defaults
			$update_options = true;
			update_option( 'advanced_excerpt_version', $GLOBALS['advanced_excerpt_version'] );
		}

		if ( $update_options ) {
			update_option( 'advanced_excerpt', $this->options );
		}
	}

	function add_pages() {
		$options_page = add_options_page( __( "Advanced Excerpt Options", 'advanced-excerpt' ), __( "Excerpt", 'advanced-excerpt' ), 'manage_options', 'advanced-excerpt', array( $this, 'page_options' ) );
		// Scripts
		add_action( 'admin_print_scripts-' . $options_page, array( $this, 'page_assets' ) );
	}

	function page_assets() {
		$version = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? time() : $this->plugin_version;
		$plugins_url = trailingslashit( plugins_url() ) . trailingslashit( $this->plugin_folder_name );

		// css
		$src = $plugins_url . 'asset/css/styles.css';
		wp_enqueue_style( 'advanced-excerpt-styles', $src, array(), $version );

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// js
		$src = $plugins_url . 'asset/js/advanced-excerpt' . $suffix . '.js';
		wp_enqueue_script( 'advanced-excerpt-script', $src, array( 'jquery' ), $version, true );
	}

	function plugin_action_links( $links ) {
		$link = sprintf( '<a href="%s">%s</a>', admin_url( $this->plugin_base ), __( 'Settings', 'advanced-excerpt' ) );
		array_unshift( $links, $link );
		return $links;
	}

	function filter_content( $content ) {
		$this->filter_type = 'content';
		return $this->filter( $content );
	}

	function filter_excerpt( $content ) {
		$this->filter_type = 'excerpt';
		return $this->filter( $content );
	}

	function filter( $content ) {

		extract( wp_parse_args( $this->options, $this->default_options ), EXTR_SKIP );
		
		if ( true === apply_filters( 'advanced_excerpt_skip_excerpt_filtering', false ) ) {
			return $content;
        }
        
        if ( is_post_type_archive( 'tribe_events' ) ) {
            return $content;
        }

		global $post;
		if ( $the_content_no_break && false !== strpos( $post->post_content, '<!--more-->' ) && 'content' == $this->filter_type ) {
			return $content;
		}

		// Avoid custom excerpts
		if ( !empty( $content ) && !$no_custom ) {
			if ( ! $no_custom_from_custom ) {
				if ( $link_on_custom_excerpt ) {
					return $this->text_add_more( $content, '', ( $add_link ) ? $read_more : false, ( $link_new_tab ) ? true : false, ( $link_screen_reader ) ? true : false );
				}	
				return $content; 
			}
		}

		$text = get_the_content( '' );

		// Remove excerpt cut sections BEFORE any other processing
		$text = $this->remove_excerpt_cut_sections( $text );

		// generate excerpt from the "custom excerpt" (only if there is a "custom excerpt" )
		if ( $no_custom && $no_custom_from_custom && ! empty( trim( $post->post_excerpt ) ) ) {
			$text = $post->post_excerpt;
		}

		// Skip apply_filters('the_content') entirely — it invokes plugin hooks
		// (do_shortcode, term queries, etc.) that exhaust memory on archive/search
		// pages where excerpts are generated for many posts at once.
		// Replicate only the core steps that are safe and necessary for excerpts.

		// For Gutenberg content, extract inner HTML from blocks without executing
		// render callbacks. do_blocks() triggers all block render_callbacks which
		// can exhaust memory when generating excerpts for many posts on archive/search
		// pages. Stripping block delimiter comments preserves the saved inner HTML
		// (static blocks like paragraph/heading/list) while producing empty strings
		// for dynamic blocks (acceptable in excerpt context). Classic editor content
		// has no block delimiters so is unaffected.
		$text = preg_replace( '/<!--\s*\/?wp:[^>]*-->/i', '', $text );

		// [excerpt_cut] and [excerpt_only] are already resolved by
		// remove_excerpt_cut_sections() above. Execute only [advanced_excerpt_text]
		// (the lang-filter shortcode) so its content is included/excluded correctly
		// before strip_shortcodes() removes all remaining shortcode tags.
		global $shortcode_tags;
		$saved_shortcodes = $shortcode_tags;
		$shortcode_tags    = array_intersect_key(
			$shortcode_tags,
			array( 'advanced_excerpt_text' => true )
		);
		$text           = do_shortcode($text);
		$shortcode_tags = $saved_shortcodes;

		$text = strip_shortcodes($text);
		$text = wptexturize($text);
		$text = wpautop($text);
		$text = shortcode_unautop($text);

		// From the default wp_trim_excerpt():
		// Some kind of precaution against malformed CDATA in RSS feeds I suppose
		$text = str_replace( ']]>', ']]&gt;', $text );

		if ( empty( $allowed_tags ) ) {
			$allowed_tags = array();
		}

		// the $exclude_tags args takes precedence over the $allowed_tags args (only if they're both defined)
		if ( ! empty( $exclude_tags ) ) {
			$allowed_tags = array_diff( $this->options_all_tags, $exclude_tags );
		}

		// Strip HTML if $allowed_tags_option is set to 'remove_all_tags_except'
		if ( 'remove_all_tags_except' === $allowed_tags_option ) {
			if ( count( $allowed_tags ) > 0 ) {
				$tag_string = '<' . implode( '><', $allowed_tags ) . '>';
			} else {
				$tag_string = '';
			}

			$text = strip_tags( $text, $tag_string );
		}

		// Drop <a> tags that aren't a real, followable link, regardless of
		// which Strip Tags mode is in use — applies to every excerpt (site
		// display and RSS, standard or Slack alike). A no-op whenever "a"
		// isn't allowed in the first place, since strip_tags() above (or the
		// 'dont_remove_any' default) is what determines whether any <a> tags
		// reach this point at all.
		$text = $this->sanitize_anchor_tags( $text );

		$text_before_trimming = $text;

		// Create the excerpt
		$text = $this->text_excerpt( $text, $length, $length_type, $finish );

		// lengths
		$text_length_before = strlen( trim( $text_before_trimming ) );
		$text_length_after = strlen( trim( $text ) );

		// Add the ellipsis or link
		if ( ! apply_filters( 'advanced_excerpt_disable_add_more', false, $text_before_trimming, $this->options ) ) {
			if ( ! $link_exclude_length || $text_length_after < $text_length_before ) {
				$text = $this->text_add_more( $text, $ellipsis, ( $add_link ) ? $read_more : false, ( $link_new_tab ) ? true : false, ( $link_screen_reader ) ? true : false );
			}
		}

		if ( $link_excerpt ) {
			$text = '<a href="' . get_permalink( $post ) . '">' . $text . '</a>';
		}

		return apply_filters( 'advanced_excerpt_content', $text );

	}

	/**
	 * Removes <a> tags that aren't a real, followable link — no href at all
	 * (e.g. a bare <a name="..."> in-page anchor), a same-page fragment
	 * (meaningless once the content is lifted out of the full page into an
	 * excerpt), or a non-navigable pseudo-scheme like javascript:/data:/
	 * vbscript: that only works via script execution inside a browser.
	 *
	 * The wrapping <a> tag is unwrapped rather than removing its content —
	 * the visible text (and any other formatting tags inside it) is kept, the
	 * same way strip_tags() above already treats any other disallowed tag.
	 * A genuine link's other attributes (target, rel, class, etc.) are left
	 * untouched; this only judges whether the href itself is worth keeping.
	 *
	 * Runs for every excerpt — site display and RSS, standard or Slack alike
	 * — independent of the Strip Tags setting: if "a" isn't in the allowed
	 * tags list, strip_tags() has already removed every <a> tag before this
	 * ever runs, so this is naturally a no-op in that case.
	 *
	 * @param string $text Text with HTML
	 * @return string Text with only real, navigable <a> tags kept
	 */
	function sanitize_anchor_tags( $text ) {
		return preg_replace_callback(
			'/<a\s+([^>]*)>(.*?)<\/a>/is',
			function( $matches ) {
				$attrs = $matches[1];
				$inner = $matches[2];

				if ( ! preg_match( '/href\s*=\s*["\']([^"\']*)["\']/i', $attrs, $href_match ) ) {
					// No href at all - not a real link.
					return $inner;
				}

				$href = trim( $href_match[1] );

				if ( '' === $href || '#' === substr( $href, 0, 1 ) ) {
					// Empty, or a same-page fragment.
					return $inner;
				}

				if ( preg_match( '/^\s*(?:javascript|data|vbscript)\s*:/i', $href ) ) {
					// Not a real destination outside a browser executing script.
					return $inner;
				}

				return $matches[0];
			},
			$text
		);
	}

	/**
	 * Remove the innermost (last) occurrence of $tag_name from a tag stack,
	 * mirroring how a real HTML parser matches a closing tag to its nearest
	 * unclosed opener. Shared by every closing-tag case in text_excerpt()'s
	 * tokenizer loop and by enforce_rss_max_length()'s own tag-balancing
	 * pass, so the same LIFO-matching behavior can't drift out of sync
	 * between the two places that need it.
	 *
	 * @param array  $tag_stack Tag stack, passed by reference
	 * @param string $tag_name  Tag name to remove the last occurrence of
	 */
	function remove_from_tag_stack( array &$tag_stack, $tag_name ) {
		for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
			if ( $tag_stack[$i] == $tag_name ) {
				array_splice( $tag_stack, $i, 1 );
				break;
			}
		}
	}

	function text_excerpt( $text, $length, $length_type, $finish ) {
		$tokens = array();
		$out = '';
		$w = 0;

		// Track HTML structure
		$tag_stack = array(); // Track open tags that need closing
		$list_stack = array(); // Track nested lists (ul/ol)
		$list_item_count = 0; // Total list items across all lists
		$top_level_list_item_count = 0; // Top-level list items only (not nested)
		$top_level_structures = 0; // Count of top-level tables and lists
		$in_header = false; // Are we inside a header tag?
		$in_table = false; // Are we inside a table?
		$table_row_count = 0; // Count rows in current table
		$looking_for_block_end = false; // For 'block' finish mode
		$truncated_list_or_table = false; // Track if we truncated due to list/table limits

		$max_list_items = isset( $this->options['max_list_items'] ) ? (int) $this->options['max_list_items'] : 0;
		$max_top_level_list_items = isset( $this->options['max_top_level_list_items'] ) ? (int) $this->options['max_top_level_list_items'] : 0;
		$max_structures = isset( $this->options['max_top_level_structures'] ) ? (int) $this->options['max_top_level_structures'] : 0;
		$skip_headers = isset( $this->options['skip_headers'] ) ? (int) $this->options['skip_headers'] : 0;
		$list_ellipsis = isset( $this->options['list_ellipsis'] ) ? $this->options['list_ellipsis'] : '';

		// Divide the string into tokens; HTML tags, or words, followed by any whitespace.
		// The tag alternative requires a letter/digit immediately after "<" (or "</"),
		// same as the tag-name shape checked below and in strip_recognized_tags_only().
		// Without that requirement, a bare "<" in plain text (e.g. "Value < $50") would
		// still match "<[^>]+>" by greedily running forward to the *next* literal ">" in
		// the string - which can belong to a real tag further along (like a following
		// </a>) - swallowing that real closing tag into what looks like one bogus token.
		// Since the tag-name regex below isn't anchored to the token's start, it could
		// then find that swallowed "</a>" shape inside the blob and misidentify the
		// whole thing as an opening <a>, leaving a stray, unmatched closing tag stacked
		// up for the real one and duplicate "</a>" fragments auto-appended at the end.
		// A lone "<" or ">" that isn't part of a tag-shaped token falls to the final
		// alternative and is kept as a single literal character instead.
		preg_match_all( '/(<\/?[a-zA-Z0-9]+(?:\s[^<>]*)?\/?>|[^<>\s]+|[<>])\s*/u', $text, $tokens );

		foreach ( $tokens[0] as $t ) {
			// Check if we've reached limits
			if ( $w >= $length && 'sentence' != $finish && 'block' != $finish ) {
				break;
			}

			// For block finish mode, activate looking for block end when length exceeded
			if ( $w >= $length && 'block' == $finish && ! $looking_for_block_end ) {
				$looking_for_block_end = true;
			}

			if ( $t[0] == '<' ) { // Token is a tag
				// Only a genuine, recognized HTML tag name is treated as
				// real markup for tag-balancing/tracking purposes below.
				// Casual bracket notation in plain text (e.g. "<Free>",
				// "<New>") matches this token's shape too — a letter
				// immediately after "<" — but isn't real markup. Treating
				// it as an opening tag needing a closing counterpart used
				// to invent an invalid closing tag (e.g. "</free>") at the
				// end of the excerpt and silently swallow the bracketed
				// word along the way. $this->options_all_tags is the same
				// allowed-tags list already used elsewhere, so an
				// unrecognized name here is never mistaken for a real tag.
				$has_tag_shaped_name = preg_match( '/<\/?([a-zA-Z0-9]+)/', $t, $tag_match );
				$is_recognized_tag = $has_tag_shaped_name && in_array( strtolower( $tag_match[1] ), (array) $this->options_all_tags );

				// In block finish mode, check for br, block tags, or the end of an inline element
				if ( $looking_for_block_end ) {
					// Check for <br> tag
					if ( preg_match( '/<br\s*\/?>/i', $t ) ) {
						$out .= $t;
						break;
					}

					// Block-level tags stop the excerpt whether they're opening or closing
					// (an opening tag here means a new block is starting, so this is also
					// a safe place to stop). Inline tags (a, strong, em, etc.) only stop the
					// excerpt once they're fully closed, so we never cut their content short
					// (e.g. a link's visible text) or leave a run of inline elements
					// unbounded while waiting for a distant block boundary.
					$block_tags = array( 'p', 'div', 'blockquote', 'li', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'article', 'section', 'header', 'footer', 'aside', 'nav', 'ul', 'ol', 'table', 'tr', 'pre', 'form', 'fieldset', 'dl', 'dt', 'dd', 'hr', 'figure', 'figcaption', 'main', 'address', 'details', 'summary', 'dialog' );
					$inline_stop_tags = array( 'a', 'strong', 'b', 'em', 'i', 'span', 'code', 'mark', 'small', 'sub', 'sup', 'u', 's', 'abbr', 'cite', 'q' );

					if ( $is_recognized_tag ) {
						$block_tag = strtolower( $tag_match[1] );
						$is_closing_tag = ( strpos( $t, '</' ) === 0 );
						$is_self_closing_tag = ( strpos( $t, '/>' ) !== false );

						if ( in_array( $block_tag, $block_tags ) ) {
							// Keep the tag stack in sync so the end-of-loop cleanup doesn't
							// also try to close it (if it's a closing tag) or leave it
							// permanently unclosed (if it's a fresh opening tag).
							if ( $is_closing_tag ) {
								$this->remove_from_tag_stack( $tag_stack, $block_tag );
							} elseif ( ! $is_self_closing_tag && $block_tag != 'hr' ) {
								array_push( $tag_stack, $block_tag );
							}
							$out .= $t;
							break;
						}

						if ( $is_closing_tag && in_array( $block_tag, $inline_stop_tags ) ) {
							$this->remove_from_tag_stack( $tag_stack, $block_tag );
							$out .= $t;
							break;
						}
					}
				}
				// Parse tag name
				if ( $is_recognized_tag ) {
					$tag_name = strtolower( $tag_match[1] );
					$is_closing = ( strpos( $t, '</' ) === 0 );
					// HTML5 void elements (br, hr, img, etc.) never need a
					// closing tag even when written without a trailing "/>"
					// (e.g. plain <hr>, as Gutenberg emits it) — without this,
					// they get pushed onto the tag stack like a normal
					// element and later auto-closed with an invalid </hr>.
					$is_self_closing = ( strpos( $t, '/>' ) !== false || in_array( $tag_name, $this->void_elements ) );

					// Handle header tags
					if ( in_array( $tag_name, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) {
						if ( ! $is_closing ) {
							$in_header = true;
						} else {
							$in_header = false;
						}
						// Add tag to output regardless (for formatting), but skip content if option enabled
						$out .= $t;
						continue;
					}

					// Handle list start tags
					if ( in_array( $tag_name, array( 'ul', 'ol' ) ) && ! $is_closing ) {
						// Check if this is a top-level list
						if ( empty( $list_stack ) ) {
							$top_level_structures++;
							if ( $max_structures > 0 && $top_level_structures > $max_structures ) {
								$truncated_list_or_table = true;
								break; // Exceeded max structures
							}
						}
						array_push( $list_stack, $tag_name );
						array_push( $tag_stack, $tag_name );
						$out .= $t;
						continue;
					}

					// Handle list end tags
					if ( in_array( $tag_name, array( 'ul', 'ol' ) ) && $is_closing ) {
						if ( ! empty( $list_stack ) && end( $list_stack ) == $tag_name ) {
							array_pop( $list_stack );
							$this->remove_from_tag_stack( $tag_stack, $tag_name );
						}
						$out .= $t;
						continue;
					}

					// Handle list items
					if ( $tag_name == 'li' ) {
						if ( ! $is_closing ) {
							$list_item_count++;

							// Check if this is a top-level list item (list_stack has only 1 element)
							$is_top_level_item = ( count( $list_stack ) == 1 );

							if ( $is_top_level_item ) {
								$top_level_list_item_count++;
								if ( $max_top_level_list_items > 0 && $top_level_list_item_count > $max_top_level_list_items ) {
									$truncated_list_or_table = true;
									break; // Exceeded max top-level list items
								}
							}

							if ( $max_list_items > 0 && $list_item_count > $max_list_items ) {
								$truncated_list_or_table = true;
								break; // Exceeded max total list items
							}
							array_push( $tag_stack, 'li' );
						} else {
							$this->remove_from_tag_stack( $tag_stack, 'li' );
						}
						$out .= $t;
						continue;
					}

					// Handle table start
					if ( $tag_name == 'table' && ! $is_closing ) {
						$in_table = true;
						$table_row_count = 0;
						$top_level_structures++;
						if ( $max_structures > 0 && $top_level_structures > $max_structures ) {
							$truncated_list_or_table = true;
							break; // Exceeded max structures
						}
						array_push( $tag_stack, 'table' );
						$out .= $t;
						continue;
					}

					// Handle table end
					if ( $tag_name == 'table' && $is_closing ) {
						$in_table = false;
						$this->remove_from_tag_stack( $tag_stack, 'table' );
						$out .= $t;
						continue;
					}

					// Handle table rows
					if ( $tag_name == 'tr' ) {
						if ( ! $is_closing ) {
							$table_row_count++;
							// Table rows count toward top-level list items limit
							if ( $max_top_level_list_items > 0 && $table_row_count > $max_top_level_list_items ) {
								$truncated_list_or_table = true;
								break; // Exceeded max rows
							}
							array_push( $tag_stack, 'tr' );
						} else {
							$this->remove_from_tag_stack( $tag_stack, 'tr' );
						}
						$out .= $t;
						continue;
					}

					// Handle all other tags, including the remaining table
					// elements (td, th, tbody, thead, tfoot) — no special
					// casing needed for those; they just push/pop like any
					// other non-void element.
					if ( ! $is_closing && ! $is_self_closing ) {
						array_push( $tag_stack, $tag_name );
					} elseif ( $is_closing ) {
						$this->remove_from_tag_stack( $tag_stack, $tag_name );
					}
				}

				$out .= $t;

			} else { // Token is not a tag - it's text content
				// Skip header content if option is enabled
				if ( $skip_headers && $in_header ) {
					continue;
				}

				$t_trimmed = trim( $t );
				if ( $w >= $length && 'sentence' == $finish && preg_match( '/[\?\.\!](?!\d).*$/uS', $t_trimmed ) == 1 ) {
					$out .= trim( $t );
					break;
				}

				if ( 'words' == $length_type ) {
					$w++;
				} else {
					if ( $finish == 'exact_w_spaces' ) {
						$chars = $t;
					} else {
						$chars = trim( $t );
					}
					$c = mb_strlen( $chars );
					if ( $c + $w > $length && 'sentence' != $finish ) {
						$c = ( 'word' == $finish ) ? $c : $length - $w;
						$t = mb_substr( $t, 0, $c );
					}
					$w += $c;
				}

				$out .= $t;
			}
		}

		// Add list/table ellipsis if truncated and option is set
		if ( $truncated_list_or_table && ! empty( $list_ellipsis ) ) {
			// Determine if we're in a list or table by checking the tag stack
			$in_list_context = false;
			$in_table_context = false;

			foreach ( $tag_stack as $tag ) {
				if ( in_array( $tag, array( 'ul', 'ol' ) ) ) {
					$in_list_context = true;
					break;
				}
				if ( $tag == 'table' ) {
					$in_table_context = true;
					break;
				}
			}

			if ( $in_list_context ) {
				// Add as a list item with no bullet point (using CSS class)
				$out .= '<li class="excerpt-ellipsis" style="list-style-type: none;">' . $list_ellipsis . '</li>';
			} elseif ( $in_table_context ) {
				// Close table first, then add as plain text below
				while ( ! empty( $tag_stack ) ) {
					$tag = array_pop( $tag_stack );
					$out .= '</' . $tag . '>';
				}
				// Add ellipsis as plain text with line break after the closed table
				$out .= '<div class="excerpt-ellipsis">' . $list_ellipsis . '</div><br />';
				// Clear tag stack since we already closed everything
				$tag_stack = array();
			}
		}

		// Close any unclosed tags in reverse order
		while ( ! empty( $tag_stack ) ) {
			$tag = array_pop( $tag_stack );
			$out .= '</' . $tag . '>';
		}

		// Remove header tags left completely empty by the Skip Headers option
		// (it removes the header's text but, by design, keeps the tag pair
		// itself so heading structure/formatting is preserved). An empty
		// heading is invisible on the website either way, but leaving the
		// tag pair in place blocks the newline runs Gutenberg puts between
		// blocks from merging into one, so they can't be collapsed down to a
		// single blank line below — and Slack appears to render the empty
		// tag as a block of its own, stacking extra visible blank lines
		// around it. Removing the tag entirely merges those runs so the
		// collapse in convert_lists_for_slack() (or whatever the destination
		// otherwise does with runs of whitespace) sees one gap, not two.
		$out = preg_replace( '/<h[1-6][^>]*>\s*<\/h[1-6]>/i', '', $out );

		// Clean up multiple line breaks and unnecessary br tags
		$out = $this->cleanup_line_breaks( $out );

		// Ensure no broken/partial tags at the end of excerpt
		$out = $this->cleanup_broken_tags( $out );

		// Convert HTML lists and other unsupported tags to Slack-friendly format
		// Only apply for Slack requests - other RSS readers handle HTML properly
		if ( is_feed() && $this->is_slack_request() ) {
			// Slack has no HTML support at all - a <br> that reached it as a
			// literal tag would show up as visible "<br>" text, not a line
			// break. Not optional, unlike the settings below: this converts
			// any <br> that survived cleanup_line_breaks() into a real
			// newline, before anything else (list/tag conversion below)
			// would otherwise just delete the tag outright via
			// strip_recognized_tags_only() and silently lose the line break.
			$out = $this->convert_br_to_newline_for_slack( $out );

			// Gutenberg occasionally fragments a single link into two (or
			// more) adjacent <a> tags sharing the same href but splitting
			// its visible text mid-word - a known editor artifact, not
			// something a post author intends. Left alone, each fragment
			// becomes its own separate link, visibly breaking the text
			// apart. Merging them back into one logical link here applies
			// equally to both branches below (native mrkdwn links and
			// Strip Links), since both would otherwise show the same split.
			$out = $this->merge_adjacent_same_href_links( $out );

			$strip_links_slack = ! empty( $this->options['strip_links_slack'] );

			if ( $strip_links_slack ) {
				// Discard every <a> entirely (visible text only, no href) even
				// if "a" is otherwise allowed by the Strip Tags setting - this
				// is a Slack-specific override, independent of that setting.
				$out = $this->strip_links_for_slack( $out );
			} else {
				// Protect <a href="..."> links with angle-bracket-free placeholders first,
				// since both the list and other-tag conversions below use strip_tags() on
				// their content and would otherwise silently discard the href along with
				// the rest of the markup.
				$out = $this->protect_links_for_slack( $out );
			}

			$out = $this->convert_lists_for_slack( $out );
			$out = $this->convert_other_tags_for_slack( $out );

			if ( $strip_links_slack ) {
				// Every <a> is already gone, but the visible text (or plain
				// text elsewhere in the post) can still contain a bare URL
				// that Slack would auto-link (and unfurl) on its own -
				// disrupt anything URL-shaped so it no longer reads as a
				// link to Slack's own auto-detection either.
				$out = $this->disable_url_autolink_for_slack( $out );
			} else {
				$out = $this->restore_links_for_slack( $out );
			}

			// Collapse every remaining blank-line gap down to a single
			// newline. Gutenberg's own block markup routinely leaves blank
			// lines between paragraphs/headings (and the blockquote and <hr>
			// conversions above add their own leading/trailing newline the
			// same way list conversion used to), so without this, ordinary
			// multi-paragraph posts still carry a blank line between every
			// block even though nothing in this pipeline needs one anymore.
			// Runs last, after every conversion that could introduce a gap.
			// Optional (off by default): blank-line spacing *within* a list
			// (between sibling items, and between an item's own text and a
			// nested sublist) is a separate, always-on cleanup handled
			// unconditionally inside convert_nested_lists() above - this
			// setting only controls spacing *between* top-level blocks.
			if ( ! empty( $this->options['strip_empty_lines_slack'] ) ) {
				$out = preg_replace( '/\n{2,}/', "\n", $out );
			}
		}

		// Enforce RSS max length if in feed and limit is set
		if ( is_feed() && isset( $this->options['rss_max_length'] ) && $this->options['rss_max_length'] > 0 ) {
			$out = $this->enforce_rss_max_length( $out, $this->options['rss_max_length'] );
		}

		return trim( $out );
	}

	function cleanup_line_breaks( $text ) {
		// Collapse a run of consecutive <br> tags down to one - a single
		// <br> is valid, meaningful markup that any HTML-capable RSS reader
		// renders fine, so there's no reason to strip it just because this
		// is a feed. Slack is the one consumer that can't render a <br> as
		// anything but literal visible text; that's handled separately,
		// downstream, by convert_br_to_newline_for_slack() converting any
		// surviving <br> into a real newline before Slack ever sees it.
		$text = preg_replace( '/<br\s*\/?>\s*(?:<br\s*\/?>\s*)+/i', '<br />', $text );

		// Remove <br> that appears right before block-level closing tags
		$block_tags = array( 'p', 'div', 'blockquote', 'li', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'article', 'section', 'header', 'footer', 'aside', 'nav' );
		foreach ( $block_tags as $tag ) {
			$text = preg_replace( '/<br\s*\/?>\s*<\/' . $tag . '>/i', '</' . $tag . '>', $text );
		}

		// Remove <br> that appears right after block-level opening tags
		foreach ( $block_tags as $tag ) {
			$text = preg_replace( '/<' . $tag . '([^>]*)>\s*<br\s*\/?>/i', '<' . $tag . '$1>', $text );
		}

		// Remove <br> between block-level tags (e.g., </p><br><p> becomes </p><p>)
		$text = preg_replace( '/<\/(p|div|blockquote|h[1-6]|article|section|header|footer|aside|nav)>\s*<br\s*\/?>\s*<(p|div|blockquote|h[1-6]|article|section|header|footer|aside|nav)/i', '</$1><$2', $text );

		return $text;
	}

	/**
	 * Remove broken/partial HTML tags at the end of excerpt
	 * Fixes issue where RSS readers with length limits cut mid-tag
	 *
	 * @param string $text Excerpt text
	 * @return string Text with broken tags removed
	 */
	function cleanup_broken_tags( $text ) {
		// Find the last complete closing tag position
		// Look for any incomplete tag at the end (starts with < but doesn't close with >)
		if ( preg_match( '/^(.*>)(<[^>]*)$/s', $text, $matches ) ) {
			// Found incomplete tag at end - remove it
			$text = $matches[1];
		}

		// Also check for broken opening tags that might have partial attributes
		// Pattern: <tagname some-attr="partial
		// This handles cases where tag was cut mid-attribute
		$text = preg_replace( '/<([a-zA-Z0-9]+)(?:\s+[^>]*)?$/s', '', $text );

		return $text;
	}

	/**
	 * Escapes literal "<" and ">" occurring naturally in plain text (always
	 * called after strip_tags() has already reduced content to text only)
	 * before it reaches Slack. Slack's own message documentation requires
	 * these to be escaped in constructed message text: "<" and ">" delimit
	 * every one of its special references — not just the <url|text> link
	 * syntax already handled by protect_links_for_slack()/
	 * restore_links_for_slack(), but channel mentions (<#channel>), user
	 * mentions (<@user>), and commands like <!here> — so a stray literal
	 * one in ordinary text risks being mistaken for an attempted reference.
	 *
	 * A bare "&" does NOT need the same treatment here: WordPress core's
	 * own convert_chars() (registered on the_excerpt_rss, downstream of
	 * this entire pipeline) already escapes any bare "&" not already part
	 * of a real entity, unconditionally, on every request this method's
	 * output ever reaches - re-doing that here would just be redundant.
	 * (Confirmed against WordPress core source, not assumed.)
	 *
	 * @param string $text Plain text, already stripped of HTML tags
	 * @return string Text with stray <, > escaped
	 */
	function escape_stray_chars_for_slack( $text ) {
		return str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $text );
	}

	/**
	 * A strip_tags() replacement, used when reducing content to plain text
	 * for Slack, that only removes genuinely recognized HTML tags (checked
	 * against $this->options_all_tags, the same allowed-tags list used
	 * elsewhere in the plugin) — leaving casual bracket notation that merely
	 * looks tag-shaped (e.g. "<Free>", "<New>") as literal text instead of
	 * silently discarding it. PHP's own strip_tags() can't make this
	 * distinction: it removes anything shaped like "<...>" unconditionally,
	 * bracketed word and all, the same blind spot text_excerpt()'s own
	 * tag-balancing loop had before it started checking tag names the same
	 * way.
	 *
	 * @param string $text Text potentially containing HTML tags
	 * @return string Text with only recognized tags removed
	 */
	function strip_recognized_tags_only( $text ) {
		return preg_replace_callback(
			'/<\/?([a-zA-Z0-9]+)(?:\s[^>]*)?\/?>/',
			function( $matches ) {
				if ( in_array( strtolower( $matches[1] ), (array) $this->options_all_tags ) ) {
					return ''; // A genuine tag - remove it, same as strip_tags().
				}
				return $matches[0]; // Not a real tag name - keep it as literal text.
			},
			$text
		);
	}

	/**
	 * Temporarily encode <a href="URL">Text</a> anchors as angle-bracket-free
	 * placeholders so the strip_tags() calls used later when converting lists,
	 * blockquotes, etc. to Slack-friendly plain text don't silently discard the
	 * href along with the rest of the markup. Pair with restore_links_for_slack(),
	 * which must run after all of that conversion has completed.
	 *
	 * @param string $text Text with HTML
	 * @return string Text with <a> tags replaced by placeholders
	 */
	function protect_links_for_slack( $text ) {
		return preg_replace_callback(
			'/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
			function( $matches ) {
				$url = trim( $matches[1] );
				$link_text = trim( $this->strip_recognized_tags_only( $matches[2] ) );
				$link_text = $this->escape_stray_chars_for_slack( $link_text );

				// A literal "|" occurring naturally in link text still needs
				// replacing even though we emit a plain <a href> tag, not
				// Slack's own <url|text> mrkdwn syntax. Slack's RSS app
				// converts incoming HTML to its own mrkdwn internally before
				// rendering it, and that internal conversion uses "|" as its
				// link delimiter — confirmed via a live example where the
				// first link whose text contained "|" is exactly where
				// Slack's RSS app started rendering raw, truncated markup
				// instead of the clean link every pipe-free one before it
				// got. Swapping it for a slash keeps the visual separation
				// the source intended without the character Slack's own
				// converter treats as special.
				$link_text = str_replace( '|', '/', $link_text );

				// No javascript:/data:/vbscript: check needed here:
				// sanitize_anchor_tags() already unwraps any <a> with such an
				// href (and any other non-navigable one) before filter() ever
				// calls text_excerpt() — the only caller of this method — so
				// by the time an <a> tag reaches this function, its href is
				// already known to be a real, followable destination.
				if ( '' === $url || '' === $link_text ) {
					// Nothing visible to show: an <a> with no text renders as
					// nothing on the site itself, so drop it rather than
					// surface a bare, unexplained URL in its place.
					return $link_text;
				}

				return "\x01" . $url . "\x02" . $link_text . "\x03";
			},
			$text
		);
	}

	/**
	 * Converts the placeholders created by protect_links_for_slack() into a
	 * minimal <a href="URL">Text</a> tag — real HTML, not Slack's <URL|Text>
	 * mrkdwn syntax.
	 *
	 * That mrkdwn syntax was tried, including a fix that made the raw pipe
	 * byte survive WordPress's own the_excerpt_rss filter chain intact
	 * (confirmed against WordPress core source, not just live testing) -
	 * but live Slack output still showed bullets rendering as empty, with
	 * nothing after them, for cleanly-formed links with no syntax issues at
	 * all. Surviving WordPress's filters only proves the bytes reaching
	 * Slack were correct; it never confirmed the RSS app's own renderer
	 * actually treats <url|text> as a link the way Slack's chat API does -
	 * and the live evidence suggests it may not, reliably.
	 *
	 * This tag is deliberately stripped down to nothing but href — no
	 * target/rel/data-* attributes. Those extras are what caused the
	 * original bug this whole pipeline exists to fix: complex <a> tags
	 * leaking through as raw, unparsed visible text. Since Slack already
	 * renders this plugin's <p> tags correctly (paragraph breaks, not
	 * literal text), a minimal, attribute-free <a> tag gets recognized by
	 * that same native HTML handling instead of falling back to raw text —
	 * confirmed against real Slack output.
	 *
	 * Even a clean, minimal <a> tag still gives Slack's RSS app a URL it
	 * may try to unfurl into a preview card, and we've occasionally seen
	 * posts render with broken/truncated text alongside what looks like an
	 * unclosed <a - almost certainly Slack's own unfurl attempt going
	 * wrong on its end, not malformed markup reaching it (this pipeline's
	 * output has been directly verified byte-correct up to this point).
	 * The only way to rule that failure mode out entirely is to not send a
	 * link at all - i.e. the "Strip Links from Slack Feeds" option (see
	 * strip_links_for_slack()), which is why that option defaults to on.
	 *
	 * @param string $text Text with link placeholders
	 * @return string Text with minimal <a href> hyperlinks
	 */
	function restore_links_for_slack( $text ) {
		return preg_replace_callback(
			'/\x01(.*?)\x02(.*?)\x03/s',
			function( $matches ) {
				$url = $matches[1];
				$link_text = $matches[2];
				return '<a href="' . $url . '">' . $link_text . '</a>';
			},
			$text
		);
	}

	/**
	 * Discards every <a href="...">Text</a> entirely, keeping only the
	 * visible text - no href, no placeholder, no restore step needed. Used
	 * instead of protect_links_for_slack() when the "Strip Links from Slack
	 * feeds" option is enabled, so links are removed from Slack output even
	 * when "a" is otherwise allowed by the Strip Tags setting for web/
	 * standard-RSS display - a Slack-specific override, independent of that
	 * setting.
	 *
	 * This is the only way to fully rule out Slack's RSS app attempting to
	 * unfurl a link into a preview card - a process we suspect is behind
	 * the occasional broken/truncated post text with what looks like an
	 * unclosed <a we've seen live even when a clean <a href> tag was sent
	 * (see restore_links_for_slack()). With no URL in the feed at all,
	 * there is nothing left for Slack to unfurl.
	 *
	 * @param string $text Text with HTML
	 * @return string Text with every <a> tag unwrapped to its visible text
	 */
	function strip_links_for_slack( $text ) {
		return preg_replace_callback(
			'/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
			function( $matches ) {
				$link_text = trim( $this->strip_recognized_tags_only( $matches[2] ) );
				return $this->escape_stray_chars_for_slack( $link_text );
			},
			$text
		);
	}

	/**
	 * Defeats Slack's own auto-link detection on anything URL-shaped, by
	 * inserting an invisible U+2060 WORD JOINER at each structural point
	 * (right after the scheme, and after every "." between domain labels)
	 * that a URL-matching regex relies on being contiguous. The text still
	 * reads identically to a human eye - Word Joiner has zero width and
	 * isn't rendered - and if literally copy-pasted, most URL/domain
	 * parsers treat it as invisible formatting rather than a literal
	 * character, unlike a visible character (e.g. a backtick) which would
	 * actually corrupt a pasted URL. Covers both full URLs (with an
	 * http(s):// scheme) and bare domain-looking text without one (e.g.
	 * "example.com/path"), since Slack auto-links both. Only scans plain
	 * text between HTML tags, never inside a tag's own attributes, so it
	 * can't corrupt markup that's still present (e.g. a kept <img src="...">).
	 *
	 * Paired with strip_links_for_slack(): once every <a> is gone, the
	 * visible text left behind (e.g. a link whose text was itself a bare
	 * domain) - or a URL that was never wrapped in a link to begin with -
	 * can still look like something worth auto-linking to Slack. If links
	 * are being suppressed on purpose, auto-linking should be too.
	 *
	 * @param string $text Text with any remaining HTML tags
	 * @return string Text with URL-shaped substrings no longer link-shaped
	 */
	function disable_url_autolink_for_slack( $text ) {
		// No trailing \b: \S+ and the optional path group already stop
		// correctly at whitespace on their own, and a trailing \b would
		// backtrack away from a URL ending in "/" or another non-word
		// character (no word/non-word transition exists right after it),
		// silently dropping it from the match.
		$url_pattern = '/\b(?:https?:\/\/\S+|(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(?:\/[^\s<>`]*)?)/i';
		$word_joiner = "\xE2\x81\xA0"; // U+2060 WORD JOINER

		$segments = preg_split( '/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $segments as &$segment ) {
			if ( isset( $segment[0] ) && '<' === $segment[0] ) {
				continue; // A tag - leave its attributes untouched.
			}
			$segment = preg_replace_callback(
				$url_pattern,
				function( $matches ) use ( $word_joiner ) {
					$url = $matches[0];
					// Break right after the scheme, if present.
					$url = preg_replace( '/^(https?:\/\/)/i', '$1' . $word_joiner, $url );
					// Break every domain-label boundary - the "word.word"
					// shape essentially every URL-detection regex relies on.
					return str_replace( '.', '.' . $word_joiner, $url );
				},
				$segment
			);
		}
		unset( $segment );

		return implode( '', $segments );
	}

	/**
	 * Converts every <br> into a real newline character. Slack has no HTML
	 * support at all - a literal <br> tag left in the text would render as
	 * visible "<br>" text, not a line break - so this always runs (it's not
	 * a setting; there's no reasonable case for leaving a raw <br> in
	 * Slack's output). Runs before any tag-stripping/conversion below,
	 * since strip_recognized_tags_only() treats "br" as a recognized tag
	 * and would otherwise just delete it outright, losing the line break
	 * instead of preserving it.
	 *
	 * @param string $text Text with HTML
	 * @return string Text with every <br> replaced by "\n"
	 */
	function convert_br_to_newline_for_slack( $text ) {
		return preg_replace( '/<br\s*\/?>/i', "\n", $text );
	}

	/**
	 * Merges adjacent <a> tags that share the same href into one - a real,
	 * observed Gutenberg RichText editor artifact where a single intended
	 * link ends up serialized as two (or more) consecutive tags with
	 * identical hrefs, splitting the visible text mid-word (e.g. "Direct
	 * link to o</a><a href="same URL">ffer</a>"). Left alone, each fragment
	 * becomes its own separate link downstream, visibly breaking the text
	 * apart instead of reading as one link. Runs once for every adjacent
	 * pair per pass, repeating until no more merges happen, so three or
	 * more fragments collapse into one just as well as two.
	 *
	 * @param string $text Text with HTML
	 * @return string Text with adjacent same-href <a> tags merged into one
	 */
	function merge_adjacent_same_href_links( $text ) {
		do {
			$text = preg_replace(
				'/(<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>)(.*?)<\/a>\s*<a\s+[^>]*href=["\']\2["\'][^>]*>(.*?)<\/a>/is',
				'$1$3$4</a>',
				$text,
				-1,
				$count
			);
		} while ( $count > 0 );

		return $text;
	}

	/**
	 * Check if the current request is from Slack RSS integration
	 * Detects Slack-related User-Agent patterns
	 *
	 * @return bool True if request is from Slack
	 */
	function is_slack_request() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		// Check for Slack-related User-Agent patterns
		return (
			stripos( $user_agent, 'slack' ) !== false ||
			stripos( $user_agent, 'Slackbot' ) !== false
		);
	}

	/**
	 * Convert HTML lists to Slack-friendly format with proper nesting
	 * Slack has limited HTML support - converts lists to formatted text
	 * Uses browser-style alternating bullets and numbering for nested lists
	 *
	 * @param string $text Excerpt text with HTML lists
	 * @return string Text with lists converted to Slack-friendly format
	 */
	function convert_lists_for_slack( $text ) {
		// Process lists recursively to handle nesting properly. Any excess
		// newlines this leaves behind are cleaned up later — this method has
		// exactly one caller, and that caller collapses every remaining
		// blank-line gap to a single newline once all Slack conversions
		// (this one included) have finished, so doing it again here would
		// just be redundant.
		return $this->convert_nested_lists( $text );
	}

	/**
	 * Count net open <ul>/<ol> tags before $offset in $text, i.e. this list's
	 * actual nesting depth at that position. Used instead of a fixed depth
	 * parameter so indentation and bullet/numbering style genuinely vary
	 * with real nesting depth — see the note in convert_nested_lists() for
	 * why a parameter alone can't express this.
	 *
	 * Pass $tag ('ul' or 'ol') to count only that tag's own ancestors,
	 * ignoring the other type entirely - used for bullet/numbering STYLE
	 * selection, so a <ul> nested inside an <ol> nested inside a <ul> is
	 * one level of <ul> nesting for style purposes (the same style as if
	 * the <ol> weren't there), even though it's two levels deep overall.
	 * Leave $tag null (the default) to count both types together - used for
	 * indentation, which should reflect total structural depth regardless
	 * of type mixing.
	 *
	 * @param string      $text   Text being scanned (the current iteration's state)
	 * @param int         $offset Byte offset of the match start within $text
	 * @param string|null $tag    'ul', 'ol', or null for both types combined
	 * @return int Nesting depth (0 = top level)
	 */
	function list_nesting_depth_at( $text, $offset, $tag = null ) {
		$before = substr( $text, 0, $offset );
		$tag_pattern = $tag ? preg_quote( $tag, '/' ) : '[uo]l';
		$opens = preg_match_all( '/<' . $tag_pattern . '[^>]*>/i', $before );
		$closes = preg_match_all( '/<\/' . $tag_pattern . '>/i', $before );
		return max( $opens - $closes, 0 );
	}

	/**
	 * Convert nested lists iteratively from innermost to outermost.
	 * Prevents infinite recursion and memory issues.
	 *
	 * Bullet/numbering style depends on each list's real nesting depth,
	 * computed per match via list_nesting_depth_at() rather than a depth
	 * parameter threaded through recursive calls - this function never
	 * actually recurses (that's the whole point of processing innermost-
	 * first in a loop), so a fixed parameter passed in from the one caller
	 * could never reflect a specific match's true position in the document;
	 * every match would always compute the same depth. Because inner lists
	 * are converted to plain text before outer ones are ever matched, the
	 * open/close count in the *current* iteration's text - not the original
	 * input - is always exactly the count of list levels still enclosing
	 * that match, giving the right answer without tracking a depth counter
	 * explicitly across iterations.
	 *
	 * @param string $text Text containing lists
	 * @return string Converted text
	 */
	function convert_nested_lists( $text ) {
		// Bullet styles by depth (like browsers): • ◦ ▪ ▫
		// Using U+2022 (•), U+25E6 (◦), U+25AA (▪), U+25AB (▫) for consistent sizing
		$bullet_styles = array( '•', '◦', '▪', '▫' );

		// Safety: prevent infinite loops with max depth and iteration limits
		$max_depth = 10;
		$max_iterations = 50;
		$iteration = 0;

		// Process lists from innermost to outermost (bottom-up approach)
		// This prevents recursion issues and memory problems
		while ( ( strpos( $text, '<ul' ) !== false || strpos( $text, '<ol' ) !== false ) && $iteration < $max_iterations ) {
			$iteration++;
			$original_text = $text;

			// Find the deepest lists first (those without nested lists)
			// Match lists that don't contain other list tags
			$scan_text = $text;
			$text = preg_replace_callback(
				'/<ul[^>]*>(?:(?!<ul|<ol).)*?<\/ul>/is',
				function( $matches ) use ( $bullet_styles, $max_depth, $scan_text ) {
					$content = $matches[0][0];
					$offset = $matches[0][1];
					// Indentation reflects total depth (any list type);
					// bullet style reflects only <ul>-ancestor depth, so an
					// <ol> in between doesn't skip this list ahead a style.
					$indent_depth = min( $this->list_nesting_depth_at( $scan_text, $offset ), $max_depth - 1 );
					$style_depth = min( $this->list_nesting_depth_at( $scan_text, $offset, 'ul' ), $max_depth - 1 );

					// Get bullet style for current depth
					$bullet = $bullet_styles[ $style_depth % count( $bullet_styles ) ];
					$indent = str_repeat( '  ', $indent_depth );

					// Gutenberg-authored lists commonly serialize with blank
					// lines between </li> and the next <li>; collapse that
					// gap now so it doesn't turn into a blank line between
					// bullets below.
					$content = preg_replace( '/<\/li>\s*<li/i', '</li><li', $content );

					// Extract list items
					$content = preg_replace_callback(
						'/<li([^>]*)>(.*?)<\/li>/is',
						function( $li_matches ) use ( $bullet, $indent ) {
							$li_attrs = $li_matches[1];
							$item_content = trim( $this->strip_recognized_tags_only( $li_matches[2] ) );
							$item_content = $this->escape_stray_chars_for_slack( $item_content );
							// Collapse any blank-line run inside the item's own content down
							// to a single newline - e.g. Gutenberg's serialized whitespace
							// between this item's text and a nested sublist that follows it,
							// which by this point is already-converted bullet text, not a
							// literal <ul>/<ol> tag anymore. Unconditional, unlike the
							// optional cross-block collapse further down the pipeline: this
							// only ever touches content already confirmed to be inside one
							// <li>, so it can't affect spacing between top-level blocks.
							$item_content = preg_replace( '/\n\s*\n+/', "\n", $item_content );
							// Add indentation to multi-line items
							$item_content = str_replace( "\n", "\n" . $indent . '  ', $item_content );
							// Skip bullet for ellipsis items (has excerpt-ellipsis class or list-style-type: none)
							if ( strpos( $li_attrs, 'excerpt-ellipsis' ) !== false || strpos( $li_attrs, 'list-style-type: none' ) !== false ) {
								return "\n" . $indent . '  ' . $item_content;
							}
							return "\n" . $indent . $bullet . ' ' . $item_content;
						},
						$content
					);

					// Remove the ul tags
					$content = preg_replace( '/<\/?ul[^>]*>/i', '', $content );

					// No extra separation from surrounding text — just the
					// single newline each bullet already starts with.
					return $content . "\n";
				},
				$text,
				-1,
				$ul_replace_count,
				PREG_OFFSET_CAPTURE
			);

			// Process ordered lists similarly
			$scan_text = $text;
			$text = preg_replace_callback(
				'/<ol[^>]*>(?:(?!<ul|<ol).)*?<\/ol>/is',
				function( $matches ) use ( $max_depth, $scan_text ) {
					$content = $matches[0][0];
					$offset = $matches[0][1];
					// Indentation reflects total depth (any list type);
					// numbering style reflects only <ol>-ancestor depth, so
					// a <ul> in between doesn't skip this list ahead a style.
					$indent_depth = min( $this->list_nesting_depth_at( $scan_text, $offset ), $max_depth - 1 );
					$style_depth = min( $this->list_nesting_depth_at( $scan_text, $offset, 'ol' ), $max_depth - 1 );
					$indent = str_repeat( '  ', $indent_depth );

					// Gutenberg-authored lists commonly serialize with blank
					// lines between </li> and the next <li>; collapse that
					// gap now so it doesn't turn into a blank line between
					// numbered items below.
					$content = preg_replace( '/<\/li>\s*<li/i', '</li><li', $content );

					// Extract list items with their attributes
					preg_match_all( '/<li([^>]*)>(.*?)<\/li>/is', $content, $items, PREG_SET_ORDER );
					$result = '';
					$item_number = 0;

					foreach ( $items as $item ) {
						$li_attrs = $item[1];
						$item_content = trim( $this->strip_recognized_tags_only( $item[2] ) );
						$item_content = $this->escape_stray_chars_for_slack( $item_content );
						// See the matching comment in the <ul> handler above: collapses
						// any blank-line run left inside this item's own content (e.g.
						// from a nested sublist), unconditionally and independently of
						// the optional cross-block collapse.
						$item_content = preg_replace( '/\n\s*\n+/', "\n", $item_content );

						// Skip marker for ellipsis items (has excerpt-ellipsis class or list-style-type: none)
						if ( strpos( $li_attrs, 'excerpt-ellipsis' ) !== false || strpos( $li_attrs, 'list-style-type: none' ) !== false ) {
							// Add indentation to multi-line items
							$item_content = str_replace( "\n", "\n" . $indent . '   ', $item_content );
							$result .= "\n" . $indent . '   ' . $item_content;
							continue;
						}

						// Different numbering styles by depth
						if ( $style_depth % 3 == 0 ) {
							$marker = ( $item_number + 1 ) . '.';
						} elseif ( $style_depth % 3 == 1 ) {
							$marker = chr( 97 + ( $item_number % 26 ) ) . ')';
						} else {
							$roman = array( 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x' );
							$marker = ( isset( $roman[$item_number] ) ? $roman[$item_number] : ( $item_number + 1 ) ) . ')';
						}

						// Add indentation to multi-line items
						$item_content = str_replace( "\n", "\n" . $indent . '   ', $item_content );
						$result .= "\n" . $indent . $marker . ' ' . $item_content;
						$item_number++;
					}

					// No extra separation from surrounding text — just the
					// single newline each item already starts with.
					return $result . "\n";
				},
				$text,
				-1,
				$ol_replace_count,
				PREG_OFFSET_CAPTURE
			);

			// If nothing changed, break to prevent infinite loop
			if ( $original_text === $text ) {
				break;
			}
		}

		// Clean up any remaining stray list tags (safety fallback)
		$text = preg_replace( '/<\/?[uo]l[^>]*>/i', '', $text );
		$text = preg_replace( '/<\/?li[^>]*>/i', '', $text );

		return $text;
	}

	/**
	 * Convert other HTML tags that Slack doesn't support well
	 * Only converts tags that won't interfere with existing functionality
	 * Called AFTER all excerpt processing (tag closing, structure limiting, etc.)
	 *
	 * @param string $text Text with HTML
	 * @return string Converted text
	 */
	function convert_other_tags_for_slack( $text ) {
		// Convert definition lists (DL/DT/DD) to readable format
		// <dl><dt>Term</dt><dd>Definition</dd></dl> → "*Term:* Definition"
		// Single asterisks: Slack's own mrkdwn bold syntax is *text*, not
		// GitHub-flavored Markdown's **text** - the latter would show up
		// as literal, visible asterisk characters instead of bold text.
		$text = preg_replace_callback(
			'/<dl[^>]*>(.*?)<\/dl>/is',
			function( $matches ) {
				$content = $matches[1];
				// Convert DT/DD pairs
				$content = preg_replace( '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', "\n*$1:* $2", $content );
				// Clean up remaining tags
				$content = preg_replace( '/<\/?d[tld][^>]*>/i', '', $content );
				return $content;
			},
			$text
		);

		// Convert blockquotes to markdown-style quoted text
		// Slack supports > prefix for quotes (markdown-style)
		$text = preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/is',
			function( $matches ) {
				$content = trim( $matches[1] );
				// Strip inner HTML tags for cleaner quotes
				$content = $this->strip_recognized_tags_only( $content );
				$content = $this->escape_stray_chars_for_slack( $content );
				// Add > prefix to each line
				$lines = explode( "\n", $content );
				$quoted = array();
				foreach ( $lines as $line ) {
					$trimmed = trim( $line );
					if ( $trimmed !== '' ) {
						$quoted[] = '> ' . $trimmed;
					}
				}
				return "\n" . implode( "\n", $quoted ) . "\n";
			},
			$text
		);

		// Convert horizontal rules to text separator
		$text = preg_replace( '/<hr\s*\/?>/i', "\n───\n", $text );

		// Note: We do NOT convert tables here - they're already properly handled
		// by the excerpt generation with structure limiting and tag closing

		return $text;
	}

	/**
	 * Enforce RSS maximum character length
	 * Truncates content if it exceeds the limit, ensuring tags remain valid.
	 * Absolute max: 40000 chars. For Slack: its own RSS app has been observed
	 * truncating messages mid-tag independently of feed length (confirmed
	 * even well under 1000 chars, on sites unrelated to this plugin), so a
	 * low setting here (e.g. 300-500) reduces but can't guarantee avoiding
	 * that separate, undocumented Slack-side truncation.
	 *
	 * @param string $text Excerpt text with HTML
	 * @param int $max_length Maximum character length
	 * @return string Truncated text with valid HTML
	 */
	function enforce_rss_max_length( $text, $max_length ) {
		// Check if we're already within limit
		if ( strlen( $text ) <= $max_length ) {
			return $text;
		}

		// We need to truncate - but preserve valid HTML
		// Strategy: Find a safe cut point before the limit
		// Safe cut points: after complete closing tags, before opening tags

		// Reserve space for ellipsis and potential tag closures (estimate 100 chars)
		$safe_limit = $max_length - 100;

		// Find all tag positions up to safe limit
		$truncated = substr( $text, 0, $safe_limit );

		// Find the last complete tag before our limit
		// Look for last closing tag: </tagname>
		if ( preg_match( '/^(.*<\/[a-zA-Z0-9]+>)/s', $truncated, $matches ) ) {
			$truncated = $matches[1];
		} else {
			// No closing tags found - look for last complete text before any tag
			if ( preg_match( '/^([^<]*)/s', $truncated, $matches ) ) {
				$truncated = $matches[1];
			}
		}

		// Now collect all unclosed tags and close them
		preg_match_all( '/<([a-zA-Z0-9]+)(?:\s[^>]*)?>/', $truncated, $opening_tags );
		preg_match_all( '/<\/([a-zA-Z0-9]+)>/', $truncated, $closing_tags );

		// Build tag stack to find unclosed tags
		$tag_stack = array();

		// Find all tags in order
		preg_match_all( '/<(\/?[a-zA-Z0-9]+)(?:\s[^>]*)?(\/)?>/i', $truncated, $all_tags, PREG_PATTERN_ORDER );

		foreach ( $all_tags[1] as $index => $tag_name ) {
			$is_closing = ( strpos( $tag_name, '/' ) === 0 );
			$is_self_closing = ( ! empty( $all_tags[2][$index] ) || in_array( strtolower( $tag_name ), $this->void_elements ) );

			if ( $is_closing ) {
				$clean_tag = strtolower( substr( $tag_name, 1 ) );
				$this->remove_from_tag_stack( $tag_stack, $clean_tag );
			} elseif ( ! $is_self_closing ) {
				$tag_stack[] = strtolower( $tag_name );
			}
		}

		// Close unclosed tags in reverse order
		while ( ! empty( $tag_stack ) ) {
			$tag = array_pop( $tag_stack );
			$truncated .= '</' . $tag . '>';
		}

		// Final length check - if still too long, do more aggressive truncation
		if ( strlen( $truncated ) > $max_length ) {
			// Emergency fallback: just cut at character limit and remove any trailing partial tag
			$truncated = substr( $text, 0, $max_length );
			$truncated = $this->cleanup_broken_tags( $truncated );
		}

		return $truncated;
	}

	public function text_add_more( $text, $ellipsis, $read_more, $link_new_tab, $link_screen_reader ) {		
		
		$ellipsis = esc_html( $ellipsis );
		$read_more = wp_kses_data( $read_more );

		if ( $read_more ) {

			$screen_reader_html = '';
			if ( $link_screen_reader ) {
				$screen_reader_html = '<span class="screen-reader-text"> &#8220;' . get_the_title() . '&#8221;</span>';
			}

			if ( $link_new_tab ) {
				$link_template = apply_filters( 'advanced_excerpt_read_more_link_template', ' <a href="%1$s" class="read-more" target="_blank">%2$s %3$s</a>', get_permalink(), $read_more );
			} else {
				$link_template = apply_filters( 'advanced_excerpt_read_more_link_template', ' <a href="%1$s" class="read-more">%2$s %3$s</a>', get_permalink(), $read_more );
			}
			
			$read_more = str_replace( '{title}', get_the_title(), $read_more );
			$read_more = do_shortcode( $read_more );
			$read_more = apply_filters( 'advanced_excerpt_read_more_text', $read_more );

			$ellipsis .= sprintf( $link_template, get_permalink(), $read_more, $screen_reader_html );

		}

		$pos = strrpos( $text, '</' );	

		if ( $pos !== false ) {
			// get the "clean" name of the last closing tag in the text, e.g. p, a, strong, div
			$last_tag = strtolower( trim( str_replace( array( '<', '/', '>' ), '', substr( $text, $pos ) ) ) );

			/*
			 * There was previously a problem where our 'read-more' links were being appending incorrectly into unsuitable HTML tags.
			 * As such we're now maintaining a whitelist of HTML tags that are suitable for being appended into.
			 */
			$allow_tags_to_append_into = apply_filters( 'advanced_excerpt_allow_tags_to_append_into', array( 'p', 'article', 'section' ) );

			if( !in_array( $last_tag, $allow_tags_to_append_into ) ) {
				// After the content
				$text .= $ellipsis;
				return $text;
			}
			// Inside last HTML tag
			$text = substr_replace( $text, $ellipsis, $pos, 0 );
			return $text;
		}

		// After the content
		$text .= $ellipsis;
		return $text;
	}

	function update_options() {
		$_POST = stripslashes_deep( $_POST );
		$this->options['length'] = isset( $_POST['length'] ) ? (int) $_POST['length'] : $this->options['length'];

		$checkbox_options = array( 'no_custom', 'no_custom_from_custom', 'no_shortcode', 'add_link', 'link_new_tab', 'link_screen_reader', 'link_exclude_length', 'link_on_custom_excerpt', 'the_excerpt', 'the_content', 'the_content_no_break', 'link_excerpt', 'enable_homepage_category_filter', 'skip_headers', 'strip_links_slack', 'strip_empty_lines_slack' );

		foreach ( $checkbox_options as $checkbox_option ) {
			$this->options[$checkbox_option] = ( isset( $_POST[$checkbox_option] ) ) ? 1 : 0;
		}

		$this->options['length_type'] = isset( $_POST['length_type'] ) ? sanitize_text_field( $_POST['length_type'] ) : $this->options['length_type'];
		$this->options['finish'] = isset( $_POST['finish'] ) ? sanitize_text_field( $_POST['finish'] ) : $this->options['finish'];
		$this->options['ellipsis'] = isset( $_POST['ellipsis'] ) ? sanitize_text_field( $_POST['ellipsis'] ) : $this->options['ellipsis'];
		$this->options['list_ellipsis'] = isset( $_POST['list_ellipsis'] ) ? sanitize_text_field( $_POST['list_ellipsis'] ) : '';
		$this->options['read_more'] = isset( $_POST['read_more'] ) ? sanitize_text_field( $_POST['read_more'] ) : $this->options['read_more'];
		$this->options['allowed_tags'] = ( isset( $_POST['allowed_tags'] ) ) ? array_unique( array_map( 'sanitize_text_field', (array) $_POST['allowed_tags'] ) ) : array();
		$this->options['exclude_pages'] = ( isset( $_POST['exclude_pages'] ) ) ? array_unique( array_map( 'sanitize_text_field', (array) $_POST['exclude_pages'] ) ) : array();
		$this->options['allowed_tags_option'] = isset( $_POST['allowed_tags_option'] ) ? sanitize_text_field( $_POST['allowed_tags_option'] ) : $this->options['allowed_tags_option'];
		$this->options['homepage_categories'] = ( isset( $_POST['homepage_categories'] ) ) ? array_map( 'intval', array_unique( (array) $_POST['homepage_categories'] ) ) : array();
		$this->options['max_list_items'] = isset( $_POST['max_list_items'] ) ? (int) $_POST['max_list_items'] : 0;
		$this->options['max_top_level_list_items'] = isset( $_POST['max_top_level_list_items'] ) ? (int) $_POST['max_top_level_list_items'] : 0;
		$this->options['max_top_level_structures'] = isset( $_POST['max_top_level_structures'] ) ? (int) $_POST['max_top_level_structures'] : 0;
		$this->options['rss_max_length'] = isset( $_POST['rss_max_length'] ) ? (int) $_POST['rss_max_length'] : 0;

		update_option( 'advanced_excerpt', $this->options );

		wp_redirect( admin_url( $this->plugin_base ) . '&settings-updated=1' );
		exit;		
	}

	function page_options() {
		extract( $this->options, EXTR_SKIP );

		$ellipsis	= htmlentities( $ellipsis );
		$read_more	= htmlentities( $read_more );

		$tag_list = array_unique( array_merge( $this->options_basic_tags, $allowed_tags ) );
		sort( $tag_list );
		$tag_cols = 5;

		// provides a set of checkboxes allowing the user to exclude the excerpt filter on certain page types
		$exclude_pages_list = array(
			'home'			=> __( 'Home Page', 'advanced-excerpt' ),
			'feed'			=> __( 'Posts RSS Feed', 'advanced-excerpt' ),
			'search'		=> __( 'Search Archive', 'advanced-excerpt' ),
			'author'		=> __( 'Author Archive', 'advanced-excerpt' ),
			'category'		=> __( 'Category Archive', 'advanced-excerpt' ),
			'tag'			=> __( 'Tag Archive', 'advanced-excerpt' ),
			'woocommerce'   => __( 'WooCommerce Products', 'advanced-excerpt' ),
		);
		$exclude_pages_list = apply_filters( 'advanced_excerpt_exclude_pages_list', $exclude_pages_list );

		require_once $this->plugin_dir_path . 'template/options.php';
	}

	function get_current_page_types() {
		global $wp_query;
		if ( ! isset( $wp_query ) ) return false;
		$wp_query_object_vars = get_object_vars( $wp_query );

		$page_types = array();
		foreach( $wp_query_object_vars as $key => $value ) {
			if ( false === strpos( $key, 'is_' ) ) continue;
			if ( true === $value ) {
				$page_types[] = str_replace( 'is_', '', $key );
			}
		}

		return $page_types;
	}

	function filter_homepage_category( $query ) {
		// Only modify the main query for either the front-end homepage
		// itself (is_home()), or the site's own default feed (e.g.
		// /feed/, /feed/atom/, etc.) - the RSS/Atom equivalent of that
		// same "homepage" set of posts.
		//
		// WordPress core's is_home() is unconditionally false whenever
		// is_feed() is true (is_feed is one of the exclusions in
		// WP_Query::parse_query()'s own is_home logic), so the default
		// feed needs an equivalent check built the same way core builds
		// is_home - just without excluding on is_feed. Deliberately
		// excludes any OTHER kind of feed - category, tag, author, date,
		// custom taxonomy/post type archive (all covered by is_archive()),
		// search, or a single post's own comment feed (is_singular()) -
		// since none of those are "the homepage" and must not be limited
		// by this filter.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_default_feed = $query->is_feed() && ! $query->is_singular() && ! $query->is_archive() && ! $query->is_search();

		if ( ! $query->is_home() && ! $is_default_feed ) {
			return;
		}

		// Check if the feature is enabled and categories are selected
		if ( 1 == $this->options['enable_homepage_category_filter'] && ! empty( $this->options['homepage_categories'] ) ) {
			// WordPress cat parameter supports comma-separated IDs for multiple categories (OR logic)
			$query->set( 'cat', implode( ',', $this->options['homepage_categories'] ) );
		}
	}

	/**
	 * Shortcode handler for [excerpt_cut] and [/excerpt_cut]
	 * Returns empty string so markers don't appear in post display
	 *
	 * @param array $atts Shortcode attributes
	 * @param string $content Content between opening and closing tags
	 * @return string Content (shown in full post, hidden in excerpt via processing)
	 */
	function excerpt_cut_shortcode( $atts, $content = '' ) {
		// In full post display, just return the content without the shortcode wrapper
		return $content;
	}

	/**
	 * Shortcode handler for [excerpt_only] and [/excerpt_only]
	 * Returns empty string so markers don't appear in post display
	 *
	 * @param array $atts Shortcode attributes (text = replacement for full post)
	 * @param string $content Content between opening and closing tags
	 * @return string Replacement text or empty string (content shown in excerpt via processing)
	 */
	function excerpt_only_shortcode( $atts, $content = '' ) {
		// In full post display, return the replacement text if specified, otherwise empty
		$atts = shortcode_atts( array( 'text' => '' ), $atts );
		return $atts['text'];
	}

	/**
	 * Process excerpt_cut and excerpt_only shortcodes for excerpts
	 * - [excerpt_cut]content[/excerpt_cut] or [excerpt_cut text="replacement"]content[/excerpt_cut]
	 *   Hides content from excerpt, optionally shows replacement text
	 * - [excerpt_only]content[/excerpt_only] or [excerpt_only text="post replacement"]content[/excerpt_only]
	 *   Shows content only in excerpt (already handled by shortcode for post display)
	 *
	 * Ignores nested shortcodes (excerpt_cut within excerpt_only and vice versa)
	 *
	 * @param string $content Post content
	 * @return string Content processed for excerpt display
	 */
	function remove_excerpt_cut_sections( $content ) {
		// First, handle excerpt_only sections - keep the content, remove the shortcode wrapper
		// Pattern matches: [excerpt_only], [excerpt_only text="..."], and [/excerpt_only]
		$content = preg_replace_callback(
			'/\[excerpt_only(?:\s+text=["\']([^"\']*)["\'])?\](.*?)\[\/excerpt_only\]/is',
			function( $matches ) {
				// In excerpt: show the content (ignore the text parameter which is for full post)
				return $matches[2]; // Return content between tags
			},
			$content
		);

		// Remove orphaned excerpt_only markers
		$content = preg_replace( '/\[excerpt_only(?:\s+text=["\'][^"\']*["\'])?\]/i', '', $content );
		$content = preg_replace( '/\[\/excerpt_only\]/i', '', $content );

		// Now handle excerpt_cut sections - remove content or replace with text parameter
		// Pattern matches: [excerpt_cut], [excerpt_cut text="..."], and [/excerpt_cut]
		$content = preg_replace_callback(
			'/\[excerpt_cut(?:\s+text=["\']([^"\']*)["\'])?\](.*?)\[\/excerpt_cut\]/is',
			function( $matches ) {
				// In excerpt: replace with text parameter if provided, otherwise remove entirely
				return isset( $matches[1] ) && $matches[1] !== '' ? $matches[1] : '';
			},
			$content
		);

		// Handle unpaired excerpt_cut (cuts to end of content)
		if ( preg_match( '/\[excerpt_cut(?:\s+text=["\']([^"\']*)["\'])?\]/i', $content, $match, PREG_OFFSET_CAPTURE ) ) {
			$start_pos = $match[0][1];
			$replacement = isset( $match[1][0] ) ? $match[1][0] : '';
			$content = substr( $content, 0, $start_pos ) . $replacement;
		}

		// Remove orphaned excerpt_cut closing markers
		$content = preg_replace( '/\[\/excerpt_cut\]/i', '', $content );

		return $content;
	}

}