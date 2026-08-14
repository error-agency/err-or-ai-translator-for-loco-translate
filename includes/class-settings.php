<?php
namespace ErrorAgency\LocoAITranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	private static $instance = null;
	const OPTION_KEY         = 'error_lait_settings';
	const LEGACY_OPTION_KEY  = 'lat_settings';
	const SCHEMA_VERSION_KEY = 'error_lait_schema_version';
	const SCHEMA_VERSION     = '1.6.1';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'maybe_migrate_settings' ] );
	}

	public function register_settings() {
		register_setting(
			'error_lait_settings_group',
			self::OPTION_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);
	}

	public function maybe_migrate_settings() {
		$current_version = get_option( self::SCHEMA_VERSION_KEY, '' );
		if ( version_compare( $current_version, self::SCHEMA_VERSION, '<' ) ) {
			$new_settings = get_option( self::OPTION_KEY, null );

			if ( null === $new_settings ) {
				$legacy = get_option( self::LEGACY_OPTION_KEY, null );
				if ( is_array( $legacy ) && ! empty( $legacy ) ) {
					$clean = $this->sanitize_settings( $legacy );
					add_option( self::OPTION_KEY, $clean );
				}
			}

			update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
		}
	}

	public function sanitize_settings( $input ) {
		$existing = get_option( self::OPTION_KEY, [] );
		$clean    = [];

		$provider          = sanitize_text_field( $input['provider'] ?? 'openrouter' );
		$clean['provider'] = in_array( $provider, [ 'openrouter', 'ollama', 'custom' ], true ) ? $provider : 'openrouter';

		$clean['api_endpoint'] = esc_url_raw( trim( $input['api_endpoint'] ?? '' ) );

		$submitted_key = trim( $input['api_key'] ?? '' );
		if ( ! empty( $input['clear_api_key'] ) ) {
			$clean['api_key'] = '';
		} elseif ( '' === $submitted_key && ! empty( $existing['api_key'] ) ) {
			$clean['api_key'] = $existing['api_key'];
		} else {
			$clean['api_key'] = sanitize_text_field( $submitted_key );
		}

		$clean['model']           = sanitize_text_field( $input['model'] ?? '' );
		$clean['batch_size']      = absint( $input['batch_size'] ?? 40 );
		$clean['max_retries']     = absint( $input['max_retries'] ?? 3 );
		$clean['system_prompt']   = sanitize_textarea_field( $input['system_prompt'] ?? '' );
		$clean['skip_translated'] = ! empty( $input['skip_translated'] ) ? 1 : 0;
		$clean['temperature']     = floatval( $input['temperature'] ?? 0.3 );

		$clean['batch_size']  = max( 5, min( 100, $clean['batch_size'] ) );
		$clean['max_retries'] = max( 0, min( 10,  $clean['max_retries'] ) );
		$clean['temperature'] = max( 0, min( 2,   $clean['temperature'] ) );

		return $clean;
	}

	public function get( $key = null, $default = null ) {
		$options = get_option( self::OPTION_KEY, [] );

		$defaults = [
			'provider'        => 'openrouter',
			'api_endpoint'    => 'https://openrouter.ai/api/v1',
			'api_key'         => '',
			'model'           => 'openai/gpt-4o-mini',
			'batch_size'      => 40,
			'max_retries'     => 3,
			'system_prompt'   => '',
			'skip_translated' => 1,
			'temperature'     => 0.3,
		];

		$options = wp_parse_args( $options, $defaults );

		if ( $key !== null ) {
			return $options[ $key ] ?? $default;
		}

		return $options;
	}

	/**
	 * Генерира системния промпт по подразбиране с 12-те задължителни правила на протокола.
	 *
	 * @param  string $target_lang Целеви език.
	 * @param  int    $nplurals    Брой множествени форми за локала.
	 * @return string
	 */
	public static function default_system_prompt( string $target_lang, $nplurals = 2 ) {
		$prompt = <<<'PROMPT'
You are a professional software localization translator specializing in WordPress plugins and themes.

Translate the provided source strings into {target_lang}.

Follow these rules strictly:

1. OUTPUT FORMAT

Return ONLY one valid JSON object.

Do not include:
- Markdown
- code fences
- explanations
- comments
- text before or after the JSON

The response format must be:

{
  "translations": [
    {
      "id": "entry_X",
      "translation": "translated text"
    }
  ]
}

2. ITEM IDS

Every input item has a unique "id".

You MUST:
- return every requested ID exactly once
- preserve every ID exactly as provided
- never invent new IDs
- never omit IDs
- never duplicate IDs

The order of returned items does not matter because items are matched by ID.

3. SINGULAR STRINGS

For normal strings return:

{
  "id": "entry_X",
  "translation": "translated text"
}

Never return a "translations" array for a singular item.

4. PLURAL STRINGS

Items containing "singular" and "plural" are plural translation units.

For plural items return:

{
  "id": "entry_X",
  "translations": [
    "plural form 0",
    "plural form 1"
  ]
}

Return EXACTLY {nplurals} plural forms.

Never return "translation" for a plural item.

Generate the plural forms according to the grammatical rules of {target_lang}.

5. CONTEXT

If an item contains a non-empty "context" property, use it to determine the intended meaning of the source string.

Context is metadata only.

DO NOT translate the context itself.
DO NOT include the context in the translated text.

The same source string may require different translations when its context differs.

6. PLACEHOLDERS

Preserve ALL technical placeholders exactly.

Examples include, but are not limited to:

%s
%d
%f
%u
%x
%X
%e
%E
%g
%c
%02d
%.2f
%1$s
%2$d
%1$.2f
%2$03d
%%
{name}
{{name}}

Never:
- remove placeholders
- add placeholders
- rename placeholders
- alter placeholder types
- corrupt positional placeholders

7. HTML AND MARKUP

Preserve HTML/XML markup and technical attributes.

Examples:

<strong>
</strong>
<a href="%s">
<span class="example">
<br>
<img>
&nbsp;
&amp;

Do not remove or corrupt tags, attributes, placeholders inside attributes, or HTML entities.

Translate only human-readable content.

8. TECHNICAL CONTENT

Do not translate or modify technical tokens unless they are clearly human-readable content.

Preserve where applicable:

- URLs
- email addresses
- file paths
- CSS classes
- HTML attributes
- WordPress shortcodes
- code fragments
- identifiers
- variable names
- product and brand names that should remain unchanged

9. ESCAPING

Return valid JSON.

Correctly escape:
- quotation marks
- backslashes
- newlines
- control characters

Do not introduce invalid JSON escaping.

10. TRANSLATION QUALITY

Produce natural, fluent, professional software UI translations.

Prefer terminology appropriate for WordPress interfaces, plugins, themes, settings screens, buttons, notices, and administrative interfaces.

Translate meaning rather than performing a literal word-for-word translation.

Keep translations concise when the source is a UI label, button, menu item, or setting name.

Do not add explanations or information that is not present in the source.

11. SOURCE STRUCTURE

Preserve meaningful punctuation, line breaks, formatting tokens, and structural elements unless natural grammar in {target_lang} requires a different word order.

Technical tokens must always remain intact.

12. FINAL VALIDATION

Before returning the JSON, verify internally that:

- every requested ID appears exactly once
- no unknown IDs were added
- every singular item has exactly one "translation"
- every plural item has exactly {nplurals} entries in "translations"
- all placeholders are preserved
- HTML and markup are preserved
- the JSON is syntactically valid

Return only the final JSON object.
PROMPT;

		return str_replace(
			[ '{target_lang}', '{nplurals}' ],
			[ $target_lang, (string) $nplurals ],
			$prompt
		);
	}

	/**
	 * Изгражда финалния системен промпт, като запазва протокола и интегрира персонализираните потребителски инструкции.
	 *
	 * @param  string $target_lang
	 * @param  int    $nplurals
	 * @param  string $custom_prompt
	 * @return string
	 */
	public static function build_system_prompt( string $target_lang, $nplurals = 2, string $custom_prompt = '' ) {
		$base_prompt = self::default_system_prompt( $target_lang, $nplurals );

		if ( empty( trim( $custom_prompt ) ) ) {
			return $base_prompt;
		}

		$custom_replaced = str_replace(
			[ '{target_lang}', '{nplurals}' ],
			[ $target_lang, (string) $nplurals ],
			$custom_prompt
		);

		// Ако потребителският промпт вече съдържа пълни протоколни правила (напр. "translations" или "entry_X"), го ползваме пряко
		if ( false !== strpos( $custom_replaced, 'entry_X' ) || false !== strpos( $custom_replaced, 'translations' ) ) {
			return $custom_replaced;
		}

		// В противен случай съчетаваме задължителния протокол с потребителските инструкции
		return $base_prompt . "\n\n13. CUSTOM USER INSTRUCTIONS\n\n" . $custom_replaced;
	}
}
