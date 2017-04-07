<?php

/**
 *
 * COCS Servers main class (user/admin/servers)
 *
 * its not very well written,
 * however it includes
 * > User System
 * > Admin system
 * > Shoddy anti XSS system
 * > Blacklisting
 * > Notifications
 *
 *
 * it was also <slow>... hostgator is like a Ferrari with a prius engine, just dont do it.
 */


class ServerList{
	public $db_un;
	public $db_pw;
	public $db_table;
	public $db_ip;
	public $db_conn;
	public $admin;
	
	function __construct() {
			//$this->is_blacklisted();
			// if enabled, it'll blacklist the user based on ISP, ie, OVH/Amazon to prevent bots spamming/signing up
	}

	function debug(){
		var_dump($this->db_un);
		var_dump($this->db_pw);
		var_dump($this->db_ip);
		var_dump($this->db_table);
		var_dump($this->db_conn);
	}
	
	function is_blacklisted(){
		$b = array("OVH SAS" => "Blocked to prevent spam", "Amazon.com" => "Blocked to prevent spam", "Digital Ocean" => "Blocked to prevent spam"); // ban by ISP
		$ip_b = array(); // specific ip bans
		$user = json_decode( file_get_contents ("http://ip-api.com/json/" . $_SERVER["REMOTE_ADDR"] ) , true) ;
		if(array_key_exists($user["isp"] , $b)){
			include 'headers.php';
			echo('<title>COCS-Servers | Blocked</title><br><br><div class="ui container">
			<div class="ui negative message transition">
				<div class="header">
					You are blocked from visiting COCS-Servers!
				</div>
				<p>
					Your ISP (<b>' . $user["isp"] . '</b>) is blocked from visiting our service, this is done to prevent spam from multiple cloud/vps/dedi providers. If you believe this is in error, please contact us directly via email: <b>contact @ cocs-servers.com</b>
				</p>
			</div></div>');
			die();
		}elseif(array_key_exists($_SERVER["REMOTE_ADDR"], $ip_b)){
			include 'headers.php';
			echo('<title>COCS-Servers | Banned</title>	<br><br><div class="ui container">
			<div class="ui negative message transition">
				<div class="header">
					You are banned from COCS-Servers!
				</div>
				<p>
					You are banned from COCS-Servers, if you think this is in error, please contact via email: <b>contact @ cocs-servers.com</b> <br>Reason: <b>' . $ip_b[$_SERVER["REMOTE_ADDR"]] . '</b>
				</p>
			</div></div>');
			die();
		}else{
			//echo "<code>User <b>" . $_SERVER["REMOTE_ADDR"] . "</b> with ISP <b>" . $user["isp"] . "</b> is allowed!</code>";
		}
		if(isset($_GET["blockedrange"])){
			//echo "<br><code>";
			//print_r($b);
			//echo "</code>";
		}
	}

	function is_admin(){
		if(isset($_SESSION["admin"])){
			if($_SESSION["admin"] == "true"){
				$this->admin = true;
			}else{
				$this->admin = false;
			}
		}
	}
	
	function admin(){
		if($this->admin){
			return true;
		}else{
			return false;
		}
	}
	
	function db_settings($ip, $user, $password, $table){
		$this->db_un = $user;
		$this->db_pw = $password;
		$this->db_table = $table;
		$this->db_ip = $ip;
		
	}
	
	function db_connect(){
		$try = mysqli_connect($this->db_ip,  $this->db_un, $this->db_pw, $this->db_table);
		if($try){ $this->db_conn = $try; }
	}
	
	function db_query($q){
		$try = mysqli_query($this->db_conn, $q);
	}
	function paginate($min, $end, $total){
		
	}
	function display_servers($start, $limit){
		$sql = "SELECT * FROM cocservers  ORDER BY premium DESC, votes desc LIMIT $start, $limit";
		/*
			I am pretty sure this is all vulnerable to SQLi 

			needs updating to PDO
		 */
		$result = mysqli_query($this->db_conn, $sql);
		if($result->num_rows > 0){
			echo("<table id='servers' class='ui fixed single line inverted table'>");
			echo("<thead><tr><th style='width: 80%;'>Server</th><th style='width: 20%;' class='right aligned'>Votes</th></tr></thead><tbody>");
			while($row = $result->fetch_assoc()) {
				$ip = $row["ip"];
				if($row["maintenance"] == "true"){
					$icon = "orange";
				}else{
					$icon = $this->isup($ip);
				}
				$id = $row["unique_id"]; $goto = $this->link_fancy($id, $row["servername"]);
				if($row["premium"] == "true"){ $premium = "<div style='float: right;' class='ui right mini yellow label'><span style='color: #1b1c1d;'>PREMIUM</span></div>"; } else { $premium = ""; }
				echo '<tr style="cursor: pointer;" onclick=\'document.location="' . $goto .'"; \'>';
				echo "<td><i class='$icon circle icon'></i> " . $row["servername"] . " $premium</td>";
				echo "<td class='collapsing right aligned'>" . $row["votes"] . "</td>";
				echo "</tr>";
			}
		}
	}
	function display_servers_json($limit){
		if($limit == 10){
			$start = 0;
		}else{
			$start = $limit - 10;
		}
		$sql = "SELECT * FROM cocservers  ORDER BY premium DESC, votes desc LIMIT $start, $limit";
		/*
			I am pretty sure this is all vulnerable to SQLi 

			needs updating to PDO
		 */
		$result = mysqli_query($this->db_conn, $sql);
		$results = array();
		if($result->num_rows > 0){
			while($row = $result->fetch_assoc()) {
				$ip = $row["ip"];
				$r = array("premium" => $row["premium"], "name" => $row["servername"], "isup" => $this->isup_tf($ip), "votes" => $row["votes"], "link" => $this->link_fancy($row["unique_id"], $row["servername"]));
				array_push($results, $r);
			}
			$e = array("success" => true, "results" => $results);
			echo(json_encode($e));
		}else{
			$a = array("success" => false);
			echo(json_encode($a));
		}
	}
	function display_server($id){
		$sql = "SELECT * FROM cocservers WHERE unique_id = '$id'";
		/*
			I am pretty sure this is all vulnerable to SQLi 

			needs updating to PDO
		 */
		$try = mysqli_query($this->db_conn, $sql);
		if($try->num_rows == 1){
			$row = $try->fetch_assoc();
			return $row;
		}else{
			return false;
		}
	}
	function countservers(){
		$sql = "SELECT COUNT(*) FROM cocservers;";

		$t = mysqli_query($this->db_conn, $sql);
		var_dump ($t);
	}
	function list_mine($id){
		$sql = "SELECT * FROM cocservers WHERE owner = '$id'";
		/*
			I am pretty sure this is all vulnerable to SQLi 

			needs updating to PDO
		 */
		$try = mysqli_query($this->db_conn, $sql);
		if($try->num_rows > 0){
			echo("<table class='ui single line unstackable inverted table'>");
			$extender = "<th></th>";
			echo("<thead><tr><th class='collapsing center aligned'></th><th >Server</th><th class='center aligned'>Votes</th>$extender</tr></thead><tbody>");
			while($row = $try->fetch_assoc()) {
				$ip = $row["ip"];
				if($row["maintenance"] == "true"){
					$icon = "orange";
				}else{
					$icon = $this->isup($ip);
				}
				$id = $row["unique_id"]; $goto = $this->link_fancy($id, $row["servername"]);
				if($row["premium"] == "true"){ $premium = "<i style='color: #FFDF00;' class='trophy icon'></i>"; } else { $premium = ""; }
				echo '<tr style="cursor: pointer;" onclick=\'document.location="' . $goto .'"; \'>';
				echo "<td class='collapsing center aligned'><i class='$icon circle icon'></i></td>";
				echo "<td>$premium" . $row["servername"] . "</td>";
				echo "<td class='center aligned' >" . $row["votes"] . "</td>";
				echo "<td><a href='../user/dashboard/?action=del&id=$id'><i class='remove red icon'></i></a><a href='../user/dashboard/add-server/?action=edit&id=$id'><i class='write yellow icon'></i></a></td>";
				echo "</tr>";
			}
			echo("</tbody></table>");
		}else{
			return ('<div class="ui fluid error message"><p>No servers found...</p></div>');
		}
	}
	function isup($ip){
		$try = @fsockopen("udp://".$ip, "9339", $errno, $errstr, 0.5);
		if($try){
			return "green";
		}else{
			return "red";
		}
	}
	/**
	 *
	 * I love how instead of using one function to check if its up, I made three...
	 *
	 */
	
	function isup_tf($ip){
		$try = @fsockopen("udp://".$ip, "9339", $errno, $errstr, 0.5);
		if($try){
			return true;
		}else{
			return false;
		}
	}
	
	function isup_fancy($ip){
		$try = @fsockopen("udp://".$ip, "9339", $errno, $errstr, 0.5);
		if($try){
			return "<i class='circle green icon'></i>Server Online!";
		}else{
			return "<i class='circle red icon'></i>Server Offline!";
		}
	}
	
	function is_edit($check){
		if(isset($_GET["id"]) && isset($_GET["action"])){
			if($_GET["action"] == "edit"){
				$sql = "SELECT * FROM cocservers WHERE unique_id = " . $_GET["id"];
				/*
					this is so fucking vulnerable to SQLi
					what was I thinking
				 */
				$try = mysqli_query($this->db_conn, $sql);
				$result = $try->fetch_array();
				if($result["owner"] == $check){
					return $result;
				}else{
					return false;
				}
			}

		}
		
	}
	
	function del_server($id, $owner){
		$sqlz = "SELECT * FROM cocservers WHERE unique_id = " . $id;
		$try = mysqli_query($this->db_conn, $sqlz);
		$t = $try->fetch_array();

		if($t["owner"] == $owner){
			/*
				the joys of learning you can delete other peoples servers
			 */
			$id = $_GET["id"];
			$sql = "DELETE FROM cocservers WHERE unique_id='$id'";
			$t = mysqli_query($this->db_conn, $sql);
			if($t){
				return ('<div class="ui fluid success message"><p>Server deleted!</p></div>');
			}
		}else{
			return ('<div class="ui fluid error message"><p>You do not own that server!</p></div>');
		}
	}

	function adel_server($id){
		$sql = "DELETE FROM cocservers WHERE unique_id='$id'";
		/*
			rather than checking if current user is an admin (would've been better), just create more functions
			and delete the server...
		 */
		$t = mysqli_query($this->db_conn, $sql);
		if($t){
			return ('<div class="ui fluid success message"><p>Server deleted!</p></div>');
		}
	}

	function init_edithandler(){
		$ip = $_POST["ip"];
	}
	
	function search_servers($query, $online, $method){
		$search = strip_tags($query);
		$ips = strip_tags($ip);
		// begin sql handlers;
		if($_POST["sonly"] == "on"){
			// no fuckin idea...
		}
		switch($method){
			case "0":
				$m = "";
				break;
			case "1":
				$m = "ORDER BY votes ASC";
				break;
			case "2":
				$m = "ORDER BY votes DESC";
				break;
			default:
				$m = "";
		}
		/*
		0 = name only
		1 = votes asc
		2 = votes desc
		*/
		$sql = "SELECT * FROM cocservers WHERE servername LIKE '%$search%' $m";
		$result = mysqli_query($this->db_conn, $sql);
		if($result->num_rows > 0){
			echo("<h1 class='ui floated header'><i class='search icon'></i><div class='content'>Search results for: $query</div></h1>");
			echo("<table class='ui single line selectable inverted table'>");
			if($this->admin){
				$extender = "<th></th>";
			}else{
				$extender = null;
			}
			echo("<thead><tr><th></th><th>Server</th><th>Owner</th><th>Votes</th>$extender</tr></thead><tbody>");
			while($row = $result->fetch_assoc()) {
				$ip = $row["ip"];
				$icon = $this->isup($ip);
				if($online){
					if($this->isup_tf($ip)){
						$id = $row["unique_id"]; $goto = $this->link_fancy($id, $row["servername"]);
						echo '<tr style="cursor: pointer;" onclick=\'document.location="' . $goto .'"; \'>';
						echo "<td><i class='$icon circle icon'></i></td>";
						echo "<td>" . $row["servername"] . "</td>";
						echo "<td>" . $row["owner"] . "</td>";
						echo "<td>" . $row["votes"] . "</td>";
						echo "</tr>";
					}
				}else{
					$id = $row["unique_id"]; $goto = $this->link_fancy($id, $row["servername"]);
					echo '<tr style="cursor: pointer;" onclick=\'document.location="' . $goto .'"; \'>';
					echo "<td><i class='$icon circle icon'></i></td>";
					echo "<td>" . $row["servername"] . "</td>";
					echo "<td>" . $row["owner"] . "</td>";
					echo "<td>" . $row["votes"] . "</td>";
					echo "</tr>";
				}
		}
		echo("</tbody></table>");
		}else{
			echo "Unfortunately we couldn't find any results for $query" . $result->num_rows;
		}
		/**
		 *
		 * this entire function bit me in the ass later on, I tried the whole "php only" approach
		 * but decided ajax loading more servers would be cool, but good lord this wasn't made for that
		 *
		 */
		
	}
	
	function vote($id, $ip){
		if($this->can_vote($ip) == true){
			$sql = "UPDATE `cocservers` SET `votes` = votes +1 WHERE `cocservers`.`unique_id` = $id;";
		
		
			$s = "INSERT INTO `votes` (`unique_id`, `ip`, `voted`, `timestamp`) VALUES (NULL, '$ip', '$id', CURRENT_TIMESTAMP);";
			$t = mysqli_query($this->db_conn, $sql);
			$r = mysqli_query($this->db_conn, $s);
			if($sql){
				return '<div class="ui fluid success message"><p>Vote added!</p></div>';
			}else{
				return ('<div class="ui fluid error message"><p>An unknown error occured!</p></div>');
			}
		}else{
			return ('<div class="ui fluid error message"><p><b>You can only vote once per 24 hours!</b></p></div>');
		}
	}
	function can_vote($ip){
		$sql = "SELECT * FROM votes WHERE ip = '$ip' ORDER BY timestamp DESC LIMIT 1";
		// the sql here returns this users LATEST IP!
		$try = mysqli_query($this->db_conn, $sql);
		$t = array();
		if($try->num_rows > 0){
			while($row = $try->fetch_assoc()){

				array_push($t, $row["timestamp"]);
			}
			$now = new DateTime("now");
			$vote_time = new DateTime($t[0]);
			$diff = date_diff($now, $vote_time);
			$interval = ($diff->h) + ($diff->days*24);
			if($interval > 24){
				return true;
			}else{
				return false;
			}
		}else{
			return true; // assumes user has never voted before
		}
	}
	function link_fancy($id, $name){
		$str = str_replace(" ", "_", $name);
		$link = "../server/?s=$id" . "_" ."$str";
		return $link;
	}
	
	function add_server($name, $ip, $owner, $desc, $cocv, $website, $woi, $bid){
		if($woi == "ip"){
			$w = "true";
		}elseif($woi == "web"){
			$w = "false";
		}
		$sql = "INSERT INTO `cocsserv_coc`.`cocservers` (`unique_id`, `servername`, `ip`, `owner`, `added`, `votes`, `premium`, `descr`, `cocv`, `website`, `woi`, `bid`) VALUES (NULL, '$name', '$ip', '$owner', CURRENT_TIMESTAMP, '0', 'false', '$desc', '$cocv', '$website', '$w', '$bid');";

		$result = mysqli_query($this->db_conn, $sql);
		if($result){
			return ('<div class="ui fluid success message"><p>Server added!</p></div>');
		}else{
			return ('<div class="ui fluid error message"><p>An unknown error occured!</p></div>');
		}
	}
	
	function acc_type($d){
		switch($d){
			case "0":
				return '';
				break;
			case "1";
				return '[User]';
				break;
			case "2":
				/**
				 *
				 * CLASSES...
				 * 
				 *
				 */
				
				return '<b style="font-variant: small-caps;font-style: bold;color: #009933;">[Mod]</b> ';
				break;
			case "3":
				return '<b style="font-variant: small-caps;font-style: bold;color: #cc0000;">[Admin]</b> ' ;
				break;
			case "4":
				return '<b style="font-variant: small-caps;font-style: bold;color: #cc0000;">[Dev]</b> ' ;
				break;
			default:
				return "Unknown";
		
		}
	}
	
	function options_d(){
		$file = file("versions.txt");
		foreach($file as $v){
			echo('<option value="' . $v . '">'. $v . '</option>');
		}
	}
	
	function user_register($username, $password, $email, $ip){
		// username available?
			$sql = "SELECT * FROM users WHERE username = '$username'";
			$try = mysqli_query($this->db_conn, $sql);
			if($try->num_rows == 0){
				if($this->e_exists($email)){
					return ('<div class="ui fluid error message"><p>Email already in use!</p></div>');
				}else{
					$pass = password_hash($password, PASSWORD_DEFAULT);
						if(strpos($email, '@') !== false) {
							$sqls = "INSERT INTO `users` (`unique_id`, `username`, `password`, `email`, `permissions`, `registeredip`) VALUES (NULL, '$username', '$pass', '$email', '0', '$ip')";
							$ntry = mysqli_query($this->db_conn, $sqls);
							if($ntry){
								return ('<div class="ui fluid success message"><p>Your account was successfully created.</p></div>');
							}
						}else{
							return ('<div class="ui fluid error message"><p>Invalid email address, try again</p></div>');
						}
					}
			}else{
				return ('<div class="ui fluid error message"><p>Username in use, try another.</p></div>');
			}
	}
	
	function server_update($id, $check, $ip, $name, $desc, $cocv, $weborip, $website){
		$owner = $this->display_server($id);
		if($owner["owner"] == $check){
			if($weborip == "ip"){
				$sql = "UPDATE `cocsserv_coc`.`cocservers` SET `cocv` = '$cocv', `descr` = '$desc', `ip` = '$ip', `servername` = '$name', `website` = '$website', `woi` = 'true' WHERE `cocservers`.`unique_id` = $id;";
				$try = mysqli_query($this->db_conn, $sql);
				if($try){
					return ('<div class="ui fluid success message"><p>Server Updated!</p></div>');
				}
			}elseif($weborip == "web"){
				$sql = "UPDATE `cocsserv_coc`.`cocservers` SET `cocv` = '$cocv', `descr` = '$desc', `ip` = '$ip', `servername` = '$name', `website` = '$website', `woi` = 'false' WHERE `cocservers`.`unique_id` = $id;";
				$try = mysqli_query($this->db_conn, $sql);
				if($try){
					return ('<div class="ui fluid success message"><p>Server Updated!</p></div>');
				}
			}
		}else{
			return ('<div class="ui fluid error message"><p>You cannot modify this server - if you believe this is in error click <a href="../contact/?name=' . $check .'&title=ID:' . $id . ' - Permissions Error! ">here</a></p></div>');
		}
	
	}
	
	function user_verify($username, $password){
		$sql = "SELECT * FROM users WHERE username = '$username'";
		$try = mysqli_query($this->db_conn, $sql);
		$row = $try->fetch_assoc();
		if(password_verify($password, $row["password"])){
			$_SESSION["username"] = $row["username"];
			$_SESSION["pid"] = $row["permissions"];
			$_SESSION["uid"] = $row["unique_id"];
			$_SESSION["email"] = $row["email"];
			if($row["permissions"] > "2"){
				$_SESSION["admin"] = true;
			}
			return ('<div class="ui fluid success message"><p>Login success! Click <a href="../index.php">here</a> to go home!</p></div>');
		}else{
			return ('<div class="ui fluid error message"><p>Incorrect password, try again</p></div>');
		}
	}
	
	function is_verified($id){
		$sql = "SELECT * FROM users WHERE id = $id";
		$try = mysqli_query($this->db_conn, $sql);
		
	}
	
	function verify_user($id, $checksum){
		$sql = "SELECT * FROM users WHERE unique_id = $id, checksum = $checksum";
		$try = mysqli_query($this->db_conn, $sql);
		if($try->num_rows == 0){
			return ('<div class="ui fluid error message"><p>ID/Checksum mismatch, contact support.</p></div>');
		}elseif($try->num_rows == 1){
			$sql = "UPDATE users SET permissions = 1 WHERE unique_id = $id";
				$try = mysqli_query($this->db_conn, $sql);
				if($try){
					return ('<div class="ui fluid success message"><p>Account Verified, you may now log in!</p></div>');
				}else{
					return ('<div class="ui fluid error message"><p>There was an error verifying your account, please contact support¬</p></div>');
				}
		}else{
			return ('<div class="ui fluid error message"><p>For some reason, multiple results were found, <b>please contact support immediately</b></p></div>');
		}
	}
	
	function send_mail($to, $subject, $content){
		$x = mail($to, $subject, wordwrap($content, 70));
		if($x){
			return ('<div class="ui fluid success message"><p>Thanks for contacting us! Expect a reply within 72 hours!</p></div>');
		}else{
			return ('<div class="ui fluid error message"><p>Unknown error occurred whilst sending email, try again later!</p></div>');
		}
	}
	
	function banner($ip, $name, $b){
		// if isnt set, proceed to img 1 (for users who havent updated banners :^)
		if(!file_exists("./imgs/$b.png")){
			$image = "./imgs/$b.png";
		}else{
			$image = "./imgs/2.png";
		}
		$font = "./fonts/afb.otf";
		$name = preg_replace("/&#?[a-z0-9]{2,8};/i","", $name);
		$status = ($this->isup_tf($ip) ? "ONLINE" : "OFFLINE");
		$img = ImageCreateFromPNG($image);
		$scolor = ($this->isup_tf($ip) ? imagecolorallocate($img, 0, 186, 0) : imagecolorallocate($img, 204, 0, 0));
		$white = imagecolorallocate($img, 255, 255, 255);
		imagettftext($img, 18, 0, 7, 32, $white, $font, $name);
		imagettftext($img, 18, 0, 7, 65, $scolor, $font, $status);
		return imagepng($img);
	}
	
	function query($sql){
		$try = mysqli_query($this->db_conn, $sql);
		return $try;
	}

	function track($a, $b, $c){
		$sql = mysqli_query($this->db_conn, "INSERT INTO `stats` (`id`, `ts`, `REMOTE_ADDR`, `REQUEST_URI`, `HTTP_REFERER`) VALUES (NULL, NULL, '$a', '$b', '$c')");
	}

	function notify($id){
		$sql = "SELECT * FROM notifications WHERE id = '$id'";
		$try = mysqli_query($this->db_conn, $sql);
		$notifications = array();
		if($try->num_rows > 0){
			while($r = $try->fetch_assoc()){
				array_push($notifications, $r["msg"]);
			}
			echo($notifications[0]);
			$this->seen($id);
			//var_dump($notifications);
		}
	}

	function add_notify($id, $msg, $type){
		//  &mdash; [tag] username
		if($type == 1){
			$t = '<div class="ui success message"><p>' . $msg . '</p></div>';
		}elseif ($type == 2) {
			// 2
			$t = '<div class="ui warning message"><p>' . $msg . '</p></div>';
		}else{
			// 3
			$t = '<div class="ui negative message"><p>' . $msg . '</p></div>';
		}
		$sql = "INSERT INTO notifications (`unique_id`, `id`, `msg`) VALUES (NULL, '$id', '$t')";
		$try = mysqli_query($this->db_conn, $sql);
		if($try){
			return true;
		}else{
			return false;
		}
	}
	
	function n_2_id($n){
		$s = "SELECT * FROM users WHERE `username` = '$n'";
		$y = mysqli_query($this->db_conn, $s);
		$u = array();
		if($y->num_rows > 0){
			while($row = $y->fetch_assoc()){
				array_push($u, $row);
			}
			
			return $u[0];
		}
	}
	
	function e_exists($email){
		$s = "SELECT * FROM `users` WHERE email = '$email'";
		$q = mysqli_query($this->db_conn, $s);
		if($q->num_rows > 0){
			return true;
		}elseif($q->num_rows == 0){
			return false;
		}
	}

	function seen($id){
		$sql = "DELETE FROM notifications WHERE id = '$id'";
		$t = mysqli_query($this->db_conn, $sql);
	}

	function addhttp($url) {
		if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
			$url = "http://" . $url;
		}
   		return $url;
	}

	function alert_admins($sid, $trigger){
		$t = '<div class="ui negative message"><p><i class="big red warning sign icon"></i><b> [COC-AV] [AUTO] Attention! ServerID: '.$sid.' has triggered AntiXSS with keyword: '.$trigger.'</b></p></div>';
		$admins = array("7", "8");
		foreach($admins as $admin){
			$sql = "INSERT INTO notifications (`unique_id`, `id`, `msg`) VALUES (NULL, '$admin', '$t')";
			$try = mysqli_query($this->db_conn, $sql);			
		}
		$n = "INSERT INTO cocav (`reportid`, `serverid`, `trigger`) VALUES (NULL, '$sid', '$trigger')";
		$e = mysqli_query($this->db_conn, $n);
	}

	function anti_xss($sid, $scan){
		$bad = array("iframe", "<?php", "<?", "?>");
		foreach($bad as $banned){
			if(strpos($scan, $banned) !== false){
				$b = true;
				$this->alert_admins($sid, $banned);
				return '<center><h4 class="ui red header"><b>[COCS-AV]</b> Preventing execution due to security measures<div class="ui divider"></div>If you think this is in error contact us immediately!</h4></center>';
				break;
			}
		}
		if(!$b){
			return $scan;
		}
	}

	function calculate_rank($server_id){
		$sql = "SELECT * FROM cocservers ORDER BY votes DESC";
		$try = mysqli_query($this->db_conn, $sql);
		$count = mysqli_query($this->db_conn, "SELECT COUNT(*) FROM cocservers");
		$rows = $count->fetch_row()[0];
		$i = 0;
		while($r = $try->fetch_assoc()){
			//var_dump($r);
			$i++;
			if($r['unique_id'] == $server_id){
				return $i;
				break 1;
			}
		}
	}
}




?>