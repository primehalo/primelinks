<?php
/**
 *
 * Prime Links. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, Ken F. Innes IV, https://www.absoluteanime.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace primehalo\primelinks\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prime Links Event listener.
 */
class main_listener implements EventSubscriberInterface
{

	// Constants
	/** @const */
	private static $file_type_classes = array(		// Give links with these file types a specific class name. Separate file extensions with a vertical bar (|).
		'pdf'						=> 'pdf-link',	// PDF files
		'bmp|gif|jpeg|jpg|png|webp'	=> 'img-link',	// Image files
		'7z|gz|rar|tar|zip|zipx'	=> 'zip-link',	// Archive files
		'doc|docx|odt|rtf|txt|wpd'	=> 'doc-link',	// Document files
	);
	const USE_TARGET_ATTR	= true;	// The attribute "target" was deprecated in HTML 4.01 but is supported in HTML5.
	const GUEST_HIDE_NO		= 0;	// Value for $this->config['primelinks_inlink_guest_hide'] and $this->config['primelinks_exlink_guest_hide']
	const GUEST_HIDE_YES	= 1;	// Value for $this->config['primelinks_inlink_guest_hide'] and $this->config['primelinks_exlink_guest_hide']
	const GUEST_HIDE_MSG	= 2;	// Value for $this->config['primelinks_inlink_guest_hide'] and $this->config['primelinks_exlink_guest_hide']

	// Variables
	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var string phpBB root path */
	protected $board_url;

	/** @var string phpBB domain host */
	protected $board_host;

	/** @var array settings for this extension */
	protected $settings;

