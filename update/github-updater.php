<?php

class GitHub_Updater
{
	private $slug;
	private $plugin_data;
	private $username;
	private $repo;
	private $plugin_file;
	private $github_response;

	public function __construct($plugin_file)
	{
		$this->plugin_file = $plugin_file;
		$this->slug = plugin_basename($plugin_file);

		add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
		add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
		add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
	}

	public function set_github_info($username, $repo)
	{
		$this->username = $username;
		$this->repo = $repo;
	}

	private function get_repository_info()
	{
		if (!empty($this->github_response)) {
			return;
		}

		$request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest',
			$this->username, $this->repo);

		$response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri)));

		if (is_array($response)) {
			return false;
		}

		$this->github_response = $response;
	}

	public function modify_transient($transient)
	{
		if (!isset($transient->checked)) {
			return $transient;
		}

		$this->get_repository_info();
		$this->plugin_data = get_plugin_data($this->plugin_file);

		$out_of_date = version_compare(
			str_replace('v', '', $this->github_response->tag_name),
			$this->plugin_data['Version'],
			'gt'
		);

		if ($out_of_date) {
			$package = $this->github_response->zipball_url;

			$new_data = [
				'slug' => $this->slug,
				'plugin' => $this->slug,
				'new_version' => str_replace('v', '', $this->github_response->tag_name),
				'url' => $this->plugin_data['PluginURI'],
				'package' => $package,
			];

			$transient->response[$this->slug] = (object)$new_data;
		}

		return $transient;
	}

	public function plugin_popup($result, $action, $args)
	{
		if ($action !== 'plugin_information') {
			return $result;
		}

		if (!isset($args->slug) || $args->slug !== $this->slug) {
			return $result;
		}

		$this->get_repository_info();

		return (object)[
			'name' => $this->plugin_data['Name'],
			'slug' => $this->slug,
			'version' => str_replace('v', '', $this->github_response->tag_name),
			'author' => $this->plugin_data['AuthorName'],
			'author_profile' => $this->plugin_data['AuthorURI'],
			'last_updated' => $this->github_response->published_at,
			'homepage' => $this->plugin_data['PluginURI'],
			'short_description' => $this->plugin_data['Description'],
			'sections' => [
				'Description' => $this->plugin_data['Description'],
				'Updates' => $this->github_response->body,
			],
			'download_link' => $this->github_response->zipball_url
		];
	}

	public function after_install($response, $hook_extra, $result)
	{
		global $wp_filesystem;

		$plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
		$wp_filesystem->move($result['destination'], $plugin_folder);
		$result['destination'] = $plugin_folder;

		return $result;
	}
}