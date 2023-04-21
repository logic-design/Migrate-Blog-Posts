<?php 
/**
* Plugin Name: Logic - Migrate Blog Posts
* Description: Import posts from remote domain via the WordPress JSON API
* Author: Logic Design & Consultancy Ltd
* Author URI: https://www.logicdesign.co.uk
* Version: 1.0.2
*/

require_once __DIR__ . '/updater/boot.php';
require_once __DIR__ . '/updater/updater.php';
require_once __DIR__ . '/updater/plugin-updater.php';

$updater = LogicMigrateBlog\WP_Updater\Boot::instance();
$updater->add([
	'type' => 'plugin',
	'source' => 'https://github.com/logic-design/Migrate-Blog-Posts'
]);

class LogicBlogMigration
{
	public function __construct()
	{
		add_action('admin_menu', [$this, 'admin_menu']);

		add_action('wp_ajax_lbm_test_connect', [$this, 'ajax_test_connection']);
		add_action('wp_ajax_lbm_import', [$this, 'ajax_import']);

		// $this->domain = 'https://crosscountrycarriers.com';
	}

	public function admin_menu()
	{
		add_menu_page(
			'Blog Migrator',
			'Blog Migrator',
			'manage_options',
			'logic-blog-migrator',
			[$this, 'admin_page'],
			'',
			null
		);
	}

	public function admin_page()
	{
		$form_action = home_url(add_query_arg([
			'page' => 'logic-blog-migrator',
		]));

		?>
		<div class="wrap">
			<h1>Blog Migrator</h1>
			<form method="post" action="<?php echo $form_action; ?>">
				<div class="card" style="min-width: 640px;">
					<table class="form-table">
						<tbody>
							<tr>
								<th>
									<label for="input-text">External Domain</label>
								</th>
								<td>
									<input 
										name="lbm[domain]" 
										type="url" 
										required 
										class="large-text" 
										placeholder="Domain to import from, including https://"
										value="<?php echo isset($_POST['lbm']['domain']) ? $_POST['lbm']['domain'] : ''; ?>"
										 />
								</td>
							</tr>
							<tr>
								<th></th>
								<td>
									<input disabled type="submit" class="button-secondary" name="lmb_test" value="Test Connection" />
									<input disabled type="submit" class="button-primary" name="lmb_submit" value="Begin Import" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</form>

			<div data-ajax-card class="card" style="min-width: 640px; display: none;">
				<h2 data-ajax-heading></h2>
				<div class="loader">
					<div style="float: none; margin-left: 0; margin-bottom: 32px;" class="spinner is-active"></div>
				</div>
				<div data-ajax-output></div>
			</div>

		</div>

		<script>
			jQuery(document).ready(function()
			{
				window.abort = false;

				function lbmDomain()
				{
					jQuery('input[name="lmb_test"]').prop('disabled', true);

					var lbmDomainSelector = jQuery('input[name="lbm[domain]"]');

					if ( lbmDomainSelector.val() != '' ) {
						jQuery('input[name="lmb_test"]').prop('disabled', false);
					}
				}

				jQuery('body').on('change', 'input[name="lbm[domain]"]', function() {
					lbmDomain();
				});

				lbmDomain();

				//////////////////////////
				// AJAX TEST CONNECTION //
				//////////////////////////
				jQuery('body').on('click', 'input[type="submit"][name="lmb_test"]', function(e) {
					e.preventDefault();

					jQuery('[data-ajax-heading]').text('Connection Test');
					jQuery('[data-ajax-card], [data-ajax-card] .loader').show();
					jQuery('[data-ajax-output]').html('');

					var data = {
						'action': 'lbm_test_connect',
						'domain': jQuery('input[name="lbm[domain]"]').val()
					};

					jQuery.ajax({
						type: 'POST',
						url: ajaxurl,
						data: data,
						dataType: 'json',
						success: function(r) {
							jQuery('[data-ajax-output]').html('<div class="notice notice-success notice-alt"><p>' + r.data.message + '</p></div>');
							jQuery('[data-ajax-output]').append('<input type="text" name="lbm[total_posts]" value="' + r.data.total_posts + '">');
							jQuery('input[name="lmb_submit"]').prop('disabled', false);
						},
						error: function(xhr, status, error) {
							jQuery('[data-ajax-output]').html('<div class="notice notice-error notice-alt"><p>' + xhr.responseJSON.data[0].message + '</p></div>');
						},
						complete: function() {
							jQuery('[data-ajax-card] .loader').hide();
						},
					});

				});


				//////////////////////
				// AJAX FULL IMPORT //
				//////////////////////
				jQuery('body').on('click', 'input[type="submit"][name="lmb_submit"]', function(e) {
					e.preventDefault();

					var total_posts = parseInt(jQuery('body').find('input[name="lbm[total_posts]"]').val());

					jQuery('[data-ajax-heading]').text('Data Migration');
					jQuery('[data-ajax-card], [data-ajax-card] .loader').show();
					jQuery('[data-ajax-output]').html('<input type="submit" class="button-primary" name="lmb_abort" value="Abort" />');

					lmbImportLoop(total_posts, 1);
				});
			});

			jQuery('body').on('click', 'input[type=submit][name="lmb_abort"]', function()
			{
				window.abort = true;
			});

			function lmbImportLoop(total_posts, current_page)
			{
				if ( current_page > total_posts ) {
					jQuery('[data-ajax-card] .loader').hide();
					jQuery('input[name="lmb_abort"]').hide();
					jQuery('[data-ajax-output]').append('<p>COMPLETE</p>');

					return true;
				}

				if ( window.abort ) {
					jQuery('[data-ajax-card] .loader').hide();
					jQuery('input[name="lmb_abort"]').hide();
					jQuery('[data-ajax-output]').append('<p>ABORTED</p>');

					return true;
				}

				var data = {
					'action': 'lbm_import',
					'domain': jQuery('input[name="lbm[domain]"]').val(),
					'page': current_page
				};

				jQuery('[data-ajax-output]').append('<p>Starting page #' + current_page + '</p>');

				jQuery.ajax({
					url: ajaxurl,
					data: data,
					type: 'POST',
					dataType: 'json',
					success: function(imported)
					{
						jQuery('[data-ajax-output]').append('<p>Imported post ' + imported.data.ID +  ' (' + imported.data.post_title + ')</p>');
					},
					complete: function(imported)
					{
						lmbImportLoop(total_posts, current_page+1);
					}
				});
			}
		</script>
		<?php
	}

