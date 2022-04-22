<?php
    // Class to used for api vaiables.
    class apiVariables {
        private $urlAPIPath;
        private $secret; // private variable to access secret code.
        // private $guid; // private variable to access GUID for meeting ID.
        private $maxParticipants; // Maximum number of participants in a meeting room.
        private $bannerColor; // The background color of the banner.
        private $bannerText; // Text to display in the banner of the meeting room.
        private $userID; // Unique ID for each user.
        private $attendeePSW; // Attendee password.
        private $moderatorPSW; // Moderator Password.

        // Create a constructor for basic variables.
        function __construct($urlAPIPath, $maxParticipants, $bannerColor, $bannerText, $userID, $attendeePSW, $moderatorPSW) {
            $this->urlAPIPath = $urlAPIPath;
            $this->maxParticipants = $maxParticipants;
            $this->bannerColor = $bannerColor;
            $this->bannerText = $bannerText;
            $this->userID = $userID;
            $this->attendeePSW = $attendeePSW;
            $this->moderatorPSW = $moderatorPSW;
        }

        // Class Methods (setters and getters).
        function set_secretKey($secretKey) {
            if ($secretKey == '')
                $this->secret = null;
            else
                $this->secret = $secretKey;
        }

        function get_secretKey() {
            return $this->secret;
        }

        function set_urlAPIPath($urlApiPath) {
            if ($urlAPIPath == '')
                $this->urlAPIPath = null;
            else
                $this->urlAPIPath = $urlAPIPath;
        }

        function get_urlAPIPath() {
            return $this->urlAPIPath;
        }

        function get_maxParticipants() {
            return $this->maxParticipants;
        }

        function get_bannerColor() {
            return $this->bannerColor;
        }

        function get_bannerText() {
            return $this->bannerText;
        }

        function get_userID() {
            return $this->userID;
        }
        
        function get_attendeePSW() {
            return $this->attendeePSW;
        }

        function get_moderatorPSW() {
            return $this->moderatorPSW;
        }
    }
?>