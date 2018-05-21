<?php
//Set headers
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');


//Define functions

function CommitQuery($SQLQuery, $conn) {
	//Commit query
	$result = $conn->query($SQLQuery);
	$error = $conn->error;
	if(strlen($error)>1) {
		//Error. Die.
		die("{\"error\":\"GENERIC_SQL_ERROR\",\"raw_error\":\"". $error."\"}");
	}
	$outp = array();
	if(gettype($result)==gettype(true)) {
		//This is a no-output thing. Return nothing.
		return null;
	}
	$outp = $result->fetch_all(MYSQLI_ASSOC);
	//Before converting to an object, fix the array strings
	return $outp;
}

function WriteSingleJson($key,$value) {
	$array = array();
	$array[$key]=$value;
	echo json_encode($array);
}

function WriteReply($isLiked,$totalLikes) {
	$array = array();
	$array["liked"]=$isLiked;
	$array["total"] = $totalLikes;
	echo json_encode($array);
}

function WriteError($error, $raw) {
	$array = array();
	$array["error"] = $error;
	$array["raw_error"] = $raw;
	echo json_encode($array);
	die();
}

function VerifyApp($id, $conn) {
	$query = 'SELECT * FROM pbl_appstore.pbl_appstore WHERE id = "'.$id.'";';
	$result = CommitQuery($query,$conn);
	return count($result)!=0;
}

function FetchLikedApp($id, $conn) {
	$query = 'SELECT * FROM pbl_appstore.usrLikes WHERE appId = "'.$id.'";';
	$result = CommitQuery($query,$conn);
	//Check if the app exists.
	if(count($result)>0) {
		//Exists. Return it.
		$result = $result[0];
		$result["users"] = json_decode($result["users"],true);
		return $result;
	} else {
		//Create data now.
		$array = array();
		$array["appId"] = $id;
		$array["totalLikes"] = 0;
		$array["users"] = array();
		$array["users"]["test"] = 0; //Zero as in no likes.
		return $array;
	}
}

function UpdateLikedApp($id, $conn, $totalLikes, $user) {
	$query = 'SELECT * FROM pbl_appstore.usrLikes WHERE appId = "'.$id.'";';
	$result = CommitQuery($query,$conn);
	//$newQuery = "UPDATE pbl_appstore.pbl_appstore SET usrHearts = ".$userHearts." WHERE id = '".$uuid."';";

	//Check if the app exists.
	if(count($result)>0) {
		//Exists. Update it.
		$user = json_encode($user);
		$user=$conn->real_escape_string($user);
		$query = 'UPDATE pbl_appstore.usrLikes SET appId="'.$id.'", totalLikes="'.$totalLikes.'", users = "'.$user.'";';
		CommitQuery($query,$conn);
	} else {
		//Create data now.
		$user = json_encode($user);
		$user=$conn->real_escape_string($user);
		$query = 'INSERT INTO pbl_appstore.usrLikes (appId, totalLikes, users) VALUES ("'.$id.'","'.$totalLikes.'","'.$user.'");';
		CommitQuery($query,$conn);
	}
}

function CheckIfAppIsAlreadyLiked($data,$badValue,$uid) {
	if($data["users"][$uid] == $badValue) {
		//Bad.
		WriteReply($data["users"][$uid] == 1,$data["totalLikes"]);
		die();
	}
}

//End define

$servername = "";
$username = "";
$password = "";
//Create connection
$conn = new mysqli($servername, $username, $password);

//Check connection
if ($conn->connect_error) {
	WriteError("CONNECT_ERROR",$conn->connect_error);
} 

$appId = $_GET['id'];
$actionString = $_GET['action'];
$uid = $_GET['uid'];

//SQL protect these
$appId=$conn->real_escape_string($appId);
$unesUid = $uid;
$uid=$conn->real_escape_string($uid);

//Verify the ID
if(VerifyApp($appId,$conn)==false) {
	//Failed to verify.
	WriteError("Requested app verification failed.","Make sure 'id' is a valid app ID.");
}

//Veify the user id
if(strlen($uid)<8) {
	WriteError("User ID verification failed.","Make sure the user ID is over 8 characters. Pass it in as 'uid'.");
}

//Fetch the current app from the database.
$data = FetchLikedApp($appId,$conn);

$data["totalLikes"] = (int)$data["totalLikes"];

//Verify the action
$action = 0;
if($actionString == "CHECK") {
	//Request to check if the user has liked an app.
	$raw = $data["users"][$unesUid];
	$exists = $raw!=null;
	if($exists==false) {
		//User doesn't exist. Write no
		WriteReply(false,$data["totalLikes"]);
	} else {
		//Check if is 0.
		WriteReply($raw==1,$data["totalLikes"]);
	}
	die();
} else if($actionString=="LIKE") {
	CheckIfAppIsAlreadyLiked($data,1,$uid);
	$data["totalLikes"]+=1;
	$data["users"][$uid] = 1;
} else if($actionString=="DISLIKE") {
	CheckIfAppIsAlreadyLiked($data,0,$uid);
	$data["totalLikes"]-=1;
	$data["users"][$uid] = 0;
} else {
	//Invalid
	WriteError("INVALID_ACTION","The 'action' wasn't valid. Make sure it is 'CHECK','LIKE', or 'DISLIKE'");
}

UpdateLikedApp($appId,$conn,$data["totalLikes"],$data["users"]);

//Write output
WriteReply($data["users"][$uid] == 1,$data["totalLikes"]);



?>
