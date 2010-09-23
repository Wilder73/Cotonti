<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=module
[END_COT_EXT]
==================== */

/**
 * RSS
 *
 * @package rss
 * @version 0.7.0
 * @author medar, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2010
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL');

/*
Example of feeds:

rss.php?c=topics&id=XX			=== Show posts from topic "XX" ===							=== Where XX - is code of topic ===

rss.php?c=section&id=XX 		=== Show posts from all topics of section "XX" ===			=== Where XX - is code of section (this and all subsections) forum ===

rss.php?c=forums				=== Show posts from all topics of all sections forum ===

rss.php?c=pages&id=XX			=== Show pages from category "XX" ===						=== Where XX - is code of category pages ===

rss.php
	OR rss.php?c=pages			=== Show pages from category "news" ===
*/

// Environment setup
define('COT_RSS', TRUE);
$env['location'] = 'rss';

cot_dieifdisabled($cfg['disable_rss']);

$c = cot_import('c', 'G', 'ALP');
$id = cot_import('id', 'G', 'ALP');
$c = empty($c) ? "pages" : $c;
$id = empty($id) ? "all" : $id;

header('Content-type: text/xml; charset=' . $cfg['module']['rss']['charset']);
$sys['now'] = time();

if ($usr['id'] === 0 && $cot_cache)
{
	$cache = $cot_cache->db->get($c . $id, 'rss');
	if ($cache)
	{
		echo $cache;
		exit;
	}
}

$rss_title = $cfg['maintitle'];
$rss_link = $cfg['mainurl'];
$rss_description = $cfg['subtitle'];

$domain = $sys['domain'];
$defult_c = true;

/* === Hook === */
foreach (cot_getextplugins('rss.create') as $pl)
{
	include $pl;
}
/* ===== */

