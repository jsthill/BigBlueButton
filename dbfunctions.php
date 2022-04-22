<?php
// Functions to do the database lifting.

// Function to open connection to database.
function connectDB() {
    $servername = "localhost";
    $username = "<place db username here>";
    $password = "<DB user password>";
    $dbname = "<DB name>";
  
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check the connection.
    if ($conn->connect_error) { 
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Function to close database connection.
function closeDBConnection($conn) {
    $conn->close();
}

// Function to insert a record into the database.
function insertRecord($sqlStr, $params) {
    $conn = connectDB();

    // Prepare the bind SQL statement.
    $stmt = $conn->prepare($sqlStr);
    $stmt->bind_param("ssssiis",
                    $roomname, $meetingPW, $attendeePW, 
                    $moderatorPW, $createTime, $numofParticipants, $meetingID);
                   
    $roomname = $params['room_name'];
    $meetingPW = $params['meetingPW'];
    $attendeePW = $params['attendeePW'];
    $moderatorPW = $params['moderatorPW'];
    $createTime = $params['createTime'];
    $numofParticipants = $params['num_of_participants'];
    $meetingID = $params['meetingID'];

    // Execute the query.
    $result = $stmt->execute();

    // Now close the statement.
    $stmt->close();
    // Close the connection.
    closeDBConnection($conn);
    // Return the results.
    return $result;
}

// Function to read record from the database and return to calling procedure.
function getRecord($sqlStr) {
    $conn = connectDB();

    // Execute the query.
    $result = $conn->query($sqlStr);
    // Close the connection.
    closeDBConnection($conn);
    // Return the results.
    return $result;
}

// Function to update record in the database and return result to calling procedure.
function updateRecord($sqlStr) {
    $conn = connectDB();

    // Prepare the bind SQL statement.
    $stmt = $conn->prepare($sqlStr);

    // Execute the query.
    $result = $stmt->execute();

    // Now close the statement.
    $stmt->close();
    // Close the connection.
    closeDBConnection($conn);
    // Return the results.
    return $result; 
}
?>