<?php
/*
+ ----------------------------------------------------------------------------+
|     e107 website system
|
|     �Steve Dunstan 2001-2002
|     http://e107.org
|     jalist@e107.org
|
|     Released under the terms and conditions of the
|     GNU General Public License (http://gnu.org).
|
|     $Source: /cvs_backup/e107_0.8/e107_admin/plugin.php,v $
|     $Revision: 1.23 $
|     $Date: 2008-12-30 19:01:09 $
|     $Author: e107steved $
+----------------------------------------------------------------------------+
*/

require_once("../class2.php");
if (!getperms("Z")) 
{
	header("location:".e_BASE."index.php");
	exit;
}
$e_sub_cat = 'plug_manage';
require_once("auth.php");
require_once(e_HANDLER.'plugin_class.php');
require_once(e_HANDLER.'file_class.php');
$plugin = new e107plugin;

$tmp = explode('.', e_QUERY);
$action = $tmp[0];
$id = intval($tmp[1]);

define('PLUGIN_SHOW_REFRESH', FALSE);


if (isset($_POST['upload']))
{
	if (!$_POST['ac'] == md5(ADMINPWCHANGE))
	{
		exit;
	}

	extract($_FILES);
	/* check if e_PLUGIN dir is writable ... */
	if(!is_writable(e_PLUGIN))
	{
		/* still not writable - spawn error message */
		$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_39);
	}
	else
	{
		/* e_PLUGIN is writable - continue */
		$pref['upload_storagetype'] = "1";
		require_once(e_HANDLER."upload_handler.php");
		$fileName = $file_userfile['name'][0];
		$fileSize = $file_userfile['size'][0];
		$fileType = $file_userfile['type'][0];

		if(strstr($file_userfile['type'][0], "gzip"))
		{
			$fileType = "tar";
		}
		else if (strstr($file_userfile['type'][0], "zip"))
		{
			$fileType = "zip";
		}
		else
		{
			/* not zip or tar - spawn error message */
			$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_41);
			require_once("footer.php");
			exit;
		}

		if ($fileSize)
		{

			$opref = $pref['upload_storagetype'];
			$pref['upload_storagetype'] = 1;		/* temporarily set upload type pref to flatfile */
			$uploaded = file_upload(e_PLUGIN);
			$pref['upload_storagetype'] = $opref;

			$archiveName = $uploaded[0]['name'];

			/* attempt to unarchive ... */

			if($fileType == "zip")
			{
				require_once(e_HANDLER."pclzip.lib.php");
				$archive = new PclZip(e_PLUGIN.$archiveName);
				$unarc = ($fileList = $archive -> extract(PCLZIP_OPT_PATH, e_PLUGIN, PCLZIP_OPT_SET_CHMOD, 0666));
			}
			else
			{
				require_once(e_HANDLER."pcltar.lib.php");
				$unarc = ($fileList = PclTarExtract($archiveName, e_PLUGIN));
			}

			if(!$unarc)
			{
				/* unarc failed ... */
				if($fileType == "zip")
				{
					$error = EPL_ADLAN_46." '".$archive -> errorName(TRUE)."'";
				}
				else
				{
					$error = EPL_ADLAN_47.PclErrorString().", ".EPL_ADLAN_48.intval(PclErrorCode());
				}
				$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_42." ".$archiveName." ".$error);
				require_once("footer.php");
				exit;
			}

			/* ok it looks like the unarc succeeded - continue */

			/* get folder name ... */
			$folderName = substr($fileList[0]['stored_filename'], 0, (strpos($fileList[0]['stored_filename'], "/")));

			if(file_exists(e_PLUGIN.$folderName."/plugin.php") || file_exists(e_PLUGIN.$folderName."/plugin.xml"))
			{
				/* upload is a plugin */
				$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_43);
			}
			else
			{
				/* upload is a menu */
				$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_45);
			}

			/* attempt to delete uploaded archive */
			@unlink(e_PLUGIN.$archiveName);
		}
	}
}


