<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido pendiente de pago</title>
</head>
<body>
<p>Hola {{ $order->shippingAddress?->name ?? 'cliente' }},</p>

<p>Recibimos tu pedido <strong>{{ $order->order_number }}</strong>, pero todavia no se encuentra confirmado.</p>

<p>La confirmacion final se realiza cuando el pago queda acreditado. Si no se acredita en {{ $expirationHours }} horas, el pedido se cancela automaticamente.</p>

<p>Total del pedido: <strong>${{ number_format($order->total, 2, ',', '.') }}</strong></p>

<p>Gracias por comprar con nosotros.</p>
</body>
</html>