if ($c == "topics")
{
	cot_require('forums');

	$defult_c = false;
	$topic_id = ($id == 'all') ? 0 : $id;

	$sql = "SELECT * FROM $db_forum_topics WHERE ft_id='$topic_id'";
	$res = cot_db_query($sql);
	if (cot_db_affectedrows() > 0)
	{
		$row = cot_db_fetchassoc($res);
		if ($row['ft_mode'] == '1')
		{
			die('This topic is private'); // TODO: Need translate
		}

		$rss_title = $domain." : ".$row['ft_title'];
		$rss_description = $L['rss_topic_item_desc'];

		// check forum read permission for guests
		$forum_id = $row['ft_sectionid'];
		if (!cot_auth('forums', $forum_id, 'R'))
		{
			die('Not readable for guests'); // TODO: Need translate
		}

		// get number of posts in topic
		$sql = "SELECT COUNT(*) FROM $db_forum_posts WHERE fp_topicid='$topic_id'";
		$res = cot_db_query($sql);
		$totalposts = cot_db_result($res, 0, "COUNT(*)");

		$sql = "SELECT * FROM $db_forum_posts WHERE fp_topicid='$topic_id' ORDER BY fp_creation DESC LIMIT ".$cfg['rss_maxitems'];
		$res = cot_db_query($sql);
		$i = 0;
		while ($row = cot_db_fetchassoc($res))
		{
			$totalposts--;
			$curpage = $cfg['maxtopicsperpage'] * floor($totalposts / $cfg['maxtopicsperpage']);

			$post_id = $row['fp_id'];
			$items[$i]['title'] = $row['fp_postername'];
			$items[$i]['description'] = cot_parse_post_text($post_id, $row['fp_text'], $row['fp_html']);
			$items[$i]['link'] = COT_ABSOLUTE_URL.cot_url('forums', "m=posts&q=$topic_id&d=$curpage", "#post$post_id", true);
			$items[$i]['pubDate'] = date('r', $row['fp_creation']);
			$i++;
		}
	}
}
elseif ($c == "section")
{
	cot_require('forums');

	$defult_c = false;
	$forum_id = ($id == 'all') ? 0 : $id;;

	$sql = "SELECT * FROM $db_forum_sections WHERE fs_id = '$forum_id'";
	$res = cot_db_query($sql);
	if (cot_db_affectedrows() > 0)
	{
		$row = cot_db_fetchassoc($res);
		$section_title = $row['fs_title'];
		$section_desc = $row['fs_desc'];
		$rss_title = $section_title;
		$rss_description = $section_desc;

		$where = "fp_sectionid = '$forum_id'";
		// get subsections
		unset($subsections);
		$sql = "SELECT fs_id FROM $db_forum_sections WHERE fs_mastername = '$section_title'";
		$res = cot_db_query($sql);
		while ($row = cot_db_fetchassoc($res))
		{
			$where .= " OR fp_sectionid ='{$row['fs_id']}'";
		}

		$sql = "SELECT * FROM $db_forum_posts WHERE $where ORDER BY fp_creation DESC LIMIT ".$cfg['rss_maxitems'];
		$res = cot_db_query($sql);
		$i = 0;

		while ($row = cot_db_fetchassoc($res))
		{
			$post_id = $row['fp_id'];
			$topic_id = $row['fp_topicid'];

			$flag_private = 0;
			$sql = "SELECT * FROM $db_forum_topics WHERE ft_id='$topic_id'";
			$res2 = cot_db_query($sql);
			$row2 = cot_db_fetchassoc($res2);
			$topic_title = $row2['ft_title'];
			if ($row2['ft_mode'] == '1')
			{
				$flag_private = 1;
			}

			if (!$flag_private AND cot_auth('forums', $forum_id, 'R'))
			{
				//$post_url = ($cfg['plugin']['search']['searchurls'] == 'Single') ? cot_url('forums', 'm=posts&id='.$post_id, "", true) : cot_url('forums', 'm=posts&p='.$post_id, '#'.$post_id, true);
				$post_url = cot_url('forums', 'm=posts&p='.$post_id, '#'.$post_id, true);
				$items[$i]['title'] = $row['fp_postername']." - ".$topic_title;
				$items[$i]['description'] = cot_parse_post_text($post_id, $row['fp_text'], $row['fp_html']);
				$items[$i]['link'] = COT_ABSOLUTE_URL.$post_url;
				$items[$i]['pubDate'] = date('r', $row['fp_creation']);
			}

			$i++;
		}
	}
}
elseif ($c == "forums")
{
	cot_require('forums');

	$defult_c = false;
	$rss_title = $domain." : ".$L['rss_allforums_item_title'];
	$rss_description = "";

	$sql = "SELECT * FROM $db_forum_posts ORDER BY fp_creation DESC LIMIT ".$cfg['rss_maxitems'];
	$res = cot_db_query($sql);
	$i = 0;
	while ($row = cot_db_fetchassoc($res))
	{
		$post_id = $row['fp_id'];
		$topic_id = $row['fp_topicid'];
		$forum_id = $row['fp_sectionid'];

		$flag_private = 0;
		$sql = "SELECT * FROM $db_forum_topics WHERE ft_id='$topic_id'";
		$res2 = cot_db_query($sql);
		$row2 = cot_db_fetchassoc($res2);
		$topic_title = $row2['ft_title'];
		if ($row2['ft_mode'] == '1')
		{
			$flag_private = 1;
		}

		if (!$flag_private AND cot_auth('forums', $forum_id, 'R'))
		{
			$items[$i]['title'] = $row['fp_postername']." - ".$topic_title;
			$items[$i]['description'] = cot_parse_post_text($post_id, $row['fp_text'], $row['fp_html']);
			$items[$i]['link'] = COT_ABSOLUTE_URL.cot_url('forums', "m=posts&p=$post_id", "#$post_id", true);
			$items[$i]['pubDate'] = date('r', $row['fp_creation']);
		}

		$i++;
	}
}
elseif ($defult_c)
{
	cot_require('page');
	
	if ($id != 'all')
	{
		$mtch = $cot_cat[$id]['path'].".";
		$mtchlen = strlen($mtch);
		$catsub = array();
		$catsub[] = $id;

		foreach ($cot_cat as $i => $x)
		{
			if (substr($x['path'], 0, $mtchlen) == $mtch)
			{
				$catsub[] = $i;
			}
		}

		$sql = cot_db_query("SELECT * FROM $db_pages WHERE page_state=0 AND page_cat NOT LIKE 'system' AND page_cat IN ('".implode("','", $catsub)."') ORDER BY page_date DESC LIMIT ".$cfg['rss_maxitems']);
	}
	else
	{
		$sql = cot_db_query("SELECT * FROM $db_pages WHERE page_state=0 AND page_cat NOT LIKE 'system' ORDER BY page_date DESC LIMIT ".$cfg['rss_maxitems']);
	}
	$i = 0;
	while ($row = cot_db_fetchassoc($sql))
	{
		$row['page_pageurl'] = (empty($row['page_alias'])) ? cot_url('page', 'id='.$row['page_id']) : cot_url('page', 'al='.$row['page_alias']);

		$items[$i]['title'] = $row['page_title'];
		$items[$i]['link'] = COT_ABSOLUTE_URL . $row['page_pageurl'];
		$items[$i]['pubDate'] = date('r', $row['page_date']);
		$items[$i]['description'] = cot_parse_page_text($row['page_id'], $row['page_type'], $row['page_text'], $row['page_html'], $row['page_pageurl']);

		$i++;
	}
}

