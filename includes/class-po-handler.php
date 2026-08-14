<?php
namespace ErrorAgency\LocoAITranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight .po file parser and writer.
 * Handles batch reading of untranslated strings and writing translations back.
 */
class Po_Handler {

	/**
	 * Parse a .po file and return structured entries.
	 *
	 * @param  string $file_path Absolute path to the .po file.
	 * @return array|\WP_Error
	 */
	public static function parse( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', 'PO file not found: ' . $file_path );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'not_readable', 'PO file is not readable: ' . $file_path );
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error( 'read_error', 'Could not read PO file.' );
		}

		return self::parse_content( $content );
	}

	/**
	 * Parse .po content string into entries.
	 */
	public static function parse_content( string $content ) {
		$entries = [];
		$blocks  = preg_split( '/\n{2,}/', $content );

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( empty( $block ) ) {
				continue;
			}

			$entry = self::parse_block( $block );
			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Parse a single PO block (comment + msgid + msgstr).
	 */
	private static function parse_block( string $block ) {
		$lines = explode( "\n", $block );
		$entry = [
			'comments'      => [],
			'references'    => [],
			'flags'         => [],
			'msgctxt'       => null,
			'msgid'         => '',
			'msgid_plural'  => null,
			'msgstr'        => '',
			'msgstr_plural' => [],
			'is_header'     => false,
			'is_fuzzy'      => false,
			'raw'           => $block,
		];

		$current = null;

		foreach ( $lines as $line ) {
			// Translator comments
			if ( 0 === strpos( $line, '# ' ) || '#' === $line ) {
				$entry['comments'][] = $line;
				continue;
			}
			// Extracted comments / references
			if ( 0 === strpos( $line, '#.' ) ) {
				$entry['comments'][] = $line;
				continue;
			}
			if ( 0 === strpos( $line, '#:' ) ) {
				$entry['references'][] = $line;
				continue;
			}
			// Flags
			if ( 0 === strpos( $line, '#,' ) ) {
				$entry['flags'][] = $line;
				if ( false !== strpos( $line, 'fuzzy' ) ) {
					$entry['is_fuzzy'] = true;
				}
				continue;
			}

			// msgctxt
			if ( 0 === strpos( $line, 'msgctxt ' ) ) {
				$current          = 'msgctxt';
				$entry['msgctxt'] = self::unquote( substr( $line, 8 ) );
				continue;
			}
			// msgid
			if ( 0 === strpos( $line, 'msgid ' ) ) {
				$current        = 'msgid';
				$entry['msgid'] = self::unquote( substr( $line, 6 ) );
				continue;
			}
			// msgid_plural
			if ( 0 === strpos( $line, 'msgid_plural ' ) ) {
				$current               = 'msgid_plural';
				$entry['msgid_plural'] = self::unquote( substr( $line, 13 ) );
				continue;
			}
			// msgstr[n]
			if ( preg_match( '/^msgstr\[(\d+)\] (.*)/', $line, $m ) ) {
				$idx                            = (int) $m[1];
				$current                        = 'msgstr_plural_' . $idx;
				$entry['msgstr_plural'][ $idx ] = self::unquote( $m[2] );
				continue;
			}
			// msgstr
			if ( 0 === strpos( $line, 'msgstr ' ) ) {
				$current         = 'msgstr';
				$entry['msgstr'] = self::unquote( substr( $line, 7 ) );
				continue;
			}
			// Continuation line
			if ( 0 === strpos( $line, '"' ) && $current ) {
				$chunk = self::unquote( $line );
				if ( 'msgid' === $current ) {
					$entry['msgid'] .= $chunk;
				} elseif ( 'msgstr' === $current ) {
					$entry['msgstr'] .= $chunk;
				} elseif ( 'msgctxt' === $current ) {
					$entry['msgctxt'] .= $chunk;
				} elseif ( 'msgid_plural' === $current ) {
					$entry['msgid_plural'] .= $chunk;
				} elseif ( 0 === strpos( $current, 'msgstr_plural_' ) ) {
					$idx                            = (int) substr( $current, 14 );
					$entry['msgstr_plural'][ $idx ] = ( $entry['msgstr_plural'][ $idx ] ?? '' ) . $chunk;
				}
			}
		}

		// Header entry has empty msgid
		if ( '' === $entry['msgid'] && ! empty( $entry['msgstr'] ) ) {
			$entry['is_header'] = true;
		}

		// Skip if no msgid at all (empty block)
		if ( '' === $entry['msgid'] && ! $entry['is_header'] ) {
			return null;
		}

		return $entry;
	}

	/**
	 * Get untranslated (and optionally fuzzy) entries.
	 *
	 * @param  array $entries   Parsed entries from parse().
	 * @param  bool  $skip_translated Skip already-translated entries.
	 * @return array [ 'index' => original_index, 'msgid' => string ]
	 */
	public static function get_untranslated( array $entries, bool $skip_translated = true ) {
		$result = [];
		$seen   = [];

		foreach ( $entries as $i => $entry ) {
			if ( $entry['is_header'] ) {
				continue;
			}

			$has_plural_translation = false;
			if ( ! empty( $entry['msgstr_plural'] ) ) {
				$has_plural_translation = ! empty( array_filter( $entry['msgstr_plural'] ) );
			}
			$has_translation = ! empty( $entry['msgstr'] ) || $has_plural_translation;
			$is_fuzzy        = $entry['is_fuzzy'];

			if ( $skip_translated && $has_translation && ! $is_fuzzy ) {
				continue;
			}

			$key = $entry['msgid'] . '|||' . ( $entry['msgid_plural'] ?? '' );

			if ( isset( $seen[ $key ] ) ) {
				$idx                           = $seen[ $key ];
				$result[ $idx ]['duplicates'][] = $i;
			} else {
				$seen[ $key ] = count( $result );
				$result[]     = [
					'index'      => $i,
					'msgid'      => $entry['msgid'],
					'plural'     => $entry['msgid_plural'],
					'duplicates' => [],
				];
			}
		}

		return $result;
	}

	/**
	 * Get the number of plural forms from the PO file headers.
	 *
	 * @param  array $entries Parsed entries.
	 * @return int
	 */
	public static function get_nplurals( array $entries ) {
		foreach ( $entries as $entry ) {
			if ( $entry['is_header'] ) {
				if ( preg_match( '/Plural-Forms:\s*nplurals=(\d+)/i', $entry['msgstr'], $matches ) ) {
					return (int) $matches[1];
				}
			}
		}
		return 2; // Default fallback
	}

	/**
	 * Write translations back into entries array.
	 *
	 * @param  array $entries      Parsed entries.
	 * @param  array $translations [ index => translated_string|array ]
	 * @return array Updated entries.
	 */
	public static function apply_translations( array $entries, array $translations ) {
		foreach ( $translations as $idx => $translated ) {
			if ( ! isset( $entries[ $idx ] ) ) {
				continue;
			}

			if ( is_array( $translated ) ) {
				$existing = $entries[ $idx ]['msgstr_plural'] ?: [];
				foreach ( $translated as $k => $v ) {
					$existing[ $k ] = (string) $v;
				}
				$entries[ $idx ]['msgstr_plural'] = $existing;
				$entries[ $idx ]['msgstr']        = ''; // Plural entries should have empty msgstr
			} else {
				$entries[ $idx ]['msgstr'] = (string) $translated;
			}
			$entries[ $idx ]['is_fuzzy'] = false;

			// Remove fuzzy flag
			$entries[ $idx ]['flags'] = array_values( array_filter(
				$entries[ $idx ]['flags'],
				function( $f ) {
					return false === strpos( $f, 'fuzzy' );
				}
			) );
		}
		return $entries;
	}

	/**
	 * Mark entries as "skipped" by adding a fuzzy flag.
	 *
	 * @param  array $entries  Parsed entries.
	 * @param  array $indices  Entry indices to flag.
	 * @return array Updated entries.
	 */
	public static function flag_as_skipped( array $entries, array $indices ) {
		foreach ( $indices as $idx ) {
			if ( ! isset( $entries[ $idx ] ) ) {
				continue;
			}
			if ( '' === $entries[ $idx ]['msgstr'] ) {
				if ( ! in_array( '#, fuzzy', $entries[ $idx ]['flags'], true ) ) {
					$entries[ $idx ]['flags'][] = '#, fuzzy';
				}
				$entries[ $idx ]['is_fuzzy'] = true;
			}
		}
		return $entries;
	}

	/**
	 * Serialize entries back to .po file content.
	 */
	public static function serialize( array $entries ) {
		$lines = [];

		foreach ( $entries as $entry ) {
			$block = [];

			// Comments
			foreach ( $entry['comments'] as $c ) {
				$block[] = $c;
			}
			foreach ( $entry['references'] as $r ) {
				$block[] = $r;
			}
			foreach ( $entry['flags'] as $f ) {
				$block[] = $f;
			}

			// msgctxt
			if ( null !== $entry['msgctxt'] ) {
				$block[] = 'msgctxt ' . self::quote( $entry['msgctxt'] );
			}

			// msgid
			$block[] = 'msgid ' . self::quote( $entry['msgid'] );

			// plural
			if ( null !== $entry['msgid_plural'] ) {
				$block[] = 'msgid_plural ' . self::quote( $entry['msgid_plural'] );
				$plurals = $entry['msgstr_plural'] ?: [ 0 => '', 1 => '' ];
				foreach ( $plurals as $i => $val ) {
					$block[] = 'msgstr[' . $i . '] ' . self::quote( $val );
				}
			} else {
				$block[] = 'msgstr ' . self::quote( $entry['msgstr'] );
			}

			$lines[] = implode( "\n", $block );
		}

		return implode( "\n\n", $lines ) . "\n";
	}

	/**
	 * Save updated entries to .po file and compile .mo.
	 *
	 * @param  string $po_path  Absolute path to .po file.
	 * @param  array  $entries  Updated entries.
	 * @return true|\WP_Error
	 */
	public static function save( string $po_path, array $entries ) {
		if ( ! is_writable( $po_path ) ) {
			return new \WP_Error( 'not_writable', 'PO file is not writable: ' . $po_path );
		}

		$content = self::serialize( $entries );
		$result  = file_put_contents( $po_path, $content );

		if ( false === $result ) {
			return new \WP_Error( 'write_error', 'Could not write PO file.' );
		}

		self::compile_mo( $po_path );

		return true;
	}

	/**
	 * Compile .po to .mo using WP's built-in POMO classes.
	 */
	private static function compile_mo( string $po_path ) {
		$mo_path = preg_replace( '/\.po$/', '.mo', $po_path );
		self::write_mo_fallback( $po_path, $mo_path );
	}

	/**
	 * MO file writer using WP's built-in PO/MO classes.
	 */
	private static function write_mo_fallback( string $po_path, string $mo_path ) {
		if ( ! class_exists( '\PO' ) && ! class_exists( 'PO' ) ) {
			require_once ABSPATH . 'wp-includes/pomo/po.php';
			require_once ABSPATH . 'wp-includes/pomo/mo.php';
		}

		$po = new \PO();
		if ( $po->import_from_file( $po_path ) ) {
			$mo          = new \MO();
			$mo->entries = $po->entries;
			$mo->set_headers( $po->headers );
			$mo->export_to_file( $mo_path );
		}
	}

	/**
	 * Check if a string is non-translatable.
	 *
	 * @param  string $str The string to check.
	 * @return bool
	 */
	public static function is_non_translatable( string $str ) {
		$str = trim( $str );
		if ( '' === $str ) {
			return true;
		}

		// 1. Purely numeric
		if ( is_numeric( $str ) ) {
			return true;
		}

		// 2. Purely placeholders
		$placeholder_pattern = '/^(?:%[0-9]*\$?[sd]|{{?[a-zA-Z0-9_\-\s]+}?})+$/';
		if ( preg_match( $placeholder_pattern, $str ) ) {
			return true;
		}

		// 3. Purely URLs
		if ( preg_match( '/^https?:\/\/[^\s]+$/i', $str ) ) {
			return true;
		}

		// 4. Purely HTML tags or special entities/punctuation without letters/words
		$stripped = strip_tags( $str );
		$stripped = html_entity_decode( $stripped );
		$stripped = preg_replace( '/&[a-zA-Z0-9#]+;/', '', $stripped );
		if ( ! preg_match( '/[a-zA-Z\p{L}0-9]/u', $stripped ) ) {
			return true;
		}

		return false;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private static function unquote( string $str ) {
		$str = trim( $str );
		if ( 0 === strpos( $str, '"' ) && '"' === substr( $str, -1 ) ) {
			$str = substr( $str, 1, -1 );
		}
		return stripcslashes( $str );
	}

	private static function quote( string $str ) {
		return '"' . addcslashes( $str, "\0\\\"\n\r\t" ) . '"';
	}
}
