<?php
/**
 * Polls functions
 *
 * @package polls
 * @version 0.7.0
 * @author esclkm, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2010
 * @license BSD License
 */

defined('COT_CODE') or die('Wrong URL');

// Requirements
cot_require_api('forms');
cot_require_lang('polls', 'module');

// Global variables
$GLOBALS['db_polls'] 		 = (isset($GLOBALS['db_polls'])) ? $GLOBALS['db_polls'] : $GLOBALS['db_x'] . 'polls';
$GLOBALS['db_polls_options'] = (isset($GLOBALS['db_polls_options'])) ? $GLOBALS['db_polls_options'] : $GLOBALS['db_x'] . 'polls_options';
$GLOBALS['db_polls_voters']  = (isset($GLOBALS['db_polls_voters'])) ? $GLOBALS['db_polls_voters'] : $GLOBALS['db_x'] . 'polls_voters';

/**
 * Adds form for create/edit Poll
 *
 * @param int $id Poll ID or Poll Code if $type is not epmty or new for new Poll
 * @param XTemplate $t Template
 * @param string $block Poll block in Template
 * @param string $type Poll type
 * @return bool
 */
function cot_poll_edit_form($id, $t = '', $block = '', $type = '')
{
	global $cfg, $db_polls, $db_polls_options, $cot_error, $poll_id, $R, $L;
	if (gettype($t) != 'object')
	{
		$t = new XTemplate(cot_skinfile('polls'));
		$block = "EDIT_POLL_FORM";
		$poll_full_template = true;
	}
	$block = (!empty($block)) ? $block."." : "";
	$counter = 0;
	if ($cot_error && !empty($poll_id))
	{
		global  $poll_options, $poll_multiple, $poll_state, $poll_text;

		$id = $poll_id;
		foreach ($poll_options as $key => $val)
		{
			if ($val != '')
			{
				$counter++;
				$t->assign("EDIT_POLL_OPTION_TEXT", cot_inputbox('text', $key, htmlspecialchars($val), 'size="40" maxlength="128"'));
				$t->parse($block.".OPTIONS");
			}
		}
	}
	elseif ((int)$id > 0)
	{
		$where = (!$type) ? "poll_id = '$id'" : "poll_type = '".cot_db_prep($type)."' AND poll_code = '$id'";
		$sql = cot_db_query("SELECT * FROM $db_polls WHERE $where LIMIT 1");
		if ($row = cot_db_fetcharray($sql))
		{
			$id = $row["poll_id"];
			$poll_text = htmlspecialchars($row["poll_text"]);

			$sql1 = cot_db_query("SELECT * FROM $db_polls_options WHERE po_pollid = '$id' ORDER by po_id ASC");
			while ($row1 = cot_db_fetcharray($sql1))
			{
				$counter++;
				$t->assign("EDIT_POLL_OPTION_TEXT", cot_inputbox('text', 'poll_option[id'.$row1['po_id'].']', htmlspecialchars($row1['po_text']), 'size="40" maxlength="128"'));
				$t->parse($block."OPTIONS");
			}
		}
	}

	while ($counter < 2)
	{
		$counter++;
		$t->assign("EDIT_POLL_OPTION_TEXT", cot_inputbox('text', 'poll_option[]', '', 'size="40" maxlength="128"'));
		$t->parse($block."OPTIONS");
	}

	if ($counter < $cfg['max_options_polls'])
	{
		$counter++;
		$t->assign("EDIT_POLL_OPTION_TEXT", cot_inputbox('text', 'poll_option[]', '', 'size="40" maxlength="128"'));
		$t->parse($block."OPTIONS");
	}

	if ((int)$id > 0)
	{
		$t->assign(array(
			"EDIT_POLL_CLOSE" => cot_checkbox($poll_state, 'poll_state' , $L['Close']),
			"EDIT_POLL_RESET" => cot_checkbox(0, 'poll_reset' , $L['Reset']),
			"EDIT_POLL_DELETE" => cot_checkbox(0, 'poll_delete' , $L['Delete']),
		));
		$t->parse($block."EDIT");
	}

	$t->assign(array(
		"EDIT_POLL_TEXT" => cot_inputbox('text', 'poll_text', $poll_text, 'size="64" maxlength="255"'),
		"EDIT_POLL_IDFIELD" => cot_inputbox('hidden', 'poll_id', $id),
		"EDIT_POLL_OPTIONSCOUNT" => $counter,
		"EDIT_POLL_ID" => $id,
		"EDIT_POLL_MULTIPLE" => cot_checkbox($poll_multiple, 'poll_state' , $L['polls_multiple']),
	));
	if ($poll_full_template == true)
	{
		$t->parse($block);
		return ($t->text($block));
	}
	return true;
}