	public function ajax_test_connection()
	{
		if ( empty($_POST['domain']) ) {
			http_response_code(403);
			wp_send_json_error(new WP_Error('001', 'Domain not defined'));
			wp_die();
		}

		$this->domain = $_POST['domain'];

		$url = $this->domain . '/wp-json/wp/v2/posts/';

		// GET TOTAL POSTS
		$response = wp_remote_get(add_query_arg(['per_page' => 1, 'page' => 1], $url));
		$headers = wp_remote_retrieve_headers($response);

		if ( ! isset($headers['x-wp-total']) ) {
			http_response_code(403);
			wp_send_json_error(new WP_Error('002', 'No posts found, or could not connect.'));
			wp_die();
		}

		wp_send_json_success([
			'total_posts' => (int) $headers['x-wp-total'],
			'message' => 'Connection Successful - ' . $headers['x-wp-total'] . ' posts found to import',
		]);
	}

	public function ajax_import()
	{
		$this->domain = $_POST['domain'];
		
		$imported = $this->import($_POST['page']);

		if ( $imported ) {
			wp_send_json_success($imported);
		}

		else {
			http_response_code(403);
			wp_send_json_error(new WP_Error('001', $imported));
			wp_die();
		}
	}

	public function import($page = 1)
	{
		$url = $this->domain . '/wp-json/wp/v2/posts/'; // can append single remote post id to import one.

		$response 	= wp_remote_get(add_query_arg(['per_page' => 1, 'page' => $page], $url));
		$headers 	= wp_remote_retrieve_headers($response);
		$body 		= json_decode(wp_remote_retrieve_body($response));
		$body 		= isset($body->id) ? $body : $body[0];

		$local_post = get_posts([
			'post_type' => 'post',
			'meta_query' => [
				[
					'key' 	=> 'logic_imported',
					'value' => $body->id,
					'type' 	=> 'numeric'
				]
			]
		]);

		if ( empty($local_post[0]->ID) ) {
			return $this->create_or_update($body);
		} else {
			return $this->create_or_update($body, $local_post[0]->ID);
		}
	}

