<?php  
// session_start();
include_once("dbfunctions.php");
include_once("bbb_api_class.php");

$apivars = init();
runTask($apivars);

// Function to initialize variables.
function init() {
    // global $urlAPIPath; // Path to the API
    $urlAPIPath = '<place url to BBB server here>'

    // Get the secret key for the BBB server.
    $sqlStr = "Select distinct secret_key from bbb_secrets where url = '" . $urlAPIPath . "'";
    $results = getRecord($sqlStr);
    if ($results->num_rows > 0) {
      // We have a key for the BBB server.
      $row = $results->fetch_assoc();
      $secretKey = $row['secret_key'];
    }
    else { // No secret key found. Abort!
      die('Error: No secret key found for specified server.');
    }

    // Set the maximum number of participants.
    $maxParticipants = 7;

    // The background color of the banner.
    $bannerColor = '%230080ff';

    // Text to display in the banner of the meeting room.
    $bannerText = 'Place%20your%20boiler%20text%20for%20the%20banner%20here';

    // Unique ID for each user.
    $userID = 'CSMP';

    // Attendee's password.
    $attendeePSW = '<place attendee password here>';

    // Moderator's password.
    $moderatorPSW = '<place moderator password here>';

    $apiVariables = new apiVariables($urlAPIPath, $maxParticipants, $bannerColor, $bannerText, $userID, $attendeePSW, $moderatorPSW);

    // Add the secret key to the class object.
    $apiVariables->set_secretKey($secretKey);

    // return the object.
    return $apiVariables;
}

// Function to run specific BBB task.
function runTask($apivars) {
    // Create an array for the results.
    $aResult = array();
    
    // Ensure that a meeting name was passed
    if( !isset($_POST['functionname']) ) {
      $aResult['error'] = 'No function name!'; 
    }
    // Ensure that parameter was also passed.
    if( !isset($_POST['arguments']) ) { 
      $aResult['error'] = 'No function arguments!'; 
    }

    // Call the appropriate function.
    if( !isset($aResult['error']) ) {
      switch($_POST['functionname']) {
          case 'createMeeting':
            if( !is_array($_POST['arguments']) || (count($_POST['arguments']) == 0) ) {
                $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = createMeeting($_POST['arguments'][0], $apivars);
                echo $aResult['result'];
            }
            break;
          case 'joinAttendee':
            if( !is_array($_POST['arguments']) || (count($_POST['arguments']) == 0) ) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = joinAttendee($_POST['arguments'][0], $_POST['arguments'][1], $apivars);
                echo $aResult['result'];
            }
            break;
          case 'joinModerator':
            if( !is_array($_POST['arguments']) || (count($_POST['arguments']) == 0) ) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = joinModerator($_POST['arguments'][0], $_POST['arguments'][1], $apivars);
                echo $aResult['result'];
            }
            break;
          case 'isMeetingRunning':
            if( !is_array($_POST['arguments'])) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = isMeetingRunning($_POST['arguments'][0], $apivars);
                echo "Meeting running: " . $aResult['result'];
            }
            break;
          case 'getMeetingInfo':
            if( !is_array($_POST['arguments'])) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = getMeetingInfo($_POST['arguments'][0], $apivars);
                echo $aResult['result'];
            }
            break;
          case 'endMeeting':
            if( !is_array($_POST['arguments'])) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = endMeeting($_POST['arguments'][0], $apivars);
                echo $aResult['result'];
            }
            break;
          case 'getMeetings':
            if( !is_array($_POST['arguments'])) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = getMeetings($apivars);
                echo $aResult['result'];
            }
            break;
          case 'enterMeeting':
            if( !is_array($_POST['arguments'])) {
              $aResult['error'] = 'Error in arguments!';
            }
            else {
                $aResult['result'] = enterMeeting($apivars);
                echo $aResult['result'];
            }
            break;        
          default:
            $aResult['error'] = 'Function not found.'.$_POST['functionname'].'!';
            break;
      }
    }
}

// Function to create a unique GUID to be used as the meeting id.
function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), 
                  mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), 
                  mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), 
                  mt_rand(0, 65535));
}

// Get meeting id from the database.
function getMeetingID($meetingName) {
    // Prepare SQL select statement.
    $sqlStr = "Select distinct meetingID from mall_sessions "
              . "where room_name = '" . $meetingName . "'";
    $results = getRecord($sqlStr);

    return $results;
}

