<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The messages of the HTTP exceptions thrown by the API are written for the user ("Tu n’as pas
 * assez d’argent de poche…", "Seuls les parents ont accès aux comptes."): send them in every
 * environment, not only in debug mode.
 *
 * The framework's own messages (unknown route, entity not found, missing role) are technical and in
 * English: those keep Symfony's generic text. Validation errors keep Symfony's format (violations).
 */
// After the firewall (priority 1), which turns the AccessDeniedException of #[IsGranted(message: …)] into
// an AccessDeniedHttpException; before Symfony's ErrorListener (-128), which renders the response
#[AsEventListener(priority: -64)]
final class ApiErrorListener
{
    /** Messages written by Symfony, not by TribuBudget */
    private const FRAMEWORK_MESSAGES = '/^(No route found for |Access Denied\.)|" object not found by "/';

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface
            || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')
            || $exception->getPrevious() instanceof ValidationFailedException
            || '' === $exception->getMessage()
            || preg_match(self::FRAMEWORK_MESSAGES, $exception->getMessage())) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'status' => $exception->getStatusCode(),
            'detail' => $exception->getMessage(),
        ], $exception->getStatusCode(), $exception->getHeaders() + ['Content-Type' => 'application/problem+json']));
    }
}
