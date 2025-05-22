<?php

function cpm_render_admin_page()
{

	$remote_url = get_option('cpm_remote_url', '');
	$secure_token = get_option('cpm_secure_token', '');

	if (empty($remote_url) || empty($secure_token)) {
		echo '<div class="notice notice-error"><p>Please set both the Remote URL and Secure Token in the plugin settings.</p></div>';
		return;
	}

	$result = test_remote_wordpress_connection($remote_url, $secure_token);
	if (is_wp_error($result)) {
		echo '<div class="notice notice-error"><p>Error: ' . esc_html($result->get_error_message()) . '</p></div>';
		return;
	} elseif ($result !== true) {
		echo '<div class="notice notice-error"><p>Error: ' . esc_html($result) . '</p></div>';
		return;
	} else {
		echo '<div class="notice notice-success"><p>Connection successful!</p></div>';
	}

?>
	<div class="wrap">
		<h1>Post Migrator</h1>
		<p>Use this tool to migrate your blog posts.</p>
		<p style="margin-bottom: 20px"><button id="cpm-fetch-posts" class="button button-primary">Fetch Posts from Source</button></p>
		<div id="cpm-posts-table-container"></div>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const fetchBtn = document.getElementById('cpm-fetch-posts');

			fetchBtn.addEventListener('click', function() {
				fetchBtn.disabled = true;
				fetchBtn.textContent = 'Fetching...';

				fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
						},
						body: 'action=cpm_fetch_remote_posts'
					})
					.then(response => response.json())
					.then(data => {
						console.log(data)
						fetchBtn.disabled = false;
						fetchBtn.textContent = 'Fetch Posts from Source';

						let container = document.getElementById('cpm-posts-table-container');
						if (!container) {
							container = document.createElement('div');
							container.id = 'cpm-posts-table-container';
							fetchBtn.parentNode.appendChild(container);
						}
						if (data.error) {
							container.innerHTML = '<div class="notice notice-error"><p>' + data.error + '</p></div>';
							return;
						}
						if (!Array.isArray(data) || data.length === 0) {
							container.innerHTML = '<div class="notice notice-warning"><p>No posts found.</p></div>';
							return;
						}

						let html = `<table class="widefat striped" style="margin-top: 20px; margin-bottom: 20px;">
										<thead>
											<tr>
												<th width="60">Import</th>
												<th width="60">Force</th>
												<th>ID</th>
												<th>Title</th>
												<th>Post Date</th>
												<th>Import Status</th>
											</tr>
										</thead>
										<tbody>`;

						data.forEach(post => {
							const dateFormatted = new Date(post.date).toLocaleDateString('en-GB', {
								year: 'numeric',
								month: '2-digit',
								day: '2-digit'
							});
							html += `<tr>
										<td><input type="checkbox" class="cpm-post-checkbox" value="${post.id}"></td>
										<td><input type="checkbox" class="cpm-post-checkbox-force" value="${post.id}" disabled></td>
										<td>${post.id}</td>
										<td>${post.title.rendered}</td>
										<td>${dateFormatted}</td>
										<td><span class="cpm-post-import-status"></span></td>
									</tr>`;
						});

						html += `</tbody>
							</table>`;

						container.innerHTML = html;

						container.querySelectorAll('.cpm-post-checkbox').forEach(cb => {
							cb.addEventListener('change', function() {
								const row = cb.closest('tr');
								const forceCb = row.querySelector('.cpm-post-checkbox-force');
								if (forceCb) {
									forceCb.disabled = !cb.checked;
									if (!cb.checked) {
										forceCb.checked = false;
									}
								}
							});
						});

						let checkAllDiv = document.createElement('div');
						checkAllDiv.style.marginBottom = '10px';

						['Check All', 'Uncheck All'].forEach((label, idx) => {
							let btn = document.createElement('input');
							btn.type = 'button';
							btn.value = label;
							btn.className = 'button';
							if (idx === 0) btn.style.marginRight = '10px';
							btn.onclick = () => {
								container.querySelectorAll('.cpm-post-checkbox').forEach(cb => cb.checked = idx === 0);
							};
							checkAllDiv.appendChild(btn);
						});

						container.insertBefore(checkAllDiv, container.firstChild);

						let importBtn = document.createElement('input');
						importBtn.type = 'button';
						importBtn.value = 'Import Selected Posts';
						importBtn.id = 'cpm-import-posts-btn';
						importBtn.className = 'button button-secondary';
						container.appendChild(importBtn);

						importBtn.addEventListener('click', async function() {
							const checkedBoxes = container.querySelectorAll('.cpm-post-checkbox:checked');
							const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);

							checkedBoxes.forEach(cb => {
								const row = cb.closest('tr');
								const statusCell = row.querySelector('.cpm-post-import-status');
								if (statusCell) {
									statusCell.textContent = 'Pending...';
								}
							});

							for (const postId of selectedIds) {
								const row = container.querySelector(`.cpm-post-checkbox[value="${postId}"]`).closest('tr');
								const statusCell = row.querySelector('.cpm-post-import-status');
								const forceCb = row.querySelector('.cpm-post-checkbox-force');
								const forceSelected = forceCb && forceCb.checked;

								statusCell.textContent = 'Importing...';

								try {
									const endpoint = `${ajaxurl}?action=cpm_import_post&post_id=${encodeURIComponent(postId)}&forceImport=${encodeURIComponent(forceSelected)}`
									const response = await fetch(endpoint, {
										method: 'GET',
										credentials: 'same-origin'
									});
									const result = await response.json();
									if (result.success) {
										statusCell.textContent = result.data || 'Import successful';
										statusCell.style.color = 'green';
									} else {
										statusCell.textContent = result.error || 'Import failed';
										statusCell.style.color = 'red';
									}
								} catch (err) {
									statusCell.textContent = 'Request error';
									statusCell.style.color = 'red';
								}

								await new Promise(resolve => setTimeout(resolve, 1000));
							}

							container.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
							container.querySelectorAll('.cpm-post-checkbox-force').forEach(cb => cb.disabled = true);

						});

					})
					.catch(error => {
						fetchBtn.disabled = false;
						fetchBtn.textContent = 'Fetch Posts from Source';
						let container = document.getElementById('cpm-posts-table-container');
						if (!container) {
							container = document.createElement('div');
							container.id = 'cpm-posts-table-container';
							fetchBtn.parentNode.appendChild(container);
						}
						container.innerHTML = '<div class="notice notice-error"><p>Error fetching posts.</p></div>';
					});
			});
		});
	</script>

<?php
}
