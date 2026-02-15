<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recordatorio de pago pendiente</title>
</head>
<body>
<p>Hola {{ $order->shippingAddress?->name ?? 'cliente' }},</p>

<p>Te recordamos que tu pedido <strong>{{ $order->order_number }}</strong> sigue pendiente de pago.</p>

<p>Hasta que no se acredite el pago, el pedido no se confirma ni pasa a preparacion.</p>

<p>Si no se acredita en {{ $expirationHours }} horas desde la compra, el pedido se cancela automaticamente.</p>

<p>Total del pedido: <strong>${{ number_format($order->total, 2, ',', '.') }}</strong></p>
</body>
</html>

