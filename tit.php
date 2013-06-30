<?php
/*
 *      Tiny Issue Tracker (TIT) v0.1
 * 		SQLite based, single file Issue Tracker
 * 
 *      Copyright 2010 Jwalanta Shrestha <jwalanta at gmail dot com>
 * 		GNU GPL
 *
 *	-------------------------------------------------------------------
 *
 *		Updated 07 May 2012
 *
 *		Tiny Issue Tracker (TIT) v0.3
 *		SQLite based, single file Issue Tracker
 * 
 *		Copyright 2011 Codefuture.co.uk
 *		GNU GPL
 */
 

///////////////////
// CONFIGURATION //
///////////////////

	$TITLE = "My Project"; // Project Title
	$EMAIL = "noreply@example.com";	// "From" email address for notifications
	$SCRIPTADDRESS = "http://www.example.com/tit.php"; // The URL to the script

// Issue file attachment settings
// Note: Set $MAXUPLOADS to 0 to trun off attachments
//       If the $UPLOADFOLDER folder doesn't exist, a new one will be created.
//       Make sure the folder is writable
	$MAXUPLOADS = 1; // The number of files that can be uploaded at once
	$UPLOADSIZE = 3; // Maximum upload file size ( in MB)
	$UPLOADFOLDER = "./uploads/"; // the folder where uploaded file will be save
	$ALLOWEDMIMES = array("image", "text");
	$ALLOWEDTYPES = array("txt", "php", "html", "htm","js" ,"jpg","png" ,"gif");

// Array of users. Format: array("username","md5_password","email","user level")
// Note: "user level" 1 users has special powers to edit & delete issues, comments & attachments
	$USERS = array(
				array("admin",md5("admin"),"admin@example.com",1),
				array("user",md5("user"),"user@example.com",3),
				array("user2",md5("user2"),"user2@example.com",3)
					);

// Location of SQLITE db file
// Note: If the file doesn't exist, a new one will be created.
//       Make sure the folder is writable
	$SQLITE = "tit.db";


// Select which notifications to send 
	$NOTIFY["ISSUE_CREATE"] 	= TRUE;	// issue created
	$NOTIFY["ISSUE_EDIT"] 		= TRUE;	// issue edited
	$NOTIFY["ISSUE_DELETE"] 	= TRUE;	// issue deleted
	$NOTIFY["ISSUE_STATUS"] 	= TRUE;	// issue status change (solved / unsolved)
	$NOTIFY["COMMENT_CREATE"] 	= TRUE;	// comment post


///////////////////////////////////////////////////////////////////////
////// DO NOT EDIT BEYOND THIS IF YOU DONT KNOW WHAT YOU'RE DOING /////
///////////////////////////////////////////////////////////////////////

// Here we go...
	session_start();

//////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////

// check for login post
	if (isset($_POST["login"])){
		$n = check_credentials($_POST["u"],md5($_POST["p"]));
		if ($n>=0){
			$_SESSION['u']=$USERS[$n][0]; // username
			$_SESSION['p']=$USERS[$n][1]; // password
			$_SESSION['e']=$USERS[$n][2]; // email
			$_SESSION['l']=$USERS[$n][3]; // user level

			header("Location: {$SCRIPTADDRESS}");
		}
		else header("Location: {$SCRIPTADDRESS}?loginerror");
	}

// check for logout 
	if (isset($_GET['logout'])){
		$_SESSION['u']=''; // username
		$_SESSION['p']=''; // password
		$_SESSION['e']=''; // email
		$_SESSION['l']=''; // user level
		
		header("Location: {$SCRIPTADDRESS}");
	}


