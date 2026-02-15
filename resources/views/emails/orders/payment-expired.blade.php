<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido cancelado por falta de pago</title>
</head>
<body>
<p>Hola {{ $order->shippingAddress?->name ?? 'cliente' }},</p>

<p>Tu pedido <strong>{{ $order->order_number }}</strong> fue cancelado automaticamente porque no recibimos el pago dentro de {{ $expirationHours }} horas.</p>

<p>Si el pago se acredita despues, nuestro equipo lo revisa manualmente.</p>

<p>Podes realizar una nueva compra cuando quieras.</p>
</body>
</html>

