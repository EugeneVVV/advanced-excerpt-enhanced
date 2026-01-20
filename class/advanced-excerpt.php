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
	);

	public $options_basic_tags; // Basic HTML tags (determines which tags are in the checklist by default)
	public $options_all_tags; // Almost all HTML tags (extra options)
	public $filter_type; // Determines wether we're filtering the_content or the_excerpt at any given time

	function __construct( $plugin_file_path ) {
		$this->load_options();

		$this->plugin_version = $GLOBALS['advanced_excerpt_version'];
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_dir_path = plugin_dir_path( $plugin_file_path );
		$this->plugin_folder_name = basename( $this->plugin_dir_path );
		$this->plugin_basename = plugin_basename( $plugin_file_path );
		$this->plugin_base ='options-general.php?page=advanced-excerpt';

		if ( isset($_SERVER['REQUEST_METHOD']) && 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_REQUEST['page'] ) && 'advanced-excerpt' === $_REQUEST['page'] ) {
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

		// prevent recursion on 'the_content' hook
		$content_has_filter = false;
		if ( has_filter( 'the_content', array( $this, 'filter_content' ) ) ) { 
			remove_filter( 'the_content', array( $this, 'filter_content' ) ); 
			$content_has_filter = true;
		}

		$text = get_the_content( '' );

		// Remove excerpt cut sections BEFORE any other processing
		$text = $this->remove_excerpt_cut_sections( $text );

		// generate excerpt from the "custom excerpt" (only if there is a "custom excerpt" )
		if ( $no_custom && $no_custom_from_custom && ! empty( trim( $post->post_excerpt ) ) ) {
			$text = $post->post_excerpt;
		}

		// remove shortcodes
		if ( $no_shortcode ) {
			$text = strip_shortcodes( $text );
		}

		$text = apply_filters( 'the_content', $text );

		// add our filter back in
		if ( $content_has_filter ) { 
            add_filter( 'the_content', array( $this, 'filter_content' ) );
		}

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

		// Divide the string into tokens; HTML tags, or words, followed by any whitespace
		preg_match_all( '/(<[^>]+>|[^<>\s]+)\s*/u', $text, $tokens );

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
				// In block finish mode, check for br or block tags (opening or closing)
				if ( $looking_for_block_end ) {
					// Check for <br> tag
					if ( preg_match( '/<br\s*\/?>/i', $t ) ) {
						$out .= $t;
						break;
					}

					// Check for block-level tags (both opening and closing)
					$block_tags = array( 'p', 'div', 'blockquote', 'li', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'article', 'section', 'header', 'footer', 'aside', 'nav', 'ul', 'ol', 'table', 'tr', 'pre', 'form', 'fieldset', 'dl', 'dt', 'dd', 'hr', 'figure', 'figcaption', 'main', 'address', 'details', 'summary', 'dialog' );
					if ( preg_match( '/<\/?([a-zA-Z0-9]+)/', $t, $tag_match ) ) {
						$block_tag = strtolower( $tag_match[1] );
						if ( in_array( $block_tag, $block_tags ) ) {
							$out .= $t;
							break;
						}
					}
				}
				// Parse tag name
				if ( preg_match( '/<\/?([a-zA-Z0-9]+)/', $t, $tag_match ) ) {
					$tag_name = strtolower( $tag_match[1] );
					$is_closing = ( strpos( $t, '</' ) === 0 );
					$is_self_closing = ( strpos( $t, '/>' ) !== false );

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
							// Remove from tag stack
							for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
								if ( $tag_stack[$i] == $tag_name ) {
									array_splice( $tag_stack, $i, 1 );
									break;
								}
							}
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
							// Remove last 'li' from tag stack
							for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
								if ( $tag_stack[$i] == 'li' ) {
									array_splice( $tag_stack, $i, 1 );
									break;
								}
							}
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
						// Remove from tag stack
						for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
							if ( $tag_stack[$i] == 'table' ) {
								array_splice( $tag_stack, $i, 1 );
								break;
							}
						}
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
							// Remove last 'tr' from tag stack
							for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
								if ( $tag_stack[$i] == 'tr' ) {
									array_splice( $tag_stack, $i, 1 );
									break;
								}
							}
						}
						$out .= $t;
						continue;
					}

					// Handle other table elements (td, th, tbody, thead, tfoot)
					if ( in_array( $tag_name, array( 'td', 'th', 'tbody', 'thead', 'tfoot' ) ) ) {
						if ( ! $is_closing && ! $is_self_closing ) {
							array_push( $tag_stack, $tag_name );
						} elseif ( $is_closing ) {
							// Remove last matching tag from stack
							for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
								if ( $tag_stack[$i] == $tag_name ) {
									array_splice( $tag_stack, $i, 1 );
									break;
								}
							}
						}
						$out .= $t;
						continue;
					}

					// Handle other regular tags
					if ( ! $is_closing && ! $is_self_closing ) {
						array_push( $tag_stack, $tag_name );
					} elseif ( $is_closing ) {
						// Remove last matching tag from stack
						for ( $i = count( $tag_stack ) - 1; $i >= 0; $i-- ) {
							if ( $tag_stack[$i] == $tag_name ) {
								array_splice( $tag_stack, $i, 1 );
								break;
							}
						}
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

		// Clean up multiple line breaks and unnecessary br tags
		$out = $this->cleanup_line_breaks( $out );

		// Ensure no broken/partial tags at the end of excerpt
		$out = $this->cleanup_broken_tags( $out );

		// Convert HTML lists and other unsupported tags to Slack-friendly format in RSS feeds
		if ( is_feed() ) {
			$out = $this->convert_lists_for_slack( $out );
			$out = $this->convert_other_tags_for_slack( $out );
		}

		// Enforce RSS max length if in feed and limit is set
		if ( is_feed() && isset( $this->options['rss_max_length'] ) && $this->options['rss_max_length'] > 0 ) {
			$out = $this->enforce_rss_max_length( $out, $this->options['rss_max_length'] );
		}

		return trim( $out );
	}

	function cleanup_line_breaks( $text ) {
		$is_feed = is_feed();

		if ( $is_feed ) {
			// In RSS feeds: remove ALL <br> tags for better readability
			// Block-level tags provide sufficient spacing in feed readers
			$text = preg_replace( '/<br\s*\/?>/i', '', $text );
		} else {
			// In regular excerpts: keep max 1 consecutive <br>
			$text = preg_replace( '/<br\s*\/?>\s*(?:<br\s*\/?>\s*)+/i', '<br />', $text );
		}

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
	 * Convert HTML lists to Slack-friendly format with proper nesting
	 * Slack has limited HTML support - converts lists to formatted text
	 * Uses browser-style alternating bullets and numbering for nested lists
	 *
	 * @param string $text Excerpt text with HTML lists
	 * @return string Text with lists converted to Slack-friendly format
	 */
	function convert_lists_for_slack( $text ) {
		// Process lists recursively to handle nesting properly
		$text = $this->convert_nested_lists( $text, 0 );

		// Clean up excessive newlines that might result from list conversion
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return $text;
	}

	/**
	 * Convert nested lists iteratively from innermost to outermost
	 * Prevents infinite recursion and memory issues
	 *
	 * @param string $text Text containing lists
	 * @param int $depth Starting depth (0-based)
	 * @return string Converted text
	 */
	function convert_nested_lists( $text, $depth = 0 ) {
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
			$text = preg_replace_callback(
				'/<ul[^>]*>(?:(?!<ul|<ol).)*?<\/ul>/is',
				function( $matches ) use ( $bullet_styles, $depth, $max_depth ) {
					// Calculate depth based on how many levels deep we are
					// Count preceding list markers to estimate depth
					$content = $matches[0];
					$current_depth = min( $depth, $max_depth - 1 );

					// Get bullet style for current depth
					$bullet = $bullet_styles[ $current_depth % count( $bullet_styles ) ];
					$indent = str_repeat( '  ', $current_depth );

					// Extract list items
					$content = preg_replace_callback(
						'/<li([^>]*)>(.*?)<\/li>/is',
						function( $li_matches ) use ( $bullet, $indent ) {
							$li_attrs = $li_matches[1];
							$item_content = trim( strip_tags( $li_matches[2] ) );
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
					return $content . "\n";
				},
				$text
			);

			// Process ordered lists similarly
			$text = preg_replace_callback(
				'/<ol[^>]*>(?:(?!<ul|<ol).)*?<\/ol>/is',
				function( $matches ) use ( $depth, $max_depth ) {
					$content = $matches[0];
					$current_depth = min( $depth, $max_depth - 1 );
					$indent = str_repeat( '  ', $current_depth );

					// Extract list items with their attributes
					preg_match_all( '/<li([^>]*)>(.*?)<\/li>/is', $content, $items, PREG_SET_ORDER );
					$result = '';
					$item_number = 0;

					foreach ( $items as $item ) {
						$li_attrs = $item[1];
						$item_content = trim( strip_tags( $item[2] ) );

						// Skip marker for ellipsis items (has excerpt-ellipsis class or list-style-type: none)
						if ( strpos( $li_attrs, 'excerpt-ellipsis' ) !== false || strpos( $li_attrs, 'list-style-type: none' ) !== false ) {
							// Add indentation to multi-line items
							$item_content = str_replace( "\n", "\n" . $indent . '   ', $item_content );
							$result .= "\n" . $indent . '   ' . $item_content;
							continue;
						}

						// Different numbering styles by depth
						if ( $current_depth % 3 == 0 ) {
							$marker = ( $item_number + 1 ) . '.';
						} elseif ( $current_depth % 3 == 1 ) {
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

					return $result . "\n";
				},
				$text
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
		// <dl><dt>Term</dt><dd>Definition</dd></dl> → "**Term:** Definition"
		$text = preg_replace_callback(
			'/<dl[^>]*>(.*?)<\/dl>/is',
			function( $matches ) {
				$content = $matches[1];
				// Convert DT/DD pairs
				$content = preg_replace( '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', "\n**$1:** $2", $content );
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
				$content = strip_tags( $content );
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
	 * Truncates content if it exceeds the limit, ensuring tags remain valid
	 * Slack recommended limit: 4000 chars, absolute max: 40000 chars
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
		$self_closing = array( 'br', 'hr', 'img', 'input', 'meta', 'link' );

		// Find all tags in order
		preg_match_all( '/<(\/?[a-zA-Z0-9]+)(?:\s[^>]*)?(\/)?>/i', $truncated, $all_tags, PREG_PATTERN_ORDER );

		foreach ( $all_tags[1] as $index => $tag_name ) {
			$is_closing = ( strpos( $tag_name, '/' ) === 0 );
			$is_self_closing = ( ! empty( $all_tags[2][$index] ) || in_array( strtolower( $tag_name ), $self_closing ) );

			if ( $is_closing ) {
				$clean_tag = substr( $tag_name, 1 );
				// Remove from stack
				$key = array_search( $clean_tag, array_reverse( $tag_stack, true ) );
				if ( $key !== false ) {
					unset( $tag_stack[count($tag_stack) - 1 - $key] );
					$tag_stack = array_values( $tag_stack );
				}
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
		$this->options['length'] = (int) $_POST['length'];

		$checkbox_options = array( 'no_custom', 'no_custom_from_custom', 'no_shortcode', 'add_link', 'link_new_tab', 'link_screen_reader', 'link_exclude_length', 'link_on_custom_excerpt', 'the_excerpt', 'the_content', 'the_content_no_break', 'link_excerpt', 'enable_homepage_category_filter', 'skip_headers' );

		foreach ( $checkbox_options as $checkbox_option ) {
			$this->options[$checkbox_option] = ( isset( $_POST[$checkbox_option] ) ) ? 1 : 0;
		}

		$this->options['length_type'] = $_POST['length_type'];
		$this->options['finish'] = $_POST['finish'];
		$this->options['ellipsis'] = $_POST['ellipsis'];
		$this->options['list_ellipsis'] = isset( $_POST['list_ellipsis'] ) ? $_POST['list_ellipsis'] : '';
		$this->options['read_more'] = isset( $_POST['read_more'] ) ? $_POST['read_more'] : $this->options['read_more'];
		$this->options['allowed_tags'] = ( isset( $_POST['allowed_tags'] ) ) ? array_unique( (array) $_POST['allowed_tags'] ) : array();
		$this->options['exclude_pages'] = ( isset( $_POST['exclude_pages'] ) ) ? array_unique( (array) $_POST['exclude_pages'] ) : array();
		$this->options['allowed_tags_option'] = $_POST['allowed_tags_option'];
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
		// Only modify the main query on the front-end homepage
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
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