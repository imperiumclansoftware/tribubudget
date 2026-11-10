<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A failed sign-in, as problem+json like every other API error: the same message whether the
 * username exists or not, and 429 once login_throttling stops the attempts.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        [$status, $detail] = $exception instanceof TooManyLoginAttemptsAuthenticationException
            ? [Response::HTTP_TOO_MANY_REQUESTS, 'Trop de tentatives : patientez quelques minutes avant de réessayer.']
            : [Response::HTTP_UNAUTHORIZED, 'Identifiant ou code incorrect.'];

        return new JsonResponse(['status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json']);
    }
}
