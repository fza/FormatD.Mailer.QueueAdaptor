<?php

namespace FormatD\Mailer\QueueAdaptor\Job;

use Exception;
use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use FormatD\Mailer\QueueAdaptor\Service\MailQueue;
use Neos\Cache\Exception as NeosCacheException;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\SymfonyMailer\Service\MailerService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

/**
 * @Flow\Scope("prototype")
 */
class MailJob implements JobInterface
{
	#[Flow\InjectConfiguration(path: 'serializationCache', package: 'FormatD.Mailer.QueueAdaptor')]
	protected array $serializationCacheSettings;

	#[Flow\Inject]
	protected MailQueue $mailQueue;

	/**
	 * Factory-backed objects (like cache) are **always** proxified by the Flow Object Manager, regardless if they're lazy or not.
	 * Such proxy objects do not extend the original class, thus a direct property type declaration gives a TypeError.
	 * @var StringFrontend
	 */
	#[Flow\Inject]
	protected mixed $mailDataCache = null;

	#[Flow\Inject]
	protected MailerService $mailerService;

	protected ?string $emailCacheIdentifier = null;

	public function __construct(
		protected Email     $email,
		protected ?Envelope $envelope = null
	)
	{
	}

	/**
	 * Execute the job
	 * A job should finish itself after successful execution using the queue methods.
	 *
	 * @throws Exception
	 */
	public function execute(QueueInterface $queue, Message $message): bool
	{
		$this->tryRestoreDataFromCache();

		if ($this->email) {
			$this->mailQueue->withoutQueuing(function () {
				$this->mailerService->getMailer()->send($this->email, $this->envelope);
			});
			return true;
		}

		// In case the mail job is executed after the email data has already been evicted from cache, we obviously cannot proceed.
		throw new Exception('Email data is no longer available in cache, so email cannot be sent.');
	}

	public function getLabel(): string
	{
		return $this->email->getSubject();
	}

	/**
	 * Serialize the email to (file) cache so we don't need to store big email data incl. attachments in the queue
	 *
	 * @return string[]
	 * @throws NeosCacheException|InvalidDataException
	 */
	public function __sleep(): array
	{
		if (($this->serializationCacheSettings['enabled'] ?? false) && $this->mailDataCache) {
			$this->emailCacheIdentifier = sprintf('email-%s', Uuid::uuid4());
			$data = [
				'email' => $this->email,
				'envelope' => $this->envelope,
			];
			$this->mailDataCache->set($this->emailCacheIdentifier, serialize($data), [], 172800); // 48h lifetime
			return ['emailCacheIdentifier'];
		}

		return ['email', 'envelope'];
	}

	protected function tryRestoreDataFromCache(): void
	{
		if ($this->emailCacheIdentifier && ($serializedData = $this->mailDataCache?->get($this->emailCacheIdentifier))) {
			$data = unserialize($serializedData);
			$this->email = $data['email'];
			$this->envelope = $data['envelope'];
		}
	}
}