if ($action == 'uninstall')
{
	if(!isset($_POST['uninstall_confirm']))
	{	// $id is already an integer
		show_uninstall_confirm($id);
		exit;
	}

	$plug = $plugin->getinfo($id);
	$text = '';
	//Uninstall Plugin
	if ($plug['plugin_installflag'] == TRUE )
	{
		$_path = e_PLUGIN.$plug['plugin_path'].'/';
		if(file_exists($_path.'plugin.xml'))
		{
			$options = array(
				'del_tables' => varset($_POST['delete_tables'],FALSE), 
				'del_userclasses' => varset($_POST['delete_userclasses'],FALSE), 
				'del_extended' => varset($_POST['delete_xfields'],FALSE)
				);
			$text .= $plugin->manage_plugin_xml($id, 'uninstall', $options);
		}
		else
		{
			include(e_PLUGIN.$plug['plugin_path'].'/plugin.php');

			$func = $eplug_folder.'_uninstall';
			if (function_exists($func))
			{
				$text .= call_user_func($func);
			}

			if($_POST['delete_tables'])
			{
				if (is_array($eplug_table_names))
				{
					$result = $plugin->manage_tables('remove', $eplug_table_names);
					if ($result !== TRUE)
					{
						$text .= EPL_ADLAN_27.' <b>'.$mySQLprefix.$result.'</b> - '.EPL_ADLAN_30.'<br />';
					}
					else
					{
						$text .= EPL_ADLAN_28."<br />";
					}
				}
			}
			else
			{
				$text .= EPL_ADLAN_49."<br />";
			}

			if (is_array($eplug_prefs))
			{
				$plugin->manage_prefs('remove', $eplug_prefs);
				$text .= EPL_ADLAN_29."<br />";
			}

			if (is_array($eplug_comment_ids))
			{
				$text .= ($plugin->manage_comments('remove', $eplug_comment_ids)) ? EPL_ADLAN_50."<br />" : "";
			}

/* Not used in 0.8
			if ($eplug_module)
			{
				$plugin->manage_plugin_prefs('remove', 'modules', $eplug_folder);
			}
			if ($eplug_status)
			{
				$plugin->manage_plugin_prefs('remove', 'plug_status', $eplug_folder);
			}

			if ($eplug_latest)
			{
				$plugin->manage_plugin_prefs('remove', 'plug_latest', $eplug_folder);
			}
*/
			if (is_array($eplug_array_pref))
			{
				foreach($eplug_array_pref as $key => $val)
				{
					$plugin->manage_plugin_prefs('remove', $key, $eplug_folder, $val);
				}
			}

/* Not used in 0.8
			if (is_array($eplug_sc))
			{
				$plugin->manage_plugin_prefs('remove', 'plug_sc', $eplug_folder, $eplug_sc);
			}

			if (is_array($eplug_bb))
			{
				$plugin->manage_plugin_prefs('remove', 'plug_bb', $eplug_folder, $eplug_bb);
			}
*/
			if ($eplug_menu_name)
			{
				$sql->db_Delete('menus', "menu_name='$eplug_menu_name' ");
			}

			if ($eplug_link)
			{
				$plugin->manage_link('remove', $eplug_link_url, $eplug_link_name);
			}

			if ($eplug_userclass)
			{
				$plugin->manage_userclass('remove', $eplug_userclass);
			}

			$sql->db_Update('plugin', "plugin_installflag=0, plugin_version='{$eplug_version}' WHERE plugin_id='{$id}' ");
			$plugin->manage_search('remove', $eplug_folder);

			$plugin->manage_notify('remove', $eplug_folder);
		}

		$admin_log->log_event('PLUGMAN_03', $plug['plugin_path'], E_LOG_INFORMATIVE, '');

		if (isset($pref['plug_installed'][$plug['plugin_path']]))
		{
			unset($pref['plug_installed'][$plug['plugin_path']]);
			save_prefs();
		}
	}

	if($_POST['delete_files'])
	{
		include_once(e_HANDLER."file_class.php");
		$fi = new e_file;
		$result = $fi->rmtree(e_PLUGIN.$eplug_folder);
		$text .= ($result ? "<br />All files removed from ".e_PLUGIN.$eplug_folder : '<br />File deletion failed<br />'.EPL_ADLAN_31.' <b>'.e_PLUGIN.$eplug_folder.'</b> '.EPL_ADLAN_32);
	}
	else
	{
		$text .= '<br />'.EPL_ADLAN_31.' <b>'.e_PLUGIN.$eplug_folder.'</b> '.EPL_ADLAN_32;
	}

	$plugin->save_addon_prefs();
	$ns->tablerender(EPL_ADLAN_1.' '.$tp->toHtml($plug['plugin_name'], "", "defs,emotes_off,no_make_clickable"), $text);
	$text = '';
}