// Creates a BigBlueButton meeting.
// The create call is idempotent: you can call it multiple times with the same parameters without side effects. 
// This simplifies the logic for joining a user into a session as your application can always call create before 
// returning the join URL to the user. This way, regardless of the order in which users join, the meeting will 
// always exist when the user tries to join (the first create call actually creates the meeting; subsequent calls 
// to create simply return SUCCESS).
function createMeeting($meetingName, $apivars) {
    $curl = curl_init();

    // Flag to be used to determine if a new record is to be inserted into the mall_sessions table.
    $insertMeetingRec = false;
    $updateMeetingRec = false;

    // First check to see if there is a meetingID in the database for the room.
    $result = getMeetingID($meetingName);

    // Check to see if the results contain any rows.
    if ($result->num_rows > 0) {
      // We have an ID that matches the meeting name.
      $row = $result->fetch_assoc();
      $meetingID = $row['meetingID'];
      $updateMeetingRec = true;
    }
    else { // The meeting room is not yet in the database.
      // Generate a GUID for the meeting room.
      $meetingID = GUID();
      $insertMeetingRec = true;
    }
    
    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Calculate the checksum value.
    $str = "createname=" . $meetingName . "&meetingID=" . $meetingID
          . "&attendeePW=" . $apivars->get_attendeePSW()
          . "&moderatorPW=" . $apivars->get_moderatorPSW() 
          . "&logoutURL=<place a url here that the user will be redirected to when they log out.>" 
          . "&maxParticipants=" . $apivars->get_maxParticipants() 
          . '&freeJoin=true'
          . '&bannerColor=' . $apivars->get_bannerColor()
          . '&bannerText=' . $apivars->get_bannerText() 
          . $secret;
    
    $checkSum = sha1($str);

    $urlStr = $apivars->get_urlAPIPath() 
              . "create?name=" . $meetingName
              . "&meetingID=" . $meetingID
              . "&attendeePW=" . $apivars->get_attendeePSW()
              . "&moderatorPW=" . $apivars->get_moderatorPSW() 
              . "&logoutURL=<place a url here that the user will be redirected to when they log out.>" 
              . "&maxParticipants=" . $apivars->get_maxParticipants() 
              . '&freeJoin=true'
              . '&bannerColor=' . $apivars->get_bannerColor()
              . '&bannerText=' . $apivars->get_bannerText() 
              . '&checksum=' . $checkSum;

    curl_setopt_array($curl, array(
    CURLOPT_URL => $urlStr,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        $secretStr 
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      // echo "cURL Error #:" . $err;
      $returnData = array (
        'result' => 'cURL Error #:' . $err
      );
    } else {
    
      if ($insertMeetingRec) {
        // Prepare an insert statement to store the room information.
        $sqlStr = "Insert into mall_sessions "
        . "(room_name, meetingPW, attendeePW, moderatorPW, createTime, num_of_participants, meetingID) "
        . "Values (?,?,?,?,?,?,?)";

        // Get the creation time for the room.
        $xml = new SimpleXMLElement($response);
        $creationTime = $xml->createTime;
        $attendeepwd = $xml->attendeePW;
        $moderatorpwd = $xml->moderatorPW;

        // Assign values to be passed to the array.
        $params = array(
                        "room_name"=>$meetingName,
                        "meetingPW"=>"password",
                        "attendeePW"=>$attendeepwd,
                        "moderatorPW"=>$moderatorpwd,
                        "createTime"=>$creationTime,
                        "num_of_participants"=>1,
                        "meetingID"=>$meetingID
                      );
                      
        if (!insertRecord($sqlStr, $params)) {
          echo 'The meeting room record was not created.';
          return;
        }
      }

      // Check to see if we need to update new meeting creation time.
      if ($updateMeetingRec) {
        // Prepare an insert statement to store the room information.
        // Get the creation time for the room.
        $xml = new SimpleXMLElement($response);
        $creationTime = $xml->createTime;

        $sqlStr = "Update mall_sessions "
                  . "Set createTime = " . $creationTime
                  . " Where meetingID = '" . $meetingID . "'";
                      
        if (!updateRecord($sqlStr)) {
          return 'Error updating meeting room session table.';
        }
      }

      $returnData = array (
        'result' => $response
      );
      // echo ($response);
      return $returnData['result'];
    }
}

// Joins a user to the meeting specified in the meetingID parameter.
// The user joins the meeting as an attendee.
function joinAttendee($meetingName, $attendeeName, $apivars) {
    $curl = curl_init();

    // First check to see if the meeting is running. 
    // If it isn't kill the process.
    if (isMeetingRunning($meetingName, $apivars) == "false") {
      die('The meeting hasn\'t started as yet. Please try again later.');
    } 

    // First check to see if there is a meetingID in the database for the room.
    $result = getMeetingID($meetingName);

    // Check to see if the results contain any rows.
    if ($result->num_rows > 0) {
      // We have an ID that matches the meeting name.
      $row = $result->fetch_assoc();
      $meetingID = $row['meetingID'];
    } 

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Generated new userID;
    $userID = $apivars->get_userID() . '_' . strtoupper(substr($attendeeName,0, 2));

    $str = "joinfullName=" . $attendeeName 
          . "&meetingID=" . $meetingID
          . "&password=" . $apivars->get_attendeePSW()
          . "&redirect=true" 
          . "&userID=" . $userID
          . $secret;
    
    $checkSum = sha1($str);

    // Build up the URL string.
    $urlStr = $apivars->get_urlAPIPath() 
              . 'join?fullName=' . $attendeeName 
              . '&meetingID=' . $meetingID
              . '&password=' . $apivars->get_attendeePSW()
              . '&redirect=true' 
              . '&userID=' . $userID
              . '&checksum=' . $checkSum;

    curl_setopt_array($curl, array(
    CURLOPT_URL => $urlStr,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        $secretStr 
    ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      $returnData = array (
        'result' => 'cURL Error #:' . $err
      );
    } else {   
        $returnData = array (
        'result' => $urlStr
      );
      return $returnData['result'];
    }
}

// Joins a user to the meeting specified in the meetingID parameter.
// The user joins the meeting as a moderator.
function joinModerator($meetingName, $moderatorName, $apivars) {
    $curl = curl_init();

    // First check to see if the meeting is running. 
    // If it isn't start one.
    if (isMeetingRunning($meetingName, $apivars) == "false") {
      // Meeting is not running so call createMeeting.
      $response = createMeeting($meetingName, $apivars);
      // If the meeting was successfully created continue with joining otherwise abort.
      $xml = new SimpleXMLElement($response);
      $returnCode = $xml->returncode;
      if ($returnCode != "SUCCESS") {
        echo 'Unable to start a meeting.';
        die('Unable to start a meeting.');
      }
    } 
      // First check to see if there is a meetingID in the database for the room.
      $result = getMeetingID($meetingName);

      // Check to see if the results contain any rows.
      if ($result->num_rows > 0) {
        // We have an ID that matches the meeting name.
        $row = $result->fetch_assoc();
        $meetingID = $row['meetingID'];
      } 

      $secret = $apivars->get_secretKey();
      $secretStr = "'" . $secret . ": '";
  
      // Generated new userID;
      $userID = 'MOD_' . $apivars->get_userID() . '_' . strtoupper(substr($moderatorName,0, 2));
  
      // Build string for checksum calculation.
      $str = "joinfullName=" . $moderatorName 
            . "&meetingID=" . $meetingID
            . "&password=" . $apivars->get_moderatorPSW()
            . "&redirect=true" 
            . "&userID=" . $userID
            . $secret;
      
      $checkSum = sha1($str);
  
      // Build up the URL string.
      $urlStr = $apivars->get_urlAPIPath() 
                . 'join?fullName=' . $moderatorName 
                . '&meetingID=' . $meetingID 
                . '&password=' . $apivars->get_moderatorPSW()
                . '&redirect=true'
                . '&userID=' . $userID
                . '&checksum=' . $checkSum;
  
      curl_setopt_array($curl, array(
        CURLOPT_URL => $urlStr,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          $secretStr
        ),
      ));
      
      $response = curl_exec($curl);
      $err = curl_error($curl);
      curl_close($curl);
  
      if ($err) {
        $returnData = array (
          'result' => 'cURL Error #:' . $err
        );
      } else {   
          $returnData = array (
          'result' => $urlStr
        );
        return $returnData['result'];
      }
}

// This call enables you to simply check on whether or not a meeting is running by looking it up with your meeting ID.
function isMeetingRunning($meetingName, $apivars) {
    $curl = curl_init();

    // First check to see if there is a meetingID in the database for the room.
    $result = getMeetingID($meetingName);

    // Check to see if the results contain any rows.
    if ($result->num_rows > 0) {
      // We have an ID that matches the meeting name.
      $row = $result->fetch_assoc();
      $meetingID = $row['meetingID'];
    } 
    else {
      return "false";
    }

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Calculate the checksum value.
    $str = "isMeetingRunningmeetingID=" . $meetingID . $secret;
    $checkSum = sha1($str);

    // Build up the URL string.
    $urlStr = $apivars->get_urlAPIPath()
              . 'isMeetingRunning?meetingID=' . $meetingID
              . '&checksum=' . $checkSum;

    curl_setopt_array($curl, array(
      CURLOPT_URL => $urlStr,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        $secretStr
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $xml = new SimpleXMLElement($response);
      $isRunning = $xml->running;
      return $isRunning;
    }
}

// This call will return all of a meeting’s information, including the list of attendees as well as start and end times.
function getMeetingInfo($meetingName, $apivars) {
    $curl = curl_init();

    // First check to see if the meeting is running. 
    // If it isn't kill the process.
    if (isMeetingRunning($meetingName, $apivars) == "false") {
      die('The meeting hasn\'t started as yet. Please try again later.');
    }  

    // First check to see if there is a meetingID in the database for the room.
    $result = getMeetingID($meetingName);

    // Check to see if the results contain any rows.
    if ($result->num_rows > 0) {
      // We have an ID that matches the meeting name.
      $row = $result->fetch_assoc();
      $meetingID = $row['meetingID'];
    } 

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Calculate the checksum value.
    $str = "getMeetingInfomeetingID=" . $meetingID 
          . "&password=" . $apivars->get_attendeePSW() 
          . $secret;

    $checkSum = sha1($str);

    // Build up the URL string.
    $urlStr = $apivars->get_urlAPIPath() 
              . 'getMeetingInfo?meetingID=' . $meetingID
              . '&password=' . $apivars->get_attendeePSW() 
              . '&checksum=' . $checkSum;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $urlStr,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          $secretStr
        ),
      ));
      
      $response = curl_exec($curl);
      $err = curl_error($curl);
      curl_close($curl);
  
      if ($err) {
        echo "cURL Error #:" . $err;
      } else {
        return $response;
      }  
}

