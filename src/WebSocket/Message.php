<?php

namespace ResizeServer\WebSocket;

class Message
{
    public static function buildNext(): Message
    {
        return new static('requestNext', 'all');
    }

    public static function buildNotification(string $key, $value): Message
    {
        $return = new static('notification', 'all');
        $return->key = $key;
        $return->value = $value;

        return $return;
    }

    public function __construct(string $type, string $destination = null)
    {
        $this->type = $type;
        if ($destination) {
            $this->destination = $destination;
        }
    }

    public function toJson(): ?string
    {
        try {
            $return = json_encode($this);
            return $return;
        } catch (Exception $e) {
            return null;
        }
    }

    public function toStdClass(): ?object
    {
        try {
            $return = json_decode($this->toJson());
            return $return;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getDestination(): ?string
    {
        if (property_exists($this, 'destination')) {
            return $this->destination;
        }

        return null;
    }
}
