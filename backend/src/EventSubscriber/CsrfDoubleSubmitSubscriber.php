<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * "Double submit" CSRF protection, the one Angular's HttpClient supports natively:
 * - every response sets a random XSRF-TOKEN cookie (readable by the app, SameSite=Strict) when the
 *   browser has none yet;
 * - every /api request that changes something, signing in included, must send the same value in
 *   the X-XSRF-TOKEN header. Another site can make the browser send the cookie, not read it.
 * It completes the SameSite=Lax session cookie (docs/adr/0001-session-cookie.md).
 */
final class CsrfDoubleSubmitSubscriber implements EventSubscriberInterface
{
    public const string COOKIE = 'XSRF-TOKEN';
    public const string HEADER = 'X-XSRF-TOKEN';
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the firewall (priority 8), so that signing in is protected too
            KernelEvents::REQUEST => ['onRequest', 16],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || \in_array($request->getMethod(), self::SAFE_METHODS, true)
            || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $cookie = (string) $request->cookies->get(self::COOKIE, '');
        $header = (string) $request->headers->get(self::HEADER, '');
        if ('' === $cookie || '' === $header || !hash_equals($cookie, $header)) {
            // Rare in normal use (cookies blocked, page kept open across an update): reloading fixes it
            $event->setResponse(new JsonResponse(
                ['status' => Response::HTTP_FORBIDDEN, 'detail' => 'Votre session a expiré : rechargez la page.'],
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'application/problem+json'],
            ));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->cookies->has(self::COOKIE)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE, bin2hex(random_bytes(32)))
                ->withSecure($request->isSecure())
                ->withHttpOnly(false) // Angular must read it
                ->withSameSite(Cookie::SAMESITE_STRICT)
                ->withPath('/'),
        );
    }
}
