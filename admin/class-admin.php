<?php
namespace ErrorAgency\LocoAITranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'plugin_action_links_' . ERROR_LAIT_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	public function register_menu() {
		add_options_page(
			__( 'Err.or AI Translator for Loco Translate', 'err-or-ai-translator-for-loco-translate' ),
			__( 'Err.or AI Translator', 'err-or-ai-translator-for-loco-translate' ),
			'manage_options',
			'err-or-ai-translator-for-loco-translate',
			[ $this, 'render_settings_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		// Settings page
		if ( 'settings_page_err-or-ai-translator-for-loco-translate' === $hook ) {
			wp_enqueue_style(
				'error-lait-admin',
				ERROR_LAIT_URL . 'assets/css/lat-admin.css',
				[],
				ERROR_LAIT_VERSION
			);
			wp_enqueue_script(
				'error-lait-admin',
				ERROR_LAIT_URL . 'assets/js/lat-admin.js',
				[ 'jquery' ],
				ERROR_LAIT_VERSION,
				true
			);
			wp_localize_script( 'error-lait-admin', 'errorLaitAdmin', $this->get_js_data() );
		}

		// Inject into Loco Translate editor pages.
		$current_page   = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		$current_action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

		$loco_hooks = [
			'loco-translate_page_loco-plugin',
			'loco-translate_page_loco-theme',
			'loco-translate_page_loco-plugin-file-edit',
			'loco-translate_page_loco-theme-file-edit',
			'loco-plugin_page_loco-plugin-file-edit',
			'loco-theme_page_loco-theme-file-edit',
		];

		$is_loco_editor = (
			in_array( $hook, $loco_hooks, true ) ||
			(
				false !== strpos( $current_page, 'loco' ) &&
				'file-edit' === $current_action
			) ||
			(
				false !== strpos( $current_page, 'loco' ) &&
				! empty( $_GET['path'] )
			)
		);

		if ( $is_loco_editor ) {
			wp_enqueue_style(
				'error-lait-loco',
				ERROR_LAIT_URL . 'assets/css/lat-admin.css',
				[],
				ERROR_LAIT_VERSION
			);
			wp_enqueue_script(
				'error-lait-loco',
				ERROR_LAIT_URL . 'assets/js/lat-loco-editor.js',
				[ 'jquery' ],
				ERROR_LAIT_VERSION,
				true
			);
			wp_localize_script( 'error-lait-loco', 'errorLaitLoco', $this->get_loco_js_data() );
		}
	}

	private function get_js_data() {
		$settings = Settings::instance();
		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'error_lait_nonce' ),
			'model'     => $settings->get( 'model' ),
			'provider'  => $settings->get( 'provider' ),
			'batchSize' => $settings->get( 'batch_size' ),
			'i18n'      => [
				'translating'  => __( 'Translating…', 'err-or-ai-translator-for-loco-translate' ),
				'done'         => __( 'Done!', 'err-or-ai-translator-for-loco-translate' ),
				'error'        => __( 'Error', 'err-or-ai-translator-for-loco-translate' ),
				'noStrings'    => __( 'No untranslated strings found.', 'err-or-ai-translator-for-loco-translate' ),
				'confirm'      => __( 'This will fill in untranslated strings using AI. Continue?', 'err-or-ai-translator-for-loco-translate' ),
				'btnTranslate' => __( '🤖 AI Translate', 'err-or-ai-translator-for-loco-translate' ),
				'btnStop'      => __( '⏹ Stop', 'err-or-ai-translator-for-loco-translate' ),
			],
		];
	}

	private function get_loco_js_data() {
		$base     = $this->get_js_data();
		$raw_path = sanitize_text_field( wp_unslash( $_GET['path'] ?? '' ) );

		$detected_locale = '';
		if ( $raw_path ) {
			$basename = pathinfo( $raw_path, PATHINFO_FILENAME );
			if ( preg_match( '/[-_]([a-z]{2,3}_[A-Z]{2,3})$/', $basename, $m ) ) {
				$detected_locale = $m[1];
			}
		}

		$base['poPath']         = $raw_path;
		$base['detectedLocale'] = $detected_locale;

		return $base;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once ERROR_LAIT_PATH . 'admin/views/settings-page.php';
	}

	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=err-or-ai-translator-for-loco-translate' ),
			__( 'Settings', 'err-or-ai-translator-for-loco-translate' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
