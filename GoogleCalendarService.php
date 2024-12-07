<?php
require_once 'vendor/autoload.php';

class GoogleCalendarService {
    private $client;
    private $service;

    public function __construct() {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Restaurant Reservation System');
        $this->client->setAuthConfig('credentials.json'); // From Google Cloud Console
        $this->client->addScope(Google_Service_Calendar::CALENDAR_EVENTS);
        $this->client->setRedirectUri('http://your-domain.com/calendar_callback.php');
        $this->client->setAccessType('offline');
        
        $this->service = new Google_Service_Calendar($this->client);
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function setAccessToken($code) {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        $this->client->setAccessToken($token);

        // Store token in session or database
        $_SESSION['google_token'] = $token;
    }

    public function createEvent($reservation) {
        if (isset($_SESSION['google_token'])) {
            $this->client->setAccessToken($_SESSION['google_token']);
        }

        if ($this->client->isAccessTokenExpired()) {
            return ['error' => 'Please authorize calendar access'];
        }

        $event = new Google_Service_Calendar_Event([
            'summary' => 'Restaurant Reservation',
            'location' => 'Your Restaurant Address',
            'description' => "Table: {$reservation['table_name']}\nGuests: {$reservation['guests']}",
            'start' => [
                'dateTime' => $reservation['date'] . 'T' . $reservation['time'],
                'timeZone' => 'Your/Timezone',
            ],
            'end' => [
                'dateTime' => $reservation['date'] . 'T' . date('H:i', strtotime($reservation['time'] . '+2 hours')),
                'timeZone' => 'Your/Timezone',
            ],
            'reminders' => [
                'useDefault' => FALSE,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
        ]);

        try {
            $calendarId = 'primary';
            $event = $this->service->events->insert($calendarId, $event);
            return ['success' => true, 'event_id' => $event->getId()];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
