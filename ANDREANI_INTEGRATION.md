# Andreani Shipping Integration - Implementation Summary

## Overview

Complete integration with Andreani v2 API following SOLID principles, clean code, and Laravel best practices.

## Architecture

### SOLID Principles Applied

1. **Single Responsibility Principle (SRP)**
   - `AndreaniApiClient` - HTTP communication only
   - `AndreaniShippingProvider` - Business logic only
   - `ShippingService` - Orchestration and persistence
   - `WebhookValidator` - Security validation only

2. **Open/Closed Principle (OCP)**
   - System open for extension via `ShippingProviderInterface`
   - Can add new providers (OCA, Correo Argentino) without modifying existing code

3. **Liskov Substitution Principle (LSP)**
   - Any provider implementing `ShippingProviderInterface` can be substituted
   - `ShippingService` works with any provider

4. **Interface Segregation Principle (ISP)**
   - Focused interface with only essential methods
   - No client forced to implement unused methods

5. **Dependency Inversion Principle (DIP)**
   - High-level `ShippingService` depends on `ShippingProviderInterface` abstraction
   - Concrete implementation bound in `ShippingServiceProvider`

## Files Created

### Core Services (13 PHP files)
```
app/Services/Shipping/
├── Contracts/
│   └── ShippingProviderInterface.php        # Provider contract
├── DTOs/
│   ├── ShippingQuoteRequest.php            # Quote request DTO
│   ├── ShippingQuoteResponse.php           # Quote response DTO
│   ├── ShippingOption.php                  # Individual option DTO
│   ├── ShipmentCreationResponse.php        # Creation response DTO
│   ├── TrackingResponse.php                # Tracking response DTO
│   └── TrackingEvent.php                   # Tracking event DTO
├── Exceptions/
│   ├── AndreaniApiException.php            # API-specific exception
│   └── ShippingTrackingException.php       # Tracking exception
├── AndreaniApiClient.php                   # HTTP client for Andreani API
├── AndreaniShippingProvider.php            # Andreani business logic
├── ShippingService.php                     # Main orchestrator (REFACTORED)
├── WebhookValidator.php                    # Webhook signature validation
└── README.md                               # Architecture documentation
```

### Configuration
```
config/
└── services.php                            # Andreani credentials config

bootstrap/
└── providers.php                           # ShippingServiceProvider registered

app/Providers/
└── ShippingServiceProvider.php             # Dependency injection bindings
```

### Controllers
```
app/Http/Controllers/Api/V1/
├── ShippingController.php                  # Quote & tracking endpoints (UPDATED)
└── WebhookController.php                   # Webhook handler (UPDATED)
```

### Environment
```
.env                                        # Andreani credentials (UPDATED)
.env.example                                # Template with Andreani vars (UPDATED)
```

### Tests
```
tests/Unit/Services/Shipping/
└── ShippingServiceTest.php                 # Unit tests example
```

### Documentation
```
app/Services/Shipping/README.md             # Complete architecture guide
ANDREANI_INTEGRATION.md                     # This file
```

## API Integration Points

### 1. Quote Endpoint
- **URL**: `POST https://apis.andreani.com/v2/envios/tarifa`
- **Authentication**: Bearer token
- **Request**: Origin/destination postal codes, weight, declared value
- **Response**: Array of shipping options with costs and delivery times

### 2. Shipment Creation
- **URL**: `POST https://apis.andreani.com/v2/envios`
- **Authentication**: Bearer token
- **Request**: Complete order data, addresses, package details
- **Response**: Tracking number, label URL, estimated delivery

### 3. Tracking
- **URL**: `GET https://apis.andreani.com/v2/envios/{tracking}/trazas`
- **Authentication**: Bearer token
- **Response**: Array of tracking events with timestamps and locations

### 4. Webhook
- **URL**: `POST {APP_URL}/api/v1/webhooks/andreani`
- **Validation**: HMAC SHA256 signature in `X-Andreani-Signature` header
- **Payload**: Tracking number, status, timestamp

## Public API Endpoints

### Quote Shipping
```http
POST /api/v1/shipping/quote
Content-Type: application/json

{
  "postal_code": "1425",
  "weight": 1.5,
  "declared_value": 15000
}

Response:
{
  "success": true,
  "data": {
    "provider": "andreani",
    "options": [
      {
        "service_code": "Estándar",
        "service_name": "Envío Estándar",
        "cost": 2500.0,
        "estimated_days": 5,
        "description": null
      }
    ],
    "free_threshold": 50000
  }
}
```

### Track Shipment
```http
GET /api/v1/shipping/track/{trackingNumber}

Response:
{
  "success": true,
  "data": {
    "tracking_number": "123456789",
    "status": "en_transito",
    "events": [
      {
        "timestamp": "2024-01-15 14:30:00",
        "status": "En tránsito",
        "description": "Paquete en camino",
        "location": "Buenos Aires"
      }
    ],
    "last_update": "2024-01-15 14:30:00"
  }
}
```

## Configuration

### Environment Variables
```env
# Andreani Shipping
ANDREANI_USERNAME=your_username
ANDREANI_PASSWORD=your_password
ANDREANI_CONTRACT_NUMBER=your_contract_number
ANDREANI_ORIGIN_POSTAL_CODE=1425
ANDREANI_SENDER_DOCUMENT=your_cuit_number
ANDREANI_WEBHOOK_SECRET=your_webhook_secret
```

