<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:08
 */

namespace diCore\Tool\Mail;

use diCore\Data\Types;
use diCore\Entity\MailIncut\Model as IncutModel;
use diCore\Entity\MailIncut\Collection as IncutCollection;
use diCore\Entity\MailQueue\Collection;
use diCore\Entity\MailQueue\Model;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;

class Queue
{
    use BasicCreate;

    const INSTANT_SEND = false;

    const SEND_PER_ATTEMPT = 1000;

    const STORED_NEWS_ID_TARGET_TYPE = Types::news;

    private $incuts = [];

    private $lastError = Error::NONE;

    public static function isRecipient($ar)
    {
        return !is_array($ar) || isset($ar['email']);
    }

    public function add(
        $from,
        $to,
        $subject,
        $body,
        $settings = [],
        $attachments = [],
        $incutIds = []
    ) {
        if (static::isRecipient($to)) {
            $to = [$to];
        }

        if (!is_array($settings)) {
            $settings = [
                'plainBody' => $settings,
            ];
        }

        $settings = extend(
            [
                'plainBody' => false,
                'replyTo' => '',
            ],
            $settings
        );

        $otherSettings = $settings;
        unset($otherSettings['plainBody']);
        unset($otherSettings['replyTo']);

        if (!is_array($incutIds)) {
            $incutIds = explode(',', $incutIds);
        }

        $ids = [];

        foreach ($to as $singleTo) {
            if (!$singleTo) {
                Logger::getInstance()->log(
                    "Empty recipient with subj='$subject'",
                    'Queue::add'
                );

                continue;
            }

            $model = Model::create()
                ->setSender($from)
                ->setRecipient($singleTo)
                ->setReplyTo($settings['replyTo'])
                ->setSubject($subject)
                ->setBody($body)
                ->setPlainBody($settings['plainBody'] ? 1 : 0)
                ->setIncutIds(join(',', $incutIds))
                ->setSettings($otherSettings ? serialize($otherSettings) : '')
                ->setSent(0);

            if (!is_array($attachments) && $attachments) {
                $model->setAttachment('')->setNewsId((int) $attachments);
            } else {
                $model->setAttachment(serialize($attachments));
            }

            $model->save();

            $ids[] = $model->getId();
        }

        return $ids;
    }

    public function addAndSend(
        $from,
        $to,
        $subject,
        $body,
        $settings = [],
        $attachments = [],
        $incutIds = ''
    ) {
        $ids = $this->add(
            $from,
            $to,
            $subject,
            $body,
            $settings,
            $attachments,
            $incutIds
        );

        foreach ($ids as $id) {
            $this->send($id);
        }

        return $this;
    }

    public function addAndMayBeSend(
        $from,
        $to,
        $subject,
        $body,
        $settings = [],
        $attachments = [],
        $incutIds = ''
    ) {
        $ids = $this->add(
            $from,
            $to,
            $subject,
            $body,
            $settings,
            $attachments,
            $incutIds
        );

        if (static::INSTANT_SEND) {
            foreach ($ids as $id) {
                $this->send($id);
            }
        }

        return $this;
    }

    public function processIncuts(Model $model)
    {
        $incutIds = $model->hasIncutIds() ? explode(',', $model->getIncutIds()) : [];

        if ($incutIds) {
            foreach ($incutIds as $incutId) {
                $incutId = (int) $incutId;
                $token = static::incutToken($incutId);

                if ($incutId && !isset($this->incuts[$token])) {
                    /** @var IncutModel $incut */
                    $incut = \diModel::create(Types::mail_incut, $incutId);

                    if ($incut->exists()) {
                        $this->incuts[$token] = $incut->getContent();
                    }
                }
            }

            $model->setBody(
                str_replace(
                    array_keys($this->incuts),
                    array_values($this->incuts),
                    $model->getBody()
                )
            );
        }

        return $this;
    }

    public function getAttachments(Model $model)
    {
        if (!$model->hasNewsId()) {
            return $model->hasAttachment()
                ? unserialize($model->getAttachment())
                : [];
        }

        $col = IncutCollection::create()
            //->filterByType(Type::binary_attachment)
            ->filterByTargetType(static::STORED_NEWS_ID_TARGET_TYPE)
            ->filterByTargetId($model->getNewsId());

        /** @var \diCore\Entity\MailIncut\Model $incut */
        $incut = $col->getFirstItem();

        return $incut->exists() && $incut->hasContent()
            ? unserialize($incut->getContent())
            : [];
    }

