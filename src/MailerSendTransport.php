<?php

namespace MailerSend\LaravelDriver;

use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Arr;
use MailerSend\Helpers\Builder\Attachment;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

class MailerSendTransport extends Transport
{
    protected array $config;

    protected MailerSend $mailersend;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->mailersend = new MailerSend([
            'api_key' => Arr::get($this->config, 'api_key'),
            'host' => Arr::get($this->config, 'host'),
            'protocol' => Arr::get($this->config, 'protocol'),
            'api_path' => Arr::get($this->config, 'api_path'),
        ]);
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        ['email' => $fromEmail, 'name' => $fromName] = $this->getFrom($message);
        ['text' => $text, 'html' => $html] = $this->getContents($message);

        $this->mailersend->email->send(
            $fromEmail,
            $fromName,
            $this->getTo($message),
            $message->getSubject(),
            $text,
            $html,
            null, // TODO Template
            [], // TODO Tags
            [], // TODO Variables
            $this->getAttachments($message),
        );

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    protected function getFrom(Swift_Mime_SimpleMessage $message): array
    {
        if ($message->getFrom()) {
            foreach ($message->getFrom() as $email => $name) {
                return ['email' => $email, 'name' => $name];
            }
        }
        return ['email' => '', 'name' => ''];
    }

    protected function getTo(Swift_Mime_SimpleMessage $message): array
    {
        $recipients = [];

        if ($message->getTo()) {
            foreach ($message->getTo() as $email => $name) {
                $recipients[] = new Recipient($email, $name);
            }
        }

        return $recipients;
    }

    protected function getContents(Swift_Mime_SimpleMessage $message): array
    {
        $content = [
            'text' => '',
            'html' => '',
        ];

        switch ($message->getContentType()) {
            case 'text/plain':
                $content['text'] = $message->getBody();

                return $content;
            case 'text/html':
                $content['html'] = $message->getBody();

                return $content;
        }

        // RFC 1341 - text/html after text/plain in multipart

        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_MimePart && $child->getContentType() === 'text/plain') {
                $content['text'] = $child->getBody();
            }
        }

        if (is_null($message->getBody())) {
            return $content;
        }

        $content['html'] = $message->getBody();

        return $content;
    }

    protected function getAttachments(Swift_Mime_SimpleMessage $message): array
    {
        $attachments = [];

        foreach ($message->getChildren() as $attachment) {
            $attachments[] = new Attachment($attachment->getBody(), $attachment->getFilename());
        }

        return $attachments;
    }
}