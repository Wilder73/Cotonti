<?php
/**
 * @package Cotonti
 * @version 0.7.0
 * @author Kilandor
 * @copyright Copyright (c) Cotonti Team 2009
 * @license BSD
 */

defined('SED_CODE') or die('Wrong URL');

//Various Generic Vars needed to operate as Normal
$skin = $cfg['defaultskin'];
$theme = $cfg['defaulttheme'];
$out['meta_lastmod'] = gmdate("D, d M Y H:i:s");
$file['config'] = './datas/config.php';
$file['config_sample'] = './datas/config-sample.php';
$file['sql'] = './sql/install.sql';

sed_sendheaders();

$mskin = sed_skinfile('install');
$t = new XTemplate($mskin);

if($_POST['submit'])
{
	$cfg['mysqlhost'] = sed_import('db_host', 'P', 'TXT');
	$cfg['mysqluser'] = sed_import('db_user', 'P', 'TXT');
	$cfg['mysqlpassword'] = sed_import('db_pass', 'P', 'TXT');
	$cfg['mysqldb'] = sed_import('db_name', 'P', 'TXT');
	$cfg['mainurl'] = sed_import('mainurl', 'P', 'TXT');
	$user['name'] = sed_import('user_name', 'P', 'TXT', 100, TRUE);
	$user['pass'] = sed_import('user_pass', 'P', 'TXT', 16);
	$user['pass2'] = sed_import('user_pass2', 'P', 'TXT', 16);
	$user['email'] = sed_import('user_email', 'P', 'TXT', 64, TRUE);
	$user['country'] = sed_import('user_country', 'P', 'TXT');
	$db_x = sed_import('db_x', 'P', 'TXT');
	$rskin = sed_import('skin', 'P', 'TXT');
	//$rtheme = sed_import('theme', 'P', 'TXT');
	//$rtheme = ($skin == $rtheme && $rskin != $rtheme) ? $rskin : $rtheme;
	$rlang = sed_import('lang', 'P', 'TXT');

	$sed_dbc = sed_sql_connect($cfg['mysqlhost'], $cfg['mysqluser'], $cfg['mysqlpassword'], $cfg['mysqldb']);
	$error .= ($sed_dbc == 1) ? $L['install_error_sql'].'<br />' : '';
	$error .= ($sed_dbc == 2) ? $L['install_error_sql_db'].'<br />' : '';
	$error .= (empty($cfg['mainurl'])) ? $L['install_error_mainurl'].'<br />' : '';
	$error .= ($user['pass']!=$user['pass2']) ? $L['aut_passwordmismatch']."<br />" : '';
	$error .= (mb_strlen($user['name'])<2) ? $L['aut_usernametooshort']."<br />" : '';
	$error .= (mb_strlen($user['pass'])<4 || sed_alphaonly($user['pass'])!=$user['pass']) ? $L['aut_passwordtooshort']."<br />" : '';
	$error .= (mb_strlen($user['email'])<4 || !preg_match('#^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]{2,})+$#i', $user['email'])) ? $L['aut_emailtooshort']."<br />" : '';
	$error .= (!file_exists($file['config_sample'])) ? sprintf($L['install_error_missing_file'], $file['config_sample']).'<br />' : '';
	$error .= (!file_exists($file['sql'])) ? sprintf($L['install_error_missing_file'], $file['sql']).'<br />' : '';
	$error .= (function_exists('version_compare') && !version_compare(PHP_VERSION, '5.1.0', '>=')) ? sprintf($L['install_error_php_ver'], PHP_VERSION).'<br />' : '';
	$error .= (!extension_loaded('mbstring')) ? $L['install_error_mbstring'].'<br />' : '';
	$error .= (!extension_loaded('mysql')) ? $L['install_error_mysql_ext'].'<br />' : '';
	$error .= ($sed_dbc != 1 && $sed_dbc != 2 && function_exists('version_compare') && !version_compare(@mysql_get_server_info($sed_dbc), '4.1.0', '>=')) ? sprintf($L['install_error_mysql_ver'], @mysql_get_server_info($sed_dbc)).'<br />' : '';

	if(!$error)
	{
		
		$sql_file = file_get_contents($file['sql']);
		$sql_queries = preg_split('/;\r?\n/', $sql_file);
		foreach($sql_queries as $sql_query)
		{
			if($db_x != 'sed_')
			{
				$sql_query = str_replace('`sed_', '`'.$db_x, $sql_query);
			}
			$result = sed_sql_query($sql_query);
			if(!$result)
			{
				$error .= sed_sql_error().'<br />';
				break;
			}
		}
		
		if(!$error)
		{
			$file_contents = file_get_contents($file['config_sample'], NULL, NULL, 5, filesize($file['config_sample']));

			$file_contents = preg_replace('/^\$cfg\[\'defaultlang\'\]\s*=\s*\'.*?\';/m', '$cfg[\'defaultlang\'] = \''.$rlang.'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'defaultskin\'\]\s*=\s*\'.*?\';/m', '$cfg[\'defaultskin\'] = \''.$rskin.'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'defaulttheme\'\]\s*=\s*\'.*?\';/m', '$cfg[\'defaulttheme\'] = \''.$rskin.'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'mysqlhost\'\]\s*=\s*\'.*?\';/m', '$cfg[\'mysqlhost\'] = \''.$cfg['mysqlhost'].'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'mysqluser\'\\]\s*=\s*\'.*?\';/m', '$cfg[\'mysqluser\'] = \''.$cfg['mysqluser'].'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'mysqlpassword\'\]\s*=\s*\'.*?\';/m', '$cfg[\'mysqlpassword\'] = \''.$cfg['mysqlpassword'].'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'mysqldb\'\]\s*=\s*\'.*?\';/m', '$cfg[\'mysqldb\'] = \''.$cfg['mysqldb'].'\';', $file_contents);
			$file_contents = preg_replace('/^\$db_x\s*=\s*\'.*?\';/m', '$db_x				= \''.$db_x.'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'mainurl\'\]\s*=\s*\'.*?\';/m', '$cfg[\'mainurl\'] = \''.$cfg['mainurl'].'\';', $file_contents);
			$file_contents = preg_replace('/^\$cfg\[\'new_install\'\]\s*=\s*.*?;/m', '$cfg[\'new_install\'] = FALSE;', $file_contents);

			//echo"<pre>".$file_contents."</pre>";
			file_put_contents($file['config'], "<?PHP".$file_contents);
			
			$sql_user = "INSERT into ".$db_x."users
			(user_name,
			user_password,
			user_maingrp,
			user_country,
			user_email,
			user_skin,
			user_theme,
			user_lang,
			user_regdate,
			user_lastip)
			VALUES
			('".sed_sql_prep($user['name'])."',
			'".md5($user['pass'])."',
			5,
			'".sed_sql_prep($user['country'])."',
			'".sed_sql_prep($user['email'])."',
			'".$rskin."',
			'".$rskin."',
			'".$rlang."',
			".time().",
			'".$_SERVER['REMOTE_ADDR']."')";
			sed_sql_query($sql_user);
			$user['id'] = sed_sql_insertid();
			sed_sql_query("INSERT INTO ".$db_x."groups_users (gru_userid, gru_groupid) VALUES (".(int)$user['id'].", 5)");
			header('Location: '.$cfg['mainurl']);
			exit;
		}
	}
}
else
{
	$rskin = $skin;
	$rtheme = $theme;
	$rlang = $cfg['defaultlang'];
}

