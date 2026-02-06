<?php

namespace Akhelij\LarabaseNotification\Tests\Unit;

use Akhelij\LarabaseNotification\LarabaseMessage;
use Akhelij\LarabaseNotification\LarabaseNotification;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LarabaseMessageTest extends TestCase
{
    public function test_fluent_api_returns_self(): void
    {
        $message = new LarabaseMessage();

        $result = $message
            ->withTitle('Test Title')
            ->withBody('Test Body')
            ->withAdditionalData(['key' => 'value'])
            ->asNotification(['token1']);

        $this->assertInstanceOf(LarabaseMessage::class, $result);
    }

    public function test_with_title_sets_title(): void
    {
        $message = (new LarabaseMessage())->withTitle('Hello');

        $this->assertSame('Hello', $message->title);
    }

    public function test_with_body_sets_body(): void
    {
        $message = (new LarabaseMessage())->withBody('World');

        $this->assertSame('World', $message->body);
    }

    public function test_with_additional_data_casts_to_strings(): void
    {
        $message = (new LarabaseMessage())->withAdditionalData([
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool_true' => true,
            'bool_false' => false,
        ]);

        $this->assertSame('hello', $message->additionalData['string']);
        $this->assertSame('42', $message->additionalData['int']);
        $this->assertSame('3.14', $message->additionalData['float']);
        $this->assertSame('1', $message->additionalData['bool_true']);
        $this->assertSame('', $message->additionalData['bool_false']);
    }

    public function test_with_additional_data_converts_null_to_empty_string(): void
    {
        $message = (new LarabaseMessage())->withAdditionalData(['key' => null]);

        $this->assertSame('', $message->additionalData['key']);
    }

    public function test_with_additional_data_throws_on_array_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FCM data values must be scalar types');

        (new LarabaseMessage())->withAdditionalData(['key' => ['nested']]);
    }

    public function test_with_additional_data_throws_on_object_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LarabaseMessage())->withAdditionalData(['key' => new \stdClass()]);
    }

    public function test_as_notification_filters_null_tokens(): void
    {
        $message = (new LarabaseMessage())->asNotification([null, 'valid-token', null]);

        $this->assertSame(['valid-token'], $message->deviceTokens);
    }

    public function test_as_notification_filters_empty_string_tokens(): void
    {
        $message = (new LarabaseMessage())->asNotification(['', 'valid-token', '']);

        $this->assertSame(['valid-token'], $message->deviceTokens);
    }

    public function test_as_notification_filters_non_string_tokens(): void
    {
        $message = (new LarabaseMessage())->asNotification([123, 'valid-token', true, null]);

        $this->assertSame(['valid-token'], $message->deviceTokens);
    }

    public function test_as_notification_deduplicates_tokens(): void
    {
        $message = (new LarabaseMessage())->asNotification(['token1', 'token1', 'token2']);

        $this->assertSame(['token1', 'token2'], $message->deviceTokens);
    }

    public function test_as_notification_handles_all_invalid_tokens(): void
    {
        $message = (new LarabaseMessage())->asNotification([null, '', false]);

        $this->assertSame([], $message->deviceTokens);
    }

    public function test_with_image_sets_image(): void
    {
        $message = (new LarabaseMessage())->withImage('https://example.com/image.png');

        $this->assertSame('https://example.com/image.png', $message->image);
    }

    public function test_with_android_sets_config(): void
    {
        $config = ['priority' => 'NORMAL', 'notification' => ['color' => '#ff0000']];
        $message = (new LarabaseMessage())->withAndroid($config);

        $this->assertSame($config, $message->androidConfig);
    }

    public function test_with_apns_sets_config(): void
    {
        $config = ['payload' => ['aps' => ['badge' => 5]]];
        $message = (new LarabaseMessage())->withApns($config);

        $this->assertSame($config, $message->apnsConfig);
    }

    public function test_with_webpush_sets_config(): void
    {
        $config = ['notification' => ['icon' => '/icon.png']];
        $message = (new LarabaseMessage())->withWebpush($config);

        $this->assertSame($config, $message->webpushConfig);
    }

    public function test_with_sound_sets_apns_sound(): void
    {
        $message = (new LarabaseMessage())->withSound('custom.caf');

        $this->assertSame('custom.caf', $message->apnsConfig['payload']['aps']['sound']);
    }

    public function test_with_badge_sets_apns_badge(): void
    {
        $message = (new LarabaseMessage())->withBadge(3);

        $this->assertSame(3, $message->apnsConfig['payload']['aps']['badge']);
    }

    public function test_with_click_action_sets_android_click_action(): void
    {
        $message = (new LarabaseMessage())->withClickAction('OPEN_ACTIVITY');

        $this->assertSame('OPEN_ACTIVITY', $message->androidConfig['notification']['click_action']);
    }

    public function test_with_priority_sets_android_priority(): void
    {
        $message = (new LarabaseMessage())->withPriority('NORMAL');

        $this->assertSame('NORMAL', $message->androidConfig['priority']);
    }

    public function test_using_client_sets_custom_client(): void
    {
        $client = new LarabaseNotification('my-project', '/path/to/creds.json');
        $message = (new LarabaseMessage())->usingClient($client);

        $this->assertSame($client, $message->client);
    }

    public function test_default_values(): void
    {
        $message = new LarabaseMessage();

        $this->assertSame('', $message->title);
        $this->assertSame('', $message->body);
        $this->assertSame([], $message->deviceTokens);
        $this->assertSame([], $message->additionalData);
        $this->assertNull($message->image);
        $this->assertNull($message->androidConfig);
        $this->assertNull($message->apnsConfig);
        $this->assertNull($message->webpushConfig);
        $this->assertNull($message->client);
    }
}
