<?php

namespace FormatD\Mailer\QueueAdaptor;

use Symfony\Component\Mime\Email;

/**
 * Optional decorator for mail queue classification
 */
class QueueableEmail extends Email
{
	protected ?string $queueName = null;

	public function setQueueName(string $queueName): void
	{
		$this->queueName = $queueName;
	}

	public function getQueueName(): ?string
	{
		return $this->queueName;
	}
}