$t = new XTemplate(cot_skinfile('rss'));
$t->assign(array(
	"RSS_ENCODING" => $cfg['rss_charset'],
	"RSS_TITLE" => htmlspecialchars($rss_title),
	"RSS_LINK" => $rss_link,
	"RSS_LANG" => $cfg['defaultlang'],
	"RSS_DESCRIPTION" => htmlspecialchars($rss_description),
	"RSS_DATE" => date("r", time())
));

if (count($items) > 0)
{
	foreach ($items as $item)
	{
		$t->assign(array(
			"RSS_ROW_TITLE" => htmlspecialchars($item['title']),
			"RSS_ROW_DESCRIPTION" => cot_convert_relative_urls($item['description']),
			"RSS_ROW_DATE" => $item['pubDate'],
			"RSS_ROW_LINK" => $item['link']
		));
		$t->parse("MAIN.ITEM_ROW");
	}
}

/* === Hook === */
foreach (cot_getextplugins('rss.output') as $pl)
{
	include $pl;
}
/* ===== */

$t->parse("MAIN");
$out_rss = $t->out("MAIN");

if ($usr['id'] === 0 && $cot_cache)
{
	$cot_cache->db->store($c . $id, $out_rss, 'rss', $cfg['rss_timetolive']);
}
echo $out_rss;

