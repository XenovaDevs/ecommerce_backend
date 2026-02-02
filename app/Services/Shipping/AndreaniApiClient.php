<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Services\Shipping\Exceptions\AndreaniApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP Client for Andreani API v2.
 * Single Responsibility: Handle HTTP communication with Andreani API.
 */
class AndreaniApiClient
{
    private const BASE_URL = 'https://apis.andreani.com/v2';
    private const TIMEOUT = 30;

    private string $username;
    private string $password;
    private ?string $token = null;
    private ?\DateTimeImmutable $tokenExpiry = null;

    public function __construct(?string $username = null, ?string $password = null)
    {
        $this->username = $username ?? config('services.andreani.username');
        $this->password = $password ?? config('services.andreani.password');

        if (empty($this->username) || empty($this->password)) {
            throw new \RuntimeException('Andreani credentials not configured');
        }
    }

    /**
     * Get shipping quote from Andreani.
     *
     * @throws AndreaniApiException
     */
    public function getShippingQuote(array $payload): array
    {
        try {
            $response = $this->makeRequest('POST', '/envios/tarifa', $payload);

            if (empty($response['tarifas'])) {
                throw new AndreaniApiException(
                    'No shipping options available',
                    200,
                    $response
                );
            }

            return $response;
        } catch (\Exception $e) {
            $this->logError('quote', $e, $payload);
            throw $this->wrapException($e);
        }
    }

    /**
     * Create shipment in Andreani.
     *
     * @throws AndreaniApiException
     */
    public function createShipment(array $payload): array
    {
        try {
            $response = $this->makeRequest('POST', '/envios', $payload);

            if (empty($response['numeroAndreani'])) {
                throw new AndreaniApiException(
                    'Shipment creation failed: no tracking number received',
                    200,
                    $response
                );
            }

            return $response;
        } catch (\Exception $e) {
            $this->logError('create_shipment', $e, $payload);
            throw $this->wrapException($e);
        }
    }

    /**
     * Get tracking information from Andreani.
     *
     * @throws AndreaniApiException
     */
    public function getTracking(string $trackingNumber): array
    {
        try {
            $response = $this->makeRequest(
                'GET',
                "/envios/{$trackingNumber}/trazas"
            );

            return $response;
        } catch (\Exception $e) {
            $this->logError('tracking', $e, ['tracking_number' => $trackingNumber]);
            throw $this->wrapException($e);
        }
    }

    /**
     * Make authenticated HTTP request to Andreani API.
     *
     * @throws AndreaniApiException
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $client = $this->buildHttpClient();

        $response = match (strtoupper($method)) {
            'GET' => $client->get(self::BASE_URL . $endpoint),
            'POST' => $client->post(self::BASE_URL . $endpoint, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            throw new AndreaniApiException(
                "Andreani API request failed: {$response->status()}",
                $response->status(),
                $response->json() ?? []
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Build HTTP client with authentication.
     */
    private function buildHttpClient(): PendingRequest
    {
        $this->ensureValidToken();

        return Http::timeout(self::TIMEOUT)
            ->withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withOptions([
                'verify' => config('app.env') === 'production',
            ]);
    }

    /**
     * Ensure we have a valid authentication token.
     *
     * @throws AndreaniApiException
     */
    private function ensureValidToken(): void
    {
        if ($this->isTokenValid()) {
            return;
        }

        $this->authenticate();
    }

    /**
     * Check if current token is still valid.
     */
    private function isTokenValid(): bool
    {
        if (!$this->token || !$this->tokenExpiry) {
            return false;
        }

        // Refresh token 5 minutes before expiry
        $now = new \DateTimeImmutable();
        $bufferTime = $this->tokenExpiry->modify('-5 minutes');

        return $now < $bufferTime;
    }

    /**
     * Authenticate with Andreani API and get token.
     *
     * @throws AndreaniApiException
     */
    private function authenticate(): void
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->post(self::BASE_URL . '/login', [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            if ($response->failed()) {
                throw new AndreaniApiException(
                    'Andreani authentication failed',
                    $response->status(),
                    $response->json() ?? []
                );
            }

            $data = $response->json();
            $this->token = $data['token'] ?? null;

            if (!$this->token) {
                throw new AndreaniApiException(
                    'No token received from Andreani',
                    200,
                    $data
                );
            }

            // Andreani tokens typically expire in 24 hours
            $this->tokenExpiry = (new \DateTimeImmutable())->modify('+24 hours');

            Log::info('Andreani authentication successful');
        } catch (AndreaniApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AndreaniApiException(
                'Authentication request failed: ' . $e->getMessage(),
                0,
                [],
                $e
            );
        }
    }

    /**
     * Wrap exception in AndreaniApiException if needed.
     */
    private function wrapException(\Exception $e): AndreaniApiException
    {
        if ($e instanceof AndreaniApiException) {
            return $e;
        }

        return new AndreaniApiException(
            'Andreani API error: ' . $e->getMessage(),
            0,
            [],
            $e
        );
    }

    /**
     * Log API errors for debugging.
     */
    private function logError(string $operation, \Exception $e, array $context = []): void
    {
        Log::error("Andreani API error during {$operation}", [
            'exception' => $e->getMessage(),
            'context' => $context,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