/**
 * Check Poll form
 */
function cot_poll_check()
{
	global $cfg, $L, $poll_id, $poll_text, $poll_multiple, $poll_state, $poll_options;
	$poll_id = cot_import('poll_id', 'P', 'TXT');
	$poll_delete = cot_import('poll_delete', 'P', 'BOL');

	if ($poll_delete && !empty($poll_id))
	{
		cot_poll_delete($poll_id);
		$poll_id = '';
	}
	if (!empty($poll_id))
	{
		$poll_reset = cot_import('poll_reset', 'P', 'BOL');
		if ($poll_reset)
		{
			cot_poll_reset($poll_id);
		}
		$poll_text = trim(cot_import('poll_text', 'P', 'HTM'));
		$poll_multiple = cot_import('poll_multiple', 'P', 'BOL');
		$poll_state = cot_import('poll_state', 'P', 'BOL');
		$poll_options = cot_import('poll_option', 'P', 'ARR');

		foreach ($poll_options as $key => $val)
		{
			$val = trim(cot_import($val, 'D', 'TXT'));
			if (!empty($val))
			{
				$poll_options_temp[$key] = $val;
			}
		}
		$poll_options = $poll_options_temp;
		if (is_int($poll_id) || $cfg['del_dup_options'])
		{
			$poll_options = array_unique($poll_options);
		}
		if (mb_strlen($poll_text) < 4)
		{
			cot_error('polls_error_title', 'poll_text');
		}
		if (count($poll_options) < 2)
		{
			cot_error('polls_error_count', 'poll_option');
		}
	}
}

/**
 * Save Poll form
 *
 * @param string $type Poll type
 * @param int $code Poll Code
 * @return bool
 */
