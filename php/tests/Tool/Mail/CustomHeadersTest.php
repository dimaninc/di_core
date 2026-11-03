<?php

namespace diCore\Tests\Tool\Mail;

use diCore\Tool\Mail\Queue;
use diCore\Tool\Mail\Sender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\RequiresMethod;
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

    // Консьюмер на sendmail может жить без PHPMailer – тогда тест пропускается, а не падает
    #[RequiresMethod(PHPMailer::class, 'addCustomHeader')]
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

    #[RequiresMethod(PHPMailer::class, 'addCustomHeader')]
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
        // Перевод строки в имени не рвёт строку лога
        foreach (RecordingSender::$errors as $error) {
            $this->assertStringNotContainsString("\n", $error);
        }
    }

    /**
     * Заголовок, который PHPMailer ставит сам, не дублируется: два From не проходят DMARC,
     * два Content-Type ломают MIME. Регистр имени не важен.
     */
    #[RequiresMethod(PHPMailer::class, 'addCustomHeader')]
    public function testReservedHeaderIsRefused(): void
    {
        $mail = $this->mailer();
        RecordingSender::addCustomHeaders($mail, [
            'from' => 'evil@example.com',
            'Content-Type' => 'text/plain',
            'Reply-To' => 'evil@example.com',
            'X-Ok' => '1',
        ]);
        $this->assertTrue($mail->preSend(), $mail->ErrorInfo);

        $raw = $mail->getSentMIMEMessage();
        $this->assertStringNotContainsString('evil@example.com', $raw);
        $this->assertSame(1, substr_count($raw, 'Content-Type:'));
        $this->assertStringContainsString("X-Ok: 1\r\n", $raw);
        $this->assertCount(3, RecordingSender::$errors);
    }

    #[RequiresMethod(PHPMailer::class, 'addCustomHeader')]
    public function testEmptyValueMeansNoHeader(): void
    {
        $mail = $this->mailer();
        RecordingSender::addCustomHeaders($mail, [
            'X-Null' => null,
            'X-False' => false,
            'X-Blank' => ' ',
        ]);
        $this->assertTrue($mail->preSend(), $mail->ErrorInfo);

        $raw = $mail->getSentMIMEMessage();
        foreach (['X-Null', 'X-False', 'X-Blank'] as $name) {
            $this->assertStringNotContainsString($name, $raw);
        }
        $this->assertSame([], RecordingSender::$errors);
    }

    /**
     * Проект может включить исключения PHPMailer – отказ заголовка всё равно не стоит письма.
     * Свою проверку переводов строк Sender делает раньше, поэтому отказ PHPMailer здесь подставной.
     */
    #[RequiresMethod(PHPMailer::class, 'addCustomHeader')]
    public function testMailerExceptionIsLoggedNotThrown(): void
    {
        $mail = new class (true) extends PHPMailer {
            public function addCustomHeader($name, $value = null)
            {
                throw new \PHPMailer\PHPMailer\Exception('refused');
            }
        };
        RecordingSender::addCustomHeaders($mail, ['X-Fine' => '1']);

        $this->assertSame([], $mail->getCustomHeaders());
        $this->assertCount(1, RecordingSender::$errors);
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
