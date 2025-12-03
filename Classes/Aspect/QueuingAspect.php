<?php

namespace FormatD\Mailer\QueueAdaptor\Aspect;

use FormatD\Mailer\QueueAdaptor\Service\MailQueue;
use FormatD\Mailer\QueueAdaptor\Transport\QueuingTransport;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use ReflectionException;
use ReflectionObject;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * @Flow\Aspect
 */
class QueuingAspect
{
	#[Flow\InjectConfiguration]
	protected array $settings;

	#[Flow\Inject]
	protected MailQueue $mailQueue;

	/**
	 * When FormatD.Mailer is **not** installed, we augment the Symfony Mailer instance directly
	 *
	 * @Flow\Around("method(Neos\SymfonyMailer\Service\MailerService->getMailer())")
	 * @throws ReflectionException
	 */
	public function decorateTransport(JoinPointInterface $joinPoint): Mailer
	{
		/** @var Mailer $mailer */
		$mailer = $joinPoint->getAdviceChain()->proceed($joinPoint);
		$transportProperty = (new ReflectionObject($mailer))->getProperty('transport');
		$transportProperty->setValue($mailer, new QueuingTransport($transportProperty->getValue($mailer)));
		return $mailer;
	}

	/**
	 * When FormatD.Mailer is installed, the above `decorateMailer()` advice is not always called depending
	 * on configuration. In that case, we intercept the special transport object's `send()` method instead.
	 *
	 * @Flow\Around("method(FormatD\Mailer\Transport\FdMailerTransport->send()) || method(FormatD\Mailer\Transport\InterceptingTransport->send())")
	 */
	public function transportSend(JoinPointInterface $joinPoint): ?SentMessage
	{
		/** @var RawMessage $message */
		$message = $joinPoint->getMethodArgument('message');

		if ($message instanceof Email && !$this->mailQueue->isMailQueuingDisabled()) {
			/** @var ?Envelope $envelope */
			$envelope = $joinPoint->getMethodArgument('envelope');

			// Queue the mail before interception, i.e. before rewrite of the mail headers (To, Bcc, etc.)
			// When the queued mail is released, the mail is intercepted again.
			$this->mailQueue->enqueueMessage($message, $envelope);
			return null;
		}

		return $joinPoint->getAdviceChain()->proceed($joinPoint);
	}
}
