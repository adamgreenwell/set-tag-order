<?php
/**
 * GitHub Updater Class
 *
 * Handles checking for updates and installing updates from GitHub repositories.
 *
 * @package    SetTagOrder
 * @subpackage Update
 * @author     Adam Greenwell
 * @since      1.0.2
 */

/**
 * Set Tag Order GitHub Updater
 *
 * Provides update functionality by connecting to GitHub API and comparing
 * version information between the installed plugin and the latest release.
 *
 * @since 1.0.2
 */
class Set_Tag_Order_GitHub_Updater
{
	/**
	 * Plugin slug
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin data from get_plugin_data()
	 *
	 * @since 1.0.2
	 * @var array
	 */
	private $plugin_data;

	/**
	 * GitHub username
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $username;

	/**
	 * GitHub repository name
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $repo;

	/**
	 * Full path to plugin file
	 *
	 * @since 1.0.2
	 * @var string
	 */
	private $plugin_file;

	/**
	 * GitHub API response data
	 *
	 * @since 1.0.2
	 * @var object
	 */
	private $github_response;

	/**
	 * Constructor
	 *
	 * Sets up the properties and hooks the actions and filters
	 *
	 * @since 1.0.2
	 * @param string $plugin_file Full path to the main plugin file
	 */
	public function __construct($plugin_file)
	{
		$this->plugin_file = $plugin_file;
		$this->slug = plugin_basename($plugin_file);

		add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
		add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
		add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
	}

	/**
	 * Set GitHub repository information
	 *
	 * @since 1.0.2
	 * @param string $username GitHub username
	 * @param string $repo     GitHub repository name
	 * @return void
	 */
	public function set_github_info($username, $repo)
	{
		$this->username = $username;
		$this->repo = $repo;
	}

	/**
	 * Fetch repository information from GitHub API
	 *
	 * @since 1.0.2
	 * @return void|bool False on failure
	 */
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

	/**
	 * Modify the transient before WordPress checks for plugin updates
	 *
	 * @since 1.0.2
	 * @param object $transient WordPress update transient
	 * @return object Modified transient
	 */
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

	/**
	 * Update plugin details in the plugin list popup
	 *
	 * @since 1.0.2
	 * @param false|object|array $result  The result object or array
	 * @param string             $action  The API action being performed
	 * @param object             $args    Plugin API arguments
	 * @return object Plugin information
	 */
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

	/**
	 * Actions to perform after installing an update
	 *
	 * @since 1.0.2
	 * @param bool  $response   Installation response
	 * @param array $hook_extra Extra arguments passed to hooked filters
	 * @param array $result     Installation result data
	 * @return array Modified installation result data
	 */
	public function after_install($response, $hook_extra, $result)
	{
		global $wp_filesystem;

		$plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
		$wp_filesystem->move($result['destination'], $plugin_folder);
		$result['destination'] = $plugin_folder;

		return $result;
	}
}