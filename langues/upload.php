<?php

define("IS_UPLOAD", true);
include("secrets.php");

function check_login($username,$password)
{
	# Yes, plaintext, but who cares for this one?
	global $_username, $_password;
	if ( $username == $_username && $password == $_password )
		return true;
	else
		return false;
}

function display_login_prompt()
{
	$login_form = '<form method="post" action="upload.php">
		<label for="username">Username:</label><input type="text" id="username" name="username"> <br>
<label for="password">Password:</label><input type="password" id="password" name="password"> <br>
<input type="submit" value="Log In">
</form>';
	echo($login_form);

}

function display_upload_prompt()
{
	//$date = date();
	//$week = $date->format("W")
	$week = date('W');
	$next_week = date('W',strtotime("+1 week"));
	$prompt='
<form action="upload.php" method="post" enctype="multipart/form-data">
    Upload current planning (for week <b>'.$week.'</b>):
    <input type="file" name="planning_current" id="planning_current">
    <input type="submit" value="Upload planning for week '.$week.'" name="submit">
</form>
<form action="upload.php" method="post" enctype="multipart/form-data">
    Upload next planning (for week <b>'.$next_week.'</b>):
    <input type="file" name="planning_next" id="planning_next">
    <input type="submit" value="Upload planning for week '.$next_week.'" name="submit">
</form>';
	$logout = '<a href="upload.php?logout">Click here to log out</a>';
	echo($prompt);
	echo($logout);
}

function put_auth_cookie($username)
{
	global $_secret_word;
	setcookie('login', $username.','.md5($username.$_secret_word));
}

function remove_auth_cookie()
{
	setcookie('login', '', 1);
}

function check_cookie($cookie)
{
	global $_secret_word;
	list($c_username,$cookie_hash) = explode(',', $cookie);
	if (md5($c_username.$_secret_word) == $cookie_hash)
	{
	        return true;
	}
	else
	{
		print "You have sent a bad cookie.";
		return false;
	}
}

function convert_to_html($target_file)
{
	#Note: we suppose there's no race condition here
	$mount_folder = "tmp/";
	copy($target_file, $mount_folder.'in.xlsx');
	echo(shell_exec("/home/docker-proxy-launcher/docker-prestage"));
	if (strpos($target_file, 'next') != false)
	{
		$destination = "next-index.html";
	}
	else
	{
		$destination = "index.html";
	}
	copy($mount_folder.'out.html',$destination);
	echo('<br />Updated file: <a href="'.$destination.'">'.$destination.'</a><br />');
	unlink($mount_folder.'in.xlsx');
}

$auth = false;

if (isset($_GET['logout']))
{
	if(isset($_COOKIE['login']))
	{
		remove_auth_cookie();
		echo("Successfully logged out");
		exit();
	}
	else
	{
		$newURL = "https://planning.galileo-cpe.net";
		header('Location: '.$newURL);
	}
}

if (isset($_POST['username']) && isset($_POST['password']))
{
	$auth = check_login($_POST['username'], $_POST['password']);
	if ($auth == true)
	{
		put_auth_cookie($_POST['username']);
		echo('authentication successful');
	}
	else
	{
		# TODO: put some rate-limitter to reduce bruteforce attempts
		echo('Authentication failed, please try again');
	}
}

if (isset($_COOKIE['login'])) {
	$auth = check_cookie($_COOKIE['login']);
}

if (! $auth)
{
	display_login_prompt();
	exit();
}

# From now on, we are authenticated

if (isset($_FILES["planning_current"]) || isset($_FILES["planning_next"]))
{
	if (isset($_FILES["planning_current"]))
		$fname = "planning_current";
	else # well, it's the other
		$fname = "planning_next";

	if($_FILES[$fname]["error"] != 0)
        {
                echo("An error occured during the upload (error: ".$_FILES[$fname]["error"].")<br />");
        }
        else
        {
		$target_dir = "uploads/";
		$target_file = $target_dir.$fname.".xlsx";
		move_uploaded_file($_FILES[$fname]["tmp_name"], $target_file);
		echo("File successfully uploaded");

		convert_to_html($target_file);
	}
}

display_upload_prompt();