function cot_poll_save($type = 'index', $code = '')
{
	global $sys, $db_polls, $db_polls_options, $cot_error, $poll_id, $poll_text, $poll_multiple, $poll_state, $poll_options;

	if (!empty($poll_id) && !$cot_error)
	{
		if ((int)$poll_id > 0)
		{
			$sql = cot_db_query("UPDATE $db_polls SET  poll_state = '".(int)$poll_state."', poll_text = '".cot_db_prep($poll_text)."', poll_multiple = '".(int)$poll_multiple."' WHERE poll_id = '$poll_id'");
			$newpoll_id = $poll_id;
		}
		else
		{
			$sql = cot_db_query("INSERT INTO $db_polls (poll_type, poll_state, poll_creationdate, poll_text, poll_multiple, poll_code)
				VALUES ('".cot_db_prep($type)."', ".(int)$poll_state.", ".(int)$sys['now_offset'].", '".cot_db_prep($poll_text)."', '".(int)$poll_multiple."', '".(int)$code."')");
			$newpoll_id = cot_db_insertid();
		}

		foreach ($poll_options as $key => $val)
		{
			if (!empty($val))
			{
				$key = mb_substr($key, 2);
				if ((int)$key > 0 &&(int)$poll_id > 0)
				{
					$sql2 = cot_db_query("UPDATE $db_polls_options SET po_text = '".cot_db_prep($val)."' WHERE po_id = '".(int)$key."'");
					$ids[] = $key;
				}
				else
				{
					$sql2 = cot_db_query( "INSERT into $db_polls_options (po_pollid, po_text, po_count) VALUES ('$newpoll_id', '".cot_db_prep($val)."', '0')");
					$ids[] = cot_db_insertid();
				}

			}
		}
		if ((int)$poll_id > 0 && count($ids) > 0)
		{
			$sql2 = cot_db_query("DELETE FROM $db_polls_options WHERE po_pollid = '".(int)$newpoll_id."' AND po_id NOT IN ('".implode("','", $ids)."')");
		}
		return ($newpoll_id);
	}
	return (false);
}

/**
 * Poll function
 */
function cot_poll_vote()
{
	global $cfg, $db_polls, $db_polls_options, $db_polls_voters, $usr;

	$vote = cot_import('vote', 'P', 'ARR');
	$id = (int) cot_import('poll_id', 'P', 'INT');

	if (count($vote) > 0)
	{
		$alreadyvoted = 0;
		$sql = cot_db_query("SELECT * FROM $db_polls WHERE poll_id = '$id'");
		if ($row = cot_db_fetcharray($sql))
		{
			if ($cfg['ip_id_polls'] == 'id' && $usr['id'] > 0)
			{
				$where = "pv_userid = '".$usr['id']."'";
			}
			elseif ($cfg['ip_id_polls'] == 'ip')
			{
				$where = ($usr['id'] > 0) ? "(pv_userid = '".$usr['id']."' OR pv_userip = '".$usr['ip']."')" : "pv_userip = '".$usr['ip']."'";
			}
			$sql2 = cot_db_query("SELECT pv_id FROM $db_polls_voters WHERE pv_pollid = '$id' AND $where LIMIT 1");
			$alreadyvoted = (cot_db_numrows($sql2) == 1) ? 1 : 0;
			if ($alreadyvoted != 1)
			{
				foreach ($vote as $val)
				{
					$sql2 = cot_db_query("UPDATE $db_polls_options SET po_count = po_count+1 WHERE po_pollid = '$id' AND po_id = '".(int)$val."'");
				}
				if (cot_db_affectedrows() > 0)
				{
					$sql2 = cot_db_query("INSERT INTO $db_polls_voters (pv_pollid, pv_userid, pv_userip) VALUES (".(int)$id.", ".(int)$usr['id'].", '".$usr['ip']."')");
				}
			}
		}
	}
}

/**
 * Generates Poll form
 *
 * @param int $id Poll ID or Poll Code if $type is not epmty
 * @param string $formlink Poll form url
 * @param string $theme Poll template name
 * @param string $type Poll type
 * @return array
 */
function cot_poll_form($id, $formlink = '', $theme = '', $type = '')
{
	global $cfg, $db_polls, $db_polls_options, $db_polls_voters, $usr;
	$canvote = false;

	if (!is_array($id))
	{
		$id = (int) $id;
		$where = (!$type) ? "poll_id = '$id'" : "poll_type = '".cot_db_prep($type)."' AND poll_code = '$id'";
		$sql = cot_db_query("SELECT * FROM $db_polls WHERE $where LIMIT 1");
		if (!$row = cot_db_fetcharray($sql))
		{
			return false;
		}
	}
	else
	{
		$row = $id;
	}
	$id = $row['poll_id'];

	$alreadyvoted = 0;
	if ($cfg['ip_id_polls'] == 'id' && $usr['id'] > 0)
	{
		$where = "pv_userid = '".$usr['id']."'";
		$canvote = true;
	}
	elseif ($cfg['ip_id_polls'] == 'ip')
	{
		$where = ($usr['id'] > 0) ? "(pv_userid = '".$usr['id']."' OR pv_userip = '".$usr['ip']."')" : "pv_userip = '".$usr['ip']."'";
		$canvote = true;
	}
	$sql2 = cot_db_query("SELECT pv_id FROM $db_polls_voters WHERE pv_pollid = '$id' AND $where LIMIT 1");
	$alreadyvoted = (cot_db_numrows($sql2) == 1) ? 1 : 0;

	$themeinput = $themefile;
	$themefile = (empty($themefile)) ? cot_skinfile('polls') : cot_skinfile($themefile, true);
	$t = new XTemplate($themefile);

	if ($alreadyvoted) $poll_block = "POLL_VIEW_VOTED";
	elseif (!$canvote) $poll_block = "POLL_VIEW_DISABLED";
	elseif ($row['poll_state']) $poll_block = "POLL_VIEW_LOCKED";
	else $poll_block = "POLL_VIEW";

	$sql2 = cot_db_query("SELECT SUM(po_count) FROM $db_polls_options WHERE po_pollid = '$id'");
	$totalvotes = cot_db_result($sql2, 0, "SUM(po_count)");

	$sql1 = cot_db_query("SELECT po_id, po_text, po_count FROM $db_polls_options WHERE po_pollid = '$id' ORDER by po_id ASC");
	while ($row1 = cot_db_fetcharray($sql1))
	{
		$po_id = $row1['po_id'];
		$po_count = $row1['po_count'];
		$percent = @round(100 * ($po_count / $totalvotes), 1);

		$input_type = $row['poll_multiple'] ? 'checkbox' : 'radio';
		$polloptions_input = ($alreadyvoted || !$canvote) ? "" : '<input type="'.$input_type.'" name="vote[]" value="'.$po_id.'" />&nbsp;'; // TODO - to resorses
		$polloptions = cot_parse(htmlspecialchars($row1['po_text']), 1, 1, 1);

		$t->assign(array(
			"POLL_OPTIONS" => $polloptions,
			"POLL_PER" => $percent,
			"POLL_COUNT" => $po_count,
			"POLL_INPUT" => $polloptions_input
		));
		$t->parse($poll_block.".POLLTABLE");
	}

	$t->assign(array(
		"POLL_VOTERS" => $totalvotes,
		"POLL_SINCE" => date($cfg['dateformat'], $row['poll_creationdate'] + $usr['timezone'] * 3600),
		"POLL_SINCE_SHORT" => date($cfg['formatmonthday'], $row['poll_creationdate'] + $usr['timezone'] * 3600),
		"POLL_TITLE" => cot_parse(htmlspecialchars($row['poll_text']), 1, 1, 1),
		"POLL_ID" => $id,
		"POLL_FORM_URL" => (empty($formlink)) ? cot_url('polls', 'id='.$id) : $formlink,
		"POLL_FORM_BUTTON" => $pollbutton
	));
	$t->parse($poll_block);
	
	$row['poll_alreadyvoted'] = $alreadyvoted;
	$row['poll_count'] = $totalvotes;
	$row['poll_block'] = $t->text($poll_block);;

	return($row);
}

/**
 * Delete Poll
 *
 * @param int $id Poll ID or Poll Code if $type is not epmty
 * @param string $type Poll type
 * @return bool
 */
function cot_poll_delete($id, $type = '')
{
	global $db_polls, $db_polls_options, $db_polls_voters;

	if ($type)
	{
		$sql = cot_db_query("SELECT poll_id FROM $db_polls WHERE poll_type = '".cot_db_prep($type)."' AND poll_code = '$id' LIMIT 1");
		$id = ($row = cot_db_fetcharray($sql)) ? $row['poll_id'] : 0;
	}
	if ((int)$id > 0)
	{
		$sql = cot_db_query("DELETE FROM $db_polls WHERE poll_id = ".$id);
		$sql = cot_db_query("DELETE FROM $db_polls_options WHERE po_pollid = ".$id);
		$sql = cot_db_query("DELETE FROM $db_polls_voters WHERE pv_pollid = ".$id);

		/* === Hook === */
		foreach (cot_getextplugins('polls.functions.delete') as $pl)
		{
			include $pl;
		}
		/* ===== */

		return (true);
	}
	else
	{
		return (false);
	}
}

/**
 * Lock Poll
 *
 * @param int $id Poll ID or Poll code if $type is not epmty
 * @param int $state Poll lock status: 0 - unlocked, 1 - locked, 3 - toggle lock status
 * @param string $type Poll type
 * @return bool
 */
function cot_poll_lock($id, $state, $type = '')
{
	global $db_polls;

	$id = (int) $id;
	$where = (!$type) ? "poll_id = '$id'" : "poll_type = '".cot_db_prep($type)."' AND poll_code = '$id'";
	if ($state == 3)
	{
		$sql = cot_db_query("SELECT poll_state FROM $db_polls WHERE  $where LIMIT 1");
		$rstate = ($row = cot_db_fetcharray($sql)) ? $row['poll_state'] : 0;
		$state = ($rstate) ? 0 : 1;
	}
	if ((int)$id > 0)
	{
		$sql = cot_db_query("UPDATE $db_polls SET poll_state = '".(int)$state."' WHERE $where");
	}

	return ((cot_db_affectedrows() > 0) ? true : false);
}

/**
 * Reset Poll votes
 *
 * @param int $id Poll ID or Poll Code if $type is not epmty
 * @param string $type Poll type
 * @return bool
 */
function cot_poll_reset($id, $type = '')
{
	global $db_polls, $db_polls_options, $db_polls_voters;
	$id = (int) $id;
	if ($type)
	{
		$sql = cot_db_query("SELECT poll_id FROM $db_polls WHERE poll_type = '".cot_db_prep($type)."' AND poll_code = '$id' LIMIT 1");
		$id = ($row = cot_db_fetcharray($sql)) ? $row['poll_id'] : 0;
	}
	if ((int)$id > 0)
	{
		$sql = cot_db_query("DELETE FROM $db_polls_voters WHERE pv_pollid = '$id'");
		$sql = cot_db_query("UPDATE $db_polls_options SET po_count = 0 WHERE po_pollid = '$id'");
	}

	return ((cot_db_affectedrows() > 0) ? true : false);
}

/**
 * Checks if Poll exists
 *
 * @param int $id Poll ID or Poll Code if $type is not epmty
 * @param string $type Poll type
 * @return bool true if Poll exists
 */
function cot_poll_exists($id, $type = '')
{
	global $db_polls;

	$id = (int) $id;
	$where = (!$type) ? "poll_id = '$id'" : "poll_type = '".cot_db_prep($type)."' AND poll_code = '$id'";
	$sql = cot_db_query("SELECT COUNT(*)  FROM $db_polls WHERE $where LIMIT 1");

	return (cot_db_result($sql, 0, "COUNT(*)"));
}

?>