	function create_or_update($body, $post_id = NULL)
	{
		$post_data = [
			'post_type' 		=> 'post',
			'post_title' 		=> $body->title->rendered,
			'post_content' 		=> $body->content->rendered,

			'post_status'		=> $body->status,
			'post_date'			=> $body->date,
			'post_date_gmt'		=> $body->date_gmt,
			'post_status'		=> $body->status,
		];

		if ( ! is_null($post_id) ) {
			$post_data['ID'] = $post_id;
			wp_insert_post($post_data);
		}

		else {
			$post_id = wp_insert_post($post_data);
		}

		add_post_meta($post_id, 'logic_imported', $body->id, true);

		////////////////////
		// FEATURED IMAGE //
		////////////////////
		if ( $body->featured_media > 0 ) {

			$url = $this->domain . '/wp-json/wp/v2/media/' . $body->featured_media;

   			$get_remote_image = wp_remote_get($url);
   			$media = json_decode(wp_remote_retrieve_body($get_remote_image));

   			// CHECK IF IMAGE EXISTS
			$local_image = get_posts([
				'post_type' => 'attachment',
				'meta_query' => [
					[
						'key' 	=> 'logic_imported',
						'value' => basename($media->guid->rendered),
					]
				]
			]);

			$local_image_id = $this->check_local_image_exists($media->guid->rendered);

			if ( ! $local_image_id ) {
	   			$tmp_media = download_url($media->guid->rendered);

				$file_array = [
					'name' => basename($media->guid->rendered),
					'tmp_name' => $tmp_media
				];

				if ( is_wp_error($tmp_media) ) {
					@unlink($file_array['tmp_name']);
				}

				else {
					$image_id = media_handle_sideload($file_array);
					set_post_thumbnail($post_id, $image_id);

					update_post_meta($image_id, 'logic_imported', basename($media->guid->rendered));
				}
			}

			else {
				// MAKE IMAGE FEATURED
				set_post_thumbnail($post_id, $local_image_id);
			}
		}

		///////////////////////
		// IMAGES IN CONTENT //
		///////////////////////
		$content_images = $this->findAllImageUrls(stripslashes($body->content->rendered));

		if ( count($content_images) > 0 ) {

			$post_content = $body->content->rendered;
			
			foreach ( $content_images as $image ) {
				$local_image_id = $this->check_local_image_exists($image['url']);

				if ( ! $local_image_id ) {
					$tmp_media = download_url($image['url']);

					$file_array = [
						'name' => basename($image['url']),
						'tmp_name' => $tmp_media
					];

					$local_image_id = media_handle_sideload($file_array);
					update_post_meta($local_image_id, 'logic_imported', basename($image['url']));
				}

				else {

					$local_image_url = wp_get_attachment_image_url($local_image_id, 'large');

					// REPLACE REMOTE IMAGE URL WITH LOCAL
					$post_content = preg_replace('/' . preg_quote($image['url'], '/') . '/', $local_image_url, $post_content);
					
					// REMOVE SRCSET (TOO COMPLICATED)
					$post_content = preg_replace('/(srcset=\".*?\")|(sizes=\".*\")/', '', $post_content);
				}
			}

			// SAVE NEW CONTENT
			$has_updated = wp_update_post([
				'ID' => $post_id,
				'post_content' => $post_content
			]);
		}

		return get_post($post_id);
	}

	function findAllImageUrls($content = '')
	{
        $urls1 = array();

        preg_match_all('/<img[^>]*src=["\']([^"\']*)[^"\']*["\'][^>]*>/i', $content, $urls, PREG_SET_ORDER);
        $urls = array_merge($urls, $urls1);

        if (count($urls) == 0) {
            return array();
        }
        foreach ($urls as $index => &$url) {
            $images[$index]['alt'] = preg_match('/<img[^>]*alt=["\']([^"\']*)[^"\']*["\'][^>]*>/i', $url[0], $alt) ? $alt[1] : null;
            $images[$index]['url'] = $url = $url[1];
        }
        foreach (array_unique($urls) as $index => $url) {
            $unique_array[] = $images[$index];
        }
        return $unique_array;
	}

	function check_local_image_exists($remote_image = null)
	{
		$local_image = get_posts([
			'post_type' => 'attachment',
			'meta_query' => [
				[
					'key' 	=> 'logic_imported',
					'value' => basename($remote_image),
				]
			]
		]);

		if ( empty($local_image[0]->ID) ) {
			return false;
		}

		return $local_image[0]->ID;
	}
}

new LogicBlogMigration();
