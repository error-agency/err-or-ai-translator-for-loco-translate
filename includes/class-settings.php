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
	const SCHEMA_VERSION     = '1.6.0';

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

			// Only migrate legacy settings if the new option key does not exist yet
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
		$clean = [];

		$clean['provider']        = sanitize_text_field( $input['provider'] ?? 'openrouter' );
		$clean['api_endpoint']    = esc_url_raw( trim( $input['api_endpoint'] ?? '' ) );
		$clean['api_key']         = sanitize_text_field( $input['api_key'] ?? '' );
		$clean['model']           = sanitize_text_field( $input['model'] ?? '' );
		$clean['batch_size']      = absint( $input['batch_size'] ?? 40 );
		$clean['max_retries']     = absint( $input['max_retries'] ?? 3 );
		$clean['system_prompt']   = sanitize_textarea_field( $input['system_prompt'] ?? '' );
		$clean['skip_translated'] = ! empty( $input['skip_translated'] ) ? 1 : 0;
		$clean['temperature']     = floatval( $input['temperature'] ?? 0.3 );

		// Clamp values
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
			'Translate JSON array items to %s. ' .
			'Rules: ' .
			'- Preserve HTML tags, spacing, and placeholders (%%s, %%d, {var}) exactly. ' .
			'- If an item is a plural array [singular, plural], translate it into exactly %d plural forms for %s as a nested JSON array. ' .
			'- Return ONLY a JSON array in the exact same order. No extra text, explanations, or markdown fences.',
			$target_lang,
			$nplurals,
			$target_lang
		);
	}
}
