<?php
/* TestLink Open Source Project - http://testlink.sourceforge.net/ */
/* $Id: installNewDB.php,v 1.19 2006/01/02 13:46:39 franciscom Exp $ */
/*
Parts of this file has been taken from:
Etomite Content Management System
Copyright 2003, 2004 Alexander Andrew Butter
*/

/*
@author Francisco Mancardi - 20050918
Found error upgrading from 1.0.4 to 1.6 on RH
due to case sensitive on table name. (USER)

@author Francisco Mancardi - 20050910
refactoring

@author Francisco Mancardi - 20050829
BUGID Mantis: 0000073: DB Creation fails with no message
wrong call to create_user_for_db()

@author Francisco Mancardi - 20050824
moved mysql version check here


*/

// 20051230 - fm
//require_once( dirname(__FILE__). '/../third_party/adodb/adodb.inc.php' );
require_once( dirname(__FILE__). '/../lib/functions/database.class.php' );
require_once("installUtils.php");
require_once("sqlParser.class.php");

session_start();
set_time_limit(180);
$inst_type = $_SESSION['installationType'];

define('LEN_PWD_TL_1_0_4',15);
define('ADD_DIR',1);

$sql_create_schema = array();
$sql_default_data = array();
$sql_update_schema = array();
$sql_update_data   = array();
$sql_create_schema[1] = 'sql/testlink_create_tables.sql';
$sql_default_data [1] = 'sql/testlink_create_default_data.sql';
$sql_schema = $sql_create_schema;
$sql_data   = $sql_default_data;
$msg_process_data = "</b><br />Importing StartUp data<b> ";
if ($inst_type == "upgrade" )
{
	$msg_process_data = "</b><br />Updating Database Contents<b> ";
  $sql_data   = array();
}
$the_title = "TestLink Install - " . $inst_type;
?>



<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" 
  "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<title><?php echo $the_title; ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <style type="text/css">
             @import url('./css/style.css');
			 
		 ul li { margin-top: 7px; }
        </style>
</head>	



<body>
<table border="0" cellpadding="0" cellspacing="0" class="mainTable">
  <tr class="fancyRow">
    <td><span class="headers">&nbsp;<img src="./img/dot.gif" alt="" style="margin-top: 1px;" />&nbsp;TestLink</span></td>
    <td align="right"><span class="headers">Installation - <?php echo $inst_type; ?> </span></td>
  </tr>
  <tr class="fancyRow2">
    <td colspan="2" class="border-top-bottom smallText" align="right">&nbsp;</td>
  </tr>
  <tr align="left" valign="top">
    <td colspan="2"><table width="100%"  border="0" cellspacing="0" cellpadding="1">
      <tr align="left" valign="top">
        <td class="pad" id="content" colspan="2">

<?php
if(!isset($_POST['licenseOK']) || empty($_POST['licenseOK'])) 
{
	echo "You need to agree to the license before proceeding with the setup!";
	close_html_and_exit();
}	
?>
TestLink setup will now attempt to setup the database:<br />

<?php

$update_pwd=0;
$create = false;
$errors = 0;

// get db info from session
$db_server     = $_SESSION['databasehost'];
$db_admin_name = $_SESSION['databaseloginname'];
$db_admin_pass = $_SESSION['databaseloginpassword'];
$db_name       = $_SESSION['databasename'];
$db_type       = $_SESSION['databasetype'];
$tl_db_login   = $_SESSION['tl_loginname'];
$tl_db_passwd  = $_SESSION['tl_loginpassword'];

// $table_prefix = $_SESSION['tableprefix'];
$table_prefix ='';

// 20050731 - fm
//$adminname = $_SESSION['cmsadmin'];
//$adminpass = $_SESSION['cmspassword'];
$adminname = '';
$adminpass = '';


// do some database checks
echo "</b><br />Creating connection to Database Server:<b> ";

// ------------------------------------------------------------------------------------------------
// Connect to DB Server without choosing an specific database
$db = new database($db_type);
define('NO_DSN',FALSE);
@$conn_result = $db->connect(NO_DSN,$db_server, $db_admin_name, $db_admin_pass); 

if( $conn_result['status'] == 0 ) 
{
	echo '<span class="notok">Failed!</span><p />Please check the database login details and try again.';
	echo '<br>Database Error Message: ' . $db->error_msg() . "<br>";
	
	close_html_and_exit();
} 
else 
{
	echo "<span class='ok'>OK!</span><p />";
}
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
// 20050824 - fm
// Succesful Connection, now try to check Database Version
//echo "</b><br />Checking Database version:<b> ";
$check=check_db_version($db);
if($check['errors'] > 0) 
{
	echo '<span class="notok">' . $check['msg'] .'</span><p />';
	close_html_and_exit();
}
else
{
	//echo "<span class='ok'>OK!", $check['msg'], "</span><p />" ;
	echo "<span class='ok'>", $check['msg'], "</span><p />" ;
}	 
$db->close();
$db=null;
// ------------------------------------------------------------------------------------------------