	public function __construct(\phpbb\template\template $template, \phpbb\user $user, \phpbb\config\config $config)
	{
		$this->template	= $template;
		$this->user		= $user;
		$this->config	= $config;

		$this->board_url	= generate_board_url(true);
		$this->board_url	= utf8_case_fold_nfc($this->board_url);
		$this->board_host	= $this->extract_host($this->board_url);
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.display_forums_before'				=> 'set_forumlist_tpl_vars',			// 3.1.4-RC1
			'core.memberlist_memberrow_before'			=> 'set_memberlist_tpl_vars',			// 3.1.7-RC1
			'core.memberlist_view_profile'				=> 'set_member_profile_tpl_vars',		// 3.1.0-a1
			'core.viewtopic_post_row_after'				=> 'set_member_post_tpl_vars',			// 3.1.0-a3
			'core.modify_format_display_text_after'		=> 'modify_format_display_text_after',	// 3.1.0-a3 Occurs in message_parser.php's format_display()
			'core.modify_text_for_display_after'		=> 'modify_text_for_display_after',		// 3.1.0-a1 Occurs in function_content.php's generate_text_for_display()
			'core.page_header' 							=> 'enable_style',						// 3.1.0-a1
		);
	}

	/**
	* Update links in posts, signatures, private messages, and many others.
	*/
	public function modify_text_for_display_after($event)
	{
		if (empty($this->config['primelinks_enable_general']))
		{
			return;
		}
		$text = $event['text'];
		$text = $this->modify_links($text);
		$event['text'] = $text;
	}

	/**
	* Update links in messages
	*/
	public function modify_format_display_text_after($event)
	{
		if (empty($this->config['primelinks_enable_general']))
		{
			return;
		}
		$text = $event['text'];
		$text = $this->modify_links($text);
		$event['text'] = $text;
	}

	/**
	* Set template variables to update the attributes of a member's website link on their profile page
	*/
	public function set_member_profile_tpl_vars($event)
	{
		if (empty($this->config['primelinks_enable_members']))
		{
			return;
		}
		$this->template->assign_var('PRIME_LINK_PAGE', 'member_profile');
		$this->set_exlink_tpl_vars();
	}

	/**
	* Set template variables to update the attributes of a member's website link in a post
	*/
	public function set_member_post_tpl_vars($event)
	{
		if (empty($this->config['primelinks_enable_members']))
		{
			return;
		}
		$this->template->assign_var('PRIME_LINK_PAGE', 'member_post');
		$this->set_exlink_tpl_vars();
	}


	/**
	* Set template variables to update the attributes of a member's website link in the member list
	*/
	public function set_memberlist_tpl_vars($event)
	{
		if (empty($this->config['primelinks_enable_members']))
		{
			return;
		}
		$this->template->assign_var('PRIME_LINK_PAGE', 'memberlist');
		$this->set_exlink_tpl_vars();
	}

	/**
	* Update link attributes for forum links (viewforum.php)
	*/
	public function set_forumlist_tpl_vars($event)
	{
		if (empty($this->config['primelinks_enable_forumlist']))
		{
			return;
		}
		$this->template->assign_var('PRIME_LINK_PAGE', 'forumlist');
		$this->set_exlink_tpl_vars();
	}

	/**
	* Set template variable to allow CSS styling of Prime Links
	*/
	public function enable_style($event)
	{
		if (empty($this->config['primelinks_enable_style']))
		{
			return;
		}
		$this->template->assign_var('S_PRIME_LINKS_STYLE', true);
	}

	/**
	* Set common template variables for external links
	*/
	private function set_exlink_tpl_vars()
	{
		$this->template->assign_var('PRIME_LINK_TARGET', $this->config['primelinks_exlink_target']);
		$this->template->assign_var('PRIME_LINK_REL', $this->config['primelinks_exlink_rel']);
		$this->template->assign_var('PRIME_LINK_CLASS', $this->config['primelinks_exlink_class']);
		$this->template->assign_var('PRIME_LINK_PREFIX', $this->config['primelinks_exlink_prefix']);
	}

	/**
	* Decodes all HTML entities. The html_entity_decode() function doesn't decode numerical entities,
	* and the htmlspecialchars_decode() function only decodes the most common form for entities.
	*/
	private function decode_entities($text)
	{
		$text = html_entity_decode($text, ENT_QUOTES, 'ISO-8859-1');				// UTF-8 does not work!
		$text = preg_replace_callback('/&#(\d+);/m', function($matches){			// Decimal notation
			return utf8_encode(chr($matches[1]));
		}, $text);
		$text = preg_replace_callback('/&#x([a-f0-9]+);/mi', function($matches){	// Hex notation
			return utf8_encode(chr($matches[1]));
		}, $text);
		return($text);
	}

	/**
	* Extract the host portion of a URL (the domain plus any subdomains)
	*/
	private function extract_host($url)
	{
		// Remove everything before and including the double slashes
		if (($double_slash_pos = strpos($url, '//')) !== false)
		{
			$url = substr($url, $double_slash_pos + 2);
		}

		// Remove everything after the domain, including the slash
		if (($domain_end_pos = strpos($url, '/')) !== false)
		{
			$url = substr($url, 0, $domain_end_pos);
		}
		return $url;
	}

	/**
	* Determine if the URL contains a domain.
	* $domains	: list of domains (an array or a string separated by semicolons)
	* $remove	: list of subdomains to remove (or TRUE/FALSE to remove all/none)
	*/
	private function match_domain($url, $domains)
	{
		$url = $this->extract_host($url);
		$url = utf8_case_fold_nfc($url);
		$url_split = array_reverse(explode('.', $url));

		//$domain_list = is_string($domains) ? explode(';', $domains) : $domains;
		$domain_list = is_string($domains) ? preg_split('/[\s,;]/', $domains, NULL, PREG_SPLIT_NO_EMPTY) : $domains;
		foreach ($domain_list as $domain)
		{
			$domain = $this->extract_host($domain);
			$domain = utf8_case_fold_nfc($domain);

			// Ignoring all subdomains, so check if our URL ends with domain
			if (substr($url, -strlen($domain)) == $domain)
			{
				return true;
			}
			$domain_split = array_reverse(explode('.', $domain));
			$match_count = 0;
			$match_list = array();
			foreach ($domain_split as $index => $segment)
			{
				if (isset($url_split[$index]) && strcmp($url_split[$index], $segment) === 0)
				{
					$match_count += 1;
					array_splice($match_list, 0, 0, $segment);
					continue;
				}
				break;
			}
			if ($match_count > 2 || ($match_count == 2 && strlen($match_list[0]) > 2)) // not the best check, but catches domains like 'co.jp'
			{
				return true;
			}
		}
		return false;
	}

	/**
	* Determines if a URL is local or external. If no valid-ish scheme is found,
	* assume a relative (thus internal) link that happens to contain a colon (:).
	*/
	private function is_url_local($url)
	{
		$url = strtolower($url);

		// Compare the URLs
		if (!($is_local = $this->match_domain($url, $this->board_url)))
		{
			// If there is no scheme, then it's probably a relative, local link
			$scheme = substr($url, 0, strpos($url, '://'));
			//$is_local = !$scheme || ($scheme && !in_array($scheme, array('http', 'https', 'mailto', 'ftp', 'gopher')));
			$is_local = !$scheme || ($scheme && !preg_match('/^[a-z0-9.]{2,16}$/i', $scheme));
		}

		// Not local, now check forced local domains
		if (!$is_local && $this->config['primelinks_inlink_domains'])
		{
			$is_local = $this->match_domain($url,$this->config['primelinks_inlink_domains']);
		}
		return($is_local);
	}

	/**
	* Removes an attribute from an HTML tag.
	*/
	private function remove_attribute($attr_name, $html_tag)
	{
		$html_tag = preg_replace('/\s+' . $attr_name . '="[^"]*"/i', '', $html_tag);
		return $html_tag;
	}

	/**
	* Insert an attribute into an HTML tag.
	*/
	private function insert_attribute($attr_name, $new_attr, $html_tag, $overwrite = false)
	{
		$javascript	= (strpos($attr_name, 'on') === 0);	// onclick, onmouseup, onload, etc.
		$old_attr	= preg_replace('/^.*' . $attr_name . '="([^"]*)".*$/i', '$1', $html_tag);
		$is_attr	= !($old_attr == $html_tag);		// Does the attribute already exist?
		$old_attr	= ($is_attr) ? $old_attr : '';

		if ($javascript)
		{
			if ($is_attr && !$overwrite)
			{
				$old_attr = ($old_attr && ($last_char = substr(trim($old_attr), -1)) && $last_char != '}' && $last_char != ';') ? $old_attr . ';' : $old_attr; // Ensure we can add code after any existing code
				$new_attr = $old_attr . $new_attr;
			}
			$overwrite = true;
		}

		if ($overwrite && is_string($overwrite))
		{
			if (strpos(' ' . $overwrite . ' ', ' ' . $old_attr . ' ') !== false)
			{
				// Overwrite the specified value if it exists, otherwise just append the value.
				$new_attr = trim(str_replace(' '  . $overwrite . ' ', ' ' . $new_attr . ' ', ' '  . $old_attr . ' '));
			}
			else
			{
				$overwrite = false;
			}
		}
		if (!$overwrite)
		{
			 // Append the new one if it's not already there.
			$new_attr = strpos(' ' . $old_attr . ' ', ' ' . $new_attr . ' ') === false ? trim($old_attr . ' ' . $new_attr) : $old_attr;
		}

		$html_tag = $is_attr ? str_replace("$attr_name=\"$old_attr\"", "$attr_name=\"$new_attr\"", $html_tag) : str_replace('>', " $attr_name=\"$new_attr\">", $html_tag);
		return($html_tag);
	}

	/**
	* Modify links within a block of text.
	*/
	private function modify_links($message = '')
	{
		// A quick check before we start using regular expressions
		if (strpos($message, '<a ') === false)
		{
			return($message);
		}

		$this->user->add_lang_ext('primehalo/primelinks', 'common');
		preg_match_all('#(<a\s[^>]+?>)(.*?)(</a>)#i', $message, $matches, PREG_SET_ORDER);
		foreach ($matches as $linkbd)
		{
			// Link breakdown
			$linkbd['full']		= $linkbd[0];
			$linkbd['open']		= $linkbd[1];
			$linkbd['text']		= $linkbd[2];
			$linkbd['close']	= $linkbd[3];

			// Get the basic link information
			$link = $new_link = $linkbd['open'];
			$href = preg_replace('/^.*href="([^"]*)".*$/i', '$1', $link);

			// No HREF was found in this anchor tag so it's not a link and we don't need to process it
			if ($href == $link)
			{
				continue;
			}

			// Check the link's protocol
			$href	= $this->decode_entities($href);
			$scheme	= substr($href, 0, strpos($href, ':'));
			if ($scheme)
			{
				$scheme = strtolower($scheme);
				if ($scheme != 'http' && $scheme != 'https') // Only classify links for these schemes (or no scheme)
				{
					continue;
				}
			}

			// Check if we should skip this link
			if ($this->config['primelinks_skip_regex'] && @preg_match($this->config['primelinks_skip_regex'], $href))
			{
				continue;
			}

			$is_local = null;
			$is_local = ($this->config['primelinks_inlink_regex'] && @preg_match($this->config['primelinks_inlink_regex'], $href)) ? true : $is_local;
			$is_local = ($this->config['primelinks_exlink_regex'] && @preg_match($this->config['primelinks_exlink_regex'], $href)) ? false : $is_local;
			if ($is_local === null)
			{
				if ($this->config['primelinks_forbidden_domains'] && $this->match_domain($href, $this->config['primelinks_forbidden_domains']))
				{
					$new_text = !empty($this->config['primelinks_forbidden_msg']) ? $this->user->lang('PRIMELINKS_FORBIDDEN_MSG') : $linkbd['text'];
					if (empty($this->config['primelinks_forbidden_new_url']))
					{
						$new_link = '<span class="link_removed">' . $new_text . '</span>';
					}
					else
					{
						$new_link = $this->insert_attribute('href', $this->config['primelinks_forbidden_new_url'], $new_link, true) . $new_text . $linkbd['close'];
					}
					$searches[]		= $linkbd['full'];
					$replacements[]	= $new_link;
					continue;
				}
				$is_local = $this->is_url_local($href);
			}
			$new_class	= $is_local ? $this->config['primelinks_inlink_class'] : $this->config['primelinks_exlink_class'];
			$new_target	= $is_local ? $this->config['primelinks_inlink_target'] : $this->config['primelinks_exlink_target'];
			$new_rel	= $is_local ? $this->config['primelinks_inlink_rel'] : $this->config['primelinks_exlink_rel'];

			// Check if this link needs a special class based on the type of file to which it points.
			foreach (self::$file_type_classes as $extensions => $class)
			{
				if ($class && $extensions && preg_match('/\.(?:' . $extensions . ')(?:[#?]|$)/', $href))
				{
					$new_class .= ' ' . $class;
					break;
				}
			}
			if ($new_class)
			{
				$new_link = $this->insert_attribute('class', $new_class, $new_link, 'postlink');
			}
			if ($new_rel)
			{
				$new_link = $this->insert_attribute('rel', $new_rel, $new_link);
			}
			if ($new_target)
			{
				if (self::USE_TARGET_ATTR === true)
				{
					$new_link = $this->insert_attribute('target', $new_target, $new_link, true);
				}
				else
				{
					$new_link = $this->insert_attribute('onclick', "this.target='$new_target';", $new_link);
				}
			}
			// Remove the link?
			$is_guest = empty($this->user->data['is_registered']);
			if ($new_target === false || ($is_guest && $this->config['primelinks_exlink_guest_hide'] && !$is_local) || ($is_guest && $this->config['primelinks_inlink_guest_hide'] && $is_local))
			{
				$new_text = $linkbd['text']; //$new_text = substr($linkbd['text'], 0, -4);
				if ($is_guest)
				{
					$new_text = (($this->config['primelinks_inlink_guest_hide'] == self::GUEST_HIDE_MSG) && $is_local) ? $this->user->lang['PRIMELINKS_INLINK_GUEST_MSG'] : $new_text;
					$new_text = (($this->config['primelinks_exlink_guest_hide'] == self::GUEST_HIDE_MSG) && !$is_local) ? $this->user->lang['PRIMELINKS_EXLINK_GUEST_MSG'] : $new_text;
				}
				$new_link = '<span class="link_removed">' . $new_text . '</span>';
				$link = $linkbd['full'];
			}
			else if ($is_local && $this->config['primelinks_inlink_prefix'])
			{
				$url_prefix = ($this->config['primelinks_skip_prefix_regex'] && @preg_match($this->config['primelinks_skip_prefix_regex'], $href)) ? '' : $this->config['primelinks_inlink_prefix'];
				$new_link = str_replace('href="', 'href="' . $url_prefix, $new_link);
			}
			else if (!$is_local && $this->config['primelinks_exlink_prefix'])
			{
				$url_prefix = ($this->config['primelinks_skip_prefix_regex'] && @preg_match($this->config['primelinks_skip_prefix_regex'], $href)) ? '' : $this->config['primelinks_exlink_prefix'];
				$new_link = str_replace('href="', 'href="' . $url_prefix, $new_link);
			}
			$searches[]		= $link;
			$replacements[]	= $new_link;
		}
		if (isset($searches) && isset($replacements))
		{
			$message = str_replace($searches, $replacements, $message);
		}
		return($message);
	}
}
