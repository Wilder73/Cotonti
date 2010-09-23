<?php
/**
 * Personal File Storage, function library
 *
 * @package pfs
 * @version 0.7.0
 * @author Neocrome, Cotonti Team
 * @copyright Copyright (c) 2008-2009 Cotonti Team
 * @license BSD License
 */

defined('COT_CODE') or die('Wrong URL');

// Requirements
cot_require_lang('pfs', 'module');
cot_require_rc('pfs');

cot_require_api('forms');

// Global variables
$GLOBALS['db_pfs'] = (isset($GLOBALS['db_pfs'])) ? $GLOBALS['db_pfs'] : $GLOBALS['db_x'] . 'pfs';
$GLOBALS['db_pfs_folders'] = (isset($GLOBALS['db_pfs_folders'])) ? $GLOBALS['db_pfs_folders'] : $GLOBALS['db_x'] . 'pfs_folders';

/**
 * Create a new PFS folder, return new folder ID
 *
 * @param int $ownerid Owners user ID
 * @param string $title Folder title
 * @param string $desc Folder description
 * @param int $parentid Parent folder ID
 * @param boolean $ispublic Public?
 * @param boolean $isgallery Gallery?
 * @return int
 */
function cot_pfs_createfolder($ownerid, $title='', $desc='', $parentid='', $ispublic='', $isgallery='')
{
	global $db_pfs_folders, $cfg, $sys, $L, $err_msg;

	if ($title==='') 		$title = cot_import('ntitle','P','TXT');
	if ($desc==='') 		$desc = cot_import('ndesc','P','TXT');
	if ($parentid==='') 	$parentid = cot_import('nparentid','P','INT');
	if ($ispublic==='') 	$ispublic = cot_import('nispublic','P','BOL');
	if ($isgallery==='') 	$isgallery = cot_import('nisgallery','P','BOL');

	if(empty($title))
	{
		$err_msg[] = $L['pfs_foldertitlemissing'];
		return 0;
	}

	$newpath = cot_urlencode(strtolower($title));
	if ($parentid > 0)
	{
		$newpath = cot_pfs_folderpath($parentid, TRUE).$newpath;

		$sql = cot_db_query("SELECT pff_id FROM $db_pfs_folders WHERE pff_userid=".(int)$ownerid." AND pff_id=".(int)$parentid);
		cot_db_numrows($sql)>0 or cot_die();
	}
	if ($cfg['pfsuserfolder'])
	{
		cot_pfs_mkdir($cfg['pfs_path'].$newpath) or cot_redirect(cot_url('message', 'msg=500&redirect='.base64_encode('pfs.php'), '', true));
		cot_pfs_mkdir($cfg['pfs_thumbpath'].$newpath) or cot_redirect(cot_url('message', 'msg=500&redirect='.base64_encode('pfs.php'), '', true));
	}

	$sql = cot_db_query("INSERT INTO $db_pfs_folders
		(pff_parentid,
		pff_userid,
		pff_title,
		pff_date,
		pff_updated,
		pff_desc,
		pff_path,
		pff_ispublic,
		pff_isgallery,
		pff_count)
		VALUES
		(".(int)$parentid.",
		".(int)$ownerid.",
		'".cot_db_prep($title)."',
		".(int)$sys['now'].",
		".(int)$sys['now'].",
		'".cot_db_prep($desc)."',
		'".cot_db_prep($newpath)."',
		".(int)$ispublic.",
		".(int)$isgallery.",
		0)"
	);
	return cot_db_insertid();
}

/**
 * Delete a PFS file
 *
 * @param int $userid User ID
 * @param int $id File ID
 * @return boolean
 */
