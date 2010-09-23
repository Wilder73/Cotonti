<?php
/**
 * @package install
 * @version 0.7.0
 * @author Kilandor, Trustmaster
 * @copyright Copyright (c) Cotonti Team 2009-2010
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL');

// Modules and plugins checked by default
$default_modules = array('index', 'page', 'rss');
$default_plugins = array('cleaner', 'ipsearch', 'markitup', 'news');

$step = empty($_SESSION['cot_inst_lang']) ? 0 : (int) $cfg['new_install'];

cot_sendheaders();

// Force Xtpl cache off
$cfg['xtpl_cache'] = false;

$mskin = cot_skinfile('install.install');
$t = new XTemplate($mskin);

$site_url = (strpos($_SERVER['SERVER_PROTOCOL'], 'HTTPS') === false ? 'http://' : 'https://')
	. $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$site_url = str_replace('\\', '/', $site_url);
$site_url = preg_replace('#/$#', '', $site_url);
define('COT_ABSOLUTE_URL', $site_url . '/');

if ($step != 2)
{
	$cot_dbc = cot_db_connect($cfg['mysqlhost'], $cfg['mysqluser'], $cfg['mysqlpassword'], $cfg['mysqldb']);
}

// Import section
switch ($step)
{
	case 2:
		$db_host = cot_import('db_host', 'P', 'TXT');
		$db_user = cot_import('db_user', 'P', 'TXT');
		$db_pass = cot_import('db_pass', 'P', 'TXT');
		$db_name = cot_import('db_name', 'P', 'TXT');
		break;

	case 3:
		$cfg['mainurl'] = cot_import('mainurl', 'P', 'TXT');
		$user['name'] = cot_import('user_name', 'P', 'TXT', 100, TRUE);
		$user['pass'] = cot_import('user_pass', 'P', 'TXT', 16);
		$user['pass2'] = cot_import('user_pass2', 'P', 'TXT', 16);
		$user['email'] = cot_import('user_email', 'P', 'TXT', 64, TRUE);
		$user['country'] = cot_import('user_country', 'P', 'TXT');
		$rtheme = explode(':', cot_import('theme', 'P', 'TXT'));
		$rscheme = $rtheme[1];
		$rtheme = $rtheme[0];
		$rlang = cot_import('lang', 'P', 'TXT');
		break;
	case 4:
		// Extension selection
		$install_modules = cot_import('install_modules', 'P', 'ARR');
		$selected_modules = array();
		if (is_array($install_modules))
		{
			foreach ($install_modules as $key => $val)
			{
				if ($val)
				{
					$selected_modules[] = $key;
				}
			}
		}
		$install_plugins = cot_import('install_plugins', 'P', 'ARR');
		$selected_plugins = array();
		if (is_array($install_plugins))
		{
			foreach ($install_plugins as $key => $val)
			{
				if ($val)
				{
					$selected_plugins[] = $key;
				}
			}
		}
		break;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	// Form submission handling
	switch ($step)
	{
		case 0:
			// Lang selection
			$_SESSION['cot_inst_lang'] = $lang;
			cot_redirect('install.php');
			break;
		case 1:
			// System info
			if (!file_exists($file['sql']))
			{
				cot_error(cot_rc('install_error_missing_file', array('file' => $file['sql'])));
			}
			if (function_exists('version_compare') && !version_compare(PHP_VERSION, '5.2.0', '>='))
			{
				cot_error(cot_rc('install_error_php_ver', array('ver' => PHP_VERSION)));
			}
			if (!extension_loaded('mbstring'))
			{
				cot_error('install_error_mbstring');
			}
			if (!extension_loaded('mysql'))
			{
				cot_error('install_error_mysql_ext');
			}
			if ($cot_dbc != 1 && $cot_dbc != 2 && function_exists('version_compare')
				&& !version_compare(@mysql_get_server_info($cot_dbc), '4.1.0', '>='))
			{
				cot_error(cot_rc('install_error_mysql_ver', array('ver' => @mysql_get_server_info($cot_dbc))));
			}

			if (!file_exists($file['config']))
			{
				if (!copy($file['config_sample'], $file['config']))
				{
					cot_error('install_error_config');
				}
			}
			break;
		case 2:
			// Database setup
			$db_x = cot_import('db_x', 'P', 'TXT');
			
			$cot_dbc = cot_db_connect($db_host, $db_user, $db_pass, $db_name);
			if ($cot_dbc == 1)
			{
				cot_error('install_error_sql', 'db_host');
			}
			if ($cot_dbc == 2)
			{
				cot_error('install_error_sql_db', 'db_name');
			}

			if (!$cot_error)
			{
				$config_contents = file_get_contents($file['config']);
				cot_install_config_replace($config_contents, 'mysqlhost', $db_host);
				cot_install_config_replace($config_contents, 'mysqluser', $db_user);
				cot_install_config_replace($config_contents, 'mysqlpassword', $db_pass);
				cot_install_config_replace($config_contents, 'mysqldb', $db_name);
				$config_contents = preg_replace("#^\\\$db_x\s*=\s*'.*?';#m",
						"\$db_x				= '$db_x';", $config_contents);
				file_put_contents($file['config'], $config_contents);

				$sql_file = file_get_contents($file['sql']);
				$error = cot_db_runscript($sql_file);

				if ($error)
				{
					cot_error(cot_rc('install_error_sql_script', array('msg' => $error)));
				}
			}
			break;
		case 3:
			// Misc settings and admin account
			if (empty($cfg['mainurl']))
			{
				cot_error('install_error_mainurl', 'mainurl');
			}
			if ($user['pass'] != $user['pass2'])
			{
				cot_error('aut_passwordmismatch', 'user_pass');
			}
			if (mb_strlen($user['name']) < 2)
			{
				cot_error('aut_usernametooshort', 'user_name');
			}
			if (mb_strlen($user['pass']) < 4
				|| cot_alphaonly($user['pass']) != $user['pass'])
			{
				cot_error('aut_passwordtooshort', 'user_pass');
			}
			if (mb_strlen($user['email']) < 4
				|| !preg_match('#^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]{2,})+$#i', $user['email']))
			{
				cot_error('aut_emailtooshort', 'user_email');
			}
			if (!file_exists($file['config_sample']))
			{
				cot_error(cot_rc('install_error_missing_file', array('file' => $file['config_sample'])));
			}

			if (!$cot_error)
			{
				$config_contents = file_get_contents($file['config']);
				cot_install_config_replace($config_contents, 'defaultlang', $rlang);
				cot_install_config_replace($config_contents, 'defaulttheme', $rtheme);
				cot_install_config_replace($config_contents, 'defaultscheme', $rscheme);
				cot_install_config_replace($config_contents, 'mainurl', $cfg['mainurl']);

				$new_site_id = cot_unique(32);
				cot_install_config_replace($config_contents, 'site_id', $new_site_id);

				file_put_contents($file['config'], $config_contents);

				if (cot_db_insert($db_x . 'users', array(
						'name' => $user['name'],
						'password' => md5($user['pass']),
						'maingrp' => COT_GROUP_SUPERADMINS,
						'country' => (string) $user['country'],
						'email' => $user['email'],
						'theme' => $rtheme,
						'scheme' => $rscheme,
						'lang' => $rlang,
						'regdate' => time(),
						'lastip' => $_SERVER['REMOTE_ADDR']
					), 'user_') == 1)
				{

					$user['id'] = cot_db_insertid();

					cot_db_insert($db_x . 'groups_users', array(
						'userid' => (int) $user['id'],
						'groupid' => COT_GROUP_SUPERADMINS
					), 'gru_');
				}
				else
				{
					cot_error(cot_rc('install_error_sql_script', array('msg' => cot_db_error())));
				}
			}

			break;
		case 4:
			// Dependency check
			$install = true;
			foreach ($selected_modules as $ext)
			{
				$install &= cot_extension_dependencies_statisfied($ext, true, $selected_modules, $selected_plugins);
			}
			foreach ($selected_plugins as $ext)
			{
				$install &= cot_extension_dependencies_statisfied($ext, false, $selected_modules, $selected_plugins);
			}

			if ($install && !$cot_error)
			{
				// Load groups
				$cot_groups = array();
				$res = cot_db_query("SELECT grp_id FROM $db_groups
					WHERE grp_disabled=0 ORDER BY grp_level DESC");
				while ($row = cot_db_fetchassoc($res))
				{
					$cot_groups[$row['grp_id']] = array(
						'id' => $row['grp_id'],
						'alias' => $row['grp_alias'],
						'level' => $row['grp_level'],
						'disabled' => $row['grp_disabled'],
						'hidden' => $row['grp_hidden'],
						'state' => $row['grp_state'],
						'title' => htmlspecialchars($row['grp_title'])
					);
				}
				cot_db_freeresult($res);
				$usr['id'] = 1;
				// Install all at once
				// Note: installation statuses are ignored in this installer
				foreach ($selected_modules as $ext)
				{
					if (!cot_extension_install($ext, true))
                    {
                        cot_error("Installing $ext module has failed");
                    }
				}
				foreach ($selected_plugins as $ext)
				{
					if (!cot_extension_install($ext, false))
                    {
                        cot_error("Installing $ext plugin has failed");
                    }
				}
			}
			break;
		case 5:
			// End credits
			break;
		default:
			// Error
			cot_redirect(cot_url('index'));
			exit;
	}

	if ($cot_error)
	{
		// One step back
		cot_redirect('install.php');
	}
	else
	{
		// Step++
		$step++;
		$config_contents = file_get_contents($file['config']);
		if ($step == 5)
		{
			$config_contents = preg_replace("#^\\\$cfg\['new_install'\]\s*=\s*.*?;#m", "\$cfg['new_install'] = false;", $config_contents);
		}
		else
		{
			$config_contents = preg_replace("#^\\\$cfg\['new_install'\]\s*=\s*.*?;#m", "\$cfg['new_install'] = $step;",
					$config_contents);
		}
		file_put_contents($file['config'], $config_contents);
	}
}

// Display
switch ($step)
{
	case 0:
		// Language selection
		$t->assign(array(
			'INSTALL_LANG' => cot_selectbox_lang($lang, 'lang')
		));
		break;
	case 1:
		// System info
		// Build CHMOD/Exists/Version data
		clearstatcache();

		if (is_dir($cfg['av_dir']))
		{
			$status['av_dir'] = (substr(decoct(fileperms($cfg['av_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['av_dir'])), -4)))));
		}
		else
		{
			$status['av_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (is_dir($cfg['cache_dir']))
		{
			$status['cache_dir'] = (substr(decoct(fileperms($cfg['cache_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['cache_dir'])), -4)))));
		}
		else
		{
			$status['cache_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (is_dir($cfg['pfs_dir']))
		{
			$status['pfs_dir'] = (substr(decoct(fileperms($cfg['pfs_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['pfs_dir'])), -4)))));
		}
		else
		{
			$status['pfs_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (is_dir($cfg['photos_dir']))
		{
			$status['photos_dir'] = (substr(decoct(fileperms($cfg['photos_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['photos_dir'])), -4)))));
		}
		else
		{
			$status['photos_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (is_dir($cfg['sig_dir']))
		{
			$status['sig_dir'] = (substr(decoct(fileperms($cfg['sig_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['sig_dir'])), -4)))));
		}
		else
		{
			$status['sig_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (is_dir($cfg['th_dir']))
		{
			$status['th_dir'] = (substr(decoct(fileperms($cfg['th_dir'])), -4) >= $cfg['dir_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($cfg['th_dir'])), -4)))));
		}
		else
		{
			$status['th_dir'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (file_exists($file['config']))
		{
			$status['config'] = (substr(decoct(fileperms($file['config'])), -4)
					>= $cfg['file_perms'])
				? $R['install_code_writable']
				: cot_rc('install_code_invalid', array('text' =>
					cot_rc('install_chmod_value', array('chmod' =>
						substr(decoct(fileperms($file['config'])), -4)))));
		}
		else
		{
			$status['config'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (file_exists($file['config_sample']))
		{
			$status['config_sample'] = $R['install_code_found'];
		}
		else
		{
			$status['config_sample'] = $R['install_code_not_found'];
		}
		/* ------------------- */
		if (file_exists($file['sql']))
		{
			$status['sql_file'] = $R['install_code_found'];
		}
		else
		{
			$status['sql_file'] = $R['install_code_not_found'];
		}
		$status['php_ver'] = (function_exists('version_compare') && version_compare(PHP_VERSION, '5.2.0', '>='))
			? cot_rc('install_code_valid', array('text' =>
				cot_rc('install_ver_valid', array('ver' => PHP_VERSION))))
			: cot_rc('install_code_invalid', array('text' =>
				cot_rc('install_ver_invalid', array('ver' => PHP_VERSION))));
		$status['mbstring'] = (extension_loaded('mbstring'))
			? $R['install_code_available'] : $R['install_code_not_available'];
		$status['mysql'] = (extension_loaded('mysql'))
			? $R['install_code_available'] : $R['install_code_not_available'];