// show login page on bad credential
	if (!isset($_SESSION['u']) || !isset($_SESSION['p']) || check_credentials($_SESSION['u'], $_SESSION['p'])==-1){
		$message = isset($_GET['loginerror'])? "<span class='error'>Invalid username or password</span>":"";
		?>
<html>
	<head>
		<title>Tiny Issue Tracker</title>
		<style>
			body{font-family: sans-serif; font-size: 11px; background-color: #f5f5f5;}
			a, a:visited{color:#004989; text-decoration:none;}
			a:hover{color: #333;}
			#container{border-radius: 2px;box-shadow: 0 2px 4px rgba(0,0,0,0.4);width: 300px; margin: 0 auto; padding: 10px; background-color: #fff;}
			#menu{border-radius: 2px;background:#5C6E83;padding: 2px 10px;text-align: right;}
			#menu h1{color: #FFF;margin: 0;text-align: left;}
			#footer{padding: 10px 0 0;text-align: center;text-shadow: 1px 1px 0 #DDD;}
			select,input{border-radius:2px;background:#fff;border: 1px solid #ddd;color: #888;font-size: 16px;margin: 0 0 15px;padding: 5px;width: 240px;}
			form{display: block;margin: 20px auto;width: 250px;}
			.button{background:#A5B9CF;border:none;color: #333;font-size: 15px;margin: 0;text-transform: uppercase;text-shadow: rgba(255,255,255,0.4) 0 1px 0;}
			.button:hover{background: #3C454F;color: #eee;}
			span.error{color: red;display: block;font-size: 16px;margin: 5px 0 10px;text-align: center;}
		</style>
	</head>
	<body>
		<div id="container">
			<div id="menu">
				<h1><?php echo $TITLE;?></h1>
			</div>
			<?php echo $message;?>
			<form method="POST">
				<input type="text" name="u" placeholder="Username" autocomplete="off"/>
				<input type="password" name="p" placeholder="Password" autocomplete="off" />
				<input class="button" type="submit" name="login" value="Login" />
			</form>
		</div>
		<div id="footer">
			Powered by <a href="https://github.com/codefuture/tit" alt="Tiny Issue Tracker" target="_blank">Tiny Issue Tracker</a>
		</div>
	</body>
</html>
		<?
		exit;
	}

//////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////

// Check if db exists
	if (!($db = sqlite_open($SQLITE, 0666, $sqliteerror))) die($sqliteerror);

// create tables if not exist
	@sqlite_query($db, 'CREATE TABLE IF NOT EXISTS issues (id INTEGER PRIMARY KEY, title TEXT, description TEXT, user TEXT, status INTEGER, priority INTEGER, notify_emails INTEGER, entrytime DATETIME)');
	@sqlite_query($db, 'CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY, issue_id INTEGER, user TEXT, description TEXT, entrytime DATETIME)');
	@sqlite_query($db, 'CREATE TABLE IF NOT EXISTS attachments (id INTEGER PRIMARY KEY, issue_id INTEGER, user TEXT, filename TEXT, filepath TEXT,filesize INTEGER, mimetype TEXT, entrytime DATETIME)');

	$priorityArr = array('not used','High','Medium','Low'); // used in notifications

//////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////


// find issue if id is set
	if (isset($_GET["id"])){
		$id			 = sqlite_escape_string($_GET['id']);
		$issue		 = sqlite_array_query($db, "SELECT id, title, description, user, status, priority, notify_emails, entrytime FROM issues WHERE id='$id'");
		$comments	 = sqlite_array_query($db, "SELECT id, user, description, entrytime FROM comments WHERE issue_id='$id' ORDER BY entrytime ASC");
		$attachments = sqlite_array_query($db, "SELECT id, issue_id, user, filename, filepath, mimetype, entrytime FROM attachments WHERE issue_id='$id'");
	}

// show new issue page
	if(isset($_GET['new'])){
		$mode="new";
	}
// show issue index list resolved/Active
	elseif (!isset($issue) || count($issue)==0){// if no issue found, go to list mode

		unset($issue, $comments, $attachments);
		// show all issues

		if (isset($_GET["resolved"])) 
			$issues_raw = sqlite_array_query($db, "SELECT id, title, user, status, priority, notify_emails, entrytime FROM issues WHERE status=1 ORDER BY priority, entrytime DESC");
		else 
			$issues_raw = sqlite_array_query($db, "SELECT id, title, user, status, priority, notify_emails, entrytime FROM issues WHERE (status=0 OR status IS NULL) ORDER BY priority, entrytime DESC");

		if(!empty($issues_raw)){
			foreach ($issues_raw as $k=>$v){
				$issues[$v['id']] = $v;
				$issues[$v['id']]['comments'] = 0;
				$issues[$v['id']]['attachment'] = 0;
				$issue_ids = (isset($issue_ids)?$issue_ids.',':'').$v['id'];
			}

			$countComments = sqlite_array_query($db, "SELECT count(*) count, issue_id FROM comments WHERE issue_id IN ({$issue_ids}) group by issue_id ");
			foreach($countComments as $countComment){
				$issues[$countComment["issue_id"]]['comments'] = $countComment["count"];
			}

			if($MAXUPLOADS){
				$attachments_raw = sqlite_array_query($db, "SELECT count(*) files, issue_id FROM attachments WHERE issue_id IN ({$issue_ids}) group by issue_id ");
				foreach($attachments_raw as $attachment_row){
					$issues[$attachment_row["issue_id"]]['attachment'] = $attachment_row["files"];
				}
			}

		}else{
			$issues = array();
		}

		$mode="list";
	}
// show issue page
	else {
		$issue = $issue[0];
		$mode = "issue";
	}

//
// PROCESS ACTIONS
//

// Create / Edit issue
if (isset($_POST["createissue"])){

	$id    = sqlite_escape_string((isset($_POST['id'])?$_POST['id']:null));
	$title = sqlite_escape_string($_POST['title']);
	$description = sqlite_escape_string($_POST['description']);
	$priority = sqlite_escape_string($_POST['priority']);

	$user = $_SESSION['u'];
	$now  = date("Y-m-d H:i:s");

// gather all emails
	$emails = array();
	for ($i=0;$i<count($USERS);$i++){
		if ($USERS[$i][2]!='') $emails[] = $USERS[$i][2];
	}
	$notify_emails = implode(",",$emails);

	if (is_null($id) || empty($id))
		$query = "INSERT INTO issues (title, description, user, priority, notify_emails, entrytime) values('$title','$description','$user','$priority','$notify_emails','$now')"; // create
	else
		$query = "UPDATE issues SET title='$title', description='$description', priority='$priority' WHERE id='$id'"; // edit

// title cant be blank
	if (trim($title)!='') {
		@sqlite_query($db, $query);
		if ($id==''){
		// created
			$id=sqlite_last_insert_rowid($db);
			if ($NOTIFY["ISSUE_CREATE"]) 
				notify(	$id,
						"[$TITLE] New Issue Created",
						"New Issue Created by {$_SESSION['u']}\r\nTitle: $title\r\nPriority: {$priorityArr[$priority]}\r\nURL: http://{$SCRIPTADDRESS}?id=$id");
		}
		else{
		// edited
			if ($NOTIFY["ISSUE_EDIT"]) 
				notify(	$id,
						"[$TITLE] Issue Edited",
						"Issue edited by {$_SESSION['u']}\r\nTitle: $title\r\nPriority: {$priorityArr[$priority]}\r\nURL: http://{$SCRIPTADDRESS}?id=$id");
		}
	}

// uploaded files 
	if ($MAXUPLOADS && isset($_FILES["uploaded"]['name'][0]) && !empty($_FILES["uploaded"]['name'][0])){
		$ALLOWEDTYPES = array_flip($ALLOWEDTYPES);
		$ALLOWEDMIMES = array_flip($ALLOWEDMIMES);
	// check upload dir is there if not make one
		if(!is_dir($UPLOADFOLDER)) mkdir($UPLOADFOLDER);

	 // Attachments
		$attachments = $_FILES["uploaded"];
		foreach($attachments["name"] as $attachment_no => $attachment_filename){
			if($attachments["error"][$attachment_no] > 0){
				continue;
			}
		// check the mime type
			$attachment_mime_type = explode("/", $attachments["type"][$attachment_no]);
			$attachment_mime_type = strtolower($attachment_mime_type[0]);
			if(!isset($ALLOWEDMIMES[$attachment_mime_type])){
				// skip the attachment, and mark it as invalid. 
				continue;
			}
		// check file extension
			$attachment_ext = pathinfo($attachments["name"][$attachment_no] ,PATHINFO_EXTENSION);
			if(!isset($ALLOWEDTYPES[$attachment_ext])){
				// skip the attachment, and mark it as invalid. 
				continue;
			}
			
		// check file size
			$attachment_filesize = filesize($attachments["tmp_name"][$attachment_no]);
			if(($attachment_filesize>=$UPLOADSIZE*1048576)){
				// skip the attachment, and mark it as invalid. 
				continue;
			}
			
			$attachment_newfilename = md5(time().$attachments["tmp_name"][$attachment_no].$attachments["name"][$attachment_no]);
			if(@move_uploaded_file( $attachments["tmp_name"][$attachment_no] , $UPLOADFOLDER. $attachment_newfilename )){
				$attachment_filename = sqlite_escape_string($attachments["name"][$attachment_no]);
				$attachment_mimetype = sqlite_escape_string($attachments["type"][$attachment_no]);

				$attachment_query = "INSERT INTO attachments (issue_id, user, filename, filepath,filesize, mimetype, entrytime )
						values('$id', '$user','$attachment_filename','$attachment_newfilename','$attachment_filesize','$attachment_mimetype', '$now')"; // create
				sqlite_query($db, $attachment_query);
			}else{
				// error moving uploaded file
			}
		}
	}

	header("Location: {$SCRIPTADDRESS}?id=$id");
}

// Delete file (attachments)
if (isset($_GET["deletefile"])){
	$id=sqlite_escape_string($_GET['id']);
	$file_id=sqlite_escape_string($_GET["deletefile"]);
	$title=get_col($id,"attachments","filename");

	// only the issue creator or admin can delete issue
	if ($_SESSION['l']===1 || $_SESSION['u']==get_col($id,"attachments","user")){
		@sqlite_query($db, "DELETE FROM attachments WHERE id='$file_id'");
		@unlink($UPLOADFOLDER.get_col($id,"attachments","filepath"));
	}
	header("Location: {$SCRIPTADDRESS}?id=$id");
}

// Delete issue
if (isset($_GET["deleteissue"])){
	$id=sqlite_escape_string($_GET['id']);
	$title=get_col($id,"issues","title");

	// only the issue creator or admin can delete issue
	if ($_SESSION['l']===1 || $_SESSION['u']==get_col($id,"issues","user")){
		@sqlite_query($db, "DELETE FROM issues WHERE id='$id'");
		@sqlite_query($db, "DELETE FROM comments WHERE issue_id='$id'");
	// remove attachments
		if($att = sqlite_array_query($db, "SELECT * FROM attachments WHERE issue_id='$id'")){
			foreach($att as $att_row){
				@unlink($UPLOADFOLDER.$att_row["filename"]);
			}
			@sqlite_query($db, "DELETE FROM attachments WHERE issue_id='$id'");  
		}
		
		if ($NOTIFY["ISSUE_DELETE"]) 
			notify(	$id,
					"[$TITLE] Issue Deleted",
					"Issue deleted by {$_SESSION['u']}\r\nTitle: $title");
	}
	header("Location: {$SCRIPTADDRESS}");

}

// Mark as solved
if (isset($_POST["marksolved"])){
	$id=sqlite_escape_string($_POST['id']);
	@sqlite_query($db, "UPDATE issues SET status='1' WHERE id='$id'");

	if ($NOTIFY["ISSUE_STATUS"]) 
		notify(	$id,
				"[$TITLE] Issue Marked as Solved",
				"Issue marked as solved by {$_SESSION['u']}\r\nTitle: ".get_col($id,"issues","title")."\r\nURL: http://{$SCRIPTADDRESS}?id=$id");

	header("Location: {$SCRIPTADDRESS}");
}

// Mark as unsolved
if (isset($_POST["markunsolved"])){
	$id=sqlite_escape_string($_POST['id']);
	@sqlite_query($db, "UPDATE issues SET status='0' WHERE id='$id'");

	if ($NOTIFY["ISSUE_STATUS"]) 
		notify(	$id,
				"[$TITLE] Issue Marked as Unsolved",
				"Issue marked as unsolved by {$_SESSION['u']}\r\nTitle: ".get_col($id,"issues","title")."\r\nPriority: {$priorityArr[$priority]}\r\nURL: http://{$SCRIPTADDRESS}?id=$id");

	header("Location: {$SCRIPTADDRESS}");
}

// Unwatch
if (isset($_POST["unwatch"])){
	$id=sqlite_escape_string($_POST['id']);
	unwatch($id);	// remove from watch list
	header("Location: {$SCRIPTADDRESS}?id=$id");
}

// Watch
if (isset($_POST["watch"])){
	$id=sqlite_escape_string($_POST['id']);
	watch($id);		// add to watch list
	header("Location: {$SCRIPTADDRESS}?id=$id");
}

// Create Comment
if (isset($_POST["createcomment"])){

	$issue_id=sqlite_escape_string($_POST['issue_id']);
	$description=sqlite_escape_string($_POST['description']);
	$user=$_SESSION['u'];
	$now=date("Y-m-d H:i:s");

	if (trim($description)!=''){
		$query = "INSERT INTO comments (issue_id, description, user, entrytime) values('$issue_id','$description','$user','$now')"; // create
		sqlite_query($db, $query);
	}

	if ($NOTIFY["COMMENT_CREATE"]) 
		notify(	$id,
				"[$TITLE] New Comment Posted",
				"New comment posted by {$_SESSION['u']}\r\nTitle: ".get_col($issue_id,"issues","title")."\r\nPriority: ".$priorityArr[get_col($issue_id,"issues","priority")]."\r\nURL: http://{$SCRIPTADDRESS}?id=$issue_id");

	header("Location: {$SCRIPTADDRESS}?id=$issue_id");
}

// Delete Comment
if (isset($_GET["deletecomment"])){
	$id=sqlite_escape_string($_GET['id']);
	$cid=sqlite_escape_string($_GET['cid']);

	// only comment poster or admin can delete comment
	if ($_SESSION['l']===1 || $_SESSION['u']==get_col($cid,"comments","user"))	
		sqlite_query($db, "DELETE FROM comments WHERE id='$cid'");

	header("Location: {$SCRIPTADDRESS}?id=$id");
}

// Download File
if (isset($_GET["file"])){
	$file_requested = sqlite_escape_string($_GET["file"]);
	if(!$file_information = sqlite_array_query($db, "SELECT * from attachments WHERE filepath = '{$file_requested}' LIMIT 1")){
		die("File Not Found");
	}else{        
		$file_information = $file_information[0];
		$file_mimetype = $file_information["mimetype"];
		header('Content-Description: File Transfer');
		header('Content-Type: '.$file_mimetype);
		header('Content-Disposition: attachment;filename="'.$file_information['filename'].'"');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: '.$file_information['filesize']);
		ob_clean();
		flush();
		readfile($UPLOADFOLDER.$file_information["filepath"]);
		exit;
	}
}


//
// 	FUNCTIONS 
//

// check credentials, returns -1 if not okay
function check_credentials($u, $p){
	global $USERS;

	$n=0;
	foreach ($USERS as $user){
		if ($user[0]==$u && $user[1]==$p) return $n;
		$n++;
	}
	return -1;
}

// get column from some table with $id
function get_col($id, $table, $col){
	global $db;
	$result = sqlite_array_query($db, "SELECT $col FROM $table WHERE id='$id'");
	return $result[0][$col];		
}

// notify via email
function notify($id, $subject, $body){
	global $db;
	$result = sqlite_array_query($db, "SELECT notify_emails FROM issues WHERE id='$id'");
	$to = $result[0]['notify_emails'];

	if ($to!=''){
		global $EMAIL;
		$headers = "From: $EMAIL" . "\r\n" . 'X-Mailer: PHP/' . phpversion();
		mail($to, $subject, $body, $headers);	// standard php mail, hope it passes spam filter :)
	}
}

// start watching an issue
function watch($id){
	global $db;
	if ($_SESSION['e']=='') return;

	$result = sqlite_array_query($db, "SELECT notify_emails FROM issues WHERE id='$id'");
	$notify_emails = $result[0]['notify_emails'];

	if (!empty($notify_emails)) $emails = explode(",",$notify_emails);
	$emails[] = $_SESSION['e'];
	$emails = array_unique($emails);
	$notify_emails = implode(",",$emails);
	sqlite_query($db, "UPDATE issues SET notify_emails='$notify_emails' WHERE id='$id'");
}

// unwatch an issue
function unwatch($id){
	global $db;
	if ($_SESSION['e']=='') return;

	$result = sqlite_array_query($db, "SELECT notify_emails FROM issues WHERE id='$id'");
	$notify_emails = $result[0]['notify_emails'];

	if ($notify_emails!=''){
		$emails = explode(",",$notify_emails);

		$final_email_list=array();
		foreach ($emails as $email){
			if ($email!=$_SESSION['e'] && $email!='') $final_email_list[] = $email;
		}
		$notify_emails = implode(",",$final_email_list);

		sqlite_query($db, "UPDATE issues SET notify_emails='$notify_emails' WHERE id='$id'");
	}
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title><?php echo $TITLE; ?> - Issue Tracker</title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<style>
		body { font-family: sans-serif; font-size: 14px; background-color: #f5f5f5;color: #555;}
		a, a:visited{color:#004989; text-decoration:none;}
		a:hover{color: #666; text-decoration: underline;}
		label{ display: block; font-weight: bold;margin-top: 5px;}
		table{border-collapse: collapse;border-color: #FFF;}
		th{background:#C9CED3;color: #FFF;font-size: 12px;font-weight: 800;padding: 10px 5px;text-align: left;}
		tr.even{background: #F7F9FB;}
		tr:hover{background: #E8EDF3;}
		th,td{border: 1px solid #fff;}
		td .button{display: inline-block;margin: 1px 0 0;width: 40px;}
		.center{text-align:center;}
		#menu{height: 35px;padding: 0px 0px;}
		#menu h1{color: #666;display: inline-block;margin: 0;}
		#menu .links{border-radius: 2px;background:#5C6E83;float: right;position: relative;top: 0px;padding: 0px 5px}
		#menu a,#menu a:visited{border-left: 1px solid #FFF;color: #C9CED3;display: inline-block;height: 21px;padding: 7px;}
		#menu a:hover,#menu a.current{background:#C9CED3;color:#5C6E83;text-decoration:none;}
		#container{border-radius: 2px;box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);width: 1000px; margin: 0 auto; padding: 10px; background: #fff;}
		h2 a.edit{float: right;padding: 5px 10px;text-decoration: none;}
		h2 .priority{color: #666;float: none;font-style: normal;}
		h2 span{color: #CCC;float: right;font-style: italic;}
		span.key{color: #666;font-size: 12px;font-style: normal;padding-top: 5px;}
		.key .keybox{border-radius: 2px;color: #FFF;display: inline;margin-right: 1px;padding: 3px 5px;text-align: center;}
		#footer{padding: 10px 0 0;text-align: center;text-shadow: 1px 1px 0 #DDD;font-size: 11px;}
		select,input,textarea,#comments,.comment,#create,.issue,.issue h2,.attachments_wrapper,.button{border-radius:2px; background-color: #f2f2f2;}
		#create{margin: 10px 0;padding: 15px;}
		.attachments_wrapper{background: #FFF;color: #CCC;padding: 5px;}
		.issue{padding:10px 20px; margin: 10px 0;}
		.issue h2{background:#FFF;margin: 0 -10px;padding: 5px 10px;}
		#comments{background: #f4f4f4;margin-top: 10px;padding:5px 20px 20px;}
		select,input,textarea{background:#fff;border: 1px solid #ddd;padding: 5px;}
		textarea{width: 850px;}
		.comment{background: #FFF;margin: 10px 0;padding: 5px 10px 10px;}
		.comment-meta{color: #666;}
		.p1{background: red;color: #FFF;}
		.p2{background: #FF7E00;color: #FFF;}
		.p3{background: #06C802;color: #FFF;}
		span.c1{color: red;}
		span.c2{color: #FF7E00;}
		span.c3{color: #06C802;}
		.hide{display:none;}
		.left{float: left;}
		.right{float: right;}
		.clear{clear:both;}
		.button:visited,
		.button{background: #A5B9CF;border: none;color: #3C454F;font-size: 12px;margin: 0;padding: 5px;text-shadow: rgba(255,255,255,0.4) 0 1px 0;}
		.button:hover{background: #3C454F;color: #eee;text-decoration:none;text-shadow: rgba(0,0,0,0.4) 0 1px 0;}
		.button:active {background: #A5B9CF;border: none;color: #3C454F;text-decoration:none;}
		a.green:visited,a.green,a.red:visited,a.red{font-weight: 600;padding: 2px 5px;text-shadow: 0 0 1px rgba(0, 0, 0, 0.4);}
		a.green:visited,a.green{background: #B0DA14;color: #FFF}
		a.red:visited,a.red{background: #D62929;color: #FFF}
		a.red:hover,
		a.green:hover{background: #333;}
	</style>
</head>
<body>
<div id='container'>
	<div id="menu">
		<h1><?php echo $TITLE; ?></h1>
		<div class="links">
			<a href="<?php echo $SCRIPTADDRESS; ?>" alt="Active Issues"<?php echo !isset($_GET['new']) &&!isset($_GET['resolved']) && !isset($_GET['id'])?' class="current"':'';?>>Active Issues</a
			><a href="<?php echo $SCRIPTADDRESS; ?>?resolved" alt="Resolved Issues"<?php echo isset($_GET['resolved'])?' class="current"':'';?>>Resolved Issues</a
			><a href="<?php echo $SCRIPTADDRESS; ?>?new" alt="New Issues"<?php echo isset($_GET['new'])?' class="current"':'';?>>New Issues</a
			><a href="<?php echo $SCRIPTADDRESS; ?>?logout" alt="Logout">Logout [<?php echo $_SESSION['u']; ?>]</a>
		</div>
	</div>

	<?php if($mode!="list"):?>
	<?php if(isset($issue['id']) && ($_SESSION['l']<=1 || $_SESSION['u']==$issue['user'])){?>
	<h2><span class="priority<?php echo ($issue['priority']==1?" c1":($issue['priority']==2?" c2":" c3"));?>">Priority:<?php echo $priorityArr[$issue['priority']];?></span> <a href="#" onclick="document.getElementById('create').className='';document.getElementById('title').focus();" class="edit button green">Edit Issue <?php echo $issue['id'];?></a></h2>
	<?}?>
	<div id="create" class='<?php echo isset($_GET['editissue']) || $mode=="new"?'':'hide'; ?>'>
		<?if($mode!="new"){?>
		<a href="#" onclick="document.getElementById('create').className='hide';" style="float: right;">[Close]</a>
		<?}?>

		<form method="POST" action="<?php echo $SCRIPTADDRESS; ?>" enctype="multipart/form-data">
			<input type="hidden" name="id" value="<?php echo (isset($issue['id'])?$issue['id']:null); ?>" />
			<label>Title</label><input type="text" size="50" name="title" id="title" value="<?php echo (isset($issue['title'])?stripslashes($issue['title']):''); ?>" /> 
			<label>Description</label><textarea name="description" rows="5" cols="50"><?php echo (isset($issue['description'])?stripslashes($issue['description']):''); ?></textarea>
			<?php if($MAXUPLOADS): ?>
            <label>Attachments</label><?php	for ($i = 1; $i <= $MAXUPLOADS; $i++) { echo '<input name="uploaded[]" type="file" /><br />';}?>
			<?php endif; ?>
			<label>Priority</label>
				<select name="priority">
					<option value="1"<?php echo (isset($issue['priority']) && $issue['priority']==1?"selected":""); ?>>High</option>
					<option value="2"<?php echo (isset($issue['priority']) && $issue['priority']==2?"selected":""); ?>>Medium</option>
					<option value="3"<?php echo (isset($issue['priority']) && $issue['priority']==3?"selected":""); ?>>Low</option>
				</select>
			<label></label><input class="button" type="submit" name="createissue" value="<?php echo (!isset($issue['id'])?"Create":"Edit"); ?>" />
		</form>
	</div>
	<?php endif; // not list mode ?>

	<?php if ($mode=="list"): ?>
	<div id="list">
	<h2><?php echo (isset($_GET['resolved'])? "Resolved ":"Active "); ?>Issues <?php if(!isset($_GET['resolved'])):?><span class="key">Priority <div class="keybox p1">High</div><div class="keybox p2">Medium</div><div class="keybox p3">Low</div></span><?php endif;?></h2>
		<table border="1" cellpadding="5" width="100%">
			<tr>
				<th width="5%" class='center'>ID</th>
				<th width="38%">Title</th>
				<th width="10%" class='center'>Created by</th>
				<th width="14%" class='center'>Date</th>
				<th class='center'>Watch</th>
				<th class='center'>Comments</th>
				<?php if($MAXUPLOADS): ?>
				<th class='center'>Attachments</th>
				<?php endif; ?>
				<th class='center' width="70">Actions</th>
			</tr>

			<?php
			$count=1;
			foreach ($issues as $issue){
				echo "<tr class='".($count++% 2?'odd':'even')."'>\n";
				echo "<td class='center ".(!isset($_GET['resolved'])?"p".$issue['priority']:'')."'>{$issue['id']}</td>\n";
				echo "<td><a href='?id={$issue['id']}' title='".($issue['priority']==1?"High":($issue['priority']==2?"Medium":"Low"))." Priority'>".htmlentities($issue['title'],ENT_COMPAT,"UTF-8")."</a></td>\n";
				echo "<td class='center'>{$issue['user']}</td>\n";
				echo "<td class='center'>{$issue['entrytime']}</td>\n";
				echo "<td class='center'>".(strpos($issue['notify_emails'],$_SESSION['e'])!==FALSE?"✔":"")."</td>\n";
				echo "<td class='center'>{$issue['comments']}</td>\n";
				echo ($MAXUPLOADS?"<td class='center'>{$issue['attachment']}</td>\n":"");
				echo "<td class='center'>";
				if ($_SESSION['l']===1 || $_SESSION['u']==$issue['user'])
					echo "<a href='?editissue&id={$issue['id']}' class='button green'>Edit</a><a href='?deleteissue&id={$issue['id']}' onclick='return confirm(\"Are you sure? All comments will be deleted too.\");' class='button red'>Delete</a>";
				echo "</td>\n";
				echo "</tr>\n";
			}
			?>

		</table>
	</div>
	<?php endif; ?>

	<?php if ($mode=="issue"): ?>
	<div id="show">
		<div class="issue">
			<h2><?php echo htmlentities(stripslashes($issue['title']),ENT_COMPAT,"UTF-8"); ?><span><?PHP echo $issue['entrytime'];?></span></h2>
			<p><?php echo str_replace("\n","<br />",htmlentities(stripslashes($issue['description']),ENT_COMPAT,"UTF-8")); ?></p>
			<?php if($MAXUPLOADS && isset($attachments) && !empty($attachments)):?>
			<div class="attachments_wrapper">
				<strong>Attachments</strong>
				<ul class="attachments">
					<?php foreach($attachments as $a): ?>
					<li>
					<?php if ($_SESSION['l']===1 || $_SESSION['u']==$issue['user']):?>[<a href="<?php echo $SCRIPTADDRESS; ?>?deletefile=<?php echo $a['id'];?>&id=<?php echo $issue['id']; ?>">Delete</a>] <?php endif;?>
						<a href="<?php echo $SCRIPTADDRESS; ?>?file=<?php echo $a["filepath"];?>"><?php echo $a["filename"];?></a>
					</li>
					<?php endforeach;?>
				</ul>
			</div>
			<?php endif; // attachments ?>
		</div>

		<div class='left'>
			<form method="POST">
				<input type="hidden" name="id" value="<?php echo $issue['id']; ?>" />
	<?if($_SESSION['l']===1 || $_SESSION['u']==$issue['user']){?>
				<input class="button" type="submit" name="mark<?php echo $issue['status']==1?"unsolved":"solved"; ?>" value="Mark as <?php echo $issue['status']==1?"Unsolved":"Solved"; ?>" />
	<?}?>
				<?php 
					if (strpos($issue['notify_emails'],$_SESSION['e'])===FALSE)
						echo "<input class='button' type='submit' name='watch' value=' Email Notifications is Off' />\n";
					else
						echo "<input class='button' type='submit' name='unwatch' value=' Email Notifications is On' />\n";
				?>
			</form>
		</div>
		<div class="clear"></div>

		<div id="comments">
			<?php
			if (count($comments)>0) echo "<h3>Comments</h3>\n";
			foreach ($comments as $comment){
				echo "<div class='comment'><p>".str_replace("\n","<br />",htmlentities(stripslashes($comment['description']),ENT_COMPAT,"UTF-8"))."</p>";
				echo "<div class='comment-meta'><em>{$comment['user']}</em> on <em>{$comment['entrytime']}</em> ";
				if ($_SESSION['l']==1 || $_SESSION['u']==$comment['user']) echo "<span class='right'><a href='{$SCRIPTADDRESS}?deletecomment&id={$issue['id']}&cid={$comment['id']}' onclick='return confirm(\"Are you sure?\");'>Delete</a></span>";
				echo "</div></div>\n";
			}
			?>
			<div id="comment-create">
				<h4>Post a comment</h4>
				<form method="POST">
					<input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>" />
					<textarea name="description" rows="5" cols="50"></textarea>
					<label></label>
					<input class="button" type="submit" name="createcomment" value="Comment" />
				</form>
			</div>
		</div>
	</div>
	<?php endif; ?>
	</div>
	<div id="footer">
		Powered by <a href="https://github.com/codefuture/tit" alt="Tiny Issue Tracker" target="_blank">Tiny Issue Tracker</a>
	</div>
</body>
</html>