### Services Config
Located in `config/services.php`:
```php
'andreani' => [
    'username' => env('ANDREANI_USERNAME'),
    'password' => env('ANDREANI_PASSWORD'),
    'contract_number' => env('ANDREANI_CONTRACT_NUMBER'),
    'origin_postal_code' => env('ANDREANI_ORIGIN_POSTAL_CODE'),
    'sender_document' => env('ANDREANI_SENDER_DOCUMENT'),
    'webhook_secret' => env('ANDREANI_WEBHOOK_SECRET'),
],
```

## Error Handling

### Exception Hierarchy
```
Exception
└── RuntimeException
    ├── AndreaniApiException          # API communication errors
    └── BaseException
        ├── ShippingQuoteException    # Quote calculation errors
        ├── ShippingCreationException # Shipment creation errors
        └── ShippingTrackingException # Tracking errors
```

### Logging Strategy
- All API calls logged with request/response data
- All errors logged with full context
- Webhook events logged for audit trail
- Token refresh events logged

### Fallback Behavior
- If Andreani API unavailable, returns default quote
- Prevents checkout failures
- User still sees estimated shipping cost

## Security

### Authentication
- Token-based authentication with auto-refresh
- Tokens cached and renewed 5 minutes before expiry
- Credentials stored in environment variables

### Webhook Validation
- HMAC SHA256 signature validation
- Secret key from environment
- Rejects requests with invalid signatures
- Logs all validation attempts

### HTTPS
- Enforced in production environment
- Certificate verification enabled

## Database Impact

### Shipments Table
Fields updated by integration:
- `tracking_number` - From Andreani response
- `label_url` - PDF label URL
- `estimated_delivery` - Estimated delivery date
- `status` - Updated from webhooks
- `metadata` - Stores full Andreani response
- `shipped_at` - Set on successful creation
- `delivered_at` - Set from webhook

### Orders Table
Status transitions:
- `PENDING` → `SHIPPED` (when shipment created)
- `SHIPPED` → `DELIVERED` (from webhook)

## Testing

### Unit Tests
Mock the provider interface to test in isolation:
```php
$mockProvider = Mockery::mock(ShippingProviderInterface::class);
$service = new ShippingService($mockProvider);
```

### Integration Tests
Test real API calls (requires credentials):
```php
$client = new AndreaniApiClient($username, $password);
$response = $client->getShippingQuote($payload);
```

### Example Test
See `tests/Unit/Services/Shipping/ShippingServiceTest.php`

## Usage Examples

### Get Quote
```php
use App\Services\Shipping\ShippingService;

$shippingService = app(ShippingService::class);

$quote = $shippingService->quote(
    postalCode: '1425',
    weight: 1.5,
    declaredValue: 15000
);
```

### Create Shipment
```php
$order = Order::find($orderId);
$shipment = $shippingService->createShipment($order);

// Shipment created with tracking number
echo $shipment->tracking_number;
```

### Track Shipment
```php
$tracking = $shippingService->trackShipment('123456789');

foreach ($tracking['events'] as $event) {
    echo "{$event['timestamp']}: {$event['description']}\n";
}
```

## Extending with New Providers

To add OCA, Correo Argentino, or other providers:

1. Create API client class
2. Create provider class implementing `ShippingProviderInterface`
3. Register in `ShippingServiceProvider`
4. No changes to existing code required

Example:
```php
class OcaShippingProvider implements ShippingProviderInterface
{
    public function getQuote(ShippingQuoteRequest $request): ShippingQuoteResponse
    {
        // OCA-specific implementation
    }

    // ... implement other methods
}
```

## Benefits of This Implementation

### Maintainability
- Clear separation of concerns
- Each class has single responsibility
- Easy to locate and fix bugs

### Testability
- Dependencies injected via constructor
- Easy to mock interfaces
- Unit tests don't require API calls

### Extensibility
- Add providers without modifying existing code
- Swap providers via configuration
- Support multiple providers simultaneously

### Type Safety
- DTOs prevent runtime errors
- IDE autocomplete support
- Static analysis friendly

### Error Resilience
- Comprehensive exception handling
- Fallback behavior for API failures
- Detailed logging for debugging

### Security
- Webhook signature validation
- Credentials in environment
- HTTPS enforcement
- No secrets in code

## Monitoring & Debugging

### Log Locations
- API calls: `storage/logs/laravel.log`
- Webhook events: Tagged as `Andreani webhook`
- Errors: Full stack traces logged

### Key Metrics to Monitor
- Quote success rate
- Shipment creation success rate
- Webhook processing time
- API response times
- Authentication failures

## Next Steps

1. **Add Credentials**: Update `.env` with Andreani credentials
2. **Test Quote**: Call quote endpoint to verify API connection
3. **Test Shipment**: Create test order and shipment
4. **Configure Webhook**: Register webhook URL with Andreani
5. **Test Webhook**: Trigger test webhook from Andreani dashboard
6. **Monitor Logs**: Check logs for any errors
7. **Add Tests**: Write integration tests for your use cases

## Support

For issues or questions:
1. Check `app/Services/Shipping/README.md` for architecture details
2. Review logs in `storage/logs/laravel.log`
3. Verify credentials in `.env`
4. Check Andreani API documentation: https://developers.andreani.com/

## Conclusion

This implementation provides a production-ready, maintainable, and extensible shipping integration following industry best practices. The architecture allows for easy addition of new providers and supports comprehensive testing and monitoring.
