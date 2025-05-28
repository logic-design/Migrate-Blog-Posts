<?php

function test_remote_wordpress_connection($site_url, $application_password, $username)
{
	if (!$site_url || !$application_password || !$username) {
		return 'Remote username or application password not set.';
	}

	$site_url = rtrim($site_url, '/');
	$endpoint = $site_url . '/wp-json/wp/v2/users/me';

	$auth = base64_encode("$username:$application_password");

	$args = [
		'headers' => [
			'Authorization' => 'Basic ' . $auth,
			'Content-Type'  => 'application/json',
		],
		'timeout' => 15,
	];

	$response = wp_remote_get($endpoint, $args);

	if (is_wp_error($response)) {
		return $response->get_error_message();
	}

	$code = wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);

	if ($code === 200) {
		return true;
	} else {
		return "HTTP Error $code: $body";
	}
}

add_action('wp_ajax_cpm_fetch_remote_posts', function () {

	$response = wp_remote_get(trailingslashit(get_option('cpm_remote_url')) . 'wp-json/wp/v2/posts?per_page=100', [
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode("get_option('cpm_secure_user'):get_option('cpm_secure_token')"),
			'Content-Type'  => 'application/json',
		]
	]);

	wp_send_json(json_decode(wp_remote_retrieve_body($response)));
});

