<?php

namespace diCore\Tests\Tool\Mail;

use diCore\Tool\Mail\Queue;
use diCore\Tool\Mail\Sender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Свои заголовки письма (List-Unsubscribe и т.п.): из settings очереди – в опции Sender,
 * из опций – в письмо PHPMailer. Заголовок с переводом строки не попадает в письмо.
 */
class CustomHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingSender::$errors = [];
    }

    public function testQueuedHeadersReachTheSender(): void
    {
        $settings = serialize(['headers' => ['List-Unsubscribe' => '<https://example.com/u/abc>']]);

        $this->assertSame(
            [
                'replyTo' => 'reply@example.com',
                'headers' => ['List-Unsubscribe' => '<https://example.com/u/abc>'],
            ],
            Queue::sendOptions('reply@example.com', $settings)
        );
    }

    public function testLetterWithoutHeadersKeepsTheOldOptions(): void
    {
        $this->assertSame(['replyTo' => ''], Queue::sendOptions(null, ''));
        $this->assertSame(['replyTo' => ''], Queue::sendOptions('', serialize(['other' => 1])));
        // Битая строка не теряет письмо
        $this->assertSame(['replyTo' => ''], Queue::sendOptions('', 'a:1:{broken'));
    }

    public function testStoredObjectsAreNotRevived(): void
    {
        $options = Queue::sendOptions('', serialize(['headers' => new \ArrayObject(['X-A' => '1'])]));

        $this->assertArrayNotHasKey('headers', $options);
    }

    public function testHeadersGoIntoTheMessage(): void
    {
        $mail = $this->mailer();
        RecordingSender::addCustomHeaders($mail, [
            'List-Unsubscribe' => '<https://example.com/u/abc>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
        $this->assertTrue($mail->preSend(), $mail->ErrorInfo);

        $raw = $mail->getSentMIMEMessage();
        $this->assertStringContainsString("List-Unsubscribe: <https://example.com/u/abc>\r\n", $raw);
        $this->assertStringContainsString("List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n", $raw);
        $this->assertSame([], RecordingSender::$errors);
    }

    public function testInjectedHeaderIsDroppedAndLogged(): void
    {
        $mail = $this->mailer();
        RecordingSender::addCustomHeaders($mail, [
            'X-Ok' => '1',
            'X-Bad' => "1\r\nBcc: victim@example.com",
            "X-Name\r\nBcc" => 'victim@example.com',
            'X Space' => '1',
            'X-Array' => ['1'],
        ]);
        $this->assertTrue($mail->preSend(), $mail->ErrorInfo);

        $raw = $mail->getSentMIMEMessage();
        $this->assertStringContainsString("X-Ok: 1\r\n", $raw);
        $this->assertStringNotContainsString('victim@example.com', $raw);
        $this->assertCount(4, RecordingSender::$errors);
    }

    private function mailer(): PHPMailer
    {
        $mail = new PHPMailer();
        $mail->setFrom('from@example.com');
        $mail->addAddress('to@example.com');
        $mail->Subject = 'subj';
        $mail->Body = 'body';

        return $mail;
    }
}

class RecordingSender extends Sender
{
    public static array $errors = [];

    protected static function logError($message)
    {
        static::$errors[] = $message;
    }
}
