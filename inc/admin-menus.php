<?php


add_action('admin_menu', function () {
	add_menu_page('Post Migrator', 'Post Migrator', 'manage_options', 'custom-post-migrator', 'cpm_render_admin_page');

	add_submenu_page(
		'custom-post-migrator',
		'Migrator Settings',
		'Settings',
		'manage_options',
		'custom-post-migrator-settings',
		'cpm_render_settings_page'
	);
});
