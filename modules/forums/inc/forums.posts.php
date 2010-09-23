<?php

/**
 * Forums posts display.
 *
 * @package forums
 * @version 0.7.0
 * @author Neocrome, Cotonti Team
 * @copyright Copyright (c) 2008-2010 Cotonti Team
 * @license BSD License
 */

defined('COT_CODE') or die('Wrong URL');

$id = cot_import('id','G','INT');
$s = cot_import('s','G','INT');
$q = cot_import('q','G','INT');
$p = cot_import('p','G','INT');
$d = cot_import('d','G','INT');
$quote = cot_import('quote','G','INT');
$unread_done = FALSE;
$fp_num = 0;

require_once cot_langfile('countries', 'core');

unset ($notlastpage);

/* === Hook === */
foreach (cot_getextplugins('forums.posts.first') as $pl)
{
	include $pl;
}
/* ===== */

if ($n=='last' && !empty($q))
{
	$sql = cot_db_query("SELECT fp_id, fp_topicid, fp_sectionid, fp_posterid
	FROM $db_forum_posts
	WHERE fp_topicid='$q'
	ORDER by fp_id DESC LIMIT 1");
	if ($row = cot_db_fetcharray($sql))
	{
		$p = $row['fp_id'];
		$q = $row['fp_topicid'];
		$s = $row['fp_sectionid'];
		$fp_posterid = $row['fp_posterid'];
	}
}
elseif ($n=='unread' && !empty($q) && $usr['id']>0)
{
	$sql = cot_db_query("SELECT fp_id, fp_topicid, fp_sectionid, fp_posterid
	FROM $db_forum_posts
	WHERE fp_topicid='$q' AND fp_updated > ". $usr['lastvisit']."
		ORDER by fp_id ASC LIMIT 1");
	if ($row = cot_db_fetcharray($sql))
	{
		$p = $row['fp_id'];
		$q = $row['fp_topicid'];
		$s = $row['fp_sectionid'];
		$fp_posterid = $row['fp_posterid'];
	}
}
elseif (!empty($p))
{
	$sql = cot_db_query("SELECT fp_topicid, fp_sectionid, fp_posterid
	FROM $db_forum_posts WHERE fp_id='$p' LIMIT 1");
	if ($row = cot_db_fetcharray($sql))
	{
		$q = $row['fp_topicid'];
		$s = $row['fp_sectionid'];
		$fp_posterid = $row['fp_posterid'];
	}
	else
	{
		cot_die();
	}
}
elseif (!empty($id))
{
	$sql = cot_db_query("SELECT fp_topicid, fp_sectionid, fp_posterid FROM $db_forum_posts WHERE fp_id='$id' LIMIT 1");
	if ($row = cot_db_fetcharray($sql))
	{
		$p = $id;
		$q = $row['fp_topicid'];
		$s = $row['fp_sectionid'];
		$fp_posterid = $row['fp_posterid'];
	}
	else
	{
		cot_die();
	}
}
elseif (!empty($q))
{
	$sql = cot_db_query("SELECT ft_sectionid FROM $db_forum_topics WHERE ft_id='$q' LIMIT 1");
	if ($row = cot_db_fetcharray($sql))
	{
		$s = $row['ft_sectionid'];
	}
	else
	{
		cot_die();
	}
}

$sql = cot_db_query("SELECT * FROM $db_forum_sections WHERE fs_id='$s' LIMIT 1");

if ($row = cot_db_fetcharray($sql))
{
	$fs_title = $row['fs_title'];
	$fs_category = $row['fs_category'];
	$fs_state = $row['fs_state'];
	$fs_allowusertext = $row['fs_allowusertext'];
	$fs_allowbbcodes = $row['fs_allowbbcodes'];
	$fs_allowsmilies = $row['fs_allowsmilies'];
	$fs_countposts = $row['fs_countposts'];
	$fs_masterid = $row['fs_masterid'];
	$fs_mastername = $row['fs_mastername'];

	list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = cot_auth('forums', $s);

	/* === Hook === */
	foreach (cot_getextplugins('forums.posts.rights') as $pl)
	{
		include $pl;
	}
	/* ===== */

	cot_block($usr['auth_read']);

	if ($fs_state)
	{
		cot_redirect(cot_url('message', "msg=602", '', true));
	}
}
else
{ 
	cot_die();
}

$sys['sublocation'] = $fs_title;
cot_online_update();

$cat = $cot_forums_str[$s];

if ($a=='newpost')
{
	cot_shield_protect();

	$sql = cot_db_query("SELECT ft_state, ft_lastposterid, ft_updated FROM $db_forum_topics WHERE ft_id='$q'");

	if ($row = cot_db_fetcharray($sql))
	{
		if ($row['ft_state'])
		{
			cot_die();
		}
		$merge = (!$cfg['antibumpforums'] && $cfg['mergeforumposts'] && $row['ft_lastposterid']==$usr['id']) ? true : false;
		if ($merge && $cfg['mergetimeout']>0 && ( ($sys['now_offset']-$row['ft_updated'])>($cfg['mergetimeout']*3600) ) )
		{
			$merge = false;
		}
	}

	$sql = cot_db_query("SELECT fp_posterid, fp_posterip FROM $db_forum_posts WHERE fp_topicid='$q' ORDER BY fp_id DESC LIMIT 1");

	if ($row = cot_db_fetcharray($sql))
	{
		if ($cfg['antibumpforums'] && ( ($usr['id']==0 && $row['fp_posterid']==0 && $row['fp_posterip']==$usr['ip']) || ($row['fp_posterid']>0 && $row['fp_posterid']==$usr['id']) ))
		{
			cot_die();
		}
	}
	else
	{
		cot_die();
	}

	/* === Hook === */
	foreach (cot_getextplugins('forums.posts.newpost.first') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$newmsg = cot_import('newmsg','P','HTM');

	if (!$cot_error && !empty($newmsg) && !empty($s) && !empty($q))
	{

		if (!$merge)
		{
			if($cfg['parser_cache'])
			{
				$rhtml = cot_db_prep(cot_parse(htmlspecialchars($newmsg), $cfg['parsebbcodeforums'] && $fs_allowbbcodes, $cfg['parsesmiliesforums'] && $fs_allowsmilies, 1));
			}
			else
			{
				$rhtml = '';
			}

			$sql = cot_db_query("INSERT into $db_forum_posts
			(fp_topicid,
			fp_sectionid,
			fp_posterid,
			fp_postername,
			fp_creation,
			fp_updated,
			fp_updater,
			fp_text,
			fp_html,
			fp_posterip)
			VALUES
			(".(int)$q.",
			".(int)$s.",
			".(int)$usr['id'].",
			'".cot_db_prep($usr['name'])."',
			".(int)$sys['now_offset'].",
			".(int)$sys['now_offset'].",
			0,
			'".cot_db_prep($newmsg)."',
			'$rhtml',
			'".$usr['ip']."')");

			$p = cot_db_insertid();

			$sql = cot_db_query("UPDATE $db_forum_topics SET
			ft_postcount=ft_postcount+1,
			ft_updated='".$sys['now_offset']."',
			ft_lastposterid='".$usr['id']."',
			ft_lastpostername='".cot_db_prep($usr['name'])."'
			WHERE ft_id='$q'");

			$sql = cot_db_query("UPDATE $db_forum_sections SET fs_postcount=fs_postcount+1 WHERE fs_id='$s'");
			$sql = ($fs_masterid>0) ? cot_db_query("UPDATE $db_forum_sections SET fs_postcount=fs_postcount+1 WHERE fs_id='$fs_masterid'") : '';


			if ($fs_countposts)
			{
				$sql = cot_db_query("UPDATE $db_users SET user_postcount=user_postcount+1 WHERE user_id='".$usr['id']."'");
			}

			/* === Hook === */
			foreach (cot_getextplugins('forums.posts.newpost.done') as $pl)
			{
				include $pl;
			}
			/* ===== */

			cot_forum_sectionsetlast($s);

			if ($cot_cache)
			{
				if ($cfg['cache_forums'])
				{
					$cot_cache->page->clear('forums');
				}
				if ($cfg['cache_index'])
				{
					$cot_cache->page->clear('index');
				}
			}

			cot_shield_update(30, "New post");
			cot_redirect(cot_url('forums', "m=posts&q=".$q."&n=last", '#bottom', true));
		}
		else
		{
			if($cfg['parser_cache'])
			{
				$rhtml = cot_db_prep(cot_parse(htmlspecialchars($newmsg), $cfg['parsebbcodeforums'] && $fs_allowbbcodes, $cfg['parsesmiliesforums'] && $fs_allowsmilies, 1));
			}
			else
			{
				$rhtml = '';
			}

			$sql = cot_db_query("SELECT fp_id, fp_text, fp_html, fp_posterid, fp_creation, fp_updated, fp_updater FROM $db_forum_posts WHERE fp_topicid='".$q."' ORDER BY fp_creation DESC LIMIT 1");
			$row = cot_db_fetcharray($sql);

			$p = (int) $row['fp_id'];

			$gap_base = empty($row['fp_updated']) ? $row['fp_creation'] : $row['fp_updated'];
			$updated = sprintf($L['for_mergetime'], cot_build_timegap($gap_base, $sys['now_offset']));

			$newmsg = cot_db_prep($row['fp_text'])."\n\n[b]".$updated."[/b]\n\n".cot_db_prep($newmsg);
			$newhtml = ($cfg['parser_cache']) ? cot_db_prep($row['fp_html'])."<br /><br /><b>".$updated."</b><br /><br />".$rhtml : '';

			$rupdater = ($row['fp_posterid'] == $usr['id'] && ($sys['now_offset'] < $row['fp_updated'] + 300) && empty($row['fp_updater']) ) ? '' : $usr['name'];

			$sql = cot_db_query("UPDATE $db_forum_posts SET fp_updated='".$sys['now_offset']."', fp_updater='".cot_db_prep($rupdater)."', fp_text='".$newmsg."', fp_html='".$newhtml."', fp_posterip='".$usr['ip']."' WHERE fp_id='".$row['fp_id']."' LIMIT 1");
			$sql = cot_db_query("UPDATE $db_forum_topics SET ft_updated='".$sys['now_offset']."' WHERE ft_id='$q'");

			/* === Hook === */
			foreach (cot_getextplugins('forums.posts.newpost.done') as $pl)
			{
				include $pl;
			}
			/* ===== */

			cot_forum_sectionsetlast($s);

			if ($cot_cache)
			{
				if ($cfg['cache_forums'])
				{
					$cot_cache->page->clear('forums');
				}
				if ($cfg['cache_index'])
				{
					$cot_cache->page->clear('index');
				}
			}

			cot_shield_update(30, "New post");
			cot_redirect(cot_url('forums', "m=posts&q=".$q."&n=last", '#bottom', true));
		}
	}
}

elseif ($a=='delete' && $usr['id']>0 && !empty($s) && !empty($q) && !empty($p) && ($usr['isadmin'] || $fp_posterid==$usr['id']))
{
	cot_check_xg();

	/* === Hook === */
	foreach (cot_getextplugins('forums.posts.delete.first') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$sql2 = cot_db_query("SELECT fp_id FROM $db_forum_posts WHERE fp_topicid='$q' ORDER BY fp_id ASC LIMIT 2");

	while ($row2 = cot_db_fetcharray($sql2))
	{
		$post12[] = $row2['fp_id'];
	}
	if ($post12[0]==$p && $post12[1]>0)
	{
		cot_die();
	}

	$sql = cot_db_query("SELECT * FROM $db_forum_posts WHERE fp_id='$p' AND fp_topicid='$q' AND fp_sectionid='$s'");

	if ($row = cot_db_fetchassoc($sql))
	{
		if ($cfg['trash_forum'])
		{
			cot_trash_put('forumpost', $L['Post']." #".$p." from topic #".$q, "p".$p."-q".$q, $row);
		}
	}
	else
	{
		cot_die();
	}

	$sql = cot_db_query("DELETE FROM $db_forum_posts WHERE fp_id='$p' AND fp_topicid='$q' AND fp_sectionid='$s'");

	if ($fs_countposts)
	{
		$sql = cot_db_query("UPDATE $db_users SET user_postcount=user_postcount-1 WHERE user_id='".$fp_posterid."' AND user_postcount>0");
	}

	cot_log("Deleted post #".$p, 'for');

	/* === Hook === */
	foreach (cot_getextplugins('forums.posts.delete.done') as $pl)
	{
		include $pl;
	}
	/* ===== */

	if ($cot_cache)
	{
		if ($cfg['cache_forums'])
		{
			$cot_cache->page->clear('forums');
		}
		if ($cfg['cache_index'])
		{
			$cot_cache->page->clear('index');
		}
	}

	$sql = cot_db_query("SELECT COUNT(*) FROM $db_forum_posts WHERE fp_topicid='$q'");

	if (cot_db_result($sql, 0, "COUNT(*)")==0)
	{
		// No posts left in this topic
		$sql = cot_db_query("SELECT * FROM $db_forum_topics WHERE ft_id='$q'");

		if ($row = cot_db_fetchassoc($sql))
		{
			if ($cfg['trash_forum'])
			{
				cot_trash_put('forumtopic', $L['Topic']." #".$q." (no post left)", "q".$q, $row);
			}
			$sql = cot_db_query("DELETE FROM $db_forum_topics WHERE ft_movedto='$q'");
			$sql = cot_db_query("DELETE FROM $db_forum_topics WHERE ft_id='$q'");

			$sql = cot_db_query("UPDATE $db_forum_sections SET
			fs_topiccount=fs_topiccount-1,
			fs_topiccount_pruned=fs_topiccount_pruned+1,
			fs_postcount=fs_postcount-1,
			fs_postcount_pruned=fs_postcount_pruned+1
			WHERE fs_id='$s'");

			if ($fs_masterid>0)
			{
				$sql = cot_db_query("UPDATE $db_forum_sections SET
				fs_topiccount=fs_topiccount-1,
				fs_topiccount_pruned=fs_topiccount_pruned+1,
				fs_postcount=fs_postcount-1,
				fs_postcount_pruned=fs_postcount_pruned+1
				WHERE fs_id='$fs_masterid'");
			}

			/* === Hook === */
			foreach (cot_getextplugins('forums.posts.emptytopicdel') as $pl)
			{
				include $pl;
			}
			/* ===== */

			cot_log("Delete topic #".$q." (no post left)",'for');
			cot_forum_sectionsetlast($s);
		}
		cot_redirect(cot_url('forums', "m=topics&s=".$s, '', true));
	}
	else
	{
		// There's at least 1 post left, let's resync
		$sql = cot_db_query("SELECT fp_id, fp_posterid, fp_postername, fp_updated
		FROM $db_forum_posts
		WHERE fp_topicid='$q' AND fp_sectionid='$s'
		ORDER BY fp_id DESC LIMIT 1");

		if ($row = cot_db_fetcharray($sql))
		{
			$sql = cot_db_query("UPDATE $db_forum_topics SET
			ft_postcount=ft_postcount-1,
			ft_lastposterid='".(int)$row['fp_posterid']."',
			ft_lastpostername='".cot_db_prep($row['fp_postername'])."',
			ft_updated='".(int)$row['fp_updated']."'
			WHERE ft_id='$q'");

			$sql = cot_db_query("UPDATE $db_forum_sections SET
			fs_postcount=fs_postcount-1,
			fs_postcount_pruned=fs_postcount_pruned+1
			WHERE fs_id='$s'");

			if ($fs_masterid>0)
			{
				$sql = cot_db_query("UPDATE $db_forum_sections SET
				fs_postcount=fs_postcount-1,
				fs_postcount_pruned=fs_postcount_pruned+1
				WHERE fs_id='$fs_masterid'");
			}

			cot_forum_sectionsetlast($s);

			$sql = cot_db_query("SELECT fp_id FROM $db_forum_posts
			WHERE fp_topicid='$q' AND fp_sectionid='$s' AND fp_id<$p
			ORDER BY fp_id DESC LIMIT 1");

			if ($row = cot_db_fetcharray($sql))
			{
				cot_redirect(cot_url('forums', "m=posts&p=".$row['fp_id'], '#'.$row['fp_id'], true));
			}
		}
	}
}

$sql = cot_db_query("SELECT * FROM $db_forum_topics WHERE ft_id='$q'");

if ($row = cot_db_fetcharray($sql))
{
	$ft_title = $row['ft_title'];
	$ft_desc = $row['ft_desc'];
	$ft_mode = $row['ft_mode'];
	$ft_state = $row['ft_state'];
	$ft_firstposterid = $row['ft_firstposterid'];

	if ($ft_mode==1 && !($usr['isadmin'] || $ft_firstposterid==$usr['id']))
	{
		cot_die();
	}
}
else
{ 
	cot_die();
}

$sql = cot_db_query("UPDATE $db_forum_topics SET ft_viewcount=ft_viewcount+1 WHERE ft_id='$q'");
$sql = cot_db_query("UPDATE $db_forum_sections SET fs_viewcount=fs_viewcount+1 WHERE fs_id='$s'");
$sql = ($fs_masterid>0) ? cot_db_query("UPDATE $db_forum_sections SET fs_viewcount=fs_viewcount+1 WHERE fs_id='$fs_masterid'") : '';
$sql = cot_db_query("SELECT COUNT(*) FROM $db_forum_posts WHERE fp_topicid='$q'");
$totalposts = cot_db_result($sql,0,"COUNT(*)");

if (!empty($p))
{
	$sql = cot_db_query("SELECT COUNT(*) FROM $db_forum_posts WHERE fp_topicid = $q and fp_id < $p");
	$postsbefore = cot_db_result($sql, 0, 0);
	$d = $cfg['maxpostsperpage'] * floor($postsbefore / $cfg['maxpostsperpage']);
}

if (empty($d))
{ 
	$d = '0';
}

if ($usr['id']>0)
{ 
	$morejavascript .= cot_build_addtxt('newpost', 'newmsg');
}


if (!empty($id))
{
	$sql = cot_db_query("SELECT p.*, u.*
	FROM $db_forum_posts AS p LEFT JOIN $db_users AS u ON u.user_id=p.fp_posterid
	WHERE fp_topicid='$q' AND fp_id='$id' ");
}
else
{
	$sql = cot_db_query("SELECT p.*, u.*
	FROM $db_forum_posts AS p LEFT JOIN $db_users AS u ON u.user_id=p.fp_posterid
	WHERE fp_topicid='$q'
	ORDER BY fp_id LIMIT $d, ".$cfg['maxpostsperpage']);
}

$title_params = array(
	'FORUM' => $L['Forums'],
	'SECTION' => $fs_title,
	'TITLE' => $ft_title
);
$out['subtitle'] = cot_title('title_forum_posts', $title_params);
$out['desc'] = htmlspecialchars(strip_tags($ft_desc));

/* === Hook === */
foreach (cot_getextplugins('forums.posts.main') as $pl)
{
	include $pl;
}
/* ===== */

require_once $cfg['system_dir'] . '/header.php';

$mskin = cot_skinfile(array('forums', 'posts', $fs_category, $s));
$t = new XTemplate($mskin);

$nbpages = ceil($totalposts / $cfg['maxpostsperpage']);
$curpage = $d / $cfg['maxpostsperpage'];
$notlastpage = (($d + $cfg['maxpostsperpage'])<$totalposts) ? TRUE : FALSE;

$pagenav = cot_pagenav('forums', "m=posts&q=$q", $d, $totalposts, $cfg['maxpostsperpage']);

$sql1 = cot_db_query("SELECT s.fs_id, s.fs_title, s.fs_category, s.fs_masterid, s.fs_mastername, s.fs_allowpolls FROM $db_forum_sections AS s LEFT JOIN
	$db_forum_structure AS n ON n.fn_code=s.fs_category
ORDER by fn_path ASC, fs_masterid, fs_order ASC");

cot_require_api('forms');

$jumpbox[cot_url('forums')] = $L['Forums'];
while ($row1 = cot_db_fetcharray($sql1))
{
	if (cot_auth('forums', $row1['fs_id'], 'R'))
	{
		$master = ($row1['fs_masterid'] > 0) ? array($row1['fs_masterid'], $row1['fs_mastername']) : false;

		$cfs = cot_build_forums($row1['fs_id'], $row1['fs_title'], $row1['fs_category'], FALSE, $master);

		if ($row1['fs_id'] != $s)
		{
			$movebox[$row1['fs_id']] = $cfs;
		}

		$jumpbox[cot_url('forums', "m=topics&s=".$row1['fs_id'], '', true)] = $cfs;
	}
}
$jumpbox = cot_selectbox($s, 'jumpbox', array_keys($jumpbox), array_values($jumpbox), false, 'onchange="redirect(this)"');

$movebox =  ($usr['isadmin']) ? '<input type="submit" class="submit" value="'.$L['Move'].'" />'.cot_selectbox('', 'ns', array_keys($movebox), array_values($movebox), false).' '. $L['for_keepmovedlink'].' '.cot_checkbox('1', 'ghost') : '';

if ($usr['isadmin'])
{
	$adminoptions = "<form id=\"movetopic\" action=\"".cot_url('forums', "m=topics&a=move&".cot_xg()."&s=".$s."&q=".$q)."\" method=\"post\">";
	$adminoptions .= $L['Topicoptions']." : <a href=\"".cot_url('forums', "m=topics&a=bump&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Bump'];
	$adminoptions .= "</a> &nbsp; <a href=\"".cot_url('forums', "m=topics&a=lock&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Lock'];
	$adminoptions .= "</a> &nbsp; <a href=\"".cot_url('forums', "m=topics&a=sticky&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Makesticky'];
	$adminoptions .= "</a> &nbsp; <a href=\"".cot_url('forums', "m=topics&a=announcement&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Announcement'];
	$adminoptions .= "</a> &nbsp; <a href=\"".cot_url('forums', "m=topics&a=private&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Private']." (#)";
	$adminoptions .= "</a> &nbsp; <a href=\"".cot_url('forums', "m=topics&a=clear&".cot_xg()."&q=".$q."&s=".$s)."\">".$L['Default'];
	$adminoptions .= "</a> &nbsp; &nbsp; ".$movebox." &nbsp; &nbsp; ".$L['Delete'].":[<a href=\"".cot_url('forums', "m=topics&a=delete&".cot_xg()."&s=".$s."&q=".$q)."\">x</a>]</form>";
}
else
{ 
	$adminoptions = "&nbsp;";
}

$ft_title = ($ft_mode == 1) ? "# ".htmlspecialchars($ft_title) : htmlspecialchars($ft_title);

$master = ($fs_masterid > 0) ? array($fs_masterid, $fs_mastername) : false;

$toptitle = cot_build_forums($s, $fs_title, $fs_category, true, $master);
$toppath  = $toptitle;
$toptitle .= ' ' . $cfg['separator'] . ' ' . $ft_title;
$toptitle .= ($usr['isadmin']) ? " *" : '';

$t->assign(array(
	"FORUMS_POSTS_ID" => $q,
	"FORUMS_POSTS_RSS" => cot_url('rss', "c=topics&id=$q"),
	"FORUMS_POSTS_PAGETITLE" => $toptitle,
	"FORUMS_POSTS_TOPICDESC" => htmlspecialchars($ft_desc),
	"FORUMS_POSTS_SHORTTITLE" => $ft_title,
	"FORUMS_POSTS_PATH" => $toppath,
	"FORUMS_POSTS_SUBTITLE" => $adminoptions,
	"FORUMS_POSTS_PAGES" => $pagenav['main'],
	"FORUMS_POSTS_PAGEPREV" => $pagenav['prev'],
	"FORUMS_POSTS_PAGENEXT" => $pagenav['next'],
	"FORUMS_POSTS_JUMPBOX" => $jumpbox,
));

$totalposts = cot_db_numrows($sql);
$fp_num=0;

/* === Hook - Part1 : Set === */
$extp = cot_getextplugins('forums.posts.loop');
/* ===== */

while ($row = cot_db_fetcharray($sql))
{
	$row['fp_text'] = htmlspecialchars($row['fp_text']);
	$row['fp_created'] = @date($cfg['dateformat'], $row['fp_creation'] + $usr['timezone'] * 3600);
	$row['fp_updated_ago'] = cot_build_timegap($row['fp_updated'], $sys['now_offset']);
	$row['fp_updated'] = @date($cfg['dateformat'], $row['fp_updated'] + $usr['timezone'] * 3600);
	$row['user_text'] = ($fs_allowusertext) ? $row['user_text'] : '';
	$lastposterid = $row['fp_posterid'];
	$lastposterip = $row['fp_posterip'];
	$fp_num++;
	$i = empty($id) ? $d + $fp_num : $id;

	$rowquote  = ($usr['id']>0) ? cot_rc('frm_rowquote', array('url' => cot_url('forums', "m=posts&s=".$s."&q=".$q."&quote=".$row['fp_id']."&n=last", "#np"))) : '';
	$rowedit   = (($usr['isadmin'] || $row['fp_posterid']==$usr['id']) && $usr['id']>0) ? cot_rc('frm_rowedit', array('url' => cot_url('forums', "m=editpost&s=".$s."&q=".$q."&p=".$row['fp_id']."&".cot_xg()))) : '';
	$rowdelete = ($usr['id']>0 && ($usr['isadmin'] || $row['fp_posterid']==$usr['id']) && !($post12[0]==$row['fp_id'] && $post12[1]>0)) ? cot_rc('frm_rowdelete', array('url' => cot_url('forums', "m=posts&a=delete&".cot_xg()."&s=".$s."&q=".$q."&p=".$row['fp_id']))) : '';
	$rowdelete .= ($fp_num==$totalposts) ? "<a name=\"bottom\" id=\"bottom\"></a>" : '';
	$adminoptions = $rowquote.' &nbsp; '.$rowedit.' &nbsp; '.$rowdelete;

	if ($usr['id']>0 && $n=='unread' && !$unread_done && $row['fp_creation']>$usr['lastvisit'])
	{
		$unread_done = TRUE;
		$adminoptions .= "<a name=\"unread\" id=\"unread\"></a>";
	}

	$row['fp_posterip'] = ($usr['isadmin']) ? cot_build_ipsearch($row['fp_posterip']) : '';
	if($cfg['parser_cache'])
	{
		if(empty($row['fp_html']) && !empty($row['fp_text']))
		{
			$row['fp_html'] = cot_parse($row['fp_text'], $cfg['parsebbcodeforums']  && $fs_allowbbcodes, $cfg['parsesmiliesforums']  && $fs_allowsmilies, 1);
			cot_db_query("UPDATE $db_forum_posts SET fp_html = '".cot_db_prep($row['fp_html'])."' WHERE fp_id = " . $row['fp_id']);
		}
		$row['fp_text'] = cot_post_parse($row['fp_html'], 'forums');
	}
	else
	{
		$row['fp_text'] = cot_parse($row['fp_text'], ($cfg['parsebbcodeforums'] && $fs_allowbbcodes), ($cfg['parsesmiliesforums'] && $fs_allowsmilies), 1);
		$row['fp_text'] = cot_post_parse($row['fp_text'], 'forums');
	}

	if (!empty($row['fp_updater']))
	{
		$row['fp_updatedby'] = sprintf($L['for_updatedby'], htmlspecialchars($row['fp_updater']), $row['fp_updated'], $row['fp_updated_ago']);
	}

	$t->assign(cot_generate_usertags($row, "FORUMS_POSTS_ROW_USER"));
	$t-> assign(array(
		"FORUMS_POSTS_ROW_ID" => $row['fp_id'],
		"FORUMS_POSTS_ROW_POSTID" => 'post_'.$row['fp_id'],
		"FORUMS_POSTS_ROW_IDURL" => cot_url('forums', "m=posts&id=".$row['fp_id']),
		"FORUMS_POSTS_ROW_URL" => cot_url('forums', "m=posts&p=".$row['fp_id'], "#".$row['fp_id']),
		"FORUMS_POSTS_ROW_CREATION" => $row['fp_created'],
		"FORUMS_POSTS_ROW_UPDATED" => $row['fp_updated'],
		"FORUMS_POSTS_ROW_UPDATER" => htmlspecialchars($row['fp_updater']),
		"FORUMS_POSTS_ROW_UPDATEDBY" => $row['fp_updatedby'],
		"FORUMS_POSTS_ROW_TEXT" => $row['fp_text'],
		"FORUMS_POSTS_ROW_ANCHORLINK" => "<a name=\"post{$row['fp_id']}\" id=\"post{$row['fp_id']}\"></a>",
		"FORUMS_POSTS_ROW_POSTERNAME" => cot_build_user($row['fp_posterid'], htmlspecialchars($row['fp_postername'])),
		"FORUMS_POSTS_ROW_POSTERID" => $row['fp_posterid'],
		"FORUMS_POSTS_ROW_POSTERIP" => $row['fp_posterip'],
		"FORUMS_POSTS_ROW_DELETE" => $rowdelete,
		"FORUMS_POSTS_ROW_EDIT" => $rowedit,
		"FORUMS_POSTS_ROW_QUOTE" => $rowquote,
		"FORUMS_POSTS_ROW_ADMIN" => $adminoptions,
		"FORUMS_POSTS_ROW_ODDEVEN" => cot_build_oddeven($fp_num),
		"FORUMS_POSTS_ROW_NUM" => $fp_num,
		"FORUMS_POSTS_ROW_ORDER" => $i,
		"FORUMS_POSTS_ROW" => $row,
	));

	/* === Hook - Part2 : Include === */
	foreach ($extp as $pl)
	{
		include $pl;
	}
	/* ===== */

	$t->parse("MAIN.FORUMS_POSTS_ROW");
}

$allowreplybox = (!$cfg['antibumpforums']) ? TRUE : FALSE;
$allowreplybox = ($cfg['antibumpforums'] && $lastposterid>0 && $lastposterid==$usr['id'] && $usr['auth_write']) ? FALSE : TRUE;

// Nested quote stripper by Spartan
function cot_stripquote($string)
{
	global $sys;
	$starttime = $sys['now'];
	$startindex = mb_stripos($string,'[quote');
	while ($startindex>=0)
	{
		if (($sys['now']-$starttime)>2000)
		{
			break;
		}
		$stopindex = mb_strpos($string,'[/quote]');
		if ($stopindex>0)
		{
			if (($sys['now']-$starttime)>3000)
			{
				break;
			}
			$fragment = mb_substr($string,$startindex,($stopindex-$startindex+8));
			$string = str_ireplace($fragment,'',$string);
			$stopindex = mb_stripos($string,'[/quote]');
		} else
		{
			break;
		}
		$string = trim($string);
		$startindex = mb_stripos($string,'[quote');
	}
	return($string);
}

if (!$notlastpage && !$ft_state && $usr['id']>0 && $allowreplybox && $usr['auth_write'])
{
	if ($quote>0)
	{
		$sql4 = cot_db_query("SELECT fp_id, fp_text, fp_postername, fp_posterid FROM $db_forum_posts WHERE fp_topicid='$q' AND fp_sectionid='$s' AND fp_id='$quote' LIMIT 1");

		if ($row4 = cot_db_fetcharray($sql4))
		{
			$newmsg = "[quote][url=forums.php?m=posts&p=".$row4['fp_id']."#".$row4['fp_id']."]#[/url] [b]".$row4['fp_postername']." :[/b]\n".cot_stripquote($row4['fp_text'])."\n[/quote]";
		}
	}

	// FIXME PFS dependency
	//$pfs = ($usr['id']>0) ? cot_build_pfs($usr['id'], "newpost", "newmsg", $L['Mypfs']) : '';
	//$pfs .= (cot_auth('pfs', 'a', 'A')) ? " &nbsp; ".cot_build_pfs(0, "newpost", "newmsg", $L['SFS']) : '';

	cot_require_api('forms');
	$post_mark = "<a name=\"np\" id=\"np\"></a>";

	$t->assign(array(
		"FORUMS_POSTS_NEWPOST_SEND" => cot_url('forums', "m=posts&a=newpost&s=".$s."&q=".$q),
		"FORUMS_POSTS_NEWPOST_TEXT" => $post_mark . cot_textarea('newmsg', htmlspecialchars($newmsg), 16, 56, '', 'input_textarea_editor'),
		"FORUMS_POSTS_NEWPOST_MYPFS" => $pfs
	));

	cot_display_messages($t);

	/* === Hook  === */
	foreach (cot_getextplugins('forums.posts.newpost.tags') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$t->parse("MAIN.FORUMS_POSTS_NEWPOST");
}

elseif ($ft_state)
{
	$t->assign("FORUMS_POSTS_TOPICLOCKED_BODY", $L['Topiclocked']);
	$t->parse("MAIN.FORUMS_POSTS_TOPICLOCKED");
}

elseif(!$allowreplybox && !$notlastpage && !$ft_state && $usr['id']>0)
{
	$t->assign("FORUMS_POSTS_ANTIBUMP_BODY", $L['for_antibump']);
	$t->parse("MAIN.FORUMS_POSTS_ANTIBUMP");
}

if ($ft_mode==1)
{
	$t->parse("MAIN.FORUMS_POSTS_TOPICPRIVATE");
}

/* === Hook  === */
foreach (cot_getextplugins('forums.posts.tags') as $pl)
{
	include $pl;
}
/* ===== */

$t->parse("MAIN");
$t->out("MAIN");

require_once $cfg['system_dir'] . '/footer.php';

if ($cot_cache && $usr['id'] === 0 && $cfg['cache_forums'])
{
	$cot_cache->page->write();
}

?>