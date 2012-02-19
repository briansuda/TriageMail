<?php
// brian suda
// triagemail.com
// 2012-02-19

session_start();
?>
<html>
  <head>
	<title>Email Triage</title>
	<!-- this needs to be updated depending on the device -->
	<style type="text/css">
		html,body { margin: 0; padding: 0; font-family:"FuturaMedium","Futura"; font-size: 16px; }
		.label { color: #000; border-bottom: 1px solid black; padding: 3px; white-space: nowrap; overflow: ellipsis; width: 100%;}
		h1 { padding: 5px; text-align: bottom; background-color: #000; color: white; font-size: 24px; }
		.counter { position: absolute; text-align: right; right: 5px; top: 8px; width: 100%; color: white; }
		.logout { position: absolute; right: 25px; top: 10px;}
		
		
		.menuBar { position: absolute; left: 0; bottom: 0px; width: 100%;}
		.button { display: block; margin: 0px; height: 80px; width: 25%; float: left;}
		form { padding: 0px;}
	</style>
	<link rel="apple-touch-icon" href="http://triagemail.com/images/apple-icon.png"/>
  </head>
  <body id="triagemail-com">
	<h1>triagemail.com</h1>
	<form action="<?=$_SERVER['PHP_SELF']?>" method="post">
<?php
	
	if(!checkRequirements()){
		echo '<div class="error">This version of PHP does NOT have the required libraries. Please see triagemail.com for more information.</div>';
	} else {
		if($_POST['logout']){
			// distroy all the variable and logout
			unset($_SESSION['username']);
			unset($_SESSION['password']);
			unset($_SESSION['connectionString']);
			unset($_SESSION['inTriage']);
			unset($_SESSION['msg_num']);
			unset($_SESSION['unread_list']);
			
			
			echo '<div class="message">'.translate('Logged out',$lang).'</div>';
			renderLogin($device,$lang); 
			
		} elseif($_SESSION['inTriage']){
			// need to reconnect and create an $mbox
			$mbox = imap_open($_SESSION['connectString'], $_SESSION['username'], $_SESSION['password']);
			

			if(!($_POST['act'])){
				if($_POST['delete']){
					// delete
					imap_delete($mbox,$_SESSION['unread_list'][$_SESSION['curr_index']]);
					imap_expunge($mbox);
				} elseif ($_POST['defer']){
					// do not mark as read, but skip it
				} elseif($_POST['ignore']) {
					// ignore
					//mark as read THIS IS NOT working with POP3 Grrrr!
					$status = imap_setflag_full($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']], "\Seen");
				} elseif ($_POST['reply']){
					// send the message
					$header  = imap_header($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']]);
					$to      = $header->reply_toaddress;
					$subject = $header->subject;
										
					// send the message
					imap_mail($to,$subject,$_POST['message']);
					//echo imap_last_error();
					
					// mark it as read
					$status = imap_setflag_full($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']], "\Seen");
				}
				$_SESSION['curr_index'] = ($_SESSION['curr_index'])+1;
							
				// get next message
				if($_SESSION['curr_index'] == count($_SESSION['unread_list'])) {
					// we have reached the end of the list of unread messages. What do to now?
					echo '<div class="message">end of triage!</div>';
					$temp = count(imap_search($mbox,'UNSEEN'));
					if(imap_search($mbox,'UNSEEN')){
						echo '<div>You you wish to QUIT or Triage the remaining messages?</div>';
					}
					renderLogout($device,$lang);

					// re-triage the defered messages?
					// quit?
			
				} else {
					renderMessageCount(($_SESSION['curr_index'])+1,count($_SESSION['unread_list']),$lang);
					$header = imap_header($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']]);
					$from    = $header->fromaddress;
					$subject = $header->subject;
					renderPreview($from,$subject,$lang);
				}

			} else {
				$header = imap_header($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']]);
				$from    = $header->fromaddress;
				$subject = $header->subject;
//				var_dump(imap_fetchstructure($mbox, $_SESSION['unread_list'][$_SESSION['curr_index']]));
				$message = imap_fetchbody($mbox,$_SESSION['unread_list'][$_SESSION['curr_index']],1);
				renderReply($from,$subject,$message,$lang,$device);
			}
			
			renderLogout($device,$lang);
			// clean-up			
			imap_close($mbox);
		} elseif($_POST['login']) {
			if((trim($_POST['username']) != '') && (trim($_POST['password']) != '') && (trim($_POST['server']) != '')){
				// scrub the input
				$username = addslashes(trim($_POST['username']));
				$password = addslashes(trim($_POST['password']));
				$server   = addslashes(trim($_POST['server']));
				// need to build this better
				$connectString = '{pop.gmail.com:995/pop3/ssl/novalidate-cert/notls}INBOX';
				$connectString = '{imap.gmail.com:993/imap/ssl}INBOX';
				$connectString = '{'.$server.':993/imap/ssl}INBOX';
				$connectString = '{'.$server.':993/imap/ssl/novalidate-cert/notls}INBOX';

				$mbox = @imap_open($connectString, $username, $password);
				if($mbox){
					//$num_msg = imap_num_msg($mbox);
					$unread_list = imap_search($mbox,'UNSEEN');

					if($unread_list){
					  renderMessageCount(count($unread_list));
					  $header = imap_header($mbox, $unread_list[0]);
					  $from    = $header->fromaddress;
					  $subject = $header->subject;
					  				  
					  renderPreview($from,$subject,$device,$lang);

						// setup session variables so we can loop through emails individually w/o having to constantly login
						$_SESSION['inTriage'] = true;
						$_SESSION['username'] = $username;
						$_SESSION['password'] = $password;
						$_SESSION['connectString']   = $connectString;
					
						$_SESSION['curr_index']  = 0;
						$_SESSION['unread_list'] = $unread_list;

					} else {
					  echo '<div class="message">Your Inbox has no un-read messages!</div>';
					}
					
					renderLogout($device,$lang);
					
					
					// clean-up
					imap_expunge($mbox);
					imap_close($mbox);
					
				} else {
					echo '<div class="error">'.translate('There was an error connecting to',$lang).' '.$server.'. '.translate('Please try again',$lang).'.</div>';				
					renderLogin($device,$lang);
					// DEBUG
					echo imap_last_error();
				}
			} else {
				echo '<div class="error">'.translate('Username, password or server were blank',$lang).'</div>';
				renderLogin($device,$lang);
			}
		} else {
			renderLogin($device,$lang);
		}
	}
?>
	</form>
  </body>
</html>

<?php

function translate($term,$lang){
	switch($lang){
		// copy this setup
		// basecase
		case 'en-us': case 'en': default:
		  return $term;
		  break;
	}
}


function renderLogin($device,$lang){
	// display login
	echo '<div><label>'.translate('Username',$lang).': <input type="text" name="username" /></label></div>';
	echo '<div><label>'.translate('Password',$lang).': <input type="password" name="password" /></label></div>';
	echo '<div><label>'.translate('Server',$lang).': <input type="text" name="server" /></label></div>';
	echo '<div><input type="submit" name="login" value="'.translate('Perform Triage!',$lang).'" /></div>';
}

function renderLogout($device,$lang){
	echo '<input type="submit" value="'.translate('Logout',$lang).'" name="logout" class="logout" />';
}

function renderReply($from,$subject,$message,$lang,$device){
  echo '<div class="label"><span>'.strtoupper(translate('from',$lang)).':</span> '.htmlspecialchars($from).'</div>';
  echo '<div class="label"><span>'.strtoupper(translate('subject',$lang)).':</span> '.htmlspecialchars($subject).'</div>';
  echo '<div>';
  echo '<textarea cols="80" rows="15" name="reply">'.$message.'</textarea>';
  echo '<input type="submit" name="reply" />';
  echo '</div>';
}

function renderPreview($from,$subject,$lang='en'){
  echo '<div class="label">'.htmlspecialchars($from).'</div>';
  echo '<div class="label">'.htmlspecialchars($subject).'</div>';
  echo '<div class="menuBar">';
  echo '<input type="image" class="button" name="delete" value="DELETE" />';
  echo '<input type="image" class="button" name="act" value="ACT" />';
  echo '<input type="image" class="button" name="defer" value="DEFER" />';
  echo '<input type="image" class="button" name="ignore" value="IGNORE" />';
  echo '</div>';
}

function renderMessageCount($total){
  echo '<div class="counter">'.$total.'</div>';
}

function checkRequirements(){
	// check for the availability of various functions in your PHP install
	
	// IMAP is needed
	if(function_exists('imap_open') && function_exists('mail')){ return true; }
	
	// everything looks good!
	return false;
}

?>