    // by default the first message is being sent
    public function send(int|string|null $id = null)
    {
        $message = $id
            ? Model::createById($id)
            : Collection::createActual()->getFirstItem();

        if ($message->exists()) {
            return $this->sendMessage($message);
        }

        return false;
    }

    public function sendWorker(
        $from,
        $to,
        $subject,
        $bodyPlain,
        $bodyHtml,
        $attachments = [],
        $options = []
    ) {
        if (!is_array($to)) {
            $to = [$to];
        }

        $sender = Sender::basicCreate();

        $res = true;

        foreach ($to as $singleTo) {
            try {
                if (
                    !$sender->send(
                        $from,
                        $singleTo,
                        $subject,
                        $bodyPlain,
                        $bodyHtml,
                        $attachments,
                        $options
                    )
                ) {
                    $res = false;

                    $this->setLastError(Error::UNKNOWN_FATAL);
                }
            } catch (\Exception $e) {
                Logger::getInstance()->log($e->getMessage(), 'Queue::sendWorker');
                $res = false;
            }
        }

        return $res;
    }

    public function sendAll($limit = 0): int
    {
        $counter = 0;
        $messages = Collection::createActual();

        if ($limit) {
            $messages->setPageSize($limit);
        }

        /** @var Model $message */
        foreach ($messages as $message) {
            if ($this->sendMessage($message)) {
                $counter++;
            }

            if ($limit && $counter > $limit) {
                break;
            }
        }

        return $counter;
    }

    public function sendAllSafe($limit = 0): int
    {
        $counter = 0;
        $messages = Collection::createActual();

        if ($limit) {
            $messages->setPageSize($limit);
        }

        /** @var Model $message */
        foreach ($messages as $message) {
            $actualMessage = Model::createById($message->getId());

            if ($actualMessage->hasVisible() && !$actualMessage->hasSent()) {
                if ($this->sendMessage($actualMessage)) {
                    $counter++;
                }
            }

            if ($limit && $counter > $limit) {
                break;
            }
        }

        return $counter;
    }

    protected function sendMessage(Model $message): bool
    {
        $message->setVisible(0)->save();

        $attachments = $this->getAttachments($message);
        $this->processIncuts($message);

        $bodyPlain = $message->hasPlainBody() ? $message->getBody() : '';
        $bodyHtml = $message->hasPlainBody() ? '' : $message->getBody();

        $options = [
            'replyTo' => $message->getReplyTo(),
        ];

        $result = $this->sendWorker(
            $message->getSender(),
            $message->getRecipient(),
            $message->getSubject(),
            $bodyPlain,
            $bodyHtml,
            $attachments,
            $options
        );

        if ($result) {
            $this->setMessageSent($message);
        }

        return $result;
    }

    private function setMessageSent(Model $message)
    {
        $message->hardDestroy();

        return $this;
    }

    public function getLastError(): int
    {
        return $this->lastError;
    }

    protected function setLastError(int $lastError = Error::NONE)
    {
        $this->lastError = $lastError;

        return $this;
    }

    protected function isLastErrorFatal(): bool
    {
        return !$this->isLastErrorLite();
    }

    protected function isLastErrorLite(): bool
    {
        return in_array($this->getLastError(), [
            Error::NONE,
            Error::QUEUE_IS_EMPTY,
            Error::NO_CREDENTIALS,
        ]);
    }

    public static function incutToken($id)
    {
        return IncutModel::token($id);
    }

    public function setVisible()
    {
        $conn = Collection::getConnection();
        $db = $conn->getDb();

        if ($conn::isRelational()) {
            $db->update(
                Types::getTable(Collection::type),
                [
                    'visible' => 1,
                ],
                'WHERE ' . $db->escapeFieldValue('visible', 0)
            );
        } else {
            Collection::create()
                ->selectId()
                ->filterByVisible(0)
                ->update([
                    'visible' => 1,
                ]);
        }

        return $this;
    }
}