// ------------------------------------------------------------------------------------------------
// Connect to the Database (if Succesful -> database exists)
$db = new database($db_type);
@$conn_result = $db->connect(NO_DSN,$db_server, $db_admin_name, $db_admin_pass,$db_name); 
//echo "<pre>debug"; print_r($conn_result); echo "</pre>";

if( $conn_result['status'] == 0 ) 
{
	$db->close();
  echo "</b><br>Database $db_name does not exist. <br>";
	
	if( $inst_type == "upgrade" )
	{
		echo "Can't Upgrade";
		close_html_and_exit();     

		$errors += 1;
	}
	else
	{
	 echo "Will attempt to create:";
	 $create = true;
	}	
	
} 
else 
{
  echo "</b><br />Connecting to database `" . $db_name . "`:<b> ";
	echo "<span class='ok'>OK!</span>";
}
// ------------------------------------------------------------------------------------------------


// ------------------------------------------------------------------------------------------------
if($create) 
{
  $db = New database($db_type);
  @$conn_result=$db->connect(NO_DSN,$db_server, $db_admin_name, $db_admin_pass);
  echo "</b><br />Creating database `" . $db_name . "`:<b> ";
  $sql_create = "CREATE DATABASE " . $db_name . " CHARACTER SET utf8 "; 
  
	if(!$db->exec_query($sql_create)) 
	{
		echo "<span class='notok'>Failed!</span></b> - Could not create database: $db! " .
			   $db->error_msg();
		$errors += 1;
		
		echo "<p> TestLink setup could not create the database, " .
		     "and no existing database with the same name was found. <br />" .
		     "Please create a database by different way (e.g. from command line)," . 
			 " or with different DB root account. Run setup again then.";
		close_html_and_exit();     
	} 
	else 
	{
		echo "<span class='ok'>OK!</span>";
	}
}

// 20050806 - fm
// in upgrade mode we detect the lenght of user password field
// to identify a version with uncrypted passwords
if ($inst_type == "upgrade" )
{
  $my_ado=$db->get_dbmgr_object();
  $the_cols=$my_ado->MetaColumns('user');
  $pwd_field_len =$the_cols['PASSWORD']->max_length;
  if ( $pwd_field_len == LEN_PWD_TL_1_0_4 )
  {
    $update_pwd=1;
    echo "<br>You are upgrading from a TL pre 1.5" .
         "<br>user's password will be crypted using MD5"; 	
  }
}
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
// 20050908 - fm
if ( $inst_type == "upgrade") 
{
  if ($update_pwd)
  {
  	$sql_upd_dir = 'sql/alter_tables/1.0.4_to_1.6/';
  }
  else
  {
    $the_version_table=$my_ado->MetaTables('TABLES',false,'db_version');
    if( count($the_version_table) == 0 )
    {
      // We are upgrading from a pre 1.6 version
  	  $sql_upd_dir = 'sql/alter_tables/1.5_to_1.6/';
    }
    else
    {
      // 20050927 - fm
      // try to know what db version is installed
      $sql = "SELECT * FROM db_version ORDER BY upgrade_date DESC LIMIT 1";
      $res = $db->exec_query($sql);  
      if (!$res)
      {
       echo "Database ERROR:" . $db->error_msg();
       exit(); 
      }

      $myrow = $db->fetch_array($res);
      switch (trim($myrow['version']))
      {
      	case '1.6':
      	break;
      	
        case '1.6 BETA 1':
        $sql_upd_dir = 'sql/alter_tables/1.6/';
        break;
       
        default:
        echo "<br>Upgrade not need from version " . trim($myrow['version']) . "<br>";
        echo "Just open your Browser and login to TestLink <br>";
        echo "<br>bye!";
        exit(); 
        break;
      }
    }
  }

  //
  $sql_schema = getDirFiles($sql_upd_dir,ADD_DIR);
}
// ------------------------------------------------------------------------------------------------




// ------------------------------------------------------------------------------------------------
// Now proceed with user checks and user creation (if needed)
//
// 20051217 - fm
// refactoring due to minor errors 
//
// 20050910 - fm
// Added support for different types of architecture/installations:
// 
// webserver and dbserver on same machines      => user will be created as user
// webserver and dbserver on DIFFERENT machines => user must be created as user@webserver
//  
// if @ in tl_db_login (username) -> get the hostname using splitting, and use it
//                                   during user creation on db. 
//
// 20051217 - fm
$user_host = explode('@',$tl_db_login);
$system_schema = new database($db_type);
$conn_res = $system_schema->connect(NO_DSN, $db_server, $db_admin_name, $db_admin_pass, 'mysql'); 

//echo "<pre>debug"; print_r($conn_res); echo "</pre>";


$msg = create_user_for_db($system_schema, $db_name, $tl_db_login, $tl_db_passwd);
echo "</b><br />Creating Testlink DB user `" . $user_host[0] . "`:<b> ";

