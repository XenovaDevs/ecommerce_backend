<?php

use App\Broadcasting\OrderChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{userId}', OrderChannel::class);