if ($action == 'install')
{
	$text = $plugin->install_plugin($id);
	if ($text === FALSE)
	{ // Tidy this up
		$ns->tablerender(LAN_INSTALL_FAIL, "Error messages above this line");
	}
	else
	{
		$plugin ->save_addon_prefs();
//	if($eplug_conffile){ $text .= "&nbsp;<a href='".e_PLUGIN."$eplug_folder/$eplug_conffile'>[".LAN_CONFIGURE."]</a>"; }
		$admin_log->log_event('PLUGMAN_01', $id.':'.$eplug_folder, E_LOG_INFORMATIVE, '');
		$ns->tablerender(EPL_ADLAN_33, $text);
	}
}

if ($action == 'upgrade')
{
	$plug = $plugin->getinfo($id);

	$_path = e_PLUGIN.$plug['plugin_path'].'/';
	if(file_exists($_path.'plugin.xml'))
	{
		$text .= $plugin->manage_plugin_xml($id, 'upgrade');
	}
	else
	{
		include(e_PLUGIN.$plug['plugin_path'].'/plugin.php');

		$func = $eplug_folder.'_upgrade';
		if (function_exists($func))
		{
			$text .= call_user_func($func);
		}

		if (is_array($upgrade_alter_tables))
		{
			$result = $plugin->manage_tables('upgrade', $upgrade_alter_tables);
			if (!$result)
			{
				$text .= EPL_ADLAN_9.'<br />';
			}
			else
			{
				$text .= EPL_ADLAN_7."<br />";
			}
		}

/* Not used in 0.8
		if ($eplug_module)
		{
			$plugin->manage_plugin_prefs('add', 'modules', $eplug_folder);
		}
		else
		{
			$plugin->manage_plugin_prefs('remove', 'modules', $eplug_folder);
		}

		if ($eplug_status)
		{
			$plugin->manage_plugin_prefs('add', 'plug_status', $eplug_folder);
		}
		else
		{
			$plugin->manage_plugin_prefs('remove', 'plug_status', $eplug_folder);
		}

		if ($eplug_latest)
		{
			$plugin->manage_plugin_prefs('add', 'plug_latest', $eplug_folder);
		}
		else
		{
			$plugin->manage_plugin_prefs('remove', 'plug_latest', $eplug_folder);
		}

		if (is_array($upgrade_add_eplug_sc))
		{
			$plugin->manage_plugin_prefs('add', 'plug_sc', $eplug_folder, $eplug_sc);
		}

		if (is_array($upgrade_remove_eplug_sc))
		{
			$plugin->manage_plugin_prefs('remove', 'plug_sc', $eplug_folder, $eplug_sc);
		}

		if (is_array($upgrade_add_eplug_bb))
		{
			$plugin->manage_plugin_prefs('add', 'plug_bb', $eplug_folder, $eplug_bb);
		}

		if (is_array($upgrade_remove_eplug_bb))
		{
			$plugin->manage_plugin_prefs('remove', 'plug_bb', $eplug_folder, $eplug_bb);
		}
*/
		if (is_array($upgrade_add_prefs))
		{
			$plugin->manage_prefs('add', $upgrade_add_prefs);
			$text .= EPL_ADLAN_8.'<br />';
		}

		if (is_array($upgrade_remove_prefs))
		{
			$plugin->manage_prefs('remove', $upgrade_remove_prefs);
		}

		if (is_array($upgrade_add_array_pref))
		{
			foreach($upgrade_add_array_pref as $key => $val)
			{
				$plugin->manage_plugin_prefs('add', $key, $eplug_folder, $val);
			}
		}

		if (is_array($upgrade_remove_array_pref))
		{
			foreach($upgrade_remove_array_pref as $key => $val)
			{
				$plugin->manage_plugin_prefs('remove', $key, $eplug_folder, $val);
			}
		}

		$plugin->manage_search('upgrade', $eplug_folder);
		$plugin->manage_notify('upgrade', $eplug_folder);

		$eplug_addons = $plugin -> getAddons($eplug_folder);

		$admin_log->log_event('PLUGMAN_02', $eplug_folder, E_LOG_INFORMATIVE, '');
		$text .= (isset($eplug_upgrade_done)) ? '<br />'.$eplug_upgrade_done : "<br />".LAN_UPGRADE_SUCCESSFUL;
		$sql->db_Update('plugin', "plugin_version ='{$eplug_version}', plugin_addons='{$eplug_addons}' WHERE plugin_id='$id' ");
		$pref['plug_installed'][$plug['plugin_path']] = $eplug_version; 			// Update the version
		save_prefs();
	}
	$ns->tablerender(EPL_ADLAN_34, $text);

	$plugin->save_addon_prefs();
}