if ( strpos($msg,'ok -') === FALSE )
{
		echo "<span class='notok'>Failed!</span></b> - Could not create user: $tl_db_login!";
		$errors += 1;
}
else
{
		echo "<span class='ok'>OK! ($msg) </span>";
}
$system_schema->close();
$system_schema=null;
// ------------------------------------------------------------------------------------------------

// Schema Operations (CREATE, ALTER, ecc).
// Important: Do it as tl_login NOT as db_admin
//
//echo "<pre>debug"; print_r($db); echo "</pre>";
$db->close();
$db=null;

$db = new database($db_type);
@$conn_result = $db->connect(NO_DSN, $db_server, $db_admin_name, $db_admin_pass, $db_name); 

$sqlParser = new SqlParser($db);

foreach ($sql_schema as $sql_file) 
{
	echo "<br>Processing:" . $sql_file;
	$sqlParser->process($sql_file);
}
echo "<br>";

/*
echo "<pre>";
foreach ($sqlParser->mysqlErrors as $v)
 echo $v . "<br>";
echo "</pre>";
*/


// 20050804 - fm
// Data Operations
if ( count($sql_data > 0) )
{
	echo $msg_process_data;
  foreach ($sql_data as $sql_file) 
  {
	  $sqlParser->process($sql_file);
  }
}

// 20050806 - fm
if ($update_pwd)
{
	echo "Password Conversion ...";
	
	// @author Francisco Mancardi - 20050918
  // Found error upgrading from 1.0.4 to 1.6 on RH
  // due to case sensitive on table name. (USER)

	$user_pwd = "UPDATE user SET password=MD5(password)";
	$result = $db->exec_query($user_pwd);
}


if($sqlParser->install_failed==true) 
{

	echo "<span class='notok'>Failed!</span></b> - Installation failed!";
	$errors += 1;

  echo "<p />" .
       "TestLink setup couldn't install the default site into the selected database. " .
       "The last error to occur was <i>" . $sqlParser->sql_errors[count($sqlParser->sql_errors)-1]["error"] .
       '</i> during the execution of SQL statement <span class="mono">' .
       strip_tags($sqlParser->sql_errors[count($sqlParser->sql_errors)-1]["sql"]). "</span>";
       
	close_html_and_exit();     
} 
else 
{
	echo "<span class='ok'>OK!</span>";
}

// -----------------------------------------------------------------------------
echo "</b><br />Writing configuration file:<b> ";
$data['db_host']=$db_server;

// 20051217 - fm - BUGID 
$data['db_login'] = $user_host[0];
$data['db_passwd'] = $tl_db_passwd;
$data['db_name'] = $db_name;
$data['db_type'] = $db_type;

$cfg_file = "../config_db.inc.php";
$yy = write_config_db($cfg_file,$data);
// -----------------------------------------------------------------------------


if(strcasecmp('ko', $yy['status']) == 0)
{
	echo "<span class='notok'>Failed!</span></b>";
	$errors += 1;

  echo "<p />" .
  "TestLink couldn't write the config file. Please copy the following into the " .
  '<span class="mono"> ' . $cfg_file . '</span> file:<br />' .
  '<textarea style="width:400px; height:160px;">' . $yy['cfg_string'] . "</textarea>";

  echo "Once that's been done, you can log into TestLink by pointing your browser at your TestLink site.";

	close_html_and_exit();     
} 
else 
{
	echo "<span class='ok'>OK!</span>";
}

echo "</b><p />" . 'Installation was successful! You can now log into the <a href="../index.php">TestLink (Please Click Me!)</a>.';
$db->close();
close_html_and_exit();     

?>

<?php
// -----------------------------------------------------------
// 20051217 - fm - BUGID 
// 20050910 - fm
function write_config_db($filename, $data)
{

$ret = array('status'     => 'ok',
             'cfg_string' => '');

               
               
$db_host  = $data['db_host'];
$db_login = $data['db_login'];

// 20051217 - fm - BUGID 
// 20050910 - fm
// if @ present in db_login, explode an take user name WITHOUT HOST
$user_host = explode('@',$db_login);

if (count($user_host) > 1 )
{
  $db_login = $user_host[0];    
}

$db_passwd = $data['db_passwd'];
$db_name = $data['db_name'];
$db_type = $data['db_type'];

// write config.inc.php
$configString = "<?php" . "\n" . "// Automatically Generated by TestLink Installer\n";
$configString .= "define('DB_TYPE', '" . $db_type . "');\n";
$configString .= "define('DB_USER', '" . $db_login . "');\n";
$configString .= "define('DB_PASS', '" . $db_passwd . "');\n";
$configString .= "define('DB_HOST', '" . $db_host . "');\n";
$configString .= "define('DB_NAME', '" . $db_name . "');\n";
$configString .= "?>";


if (@!$handle = fopen($filename, 'w')) {
	$ret['status'] = 'ko';
}

// Write $somecontent to our opened file.
if (@fwrite($handle, $configString) === FALSE) {
	$ret['status'] = 'ko';
}
@fclose($handle);	

$ret['cfg_string'] = $configString;

return($ret);

}  //function end
// --------------------------------------------------------------------------

?>
