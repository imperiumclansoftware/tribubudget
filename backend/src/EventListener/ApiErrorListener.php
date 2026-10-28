<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The messages of the HTTP exceptions thrown by the API are written for the user ("Tu n’as pas
 * assez d’argent de poche…"): send them in every environment, not only in debug mode.
 * Validation errors keep Symfony's own format (violations).
 */
#[AsEventListener(priority: 10)]
final class ApiErrorListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface
            || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')
            || $exception->getPrevious() instanceof ValidationFailedException
            || '' === $exception->getMessage()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'status' => $exception->getStatusCode(),
            'detail' => $exception->getMessage(),
        ], $exception->getStatusCode(), $exception->getHeaders() + ['Content-Type' => 'application/problem+json']));
    }
}