//		$status['mysql_ver'] = '/ '
//		. ($cot_dbc && function_exists('version_compare')
//				&& version_compare(@mysql_get_server_info($cot_dbc), '4.1.0', '>='))
//			? cot_rc('install_code_valid',
//				array('text' => cot_rc('install_ver_valid',
//					array('ver' => mysql_get_server_info($cot_dbc)))))
//			: $R['install_code_not_available'];

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
			'INSTALL_MYSQL_VER' => $status['mysql_ver']
		));
		break;
	case 2:
		// Database form
		$t->assign(array(
			'INSTALL_DB_HOST' => is_null($db_host) ? $cfg['mysqlhost'] : $db_host,
			'INSTALL_DB_USER' => is_null($db_user) ? $cfg['mysqluser'] : $db_user,
			'INSTALL_DB_NAME' => is_null($db_name) ? $cfg['mysqldb'] : $db_name,
			'INSTALL_DB_X' => $db_x,
		));
		break;
	case 3:
		// Settings
		if ($_POST['step'] != 3)
		{
			$rtheme = $theme;
			$rscheme = $scheme;
			$rlang = $cfg['defaultlang'];
			$cfg['mainurl'] = $site_url;
		}

		$t->assign(array(
			'INSTALL_THEME_SELECT' => cot_selectbox_theme($rtheme, $rscheme, 'theme'),
			'INSTALL_LANG_SELECT' => cot_selectbox_lang($rlang, 'lang'),
			'INSTALL_COUNTRY_SELECT' => cot_selectbox_countries($user['country'], 'user_country')
		));
	case 4:
		// Extensions
		cot_install_parse_extensions('Module', $default_modules, $selected_modules);
		cot_install_parse_extensions('Plugin', $default_plugins, $selected_plugins);
		break;
	case 5:
		// End credits
		break;

}

