<?php
use ErrorAgency\LocoAITranslator\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$settings = Settings::instance()->get();
$provider = $settings['provider'];
?>
<div class="wrap lat-settings-wrap">
	<h1 class="lat-page-title">
		<span class="lat-logo">🤖</span>
		<?php esc_html_e( 'Err.or AI Translator for Loco Translate', 'err-or-ai-translator-for-loco-translate' ); ?>
		<span class="lat-version">v<?php echo esc_html( ERROR_LAIT_VERSION ); ?></span>
	</h1>

	<?php settings_errors( 'error_lait_settings_group' ); ?>

	<div class="lat-layout">

		<!-- ── MAIN SETTINGS ── -->
		<div class="lat-main-col">
			<form method="post" action="options.php" id="lat-settings-form">
				<?php settings_fields( 'error_lait_settings_group' ); ?>

				<!-- Provider Card -->
				<div class="lat-card">
					<h2 class="lat-card-title">⚡ <?php esc_html_e( 'Provider', 'err-or-ai-translator-for-loco-translate' ); ?></h2>

					<div class="lat-provider-tabs">
						<label class="lat-provider-tab <?php echo 'openrouter' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="error_lait_settings[provider]" value="openrouter"
								<?php checked( $provider, 'openrouter' ); ?>>
							<span class="lat-provider-icon">🌐</span>
							<strong>OpenRouter</strong>
							<small><?php esc_html_e( 'Cloud API aggregator', 'err-or-ai-translator-for-loco-translate' ); ?></small>
						</label>
						<label class="lat-provider-tab <?php echo 'ollama' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="error_lait_settings[provider]" value="ollama"
								<?php checked( $provider, 'ollama' ); ?>>
							<span class="lat-provider-icon">🏠</span>
							<strong>Ollama</strong>
							<small><?php esc_html_e( 'Local / self-hosted LLM', 'err-or-ai-translator-for-loco-translate' ); ?></small>
						</label>
						<label class="lat-provider-tab <?php echo 'custom' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="error_lait_settings[provider]" value="custom"
								<?php checked( $provider, 'custom' ); ?>>
							<span class="lat-provider-icon">🔧</span>
							<strong><?php esc_html_e( 'Custom Endpoint', 'err-or-ai-translator-for-loco-translate' ); ?></strong>
							<small><?php esc_html_e( 'OpenAI-compatible API', 'err-or-ai-translator-for-loco-translate' ); ?></small>
						</label>
					</div>

					<table class="form-table lat-form-table">
						<tr>
							<th><?php esc_html_e( 'API Endpoint', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="url" name="error_lait_settings[api_endpoint]"
									value="<?php echo esc_attr( $settings['api_endpoint'] ); ?>"
									class="regular-text" id="lat-api-endpoint"
									placeholder="https://openrouter.ai/api/v1">
								<div class="lat-presets">
									<button type="button" class="button button-small lat-preset"
										data-value="https://openrouter.ai/api/v1">
										OpenRouter
									</button>
									<button type="button" class="button button-small lat-preset"
										data-value="http://localhost:11434">
										Ollama local
									</button>
									<button type="button" class="button button-small lat-preset"
										data-value="https://api.openai.com/v1">
										OpenAI
									</button>
								</div>
							</td>
						</tr>
						<tr class="lat-row-apikey" <?php echo 'ollama' === $provider ? 'style="display:none"' : ''; ?>>
							<th><?php esc_html_e( 'API Key', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="password" name="error_lait_settings[api_key]"
									value=""
									placeholder="<?php echo ! empty( $settings['api_key'] ) ? esc_attr__( 'API key saved — leave empty to keep', 'err-or-ai-translator-for-loco-translate' ) : ''; ?>"
									class="regular-text" autocomplete="new-password">
								<?php if ( ! empty( $settings['api_key'] ) ) : ?>
									<label style="margin-left:10px;">
										<input type="checkbox" name="error_lait_settings[clear_api_key]" value="1">
										<?php esc_html_e( 'Clear saved API key', 'err-or-ai-translator-for-loco-translate' ); ?>
									</label>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Leave empty if using a local Ollama endpoint without authentication.', 'err-or-ai-translator-for-loco-translate' ); ?>
									<a href="https://openrouter.ai/keys" target="_blank" rel="noopener">
										<?php esc_html_e( 'Get OpenRouter key ↗', 'err-or-ai-translator-for-loco-translate' ); ?>
									</a>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Model Card -->
				<div class="lat-card">
					<h2 class="lat-card-title">🧠 <?php esc_html_e( 'Model', 'err-or-ai-translator-for-loco-translate' ); ?></h2>

					<table class="form-table lat-form-table">
						<tr>
							<th><?php esc_html_e( 'Model ID', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<div class="lat-model-row">
									<input type="text" name="error_lait_settings[model]" id="lat-model-input"
										value="<?php echo esc_attr( $settings['model'] ); ?>"
										class="regular-text"
										placeholder="openai/gpt-4o-mini">
									<button type="button" id="lat-fetch-models" class="button">
										<?php esc_html_e( '↻ Load Models', 'err-or-ai-translator-for-loco-translate' ); ?>
									</button>
								</div>
								<select id="lat-model-select" style="display:none; margin-top:8px; width:100%; max-width:500px;">
									<option value=""><?php esc_html_e( '— choose a model —', 'err-or-ai-translator-for-loco-translate' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Type model ID directly or click Load Models to fetch from provider.', 'err-or-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Temperature', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="error_lait_settings[temperature]"
									value="<?php echo esc_attr( $settings['temperature'] ); ?>"
									min="0" max="2" step="0.1" class="small-text">
								<p class="description">
									<?php esc_html_e( '0 = deterministic, 1 = creative. Recommended: 0.1–0.4 for translations.', 'err-or-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Batch Size', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="error_lait_settings[batch_size]"
									value="<?php echo esc_attr( $settings['batch_size'] ); ?>"
									min="5" max="100" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Strings per API call. Default: 40. Range: 5–100.', 'err-or-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Max Retries', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="error_lait_settings[max_retries]"
									value="<?php echo esc_attr( $settings['max_retries'] ?? 3 ); ?>"
									min="0" max="10" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Number of retries per batch on API failure before skipping. Default: 3.', 'err-or-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Translation Behaviour -->
				<div class="lat-card">
					<h2 class="lat-card-title">⚙️ <?php esc_html_e( 'Translation Behaviour', 'err-or-ai-translator-for-loco-translate' ); ?></h2>

					<table class="form-table lat-form-table">
						<tr>
							<th><?php esc_html_e( 'Skip Translated', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="error_lait_settings[skip_translated]" value="1"
										<?php checked( $settings['skip_translated'], 1 ); ?>>
									<?php esc_html_e( 'Skip strings that already have a translation', 'err-or-ai-translator-for-loco-translate' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'System Prompt', 'err-or-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<textarea name="error_lait_settings[system_prompt]" rows="6"
									class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use the default translation prompt.', 'err-or-ai-translator-for-loco-translate' ); ?>"
								><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Override the default prompt. Use {target_lang} for the language placeholder.', 'err-or-ai-translator-for-loco-translate' ); ?>
								</p>
								<button type="button" id="lat-show-default-prompt" class="button button-small">
									<?php esc_html_e( 'View default prompt', 'err-or-ai-translator-for-loco-translate' ); ?>
								</button>
								<pre id="lat-default-prompt-preview" style="display:none; background:#f6f7f7; padding:12px; border-radius:4px; white-space:pre-wrap; font-size:12px;"><?php echo esc_html( Settings::default_system_prompt( '{target_lang}' ) ); ?></pre>
							</td>
						</tr>
					</table>
				</div>

				<div class="lat-actions">
					<?php submit_button( __( 'Save Settings', 'err-or-ai-translator-for-loco-translate' ), 'primary large', 'submit', false ); ?>
					<button type="button" id="lat-test-connection" class="button button-large">
						🔌 <?php esc_html_e( 'Test Connection', 'err-or-ai-translator-for-loco-translate' ); ?>
					</button>
					<span id="lat-test-result" class="lat-test-result"></span>
				</div>

			</form>
		</div>

		<!-- ── SIDEBAR ── -->
		<div class="lat-sidebar">

			<!-- How to Use Card -->
			<div class="lat-card lat-sidebar-card lat-card-info">
				<h2 class="lat-card-title">💡 <?php esc_html_e( 'How to Use in Loco Translate', 'err-or-ai-translator-for-loco-translate' ); ?></h2>
				<ol class="lat-how-to">
					<li><?php esc_html_e( 'Go to Loco Translate → Plugins or Themes', 'err-or-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click Edit on a translation file', 'err-or-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click the "🤖 AI Translate" button in the toolbar', 'err-or-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Translations are automatically saved to PO and MO files', 'err-or-ai-translator-for-loco-translate' ); ?></li>
				</ol>
			</div>

		</div><!-- /.lat-sidebar -->

	</div><!-- /.lat-layout -->
</div>
