<?php

namespace Tests\Traits;

trait AssertValidationErrors
{
    /**
     * Assert that the response has validation errors for the given keys in custom format.
     */
    protected function assertCustomValidationErrors($response, array $keys): void
    {
        $response->assertStatus(422);
        
        $json = $response->json();
        
        $this->assertArrayHasKey('error', $json, 'Response should have error key');
        $this->assertArrayHasKey('details', $json['error'], 'Error should have details key');
        
        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $json['error']['details'],
                "Validation error for '{$key}' not found in response"
            );
        }
    }
}
