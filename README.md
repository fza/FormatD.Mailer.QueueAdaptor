
FormatD.Mailer.QueueAdaptor
==========

This package changes the mail delivery in Neos (`neos/symfonymailer`) to asynchronously send mails via a queue.
The idea is to make it work as a plug-and-play replacement for every mail generated in the system.

Setup
----------

### Choose a queue backend

This Packages uses flowpack/jobqueue-common (https://github.com/Flowpack/jobqueue-common) to set up a mail queue.
You can choose a backend of your taste, install it via composer and then override the `className` in the configuration:

	Flowpack:
	  JobQueue:
	    Common:
	      queues:
            'fdmailer-mail-queue':
              className: 'Flowpack\JobQueue\Doctrine\Queue\DoctrineQueue'


To use the default db backend just install `flowpack/jobqueue-doctrine`.

### Setup queue

The queue must be set up with this command. See documentation here for details: https://github.com/Flowpack/jobqueue-common.

	./flow queue:setup fdmailer-mail-queue


### Start worker for queue

After these steps mails are put into the queue instead of beeing sent directly during the request.
To send the queued mails run a worker cronjob on CLI:

	# work on max 25 jobs in max 50 sec
	./flow job:work fdmailer-mail-queue --limit 25 --exitAfter 50


Now test if it is working:

	./flow email:send --body "Hello World" from@example.com to@example.com "My Test Mail"

## Send mail via specific queue

All email objects (that are or extend `\Symfony\Component\Mime\Email`) are placed into the default queue
`fdmailer-mail-queue`. The default queue can be configured, see [Settings.yaml](Configuration/Settings.yaml).
Sending mail via a specific queue is also possible:

```php
    $mail = new \FormatD\Mailer\QueueAdaptor\QueueableEmail(); // extends \Symfony\Component\Mime\Email
    $mail->setQueueName('my-queue');
    //...
    $mailerService->getMailer()->send($mail); // Will be intercepted and placed into the specified queue
```

## Send mail immediately (without queue)

```php
    $mailQueue = $this->objectManager->get('\FormatD\Mailer\QueueAdaptor\Service\MailQueue');
    $mailQueue->withoutQueuing(function () {
        $mail = new \Symfony\Component\Mime\Email();
        //...
        $mailerService->getMailer()->send($mail);
    });
```

## Interoperability

* Needs a Neos installation using [`neos/symfonymailer`](https://packagist.org/packages/neos/symfonymailer) (default as of Neos 8.3.24)
* Works with _and without_ [`formatd/mailer`](https://github.com/Format-D/FormatD.Mailer)

## Compatibility

Versioning scheme:

     1.0.0 
     | | |
     | | Bugfix Releases (non breaking)
     | Neos Compatibility Releases (non breaking except framework dependencies)
     Feature Releases (breaking)

Releases und compatibility:

| Package-Version | Neos Flow Version | neos/fusion-form |
|-----------------|-------------------|------------------|
| 1.0.0           | ^8.0              | ^3.0             |
