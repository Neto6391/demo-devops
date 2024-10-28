<?php
namespace App\EventListener;

use App\Service\BlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TokenValidationListener
{
    private BlacklistService $blacklistService;

    public function __construct(BlacklistService $blacklistService)
    {
        $this->blacklistService = $blacklistService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_decoded' => 'onJWTDecoded',
        ];
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $token = $event->getPayload();
        
        if ($this->blacklistService->isTokenBlacklisted($event->getToken())) {
            $event->markAsInvalid();
        }
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response = new JsonResponse(
            [
                'status' => 'error',
                'message' => 'Token not found or invalid'
            ],
            Response::HTTP_UNAUTHORIZED
        );

        $event->setResponse($response);
    }
}