$t->parse("MAIN.STEP_$step");

// Error & message display
cot_display_messages($t);

$t->assign(array(
	'INSTALL_STEP' => $step == 5 ? $L['Complete'] : cot_rc('install_step', array('step' => $step, 'total' => 4)),
	'INSTALL_LANG' => cot_selectbox_lang($lang, 'lang')
));


$t->parse('MAIN');
$t->out('MAIN');

/**
 * Replaces a sample config with its actual value
 *
 * @param string $file_contents Config file contents
 * @param string $config_name Config option name
 * @param string $config_value Config value to set
 * @return string Modified file contents
 */
function cot_install_config_replace(&$file_contents, $config_name, $config_value)
{
	$file_contents = preg_replace("#^\\\$cfg\['$config_name'\]\s*=\s*'.*?';#m",
		"\$cfg['$config_name'] = '$config_value';", $file_contents);
}

/**
 * Parses extensions selection section
 *
 * @param string $ext_type Extension type: 'Module' or 'Plugin'
 * @param array $default_list A list of recommended extensions (checked by default)
 * @param array $selected_list A list of previously selected extensions
 */
function cot_install_parse_extensions($ext_type, $default_list = array(), $selected_list = array())
{
	global $t, $cfg, $L;
	$ext_type_lc = strtolower($ext_type);
	$ext_type_uc = strtoupper($ext_type);

	$ext_list = array();
	$dp = opendir($cfg["{$ext_type_lc}s_dir"]);
	while ($f = readdir($dp))
	{
		$path = $cfg["{$ext_type_lc}s_dir"] . '/' . $f;
		if ($f[0] != '.' && is_dir($path) && file_exists("$path/$f.setup.php"))
		{
			$ext_list[$f] = "$path/$f.setup.php";
		}
	}
	closedir($dp);

	ksort($ext_list);

	foreach ($ext_list as $f => $ext_setup)
	{
		$info = cot_infoget($ext_setup, 'COT_EXT');
		if (is_array($info))
		{
			if (!empty($info["Requires_modules"]) || !empty($info['Requires_plugins']))
			{
				$modules_list = empty($info['Requires_modules']) ? $L['None']
					: implode(', ', explode(',', $info['Requires_modules']));
				$plugins_list = empty($info['Requires_plugins']) ? $L['None']
					: implode(', ', explode(',', $info['Requires_plugins']));
				$requires = cot_rc('install_code_requires',
						array('modules_list' => $modules_list, 'plugins_list' => $plugins_list));
			}
			else
			{
				$requires = '';
			}
			if (!empty($info['Recommends_modules']) || !empty($info['Recommends_plugins']))
			{
				$modules_list = empty($info['Recommends_modules']) ? $L['None']
					: implode(', ', explode(',', $info['Recommends_modules']));
				$plugins_list = empty($info['Recommends_plugins']) ? $L['None']
					: implode(', ', explode(',', $info['Recommends_plugins']));
				$recommends = cot_rc('install_code_recommends',
						array('modules_list' => $modules_list, 'plugins_list' => $plugins_list));
			}
			else
			{
				$recommends = '';
			}
			if (count($selected_list) > 0)
			{
				$checked = in_array($f, $selected_list);
			}
			else
			{
				$checked = in_array($f, $default_list);
			}
			$t->assign(array(
				"{$ext_type_uc}_ROW_CHECKBOX" => cot_checkbox($checked, "install_{$ext_type_lc}s[$f]"),
				"{$ext_type_uc}_ROW_TITLE" => $info['Name'],
				"{$ext_type_uc}_ROW_DESCRIPTION" => $info['Description'],
				"{$ext_type_uc}_ROW_REQUIRES" => $requires,
				"{$ext_type_uc}_ROW_RECOMMENDS" => $recommends
			));
			$t->parse("MAIN.STEP_4.{$ext_type_uc}_ROW");
		}
	}
}
?>