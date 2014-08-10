<?php

namespace Jigoshop\Helper;

use Jigoshop\Core\Pages;
use WPAL\Wordpress;

/**
 * Scripts helper.
 *
 * @package Jigoshop\Helper
 * @author Amadeusz Starzykiewicz
 */
class Scripts
{
	/** @var \Jigoshop\Core\Pages */
	private $pages;
	/** @var \WPAL\Wordpress */
	private $wp;

	public function __construct(Wordpress $wp, Pages $pages)
	{
		$this->wp = $wp;
		$this->pages = $pages;
	}

	/**
	 * Enqueues script.
	 * Calls filter `jigoshop\script\add`. If the filter returns empty value the script is omitted.
	 * Available options:
	 *   * version - Wordpress script version number
	 *   * in_footer - is this script required to add to the footer?
	 *   * page - list of pages to use the script
	 * Options could be extended by plugins.
	 *
	 * @param string $handle Handle name.
	 * @param bool $src Source file.
	 * @param array $dependencies List of dependencies to the script.
	 * @param array $options List of options.
	 * @since 2.0
	 */
	public function add($handle, $src, array $dependencies = array(), array $options = array())
	{
		$page = isset($options['page']) ? (array)$options['page'] : array('all');

		if ($this->pages->isOneOf($page)) {
			$handle = $this->wp->applyFilters('jigoshop\script\add', $handle, $src, $dependencies, $options);

			if (!empty($handle)) {
				$version = isset($options['version']) ? $options['version'] : false;
				$footer = isset($options['in_footer']) ? $options['in_footer'] : false;
				$this->wp->wpEnqueueScript($handle, $src, $dependencies, $version, $footer);
			}
		}
	}

	/**
	 * Localizes script.
	 * Calls filter `jigoshop\script\localize`. If the filter returns empty value the script is omitted.
	 *
	 * @param string $handle Handle name.
	 * @param string $variable Variable name.
	 * @param array $value List of values to localize.
	 */
	public function localize($handle, $variable, array $value)
	{
		$handle = apply_filters('jigoshop\script\localize', $handle, $value, $value);

		if (!empty($handle)) {
			wp_localize_script($handle, $variable, $value);
		}
	}
}