// Use this to forcibly end a meeting and kick all participants out of the meeting.
// Please note that when you call end meeting, it is simply sending a request to the 
// backend (Red5) server that is handling all the conference traffic. That backend 
// server will immediately attempt to send every connected client a logout event, 
// kicking them from the meeting. It will then disconnect them, and the meeting will be ended. 
// However, this may take several seconds, depending on network conditions. 
// Therefore, the end meeting call will return a success as soon as the request is sent.
function endMeeting($meetingName, $apivars) {
    $curl = curl_init();

    // First check to see if the meeting is running. 
    // If it isn't kill the process.
    if (isMeetingRunning($meetingName, $apivars) == "false") {
      die('The meeting hasn\'t started as yet. Please try again later.');
    } 

    // First check to see if there is a meetingID in the database for the room.
    $result = getMeetingID($meetingName);

    // Check to see if the results contain any rows.
    if ($result->num_rows > 0) {
      // We have an ID that matches the meeting name.
      $row = $result->fetch_assoc();
      $meetingID = $row['meetingID'];
    } 

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Calculate the checksum value.
    $str = "endmeetingID=" . $meetingID 
          . "&password=" . $apivars->get_moderatorPSW() 
          . $secret;
    $checkSum = sha1($str);

    // Build up the URL string.
    $urlStr = $apivars->get_urlAPIPath() 
              . 'end?meetingID=' . $meetingID
              . '&password=' . $apivars->get_moderatorPSW() 
              . '&checksum=' . $checkSum;
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $urlStr,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        $secretStr
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      return "Meeting " . $meetingName . " ended.";
    }
}

// This call will return a list of all the meetings found on this server.
function getMeetings($apivars) {
    $curl = curl_init();

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    // Calculate the checksum value.
    $str = "getMeetings" . $secret;
    $checkSum = sha1($str); 

    // Build up the URL string.
    $urlStr = $apivars->get_urlAPIPath() 
              . 'getMeetings?checksum=' . $checkSum;
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $urlStr,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        $secretStr
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      return $response;
    }
}

function enterMeeting($apivars) {
    $curl = curl_init();

    $secret = $apivars->get_secretKey();
    $secretStr = "'" . $secret . ": '";

    $urlStr = $apivars->get_urlAPIPath() . 'enter';

    curl_setopt_array($curl, array(
      CURLOPT_URL => $urlStr,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        $secretStr
      ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      return $urlStr;
    }      
}

?>