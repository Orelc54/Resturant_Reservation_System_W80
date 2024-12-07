<?php
require_once '/Users/orelc/vendor/autoload.php';

class GoogleCalendarService {
    private $client;
    private $service;

    public function __construct() {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Restaurant Reservation System');
        $this->client->setAuthConfig('/Users/orelc/Desktop/sadna/credentials.json');
        $this->client->addScope(Google_Service_Calendar::CALENDAR_EVENTS);
        $this->client->setRedirectUri('http://localhost:3000/googleCalander/calendar_callback.php');
        $this->client->setAccessType('offline');
        
        // Check for existing token and handle refresh if needed
        if (isset($_SESSION['google_token'])) {
            $this->client->setAccessToken($_SESSION['google_token']);
            
            if ($this->client->isAccessTokenExpired()) {
                // Try to refresh the token
                if ($this->client->getRefreshToken()) {
                    try {
                        $newToken = $this->client->fetchAccessTokenWithRefreshToken(
                            $this->client->getRefreshToken()
                        );
                        $_SESSION['google_token'] = $newToken;
                    } catch (Exception $e) {
                        // If refresh fails, clear the tokens
                        unset($_SESSION['google_token']);
                        unset($_SESSION['refresh_token']);
                    }
                }
            }
        }
        
        $this->service = new Google_Service_Calendar($this->client);
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function setAccessToken($code) {
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                throw new Exception($token['error_description']);
            }
            
            // Store both access and refresh tokens
            $this->client->setAccessToken($token);
            $_SESSION['google_token'] = $token;
            
            if ($this->client->getRefreshToken()) {
                $_SESSION['refresh_token'] = $this->client->getRefreshToken();
            }
        } catch (Exception $e) {
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        }
    }

    public function isAuthenticated() {
        return isset($_SESSION['google_token']) && !$this->client->isAccessTokenExpired();
    }

    public function createEvent($reservation) {
        if (!isset($_SESSION['google_token'])) {
            return ['error' => 'Not authenticated'];
        }

        $this->client->setAccessToken($_SESSION['google_token']);

        if ($this->client->isAccessTokenExpired()) {
            if (isset($_SESSION['refresh_token'])) {
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($_SESSION['refresh_token']);
                    $_SESSION['google_token'] = $newToken;
                } catch (Exception $e) {
                    return ['error' => 'Failed to refresh authentication. Please authorize calendar access again.'];
                }
            } else {
                return ['error' => 'Authentication expired. Please authorize calendar access again.'];
            }
        }

        // Validate required fields
        if (empty($reservation['date']) || empty($reservation['time']) || 
            empty($reservation['table_name']) || empty($reservation['guests'])) {
            return ['error' => 'Missing required reservation details'];
        }

        try {
            // Format the date and time properly
            $startDateTime = date('c', strtotime($reservation['date'] . ' ' . $reservation['time']));
            $endDateTime = date('c', strtotime($reservation['date'] . ' ' . $reservation['time'] . ' +2 hours'));

            $event = new Google_Service_Calendar_Event([
                'summary' => 'Restaurant Reservation',
                'location' => 'Your Restaurant Address',
                'description' => "Table: {$reservation['table_name']}\nGuests: {$reservation['guests']}",
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => 'Asia/Jerusalem',
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'Asia/Jerusalem',
                ],
                'reminders' => [
                    'useDefault' => FALSE,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60],
                        ['method' => 'popup', 'minutes' => 60],
                    ],
                ],
            ]);

            $calendarId = 'primary';
            $createdEvent = $this->service->events->insert($calendarId, $event);
            
            return [
                'success' => true, 
                'event_id' => $createdEvent->getId(),
                'event_link' => $createdEvent->getHtmlLink()
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to create event: ' . $e->getMessage()];
        }
    }

    public function revokeAccess() {
        if (isset($_SESSION['google_token'])) {
            try {
                $this->client->revokeToken($_SESSION['google_token']);
            } catch (Exception $e) {
                // Token might already be invalid
            }
            unset($_SESSION['google_token']);
            unset($_SESSION['refresh_token']);
        }
    }
}
?>