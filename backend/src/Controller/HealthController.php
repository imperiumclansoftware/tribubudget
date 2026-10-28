<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Checked by the Docker health check: the application answers and reaches its database. */
final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'], format: 'json')]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return $this->json(['status' => 'database unreachable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['status' => 'ok', 'version' => $_SERVER['APP_VERSION'] ?? 'dev']);
    }
}
