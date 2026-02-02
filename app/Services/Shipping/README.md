# Shipping Service Architecture

## Overview

This shipping integration follows SOLID principles and clean architecture patterns to provide a maintainable, testable, and extensible solution for shipping operations.

## Architecture Layers

### 1. Contracts (Interfaces)
- `ShippingProviderInterface` - Defines the contract all shipping providers must implement
- Follows **Dependency Inversion Principle** - high-level code depends on abstractions

### 2. DTOs (Data Transfer Objects)
Immutable value objects for data transfer:
- `ShippingQuoteRequest` - Quote request parameters
- `ShippingQuoteResponse` - Quote response with options
- `ShippingOption` - Individual shipping option
- `ShipmentCreationResponse` - Shipment creation result
- `TrackingResponse` - Tracking information
- `TrackingEvent` - Individual tracking event

**Benefits:**
- Type safety
- Immutability prevents accidental state changes
- Clear data contracts between layers

### 3. API Client Layer
- `AndreaniApiClient` - **Single Responsibility**: HTTP communication with Andreani API
- Handles authentication, token management, and HTTP requests
- Throws `AndreaniApiException` for API-specific errors

### 4. Provider Layer
- `AndreaniShippingProvider` - **Single Responsibility**: Business logic for Andreani operations
- Implements `ShippingProviderInterface`
- Transforms domain objects to/from API payloads
- Maps provider-specific statuses to internal enums

### 5. Service Orchestration
- `ShippingService` - Main orchestrator
- Coordinates provider operations
- Handles database persistence
- Manages order status updates
- Provides fallback behavior

### 6. Validation & Security
- `WebhookValidator` - Validates webhook signatures using HMAC SHA256

### 7. Exception Hierarchy
- `AndreaniApiException` - API communication errors
- `ShippingQuoteException` - Quote calculation errors
- `ShippingCreationException` - Shipment creation errors
- `ShippingTrackingException` - Tracking errors

## SOLID Principles Applied

### Single Responsibility Principle (SRP)
Each class has ONE reason to change:
- `AndreaniApiClient` - HTTP communication
- `AndreaniShippingProvider` - Business logic
- `ShippingService` - Orchestration & persistence
- `WebhookValidator` - Security validation

### Open/Closed Principle (OCP)
- System is **open for extension** - can add new providers by implementing `ShippingProviderInterface`
- System is **closed for modification** - existing code doesn't change when adding providers

### Liskov Substitution Principle (LSP)
- Any implementation of `ShippingProviderInterface` can be substituted
- `ShippingService` works with any provider implementation

### Interface Segregation Principle (ISP)
- `ShippingProviderInterface` is focused - only essential methods
- No client is forced to depend on methods it doesn't use

### Dependency Inversion Principle (DIP)
- `ShippingService` depends on `ShippingProviderInterface` (abstraction)
- Not on concrete `AndreaniShippingProvider`
- Binding configured in `ShippingServiceProvider`

## Flow Diagrams

### Quote Flow
```
Controller
  → ShippingService.quote()
    → AndreaniShippingProvider.getQuote()
      → AndreaniApiClient.getShippingQuote()
        → [Andreani API]
      ← Response
    ← ShippingQuoteResponse (DTO)
  ← Array (enriched with free shipping)
```

### Shipment Creation Flow
```
OrderController
  → ShippingService.createShipment(Order)
    → DB Transaction Start
    → Create pending Shipment record
    → AndreaniShippingProvider.createShipment(Order)
      → AndreaniApiClient.createShipment()
        → [Andreani API]
      ← Response with tracking number
    ← ShipmentCreationResponse (DTO)
    → Update Shipment with tracking info
    → Update Order status to SHIPPED
    → DB Transaction Commit
  ← Shipment
```

### Webhook Flow
```
Andreani → POST /api/v1/webhooks/andreani
  → WebhookController.andreani()
    → WebhookValidator.validateAndreaniSignature()
    → ShippingService.processWebhook()
      → Find Shipment by tracking number
      → Map status using Provider
      → Update Shipment record
      → Update Order status if delivered
      → Log event
```