if ($action == 'refresh')
{
	$plug = $plugin->getinfo($id);

	$_path = e_PLUGIN.$plug['plugin_path'].'/';
	if(file_exists($_path.'plugin.xml'))
	{
		$text .= $plugin->manage_plugin_xml($id, 'refresh');
		$admin_log->log_event('PLUGMAN_04', $id.':'.$plug['plugin_path'], E_LOG_INFORMATIVE, '');
	}
}


// Check for new plugins, create entry in plugin table ...

$plugin->update_plugins_table();

// ----------------------------------------------------------
//        render plugin information ...

/* plugin upload form */

if(!is_writable(e_PLUGIN))
{
	$ns->tablerender(EPL_ADLAN_40, EPL_ADLAN_44);
}
else
{
  // Get largest allowable file upload
  require_once(e_HANDLER.'upload_handler.php');
  $max_file_size = get_user_max_upload();

  $text = "<div style='text-align:center'>
	<form enctype='multipart/form-data' method='post' action='".e_SELF."'>
	<table style='".ADMIN_WIDTH."' class='fborder'>
	<tr>
	<td class='forumheader3' style='width: 50%;'>".EPL_ADLAN_37."</td>
	<td class='forumheader3' style='width: 50%;'>
	<input type='hidden' name='MAX_FILE_SIZE' value='{$max_file_size}' />
	<input type='hidden' name='ac' value='".md5(ADMINPWCHANGE)."' />
	<input class='tbox' type='file' name='file_userfile[]' size='50' />
	</td>
	</tr>
	<tr>
	<td colspan='2' style='text-align:center' class='forumheader'>
	<input class='button' type='submit' name='upload' value='".EPL_ADLAN_38."' />
	</td>
	</tr>
	</table>
	</form>
	<br />\n";
}
// Uninstall and Install sorting should be fixed once and for all now !
$installed = $plugin->getall(1);
$uninstalled = $plugin->getall(0);

$text .= "<table style='".ADMIN_WIDTH."' class='fborder'>";
$text .= "<tr><td class='fcaption' colspan='3'>".EPL_ADLAN_22."</td></tr>";
$text .= render_plugs($installed);
$text .= "<tr><td class='fcaption' colspan='3'>".EPL_ADLAN_23."</td></tr>";
$text .= render_plugs($uninstalled);


