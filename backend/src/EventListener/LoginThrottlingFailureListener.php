<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Lexik's failure handler maps AuthenticationException::getCode() to an HTTP
 * status, but TooManyLoginAttemptsAuthenticationException doesn't carry a
 * 429 code — it always surfaces as a 401. This corrects the response for
 * that specific case so login_throttling actually yields a 429.
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
class LoginThrottlingFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        if (!$event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $exception = $event->getException();
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        $event->setResponse(new JWTAuthenticationFailureResponse($message, Response::HTTP_TOO_MANY_REQUESTS));
    }
}