## Configuration

### Environment Variables
```env
ANDREANI_USERNAME=your_username
ANDREANI_PASSWORD=your_password
ANDREANI_CONTRACT_NUMBER=your_contract
ANDREANI_ORIGIN_POSTAL_CODE=1425
ANDREANI_SENDER_DOCUMENT=your_cuit
ANDREANI_WEBHOOK_SECRET=your_secret
```

### Service Provider Registration
In `bootstrap/providers.php`:
```php
App\Providers\ShippingServiceProvider::class,
```

### Dependency Injection
The `ShippingServiceProvider` binds:
- `AndreaniApiClient` as singleton
- `AndreaniShippingProvider` as singleton
- `ShippingProviderInterface` to `AndreaniShippingProvider`
- `ShippingService` with auto-injected provider

## Adding a New Provider

To add OCA, Correo Argentino, etc:

1. **Create Provider Class**
```php
class OcaShippingProvider implements ShippingProviderInterface
{
    public function getQuote(ShippingQuoteRequest $request): ShippingQuoteResponse { }
    public function createShipment(Order $order): ShipmentCreationResponse { }
    public function trackShipment(string $trackingNumber): TrackingResponse { }
    public function getName(): string { return 'oca'; }
}
```

2. **Create API Client**
```php
class OcaApiClient { /* HTTP logic */ }
```

3. **Register in ServiceProvider**
```php
$this->app->when(ShippingService::class)
    ->needs(ShippingProviderInterface::class)
    ->give(fn($app) => $app->make(OcaShippingProvider::class));
```

4. **No changes to existing code required!**

## Error Handling Strategy

1. **API Errors** - Wrapped in domain exceptions
2. **Logging** - All errors logged with context
3. **Fallback** - Default quotes when API unavailable
4. **Webhook Errors** - Return 200 to prevent retries
5. **Transactional** - DB operations wrapped in transactions

## Testing Considerations

### Unit Tests
- Mock `AndreaniApiClient` to test `AndreaniShippingProvider`
- Mock `ShippingProviderInterface` to test `ShippingService`
- Test DTOs for immutability and transformations

### Integration Tests
- Test real API calls (separate from unit tests)
- Test webhook signature validation
- Test database transactions

### Example
```php
public function test_quote_with_mocked_provider()
{
    $mockProvider = Mockery::mock(ShippingProviderInterface::class);
    $mockProvider->shouldReceive('getQuote')
        ->andReturn(new ShippingQuoteResponse('test', []));

    $service = new ShippingService($mockProvider);
    $result = $service->quote('1425', 1.0, 1000);

    $this->assertArrayHasKey('options', $result);
}
```

## API Endpoints

### Quote
```
POST /api/v1/shipping/quote
Body: {
  "postal_code": "1425",
  "weight": 1.5,
  "declared_value": 15000
}
```

### Track
```
GET /api/v1/shipping/track/{trackingNumber}
```

### Webhook
```
POST /api/v1/webhooks/andreani
Headers: {
  "X-Andreani-Signature": "hmac_sha256_signature"
}
Body: {
  "numeroAndreani": "123456789",
  "estado": "Entregado",
  "fecha": "2024-01-15T14:30:00"
}
```

## Security

1. **Webhook Signature Validation** - HMAC SHA256
2. **HTTPS Enforcement** - In production
3. **Token Management** - Auto-refresh before expiry
4. **Secrets in .env** - Never committed
5. **Rate Limiting** - Applied to public endpoints

## Benefits of This Architecture

1. **Maintainability** - Clear separation of concerns
2. **Testability** - Easy to mock dependencies
3. **Extensibility** - Add providers without changing existing code
4. **Type Safety** - DTOs prevent runtime errors
5. **Error Handling** - Consistent exception hierarchy
6. **Logging** - Comprehensive audit trail
7. **Performance** - Token caching, singleton services
8. **Security** - Signature validation, HTTPS