function render_plugs($pluginList)
{
	global $tp, $imode, $plugin;

	if (empty($pluginList)) return '';

	foreach($pluginList as $plug)
	{
		$_path = e_PLUGIN.$plug['plugin_path'].'/';
		$plug_vars = false;
//		if($plugin->parse_plugin($_path))
		if($plugin->parse_plugin($plug['plugin_path']))
		{
			$plug_vars = $plugin->plug_vars;
		}
		if($plug_vars)
		{

			if ($plug_vars['@attributes']['installRequired'])
			{
				$img = (!$plug['plugin_installflag'] ? "<img src='".e_IMAGE."packs/".$imode."/admin_images/uninstalled.png' alt='' />" : "<img src='".e_IMAGE."packs/".$imode."/admin_images/installed.png' alt='' />");
			}
			else
			{
				$img = "<img src='".e_IMAGE."packs/".$imode."/admin_images/noinstall.png' alt='' />";
			}

			if ($plug['plugin_version'] != $plug_vars['@attributes']['version'] && $plug['plugin_installflag'])
			{
				$img = "<img src='".e_IMAGE."packs/".$imode."/admin_images/upgrade.png' alt='' />";
			}

			$icon_src = (isset($plug_vars['plugin_php']) ? e_PLUGIN : $_path).$plug_vars['administration']['icon'];
			$plugin_icon = $plug_vars['administration']['icon'] ? "<img src='{$icon_src}' alt='' style='border:0px;vertical-align: bottom; width: 32px; height: 32px' />" : E_32_CAT_PLUG;

			if ($plug_vars['administration']['configFile'] && $plug['plugin_installflag'] == true)
			{
				$conf_title = LAN_CONFIGURE.' '.$tp->toHtml($plug_vars['@attributes']['name'], "", "defs,emotes_off, no_make_clickable");
				$plugin_icon = "<a title='{$conf_title}' href='".e_PLUGIN.$plug['plugin_path'].'/'.$plug_vars['administration']['configFile']."' >".$plugin_icon.'</a>';
			}

			$plugEmail = varset($plug_vars['author']['@attributes']['email'],'');
			$plugAuthor = varset($plug_vars['author']['@attributes']['name'],'');
			$plugURL = varset($plug_vars['author']['@attributes']['url'],'');
			$text .= "
			<tr>
			<td class='forumheader3' style='width:160px; text-align:center; vertical-align:top'>
			<table style='width:100%'><tr><td style='text-align:left;width:40px;vertical-align:top'>
			".$plugin_icon."
			</td><td>
			{$img} <b>".$tp->toHTML($plug['plugin_name'], false, "defs,emotes_off, no_make_clickable")."</b><br /><b>".EPL_ADLAN_11."</b>&nbsp;{$plug['plugin_version']}
			<br /><br />
			<b>".EPL_ADLAN_64."</b>&nbsp;".$plug['plugin_path']."
			<br />
			</td>
			</tr></table>
			</td>
			<td class='forumheader3' style='vertical-align:top'>
			<table cellspacing='3' style='width:98%'>
			<tr><td style='vertical-align:top;width:15%'><b>".EPL_ADLAN_12."</b>:</td>
				<td style='vertical-align:top'><a href='mailto:{$plugEmail}' title='{$plugEmail}'>{$plugAuthor}</a>&nbsp;";
			if($plugURL)
			{
				$text .= "&nbsp;&nbsp;[ <a href='{$plugURL}' title='{$plugURL}' >".EPL_WEBSITE."</a> ] ";
			}
			$text .="</td></tr>
			<tr><td style='vertical-align:top'><b>".EPL_ADLAN_14."</b>:</td><td style='vertical-align:top'> ".$tp->toHTML($plug_vars['description'], false, "defs,emotes_off, no_make_clickable")."&nbsp;";
			if ($plug_vars['readme'])
			{
				$text .= "[ <a href='".e_PLUGIN.$plug['plugin_path']."/".$plug_vars['readme']."'>".$plug_vars['readme']."</a> ]";
			}

			$text .="</td></tr>
			<tr><td style='vertical-align:top'><b>".EPL_ADLAN_13."</b>:</td><td style='vertical-align:top'><span style='vertical-align:top'> ".varset($plug_vars['@attributes']['compatibility'],'')."&nbsp;</span>";

			if ($plug_vars['compliant'])
			{
				$text .= "&nbsp;&nbsp;<img src='".e_IMAGE."generic/valid-xhtml11_small.png' alt='' style='margin-top:0px' />";
			}
			$text .="</td></tr>\n";

			$text .= "</table></td>";
			$text .= "<td class='forumheader3' style='width:70px;text-align:center'>";

			if ($plug_vars['@attributes']['installRequired'])
			{
				if ($plug['plugin_installflag'])
				{
					$text .= ($plug['plugin_installflag'] ? "<input type='button' class='button' onclick=\"location.href='".e_SELF."?uninstall.{$plug['plugin_id']}'\" title='".EPL_ADLAN_1."' value='".EPL_ADLAN_1."' /> " : "<input type='button' class='button' onclick=\"location.href='".e_SELF."?install.{$plug['plugin_id']}'\" title='".EPL_ADLAN_0."' value='".EPL_ADLAN_0."' />");
					if (PLUGIN_SHOW_REFRESH && !varsettrue($plug_vars['plugin_php']))
					{
						$text .= "<br /><br /><input type='button' class='button' onclick=\"location.href='".e_SELF."?refresh.{$plug['plugin_id']}'\" title='".'Refresh plugin settings'."' value='".'Refresh plugin settings'."' /> ";
					}
				}
				else
				{
					$text .=  "<input type='button' class='button' onclick=\"location.href='".e_SELF."?install.{$plug['plugin_id']}'\" title='".EPL_ADLAN_0."' value='".EPL_ADLAN_0."' />";
				}
			}
			else
			{
				if ($plug_vars['menuName'])
				{
					$text .= EPL_NOINSTALL.str_replace("..", "", e_PLUGIN.$plug['plugin_path'])."/ ".EPL_DIRECTORY;
				}
				else
				{
					$text .= EPL_NOINSTALL_1.str_replace("..", "", e_PLUGIN.$plug['plugin_path'])."/ ".EPL_DIRECTORY;
					if($plug['plugin_installflag'] == false)
					{
						global $sql;
						$sql->db_Delete('plugin', "plugin_installflag=0 AND (plugin_path='{$plug['plugin_path']}' OR plugin_path='{$plug['plugin_path']}/' )  ");
					}
				}
			}

			if ($plug['plugin_version'] != $plug_vars['@attributes']['version'] && $plug['plugin_installflag']) {
				$text .= "<br /><input type='button' class='button' onclick=\"location.href='".e_SELF."?upgrade.{$plug['plugin_id']}'\" title='".EPL_UPGRADE." to v".$plug_vars['@attributes']['version']."' value='".EPL_UPGRADE."' />";
			}

			$text .="</td>";
			$text .= "</tr>";
		}
	}
	return $text;
}

