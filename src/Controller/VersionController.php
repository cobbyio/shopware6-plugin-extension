<?php

declare(strict_types=1);

namespace CobbyPlugin\Controller;

use CobbyPlugin\CobbyPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class VersionController
{
    #[Route(path: '/api/cobby-version', name: 'api.action.cobby.version', methods: ['GET'], defaults: ['_routeScope' => ['api']])]
    public function getVersion(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'version' => CobbyPlugin::PLUGIN_VERSION,
        ]);
    }
}