function cot_pfs_deletefile($userid, $id)
{
	global $db_pfs, $cfg;

	$sql = cot_db_query("SELECT pfs_id FROM $db_pfs WHERE pfs_userid=".(int)$userid." AND pfs_id=".(int)$id." LIMIT 1");

	if (cot_db_numrows($sql)>0)
	{
		$fpath = cot_pfs_filepath($id);

		if (file_exists($cfg['pfs_thumbpath'].$fpath))
		{
			@unlink($cfg['pfs_thumbpath'].$fpath);
		}
		if (file_exists($cfg['pfs_path'].$fpath))
		{
			@unlink($cfg['pfs_path'].$fpath);
		}
		else
		{
			return FALSE;
		}

		cot_db_query("DELETE FROM $db_pfs WHERE pfs_id=".(int)$id);
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}

/**
 * Delete a PFS folder
 *
 * @param int $userid User ID
 * @param int $folderid Folder ID
 * @return boolean
 */
function cot_pfs_deletefolder($userid, $folderid)
{
	global $db_pfs_folders, $db_pfs, $cfg;

	$sql = cot_db_query("SELECT pff_path FROM $db_pfs_folders WHERE pff_userid=".(int)$userid." AND pff_id=".(int)$folderid." LIMIT 1");
	if($row = cot_db_fetcharray($sql))
	{
		$fpath = $row['pff_path'];

		// Remove files
		$sql = cot_db_query("SELECT pfs_id FROM $db_pfs WHERE pfs_folderid IN (SELECT pff_id FROM $db_pfs_folders WHERE pff_path LIKE '".$fpath."%')");
		while($row = cot_db_fetcharray($sql))
		{
			cot_pfs_deletefile($row['pfs_id']);
		}

		// Remove folders
		$sql = cot_db_query("SELECT pff_id, pff_path FROM $db_pfs_folders WHERE pff_path LIKE '".$fpath."%' ORDER BY CHAR_LENGTH(pff_path) DESC");
		while($row = cot_db_fetcharray($sql))
		{
			if($cfg['pfsuserfolder'])
			{
				@rmdir($cfg['pfs_path'].$row['pff_path']);
				@rmdir($cfg['pfs_thumbpath'].$row['pff_path']);
			}
			cot_db_query("DELETE FROM $db_pfs_folders WHERE pff_id=".(int)$row['pff_id']);
		}
		if(cot_db_numrows($sql)>0)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	else
	{
		return FALSE;
	}
}


/**
 * Delete all PFS files for a specific user. Returns number of items removed.
 *
 * @param int $userid User ID
 * @return int
 */
function cot_pfs_deleteall($userid)
{
	global $db_pfs_folders, $db_pfs, $cfg;

	if (!$userid)
	{
		return 0;
	}
	$sql = cot_db_query("SELECT pfs_file, pfs_folderid FROM $db_pfs WHERE pfs_userid='$userid'");

	while($row = cot_db_fetcharray($sql))
	{
		$pfs_file = $row['pfs_file'];
		$f = $row['pfs_folderid'];
		$ff = $cfg['pfs_dir_user'].$pfs_file;

		if (file_exists($ff))
		{
			@unlink($ff);
			if(file_exists($cfg['th_dir_user'].$pfs_file))
			{
				@unlink($cfg['th_dir_user'].$pfs_file);
			}
		}
	}
	$sql = cot_db_query("DELETE FROM $db_pfs_folders WHERE pff_userid='$userid'");
	$num = $num + cot_db_affectedrows();
	$sql = cot_db_query("DELETE FROM $db_pfs WHERE pfs_userid='$userid'");
	$num = $num + cot_db_affectedrows();

	if ($cfg['pfsuserfolder'] && $userid>0)
	{
		@rmdir($cfg['pfs_dir_user']);
		@rmdir($cfg['th_dir_user']);
	}

	return($num);
}


/**
 * Returns path to file relative from user's/system directory
 *
 * @param string $id File ID
 * @return string
 */
function cot_pfs_filepath($id)
{
	global $db_pfs_folders, $db_pfs, $cfg;

	$sql = cot_db_query("SELECT p.pfs_file AS file, f.pff_path AS path FROM $db_pfs AS p LEFT JOIN $db_pfs_folders AS f ON p.pfs_folderid=f.pff_id WHERE p.pfs_id=".(int)$id." LIMIT 1");
	if($row = cot_db_fetcharray($sql))
	{
		return ($cfg['pfsuserfolder'] && $row['path']!='') ? $row['path'].'/'.$row['file'] : $row['file'];
	}
	else
	{
		return '';
	}
}

/**
 * Returns path to folder relative from user's/system directory
 *
 * @param int $id Folder ID
 * @param boolean $fullpath Return full path like in Folder Storage Mode ?
 * @return mixed
 */
function cot_pfs_folderpath($folderid, $fullpath='')
{
	global $db_pfs_folders, $cfg;

	if($fullpath==='') $fullpath = $cfg['pfsuserfolder'];

	if($fullpath && $folderid>0)
	{
		$sql = cot_db_query("SELECT pff_path FROM $db_pfs_folders WHERE pff_id=".(int)$folderid);
		if(cot_db_numrows($sql)==0)
		{
			return FALSE;
		}
		else
		{
			return cot_db_result($sql, 0, 'pff_path').'/';
		}
	}
	else
	{
		return '';
	}
}

/**
 * Get filesize limits
 *
 * @param int $userid User ID
 * @return array
 */
function cot_pfs_limits($userid)
{
	global $db_groups, $db_groups_users;

	$maxfile = 0;
	$maxtotal = 0;
	$sql = cot_db_query("SELECT MAX(grp_pfs_maxfile) AS maxfile, MAX(grp_pfs_maxtotal) AS maxtotal
	FROM $db_groups	WHERE grp_id IN (SELECT gru_groupid FROM $db_groups_users WHERE gru_userid=".(int)$userid.")");
	if ($row = cot_db_fetcharray($sql))
	{
		$maxfile = min($row['maxfile'], cot_get_uploadmax());
		$maxtotal = $row['maxtotal'];
	}
	return array($maxfile, $maxtotal);
}

/**
 * Create a new directory
 *
 * @param string $path Path relative to site root
 * $param boolean $feedback Prevent endless loop
 * @return boolean
 */
function cot_pfs_mkdir($path, $feedback=FALSE)
{
	global $cfg;

	if(substr($path, 0, 2) == './')
	{
		$path = substr($path, 2);
	}
	if(!$feedback && !file_exists($cfg['pfs_path']))
	{
		cot_pfs_mkdir($cfg['pfs_path'], TRUE);
	}
	if(@mkdir($path, $cfg['dir_perms']))
	{
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}

/**
 * Returns PFS path for a user, relative from site root
 *
 * @param int $userid User ID
 * @return string
 */
function cot_pfs_path($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{
		return($cfg['pfs_dir'].$userid.'/');
	}
	else
	{
		return($cfg['pfs_dir']);
	}
}

/**
 * Returns PFS path for a user, relative from PFS root
 *
 * @param int $userid User ID
 * @return string
 */
function cot_pfs_relpath($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{
		return($userid.'/');
	}
	else
	{
		return('');
	}
}

/**
 * Returns absolute path
 *
 * @param unknown_type $userid
 * @return unknown
 */
function cot_pfs_thumbpath($userid)
{
	global $cfg;

	if ($cfg['pfsuserfolder'])
	{
		return($cfg['th_dir'].$userid.'/');
	}
	else
	{
		return($cfg['th_dir']);
	}
}

/**
 * Upload one or more files, return parent folder ID
 *
 * @param int $userid User ID
 * @param int $folderid Folder ID
 * @return int
 */

function cot_pfs_upload($userid, $folderid='')
{
	global $cfg, $sys, $cot_extensions, $gd_supported, $maxfile, $maxtotal, $db_pfs, $db_pfs_folders, $L, $err_msg;

	if($folderid==='') $folderid = cot_import('folderid','P','INT');
	$ndesc = cot_import('ndesc','P','ARR');
	$npath = cot_pfs_folderpath($folderid);

	/* === Hook === */
	foreach (cot_getextplugins('pfs.upload.first') as $pl)
	{
		include $pl;
	}
	/* ===== */

	cot_die($npath===FALSE);

	for ($ii = 0; $ii < $cfg['pfsmaxuploads']; $ii++)
	{
		$disp_errors = '';
		$u_tmp_name = $_FILES['userfile']['tmp_name'][$ii];
		$u_type = $_FILES['userfile']['type'][$ii];
		$u_name = $_FILES['userfile']['name'][$ii];
		$u_size = $_FILES['userfile']['size'][$ii];
		$u_name  = str_replace("\'",'',$u_name );
		$u_name  = trim(str_replace("\"",'',$u_name ));

		if (!empty($u_name))
		{
			$disp_errors .= $u_name . ' : ';
			$u_name = mb_strtolower($u_name);
			$dotpos = mb_strrpos($u_name,".")+1;
			$f_extension = mb_substr($u_name, $dotpos);
			$f_extension_ok = 0;
			$desc = $ndesc[$ii];

			if($cfg['pfstimename'])
			{
				$u_name = time().'_'.$u_name;
			}
			if(!$cfg['pfsuserfolder'])
			{
				$u_name = $usr['id'].'_'.$u_name;
			}

			$u_newname = cot_safename($u_name, true);
			$u_sqlname = cot_db_prep($u_newname);

			if ($f_extension!='php' && $f_extension!='php3' && $f_extension!='php4' && $f_extension!='php5')
			{
				foreach ($cot_extensions as $k => $line)
				{
					if (mb_strtolower($f_extension) == $line[0])
					{
						$f_extension_ok = 1;
					}
				}
			}

			if (is_uploaded_file($u_tmp_name) && $u_size>0 && $u_size<($maxfile*1024) && $f_extension_ok
				&& ($pfs_totalsize+$u_size)<$maxtotal*1024   )
			{
				$fcheck = cot_file_check($u_tmp_name, $u_name, $f_extension);
				if($fcheck == 1)
				{
					if (!file_exists($cfg['pfs_path'].$npath.$u_newname))
					{
						$is_moved = true;

						if ($cfg['pfsuserfolder'])
						{
							if (!is_dir($cfg['pfs_path']))
							{
								$is_moved &= mkdir($cfg['pfs_path'], $cfg['dir_perms']);
							}
							if (!is_dir($cfg['pfs_thumbpath']))
							{
								$is_moved &= mkdir($cfg['pfs_thumbpath'], $cfg['dir_perms']);
							}
						}

						$is_moved &= move_uploaded_file($u_tmp_name, $cfg['pfs_path'].$npath.$u_newname);
						$is_moved &= chmod($cfg['pfs_path'].$npath.$u_newname, $cfg['file_perms']);

						$u_size = filesize($cfg['pfs_path'].$npath.$u_newname);

						if ($is_moved && (int)$u_size > 0)
						{
							/* === Hook === */
							foreach (cot_getextplugins('pfs.upload.moved') as $pl)
							{
								include $pl;
							}
							/* ===== */

							$sql = cot_db_query("INSERT INTO $db_pfs
							(pfs_userid,
							pfs_date,
							pfs_file,
							pfs_extension,
							pfs_folderid,
							pfs_desc,
							pfs_size,
							pfs_count)
							VALUES
							(".(int)$userid.",
							".(int)$sys['now_offset'].",
							'".cot_db_prep($u_sqlname)."',
							'".cot_db_prep($f_extension)."',
							".(int)$folderid.",
							'".cot_db_prep($desc)."',
							".(int)$u_size.",
							0) ");

							$sql = cot_db_query("UPDATE $db_pfs_folders SET pff_updated='".$sys['now']."'
								WHERE pff_id='$folderid'");
							$disp_errors .= $L['Yes'];
							$pfs_totalsize += $u_size;

							/* === Hook === */
							foreach (cot_getextplugins('pfs.upload.done') as $pl)
							{
								include $pl;
							}
							/* ===== */

							if (in_array($f_extension, $gd_supported) && $cfg['th_amode']!='Disabled'
								&& file_exists($cfg['pfs_path'].$u_newname))
							{
								@unlink($cfg['pfs_thumbpath'].$npath.$u_newname);
								$th_colortext = array(hexdec(substr($cfg['th_colortext'],0,2)),
									hexdec(substr($cfg['th_colortext'],2,2)), hexdec(substr($cfg['th_colortext'],4,2)));
								$th_colorbg = array(hexdec(substr($cfg['th_colorbg'],0,2)),
									hexdec(substr($cfg['th_colorbg'],2,2)), hexdec(substr($cfg['th_colorbg'],4,2)));
								cot_createthumb($cfg['pfs_path'].$npath.$u_newname,
									$cfg['pfs_thumbpath'].$npath.$u_newname, $cfg['th_x'],$cfg['th_y'],
									$cfg['th_keepratio'], $f_extension, $u_newname, floor($u_size/1024), $th_colortext,
									$cfg['th_textsize'], $th_colorbg, $cfg['th_border'], $cfg['th_jpeg_quality'],
									$cfg['th_dimpriority']);
							}
						}
						else
						{
							@unlink($cfg['pfs_path'].$npath.$u_newname);
							$disp_errors .= $L['pfs_filenotmoved'];
						}
					}
					else
					{
						$disp_errors .= $L['pfs_fileexists'];
					}
				}
				elseif($fcheck == 2)
				{
					$disp_errors .= sprintf($L['pfs_filemimemissing'], $f_extension);
				}
				else
				{
					$disp_errors .= sprintf($L['pfs_filenotvalid'], $f_extension);
				}
			}
			else
			{
				$disp_errors .= $L['pfs_filetoobigorext'];
			}
			$err_msg[] = $disp_errors;
		}
	}
	return $folderid;
}

/**
 * Strips all unsafe characters from file base name and converts it to latin
 *
 * @param string $basename File base name
 * @param bool $underscore Convert spaces to underscores
 * @param string $postfix Postfix appended to filename
 * @return string
 */
function cot_safename($basename, $underscore = true, $postfix = '')
{
	global $lang, $cot_translit;
	$fname = mb_substr($basename, 0, mb_strrpos($basename, '.'));
	$ext = mb_substr($basename, mb_strrpos($basename, '.') + 1);
	if($lang != 'en' && is_array($cot_translit))
	{
		$fname = strtr($fname, $cot_translit);
	}
	if($underscore) $fname = str_replace(' ', '_', $fname);
	$fname = preg_replace('#[^a-zA-Z0-9\-_\.\ \+]#', '', $fname);
	$fname = str_replace('..', '.', $fname);
	if(empty($fname)) $fname = cot_unique();
	return $fname . $postfix . '.' . mb_strtolower($ext);
}

/**
 * Renders PFS folder selection dropdown
 *
 * @param int $user User ID
 * @param int $skip Skip folder
 * @param int $check Checked folder
 * @param string $name Input name
 * @return string
 */
function cot_selectbox_folders($user, $skip, $check, $name = 'folderid')
{
	global $db_pfs_folders, $R;

	$sql = cot_db_query("SELECT pff_id, pff_title, pff_isgallery, pff_ispublic FROM $db_pfs_folders WHERE pff_userid='$user' ORDER BY pff_title ASC");

	$check = (empty($check) || $check == '/') ? '0' : $check;

	$result_arr = array();

	if ($skip != '/' && $skip != '0')
	{
		$result_arr[0] = '/';
	}
	
	while ($row = cot_db_fetcharray($sql))
	{
		if ($skip != $row['pff_id'])
		{
			$result_arr[$row['pff_id']] = htmlspecialchars($row['pff_title']);
		}
	}

	$result = cot_selectbox($check, $name, array_keys($result_arr), array_values($result_arr), false);

	return ($result);
}

/**
 * Fetches user entry from DB
 *
 * @param int $id User ID
 * @return array
 */
function cot_userinfo($id)
{
	global $db_users;

	$sql = cot_db_query("SELECT * FROM $db_users WHERE user_id='$id'");
	if ($res = cot_db_fetcharray($sql))
	{
		return ($res);
	}
	else
	{
		$res['user_name'] = '?';
		return ($res);
	}
}

?>