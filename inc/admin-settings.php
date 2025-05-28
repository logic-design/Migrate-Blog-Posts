<?php

add_action('admin_init', function () {
	register_setting('cpm_settings', 'cpm_secure_user');
	register_setting('cpm_settings', 'cpm_secure_token');
	register_setting('cpm_settings', 'cpm_remote_url');

	add_settings_section(
		'cpm_main_section',
		'Main Settings',
		null,
		'custom-post-migrator-settings'
	);


	add_settings_field(
		'cpm_secure_user',
		'WordPress Username',
		function () {
			$value = esc_attr(get_option('cpm_secure_user', ''));
			echo "<input type='text' name='cpm_secure_user' value='{$value}' class='regular-text' />";
		},
		'custom-post-migrator-settings',
		'cpm_main_section'
	);


	add_settings_field(
		'cpm_secure_token',
		'Secure Token',
		function () {
			$value = esc_attr(get_option('cpm_secure_token', ''));
			echo "<input type='text' name='cpm_secure_token' value='{$value}' class='regular-text' />";
		},
		'custom-post-migrator-settings',
		'cpm_main_section'
	);

	add_settings_field(
		'cpm_remote_url',
		'Remote URL',
		function () {
			$value = esc_url(get_option('cpm_remote_url', ''));
			echo "<input type='url' name='cpm_remote_url' value='{$value}' class='regular-text' />";
		},
		'custom-post-migrator-settings',
		'cpm_main_section'
	);
});

function cpm_render_settings_page()
{
?>
	<div class="wrap">
		<h1>Migrator Settings</h1>
		<?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
			<div id="message" class="updated notice is-dismissible">
				<p>Settings saved</p>
			</div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php

			settings_fields('cpm_settings');
			do_settings_sections('custom-post-migrator-settings');
			submit_button();

			if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
				$remote_url = get_option('cpm_remote_url', '');
				$secure_user = get_option('cpm_secure_user', '');
				$secure_token = get_option('cpm_secure_token', '');

				if ($remote_url && $secure_token) {
					$result = test_remote_wordpress_connection($remote_url, $secure_token, $secure_user);

					if ($result === true) {
						echo '<div class="notice notice-success"><p>Connection successful!</p></div>';
					} else {
						echo '<div class="notice notice-error"><p>Connection failed: ' . esc_html($result) . '</p></div>';
					}
				}
			}
			?>
	</div>
<?php
}
