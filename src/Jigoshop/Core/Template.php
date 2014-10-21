<?php


namespace Jigoshop\Core;

use Jigoshop\Exception;
use Jigoshop\Frontend\Page\PageInterface;
use Jigoshop\Helper\Render;
use WPAL\Wordpress;

/**
 * Class binding all basic templates.
 *
 * @package Jigoshop\Core
 */
class Template
{
	/** @var \WPAL\Wordpress */
	private $wp;
	/** @var \Jigoshop\Core\Options */
	private $options;
	/** @var \Jigoshop\Core\Pages */
	private $pages;
	/** @var PageInterface */
	private $page;

	public function __construct(Wordpress $wp, Options $options, Pages $pages)
	{
		$this->wp = $wp;
		$this->options = $options;
		$this->pages = $pages;
	}

	/**
	 * Sets current page object.

	 *
*@param PageInterface $page
	 */
	public function setPage($page)
	{
		$this->page = $page;
	}

	/**
	 * Redirect Jigoshop pages to proper types.
	 */
	public function redirect()
	{
		if ($this->page !== null) {
			$this->page->action();
		}
	}

	/**
	 * Loads proper template based on current page.
	 *
	 * @param $template string Template chain.
	 * @return string Template to load.
	 */
	public function process($template)
	{
		if (!$this->pages->isJigoshop()) {
			return $template;
		}

		if ($this->page === null) {
			throw new Exception('Page object should already be set for Jigoshop pages, but none found.');
		}

		$content = $this->page->render();
		$template = $this->wp->getOption('template');
		$theme = $this->wp->wpGetTheme();
		if ($theme->get('Author') === 'WooThemes') {
			$template = 'woothemes';
		}

		Render::output('layout/'.$template, array(
			'content' => $content,
		));

		return false;
	}
}
