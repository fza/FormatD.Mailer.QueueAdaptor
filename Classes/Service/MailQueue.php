<?php

namespace FormatD\Mailer\QueueAdaptor\Service;

use Closure;
use Exception;
use Flowpack\JobQueue\Common\Job\JobManager;
use FormatD\Mailer\QueueAdaptor\Job\MailJob;
use FormatD\Mailer\QueueAdaptor\QueueableEmail;
use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

/**
 * @Flow\Scope("singleton")
 */
class MailQueue
{
	#[Flow\Inject]
	protected JobManager $jobManager;

	#[Flow\InjectConfiguration]
	protected array $settings;

	protected bool $mailQueuingDisabled = false;

	public function enqueueMessage(Email $message, ?Envelope $envelope = null): void
	{
		$job = new MailJob($message, $envelope);
		$queueName = ($message instanceof QueueableEmail ? $message->getQueueName() : null) ?? $this->settings['queueName'];
		$this->jobManager->queue($queueName, $job);
	}

	public function isMailQueuingDisabled(): bool
	{
		return $this->mailQueuingDisabled;
	}

	public function withoutQueuing(Closure $callback): void
	{
		$previousMailQueuingDisabledState = $this->mailQueuingDisabled;
		$this->mailQueuingDisabled = true;
		try {
			$callback->__invoke();
		} catch (Exception $exception) {
			$this->mailQueuingDisabled = $previousMailQueuingDisabledState;
			throw $exception;
		}
		$this->mailQueuingDisabled = $previousMailQueuingDisabledState;
	}
}
