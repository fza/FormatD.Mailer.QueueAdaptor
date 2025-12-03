<?php

namespace FormatD\Mailer\QueueAdaptor\Transport;

use Exception;
use FormatD\Mailer\QueueAdaptor\Service\MailQueue;
use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class QueuingTransport implements TransportInterface
{
	#[Flow\Inject]
	protected MailQueue $mailQueue;

	public function __construct(
		protected TransportInterface $actualTransport
	)
	{
	}

	/**
	 * @throws TransportExceptionInterface|Exception
	 */
	public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
	{
		if (!($message instanceof Email) || $this->mailQueue->isMailQueuingDisabled()) {
			return $this->actualTransport->send($message, $envelope);
		}

		$this->mailQueue->enqueueMessage($message, $envelope);
		return null;
	}

	public function __toString(): string
	{
		return 'fd-mailer-queue';
	}
}
