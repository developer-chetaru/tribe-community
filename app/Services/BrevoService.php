<?php
namespace App\Services;
use GuzzleHttp\Client;
class BrevoService
{
    protected $client;
    protected $apiKey;
  
    /**
     * BrevoService constructor.
     *
     * Initializes the HTTP client and retrieves the API key from environment.
     */
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'timeout'  => 5.0,
        ]);
        $this->apiKey = env('BREVO_API_KEY');
    }
  
  	/**
     * Add or update a contact in Brevo.
     *
     * @param string $email The email address of the contact.
     * @param string|null $firstName Optional first name of the contact.
     * @param string|null $lastName Optional last name of the contact.
     * @return array|false Returns API response as array on success, false on failure.
     */
    public function addContact($email, $firstName = null, $lastName = null)
    {
        try {
            $response = $this->client->post('contacts', [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'api-key' => $this->apiKey,
                ],
                'json' => [
                    'email' => $email,
                    'attributes' => [
                        'FIRSTNAME' => $firstName,
                        'LASTNAME' => $lastName,
                    ],
                    'updateEnabled' => true 
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            \Log::error("Brevo API Error: " . $e->getMessage());
            return false;
        }
    }
}