$text .= "</table>
<div style='text-align:center'><br />
<img src='".e_IMAGE."packs/".$imode."/admin_images/uninstalled.png' alt='' /> ".EPL_ADLAN_23."&nbsp;&nbsp;
<img src='".e_IMAGE."packs/".$imode."/admin_images/installed.png' alt='' /> ".EPL_ADLAN_22."&nbsp;&nbsp;
<img src='".e_IMAGE."packs/".$imode."/admin_images/upgrade.png' alt='' /> ".EPL_ADLAN_24."&nbsp;&nbsp;
<img src='".e_IMAGE."packs/".$imode."/admin_images/noinstall.png' alt='' /> ".EPL_ADLAN_25."</div></div>";

$ns->tablerender(EPL_ADLAN_16, $text);
// ----------------------------------------------------------

require_once("footer.php");
exit;

function show_uninstall_confirm($id)
{
	global $plugin, $tp, $ns;
	$plug = $plugin->getinfo($id);

	if ($plug['plugin_installflag'] == true )
	{
		if($plugin->parse_plugin($plug['plugin_path']))
		{
			$plug_vars = $plugin->plug_vars;
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
	$userclasses = '';
	$eufields = '';
	if (isset($plug_vars['userclass']))
	{
		if (isset($plug_vars['userclass']['@attributes']))
		{
			$plug_vars['userclass'][0]['@attributes'] = $plug_vars['userclass']['@attributes'];
			unset($plug_vars['userclass']['@attributes']);
		}
		$spacer = '';
		foreach ($plug_vars['userclass'] as $uc)
		{
			$userclasses .= $spacer.$uc['@attributes']['name'].' - '.$uc['@attributes']['description'];
			$spacer = '<br />';
		}
	}
	if (isset($plug_vars['extendedField']))
	{
		if (isset($plug_vars['extendedField']['@attributes']))
		{
			$plug_vars['extendedField'][0]['@attributes'] = $plug_vars['extendedField']['@attributes'];
			unset($plug_vars['extendedField']['@attributes']);
		}
		$spacer = '';
		foreach ($plug_vars['extendedField'] as $eu)
		{
			$eufields .= $spacer.'plugin_'.$plug_vars['folder'].'_'.$eu['@attributes']['name'];
			$spacer = '<br />';
		}
	}

	if(is_writable(e_PLUGIN.$plug['plugin_path']))
	{
		$del_text = "
		<select class='tbox' name='delete_files'>
		<option value='0'>".LAN_NO."</option>
		<option value='1'>".LAN_YES."</option>
		</select>
		";
	}
	else
	{
		$del_text = "
		".EPL_ADLAN_53."
		<input type='hidden' name='delete_files' value='0' />
		";
	}

	$text = "
	<form action='".e_SELF."?".e_QUERY."' method='post'>
	<table style='".ADMIN_WIDTH."' class='fborder'>
	<colgroup>
	<col style='width:75%' />
	<col style='width:25%' />
	</colgroup>
	<tr>
	<td colspan='2' class='forumheader'>".EPL_ADLAN_54." ".$tp->toHtml($plug_vars['name'], "", "defs,emotes_off, no_make_clickable")."</td>
	</tr>
	<tr>
	<td class='forumheader3'>".EPL_ADLAN_55."</td>
	<td class='forumheader3'>".LAN_YES."</td>
	</tr>
	<tr>
	<td class='forumheader3'>
	".EPL_ADLAN_57."<div class='smalltext'>".EPL_ADLAN_58."</div>
	</td>
	<td class='forumheader3'>
	<select class='tbox' name='delete_tables'>
	<option value='1'>".LAN_YES."</option>
	<option value='0'>".LAN_NO."</option>
	</select>
	</td>
	</tr>";
	
	if ($userclasses)
	{
		$text .= "	<tr>
		<td class='forumheader3'>
		".EPL_ADLAN_78."<div class='indent'>".$userclasses."</div><div class='smalltext'>".EPL_ADLAN_79."</div>
		</td>
		<td class='forumheader3'>
			<select class='tbox' name='delete_userclasses'>
			<option value='1'>".LAN_YES."</option>
			<option value='0'>".LAN_NO."</option>
			</select>
		</td>
		</tr>";
	}

	if ($eufields)
	{
		$text .= "	<tr>
		<td class='forumheader3'>
		".EPL_ADLAN_80."<div class='indent'>".$eufields."</div><div class='smalltext'>".EPL_ADLAN_79."</div>
		</td>
		<td class='forumheader3'>
			<select class='tbox' name='delete_xfields'>
			<option value='1'>".LAN_YES."</option>
			<option value='0'>".LAN_NO."</option>
			</select>
		</td>
		</tr>";
	}

	$text .="<tr>
	<td class='forumheader3'>".EPL_ADLAN_59."<div class='smalltext'>".EPL_ADLAN_60."</div></td>
	<td class='forumheader3'>{$del_text}</td>
	</tr>
	<tr>
	<td colspan='2' class='forumheader' style='text-align:center'><input class='button' type='submit' name='uninstall_confirm' value=\"".EPL_ADLAN_3."\" />&nbsp;&nbsp;<input class='button' type='submit' name='uninstall_cancel' value='".EPL_ADLAN_62."' onclick=\"location.href='".e_SELF."'; return false;\"/></td>
	</tr>
	</table>
	</form>
	";
	$ns->tablerender(EPL_ADLAN_63." ".$tp->toHtml($plug_vars['name'], "", "defs,emotes_off, no_make_clickable"), $text);
	require_once(e_ADMIN."footer.php");
	exit;
}

?>