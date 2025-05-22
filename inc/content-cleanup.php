<?php

add_filter('cpm_import_post_content', function ($content, $post_data, $remote_base) {

	$site_url = get_site_url();

	if (substr($site_url, -1) !== '/') {
		$site_url .= '/';
	}

	$content = str_replace($remote_base, $site_url, $content);

	return $content;
}, 10, 3);
