<?php

namespace Akhelij\LarabaseNotification;

use InvalidArgumentException;

class LarabaseMessage
{
    public string $title = '';

    public string $body = '';

    public array $deviceTokens = [];

    public array $additionalData = [];

    public ?string $image = null;

    public ?array $androidConfig = null;

    public ?array $apnsConfig = null;

    public ?array $webpushConfig = null;

    public ?LarabaseNotification $client = null;

    /**
     * Set the title for the notification.
     */
    public function withTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the body for the notification.
     */
    public function withBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set additional data for the notification.
     *
     * FCM requires all data values to be strings. Scalar values will be cast
     * to string automatically. Null values become empty strings.
     *
     * @throws InvalidArgumentException if a non-scalar value is provided
     */
    public function withAdditionalData(array $data): static
    {
        $this->additionalData = array_map(function ($value) {
            if (is_null($value)) {
                return '';
            }

            if (! is_scalar($value)) {
                throw new InvalidArgumentException(
                    'FCM data values must be scalar types (string, int, float, bool). '
                    . 'Got: ' . get_debug_type($value)
                );
            }

            return (string) $value;
        }, $data);

        return $this;
    }

    /**
     * Set the notification image URL.
     */
    public function withImage(string $imageUrl): static
    {
        $this->image = $imageUrl;

        return $this;
    }

    /**
     * Set Android-specific configuration.
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#AndroidConfig
     */
    public function withAndroid(array $config): static
    {
        $this->androidConfig = $config;

        return $this;
    }

    /**
     * Set APNs (iOS) specific configuration.
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#ApnsConfig
     */
    public function withApns(array $config): static
    {
        $this->apnsConfig = $config;

        return $this;
    }

    /**
     * Set Webpush-specific configuration.
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#WebpushConfig
     */
    public function withWebpush(array $config): static
    {
        $this->webpushConfig = $config;

        return $this;
    }

    /**
     * Set the notification sound (APNS).
     */
    public function withSound(string $sound = 'default'): static
    {
        $this->apnsConfig = array_replace_recursive($this->apnsConfig ?? [], [
            'payload' => ['aps' => ['sound' => $sound]],
        ]);

        return $this;
    }

    /**
     * Set the badge count (APNS).
     */
    public function withBadge(int $count): static
    {
        $this->apnsConfig = array_replace_recursive($this->apnsConfig ?? [], [
            'payload' => ['aps' => ['badge' => $count]],
        ]);

        return $this;
    }

    /**
     * Set the click action (Android).
     */
    public function withClickAction(string $action): static
    {
        $this->androidConfig = array_replace_recursive($this->androidConfig ?? [], [
            'notification' => ['click_action' => $action],
        ]);

        return $this;
    }

    /**
     * Set the Android notification priority.
     */
    public function withPriority(string $priority = 'HIGH'): static
    {
        $this->androidConfig = array_replace_recursive($this->androidConfig ?? [], [
            'priority' => $priority,
        ]);

        return $this;
    }

    /**
     * Use a custom LarabaseNotification client (e.g. for multi-tenant Firebase projects).
     */
    public function usingClient(LarabaseNotification $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the device tokens for the notification.
     *
     * Filters out null, empty, and non-string values. Deduplicates tokens.
     */
    public function asNotification(array $tokens): static
    {
        $this->deviceTokens = array_values(
            array_unique(
                array_filter($tokens, fn ($token) => is_string($token) && $token !== '')
            )
        );

        return $this;
    }
}
