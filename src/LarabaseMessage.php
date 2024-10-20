<?php

namespace Akhelij\LarabaseNotification;

class LarabaseMessage
{
    public $title;
    public $body;
    public $deviceTokens = [];
    public $additionalData = [];

    /**
     * Set the title for the notification.
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the body for the notification.
     *
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set additional data for the notification.
     *
     * @param array $data
     * @return $this
     */
    public function setAdditionalData(array $data)
    {
        $this->additionalData = $data;
        return $this;
    }

    /**
     * Set the device tokens for the notification.
     *
     * @param array $tokens
     * @return $this
     */
    public function asNotification(array $tokens)
    {
        $this->deviceTokens = $this->validateToken($tokens);
        return $this;
    }


    private function validateToken($tokens)
    {
        if (is_array($tokens)) {
            return $tokens;
        }

        if (is_string($tokens)) {
            return explode(',', $tokens);
        }

        throw new \Exception('Please pass tokens as array [token1, token2] or as string (use comma as separator if multiple passed).');
    }
}
