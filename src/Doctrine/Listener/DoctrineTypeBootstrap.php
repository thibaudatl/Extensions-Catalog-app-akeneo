<?php

namespace App\Doctrine\Listener;

use App\Doctrine\Type\EncryptedStringType;
use App\Security\Encryptor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
class DoctrineTypeBootstrap
{
    public function __construct(private Encryptor $encryptor)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        EncryptedStringType::setEncryptor($this->encryptor);
    }
}
