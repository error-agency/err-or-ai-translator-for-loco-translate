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

		// Запазване на съществуващия API ключ, ако е изпратено празно поле и не е поискано изчистване
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

		// Ограничаване на стойностите
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

	public static function default_system_prompt( $target_lang, $nplurals = 2 ) {
		return sprintf(
			'You are a professional WordPress software translator translating strings into %s.' . "\n" .
			'Rules:' . "\n" .
			'1. Return ONLY a valid JSON object envelope with format: {"translations": [ {"id": "entry_X", "translation": "..."}, ... ]}' . "\n" .
			'2. For plural items (having "singular" and "plural"), return: {"id": "entry_X", "translations": ["form1", "form2", ...]} with EXACTLY %d plural forms required for %s.' . "\n" .
			'3. Preserve ALL placeholders (%%s, %%d, %%1$s, {var}, {{var}}) and HTML tags/attributes/entities exactly.' . "\n" .
			'4. Respect the "context" property if present to provide accurate domain-specific translations.' . "\n" .
			'5. Maintain exact item IDs ("entry_X"). Do not omit any requested ID or add extra IDs.',
			$target_lang,
			$nplurals,
			$target_lang
		);
	}
}