//Build CHMOD/Exists/Version data
clearstatcache();

if(is_dir($cfg['av_dir']))
	$status['av_dir'] = (substr(decoct(fileperms($cfg['av_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['av_dir'])), -4)).'</span>';
else
	$status['av_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(is_dir($cfg['cache_dir']))
	$status['cache_dir'] = (substr(decoct(fileperms($cfg['cache_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['cache_dir'])), -4)).'</span>';
else
	$status['cache_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(is_dir($cfg['pfs_dir']))
	$status['pfs_dir'] = (substr(decoct(fileperms($cfg['pfs_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['pfs_dir'])), -4)).'</span>';
else
	$status['pfs_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(is_dir($cfg['photos_dir']))
	$status['photos_dir'] = (substr(decoct(fileperms($cfg['photos_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['photos_dir'])), -4)).'</span>';
else
	$status['photos_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(is_dir($cfg['sig_dir']))
	$status['sig_dir'] = (substr(decoct(fileperms($cfg['sig_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['sig_dir'])), -4)).'</span>';
else
	$status['sig_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(is_dir($cfg['th_dir']))
	$status['th_dir'] = (substr(decoct(fileperms($cfg['th_dir'])), -4) >= $cfg['dir_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($cfg['th_dir'])), -4)).'</span>';
else
	$status['th_dir'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(file_exists($file['config']))
	$status['config'] = (substr(decoct(fileperms($file['config'])), -4) >= $cfg['file_perms']) ? '<span class="install_valid">'.$L['install_writable'].'</span>' : '<span class="install_invalid">'.sprintf($L['install_chmod_value'], substr(decoct(fileperms($file['config'])), -4)).'</span>';
else
	$status['config'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(file_exists($file['config_sample']))
	$status['config_sample'] = '<span class="install_valid">'.$L['Found'].'</span>';
else
	$status['config_sample'] = '<span class="install_invalid">'.$L['nf'].'</span>';
/* ------------------- */
if(file_exists($file['sql']))
	$status['sql_file'] = '<span class="install_valid">'.$L['Found'].'</span>';
else
	$status['sql_file'] = '<span class="install_invalid">'.$L['nf'].'</span>';

$status['php_ver'] = (function_exists('version_compare') && version_compare(PHP_VERSION, '5.1.0', '>=')) ? '<span class="install_valid">'.sprintf($L['install_ver_valid'],  PHP_VERSION).'</span>' : '<span class="install_invalid">'.sprintf($L['install_ver_invalid'],  PHP_VERSION).'</span>';
$status['mbstring'] = (extension_loaded('mbstring')) ? '<span class="install_valid">'.$L['Available'].'</span>' : '<span class="install_invalid">'.$L['na'].'</span>';
$status['mysql'] = (extension_loaded('mysql')) ? '<span class="install_valid">'.$L['Available'].'</span>' : '<span class="install_invalid">'.$L['na'].'</span>';

if($_POST['submit'])
{
	$status['mysql_ver'] = ($sed_dbc && function_exists('version_compare') && version_compare(@mysql_get_server_info($sed_dbc), '4.1.0', '>=')) ? '<span class="install_valid">'.sprintf($L['install_ver_valid'],  mysql_get_server_info($sed_dbc)).'</span>' : '<span class="install_invalid">'.$L['na'].'</span>';
}
else
{
	$status['mysql_ver'] = '<span class="install_invalid">'.$L['na'].'</span>';
}
if($error)
{
	$t->assign(array(
		'INSTALL_ERROR' => $error
		));
	$t->parse('MAIN.ERROR');
}

$t->assign(array(
	'INSTALL_AV_DIR' => $status['av_dir'],
	'INSTALL_CACHE_DIR' => $status['cache_dir'],
	'INSTALL_PFS_DIR' => $status['pfs_dir'],
	'INSTALL_PHOTOS_DIR' => $status['photos_dir'],
	'INSTALL_SIG_DIR' => $status['sig_dir'],
	'INSTALL_TH_DIR' => $status['th_dir'],
	'INSTALL_CONFIG' => $status['config'],
	'INSTALL_CONFIG_SAMPLE' => $status['config_sample'],
	'INSTALL_SQL_FILE' => $status['sql_file'],
	'INSTALL_PHP_VER' => $status['php_ver'],
	'INSTALL_MBSTRING' => $status['mbstring'],
	'INSTALL_MYSQL' => $status['mysql'],
	'INSTALL_MYSQL_VER' => $status['mysql_ver'],
	'INSTALL_DB_HOST' => $cfg['mysqlhost'],
	'INSTALL_DB_USER' => $cfg['mysqluser'],
	'INSTALL_DB_NAME' => $cfg['mysqldb'],
	'INSTALL_DB_X' => $db_x,
	'INSTALL_SKIN_SELECT' => sed_selectbox_skin($rskin, 'skin'),
	//'INSTALL_THEME_SELECT' => sed_selectbox_theme($rskin, 'theme', $theme),
	'INSTALL_LANG_SELECT' => sed_selectbox_lang($rlang, 'lang'),
	'INSTALL_COUNTRY_SELECT' => sed_selectbox_countries($user['country'], 'user_country'),
	));

$t->parse("MAIN");
$t->out("MAIN");

?>