add_action('wp_ajax_cpm_import_post', function () {
	$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : false;
	$forceImport = isset($_GET['forceImport']) && $_GET['forceImport'] === 'true';

	if (!$post_id) {
		wp_send_json_error('Invalid post ID.');
	}

	$existing = new WP_Query([
		'meta_key' => '_original_site_post_id',
		'meta_value' => $post_id,
		'post_type' => 'post',
		'post_status' => 'any',
		'fields' => 'ids'
	]);

	if ($existing->have_posts() && !$forceImport) {
		wp_send_json_success('Post already imported.');
	}

	if ($forceImport && $existing->have_posts()) {
		foreach ($existing->posts as $existing_post_id) {
			$attachments = get_children([
				'post_parent' => $existing_post_id,
				'post_type'   => 'attachment',
				'numberposts' => -1,
			]);

			if ($attachments) {
				foreach ($attachments as $attachment) {
					wp_delete_attachment($attachment->ID, true);
				}
			}

			wp_delete_post($existing_post_id, true);
		}
	}

	$response = wp_remote_get(trailingslashit(get_option('cpm_remote_url')) . "wp-json/wp/v2/posts/$post_id", [
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode("get_option('cpm_secure_user'):get_option('cpm_secure_token')"),
			'Content-Type'  => 'application/json',
		]
	]);

	$post_data = json_decode(wp_remote_retrieve_body($response), true);

	// Allow filtering/cleanup of post content before import
	$clean_content = $post_data['content']['rendered'];

	$new_post_id = wp_insert_post([
		'post_title'   => $post_data['title']['rendered'],
		'post_content' => $clean_content,
		'post_status'  => 'publish',
		'post_author'  => get_current_user_id(),
		'post_date'    => isset($post_data['date']) ? $post_data['date'] : current_time('mysql'),
	]);

	// Import post author
	if (isset($post_data['author'])) {
		$remote_author_id = $post_data['author'];

		// Fetch author data from remote site
		$author_response = wp_remote_get(trailingslashit(get_option('cpm_remote_url')) . "wp-json/wp/v2/users/$remote_author_id", [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode("get_option('cpm_secure_user'):get_option('cpm_secure_token')"),
				'Content-Type'  => 'application/json',
			]
		]);

		$author_data = json_decode(wp_remote_retrieve_body($author_response), true);

		if (!empty($author_data['slug'])) {
			$user = get_user_by('slug', $author_data['slug']);

			if (!$user) {
				// Create user if not exists
				$username = sanitize_user($author_data['slug'], true);
				if (username_exists($username)) {
					$username .= '_' . wp_generate_password(4, false);
				}
				$user_id = wp_insert_user([
					'user_login' => $username,
					'user_pass'  => wp_generate_password(),
					// 'user_email' => ... // not available via wp-json
					'display_name' => $author_data['name'],
					'first_name' => $author_data['name'],
					'description' => $author_data['description'] ?? '',
					'role' => 'author'
				]);
			} else {
				$user_id = $user->ID;
			}

			// Assign post to imported/created user
			wp_update_post([
				'ID' => $new_post_id,
				'post_author' => $user_id
			]);
		}
	}

	update_post_meta($new_post_id, '_original_site_post_id', $post_id);

	// Import images
	if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post_data['content']['rendered'], $matches)) {
		$updated_content = $clean_content;

		$featured_img_url = false;
		if (isset($post_data['featured_media']) && $post_data['featured_media']) {
			$media_response = wp_remote_get($remote_url . "wp-json/wp/v2/media/" . $post_data['featured_media'], [
				'headers' => [
					'Authorization' => 'Bearer ' . $secure_token
				]
			]);

			$media_data = json_decode(wp_remote_retrieve_body($media_response), true);
			$featured_img_url = !empty($media_data['source_url']) ? $media_data['source_url'] : false;
		}

		if ($featured_img_url && !in_array($featured_img_url, $matches[1])) {
			$matches[1][] = $featured_img_url;
		}

		foreach ($matches[1] as $img_url) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$tmp = download_url($img_url);
			if (!is_wp_error($tmp)) {
				$file_array = [
					'name'     => basename($img_url),
					'tmp_name' => $tmp
				];
				$attachment_id = media_handle_sideload($file_array, $new_post_id);
				if (is_wp_error($attachment_id)) {
					@unlink($tmp);
				} else {
					// Get new URL
					$new_url = wp_get_attachment_url($attachment_id);

					// Replace old URL with new URL in content
					$updated_content = str_replace($img_url, $new_url, $updated_content);

					$updated_content = preg_replace(
						'/(<img[^>]+src=["\']' . preg_quote($new_url, '/') . '["\'][^>]*)\s+srcset=["\'][^"\']*["\']/i',
						'$1',
						$updated_content
					);

					if ($featured_img_url && $img_url === $featured_img_url) {
						set_post_thumbnail($new_post_id, $attachment_id);
					}
				}
			}
		}

		// FINAL CONTENT CLEANUP
		$updated_content = apply_filters('cpm_import_post_content', $updated_content, $post_data, $remote_url);

		// Update post content with new image URLs
		wp_update_post([
			'ID' => $new_post_id,
			'post_content' => $updated_content
		]);
	}

	$categories = isset($post_data['categories']) ? $post_data['categories'] : [];
	$tags = isset($post_data['tags']) ? $post_data['tags'] : [];

	// Import categories
	if (!empty($categories)) {
		$category_ids = [];
		foreach ($categories as $remote_cat_id) {
			// Fetch category data from remote site
			$cat_response = wp_remote_get(trailingslashit(get_option('cpm_remote_url')) . "wp-json/wp/v2/categories/$remote_cat_id", [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode(get_option('cpm_secure_user') . ':' . get_option('cpm_secure_token')),
					'Content-Type'  => 'application/json',
				]
			]);
			$cat_data = json_decode(wp_remote_retrieve_body($cat_response), true);

			if (!empty($cat_data['slug'])) {
				// Check if category exists locally
				$term = get_term_by('slug', $cat_data['slug'], 'category');
				if (!$term) {
					// Create category if not exists
					$new_term = wp_insert_term($cat_data['name'], 'category', [
						'slug' => $cat_data['slug'],
						'description' => isset($cat_data['description']) ? $cat_data['description'] : '',
						'parent' => 0
					]);
					if (!is_wp_error($new_term)) {
						$category_ids[] = $new_term['term_id'];
					}
				} else {
					$category_ids[] = $term->term_id;
				}
			}
		}
		if (!empty($category_ids)) {
			wp_set_post_categories($new_post_id, $category_ids);
		}
	}

	// Import tags
	if (!empty($tags)) {
		$tag_ids = [];
		foreach ($tags as $remote_tag_id) {
			// Fetch tag data from remote site
			$tag_response = wp_remote_get(trailingslashit(get_option('cpm_remote_url')) . "wp-json/wp/v2/tags/$remote_tag_id", [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode(get_option('cpm_secure_user') . ':' . get_option('cpm_secure_token')),
					'Content-Type'  => 'application/json',
				]
			]);
			$tag_data = json_decode(wp_remote_retrieve_body($tag_response), true);

			if (!empty($tag_data['slug'])) {
				// Check if tag exists locally
				$term = get_term_by('slug', $tag_data['slug'], 'post_tag');
				if (!$term) {
					// Create tag if not exists
					$new_term = wp_insert_term($tag_data['name'], 'post_tag', [
						'slug' => $tag_data['slug'],
						'description' => isset($tag_data['description']) ? $tag_data['description'] : '',
					]);
					if (!is_wp_error($new_term)) {
						$tag_ids[] = $new_term['term_id'];
					}
				} else {
					$tag_ids[] = $term->term_id;
				}
			}
		}
		if (!empty($tag_ids)) {
			wp_set_post_tags($new_post_id, $tag_ids);
		}
	}

	if ($forceImport) {
		wp_send_json_success("Post imported (Forced).");
	} else {
		wp_send_json_success("Post imported.");
	}
});

add_action('wp_ajax_cpm_delete_imported_posts', function () {
	// Check user capabilities if needed
	if (!current_user_can('delete_posts')) {
		wp_send_json_error('Insufficient permissions.');
	}

	$imported_query = new WP_Query([
		'post_type'   => 'post',
		'post_status' => 'any',
		'meta_key'    => '_original_site_post_id',
		'fields'      => 'ids',
		'posts_per_page' => -1,
	]);

	if (empty($imported_query->posts)) {
		wp_send_json_success('No imported posts found.');
	}

	foreach ($imported_query->posts as $post_id) {
		$attachments = get_children([
			'post_parent' => $post_id,
			'post_type'   => 'attachment',
			'numberposts' => -1,
		]);

		if ($attachments) {
			foreach ($attachments as $attachment) {
				wp_delete_attachment($attachment->ID, true);
			}
		}

		wp_delete_post($post_id, true);
	}

	wp_send_json_success('All imported posts deleted.');
});
