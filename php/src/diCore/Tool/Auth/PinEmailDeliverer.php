<?php

namespace diCore\Tool\Auth;

use diCore\Data\Config;
use diCore\Data\Configuration;
use diCore\Entity\User\Model as User;
use diCore\Tool\Mail\Queue;

/**
 * Default email delivery: renders `emails/authorization_pin/<purpose name>`
 * (falling back to `emails/authorization_pin/default`, shipped by the core),
 * wraps it into the user mail base template and queues it the way user mails are
 * queued. Every step is a protected hook; a project overrides the template simply
 * by putting a file with the same name into its own `templates/`.
 *
 * Context keys read here: user, language, twig, instant (send now instead of queueing).
 */
class PinEmailDeliverer implements PinDeliverer
{
    const TEMPLATE_FOLDER = 'emails/authorization_pin/';
    const DEFAULT_TEMPLATE = 'default';

    // {code} is replaced with the code. Per language, per purpose name, 'default' as the fallback.
    protected static $subjects = [
        'en' => [
            'default' => 'Confirmation code: {code}',
            'authentication' => '{code} is your sign-in code',
        ],
        'ru' => [
            'default' => 'Код подтверждения: {code}',
            'authentication' => '{code} – код для входа',
        ],
    ];

    public function deliver(\diModel $pin, string $plainValue, array $context): void
    {
        $twig = $this->getTwig($context);
        $data = $this->getTemplateData($pin, $plainValue, $context);
        $body = $twig->parse($this->getTemplateName($twig, $pin, $context), $data);

        $this->queue(
            $this->getSender($pin, $context),
            (string) $pin->getTarget(),
            $this->getSubject($pin, $plainValue, $context),
            $this->wrapHtml($twig, $body, $data),
            $context
        );
    }

    protected function getTwig(array $context): \diTwig
    {
        return $context['twig'] ?? \diTwig::create();
    }

    protected function getLanguage(array $context): string
    {
        return (string) ($context['language'] ?? Config::getMainLanguage());
    }

    protected function getTemplateName(
        \diTwig $twig,
        \diModel $pin,
        array $context
    ): string {
        $name = (string) ($context['purpose_name'] ?? '');

        if ($name !== '' && $twig->exists(static::TEMPLATE_FOLDER . $name)) {
            return static::TEMPLATE_FOLDER . $name;
        }

        return static::TEMPLATE_FOLDER . static::DEFAULT_TEMPLATE;
    }

    protected function getTemplateData(
        \diModel $pin,
        string $plainValue,
        array $context
    ): array {
        return [
            'code' => $plainValue,
            'purpose' => (int) $pin->getPurpose(),
            'purpose_name' => $context['purpose_name'] ?? '',
            'ttl_minutes' => (int) ceil(($context['ttl'] ?? 0) / 60),
            'expired_at' => $pin->getExpiredAt(),
            'user' => $context['user'] ?? null,
            'lang' => $this->getLanguage($context),
            'title' => Config::getSiteTitle(),
            'domain' => Config::getMainDomain(),
            'context' => $context,
        ];
    }

    protected function getSubject(
        \diModel $pin,
        string $plainValue,
        array $context
    ): string {
        $subjects =
            static::$subjects[$this->getLanguage($context)] ??
            static::$subjects['en'];
        $name = (string) ($context['purpose_name'] ?? '');

        return strtr($subjects[$name] ?? $subjects['default'], [
            '{code}' => $plainValue,
        ]);
    }

    protected function getSender(\diModel $pin, array $context)
    {
        return Configuration::get('sender_email');
    }

    protected function wrapHtml(\diTwig $twig, string $body, array $data): string
    {
        return $twig->parse(
            User::MAIL_BASE_TEMPLATE,
            extend($data, ['body' => $body])
        );
    }

    protected function queue(
        $from,
        string $to,
        string $subject,
        string $html,
        array $context
    ): void {
        $queue = Queue::basicCreate();

        if (!empty($context['instant'])) {
            $queue->addAndSend($from, $to, $subject, $html);
        } else {
            $queue->add($from, $to, $subject, $html);
        }
    }
}