function cot_parse_page_text($pag_id, $pag_type, $pag_text, $pag_html, $pag_pageurl)
{
	global $cfg, $db_pages, $usr;
	switch($pag_type)
	{
		case '1':
			$text = $pag_text;
		break;

		case '2':
			if ($cfg['allowphp_pages'] && $cfg['allowphp_override'])
			{
				ob_start();
				eval($pag_text);
				$text = ob_get_clean();
			}
			else
			{
				$text = "The PHP mode is disabled for pages.<br />Please see the administration panel, then \"Configuration\", then \"Parsers\"."; // TODO: Need translate
			}
		break;

		default:
			if ($cfg['parser_cache'])
			{
				if (empty($pag_html))
				{
					$pag_html = cot_parse(htmlspecialchars($pag_text), $cfg['parsebbcodepages'], $cfg['parsesmiliespages'], 1);
					cot_db_query("UPDATE $db_pages SET page_html = '".cot_db_prep($pag_html)."' WHERE page_id = ".$pag_id);
				}
				$readmore = mb_strpos($pag_html, "<!--more-->");
				if ($readmore > 0)
				{
					$pag_html = mb_substr($pag_html, 0, $readmore);
					$pag_html .= " <span class=\"readmore\"><a href=\"".$pag_pageurl."\">".$L['ReadMore']."</a></span>";
				}

				$newpage = mb_strpos($pag_html, '[newpage]');

				if ($newpage !== false)
				{
					$pag_html = mb_substr($pag_html, 0, $newpage);
				}

				$pag_html = preg_replace('#\[title\](.*?)\[/title\][\s\r\n]*(<br />)?#i', '', $pag_html);

				$cfg['parsebbcodepages'] ? $text = cot_post_parse($pag_html, 'pages') : $text = htmlspecialchars($pag_text);
			}
			else
			{
				$pag_text = cot_parse(htmlspecialchars($pag_text), $cfg['parsebbcodepages'], $cfg['parsesmiliespages'], 1);
				$readmore = mb_strpos($pag_text, "<!--more-->");
				if ($readmore > 0)
				{
					$pag_text = mb_substr($pag_text, 0, $readmore);
					$pag_text .= " <span class=\"readmore\"><a href=\"".$pag_pageurl."\">".$L['ReadMore']."</a></span>";
				}

				$newpage = mb_strpos($pag_html, '[newpage]');

				if ($newpage !== false)
				{
					$pag_html = mb_substr($pag_html, 0, $newpage);
				}

				$pag_html = preg_replace('#\[title\](.*?)\[/title\][\s\r\n]*(<br />)?#i', '', $pag_html);

				$pag_text = cot_post_parse($pag_text, 'pages');
				$text = $pag_text;
			}
		break;
	}
	if ((int)$cfg['rss_pagemaxsymbols'] > 0)
	{
		$text .= (cot_string_truncate($text, $cfg['rss_pagemaxsymbols'])) ? '...' : '';
	}
	return $text;
}

function cot_parse_post_text($post_id, $post_text, $post_html)
{
	global $cfg, $db_forum_posts, $usr, $fs_allowbbcodes, $fs_allowsmilies;
	if ($cfg['parser_cache'])
	{
		if (empty($post_html) && !empty($post_text))
		{
			$post_html = cot_parse($post_text, $cfg['parsebbcodeforums'] && $fs_allowbbcodes, $cfg['parsesmiliesforums'] && $fs_allowsmilies, 1);
			cot_db_query("UPDATE $db_forum_posts SET fp_html = '".cot_db_prep($post_html)."' WHERE fp_id = ".$post_id);
		}
		$post_text = cot_post_parse($post_html, 'forums');
	}
	else
	{
		$post_text = cot_parse($post_text, ($cfg['parsebbcodeforums'] && $fs_allowbbcodes), ($cfg['parsesmiliesforums'] && $fs_allowsmilies), 1);
		$post_text = cot_post_parse($post_text, 'forums');
	}
	if ((int)$cfg['rss_postmaxsymbols'] > 0)
	{
		$post_text .= (cot_string_truncate($text, $cfg['rss_postmaxsymbols'])) ? '...' : '';
	}
	return $post_text;
}

function cot_relative2absolute($matches)
{
	global $sys;
	$res = $matches[1].$matches[2].'='.$matches[3];
	if (preg_match('#^(http|https|ftp)://#', $matches[4]))
	{
		$res .= $matches[4];
	}
	else
	{
		if ($matches[4][0] == '/')
		{
			$scheme = $sys['secure'] ? 'https' : 'http';
			$res .= $scheme . '://' . $sys['host'] . $matches[4];
		}
		else
		{
			$res .= COT_ABSOLUTE_URL . $matches[4];
		}
	}
	$res .= $matches[5];
	return $res;
}

function cot_convert_relative_urls($text)
{
	$text = preg_replace_callback('#(\s)(href|src)=("|\')?([^"\'\s>]+)(["\'\s>])#', 'cot_relative2absolute', $text);
	return $text;
}

?>