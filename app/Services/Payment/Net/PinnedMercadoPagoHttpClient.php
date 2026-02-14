<?php

declare(strict_types=1);

namespace App\Services\Payment\Net;

use Exception;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\CurlRequest;
use MercadoPago\Net\HttpRequest;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;

/**
 * Custom Mercado Pago HTTP client with public key pinning support.
 * Uses CURLOPT_PINNEDPUBLICKEY while preserving SDK retry behavior.
 */
class PinnedMercadoPagoHttpClient implements MPHttpClient
{
    private const ONE_MILLISECOND = 1000;

    private HttpRequest $httpRequest;

    public function __construct(
        private readonly string $pinnedPublicKey,
        ?HttpRequest $httpRequest = null
    ) {
        $this->httpRequest = $httpRequest ?? new CurlRequest();
    }

    public function send(MPRequest $request): MPResponse
    {
        $maxRetries = MercadoPagoConfig::getMaxRetries();

        for ($retryCount = 0; $retryCount <= $maxRetries; $retryCount++) {
            try {
                return $this->makeRequest($request);
            } catch (MPApiException $e) {
                $statusCode = $e->getApiResponse()->getStatusCode();
                if ($this->isServerError($statusCode) && !$this->isLastRetry($retryCount)) {
                    $this->doExponentialBackoff($retryCount);
                } else {
                    throw $e;
                }
            } catch (Exception $e) {
                if (!$this->isLastRetry($retryCount)) {
                    $this->doExponentialBackoff($retryCount);
                } else {
                    throw $e;
                }
            }
        }

        throw new Exception('Error processing request. Please try again.');
    }

    private function makeRequest(MPRequest $request): MPResponse
    {
        $requestOptions = $this->createHttpRequestOptions($request);
        $this->httpRequest->setOptionArray($requestOptions);

        $apiResult = $this->httpRequest->execute();
        $statusCode = $this->httpRequest->getInfo(CURLINFO_HTTP_CODE);
        $content = json_decode($apiResult, true);
        $mpResponse = new MPResponse($statusCode, $content);

        if ($apiResult === false) {
            $errorMessage = $this->httpRequest->error();
            $this->httpRequest->close();
            throw new Exception($errorMessage);
        }

        if ($this->isApiError($statusCode)) {
            $this->httpRequest->close();
            throw new MPApiException('Api error. Check response for details', $mpResponse);
        }

        $this->httpRequest->close();

        return $mpResponse;
    }

    private function createHttpRequestOptions(MPRequest $request): array
    {
        $connectionTimeout = $request->getConnectionTimeout() ?: MercadoPagoConfig::getConnectionTimeout();

        $options = [
            CURLOPT_URL => MercadoPagoConfig::$BASE_URL . $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $request->getHeaders(),
            CURLOPT_POSTFIELDS => $request->getPayload(),
            CURLOPT_CONNECTTIMEOUT_MS => $connectionTimeout,
            CURLOPT_MAXCONNECTS => MercadoPagoConfig::getMaxConnections(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if (defined('CURLOPT_PINNEDPUBLICKEY')) {
            $options[CURLOPT_PINNEDPUBLICKEY] = $this->pinnedPublicKey;
        }

        return $options;
    }

    private function doExponentialBackoff(int $retryCount): void
    {
        $exponentialBackoffTime = pow(2, $retryCount);
        $retryDelayMicroseconds = $exponentialBackoffTime * self::ONE_MILLISECOND * MercadoPagoConfig::getRetryDelay();
        usleep($retryDelayMicroseconds);
    }

    private function isServerError(int $statusCode): bool
    {
        return $statusCode >= 500;
    }

    private function isApiError(int $statusCode): bool
    {
        return $statusCode < 200 || $statusCode >= 300;
    }

    private function isLastRetry(int $retryCount): bool
    {
        return $retryCount >= MercadoPagoConfig::getMaxRetries();
    }
}

