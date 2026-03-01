<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ActivateController extends AbstractController
{
    private const SCOPES = [
        'manage_extensions',
    ];

    #[Route('/activate', name: 'activate', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $pimUrl = $request->query->get('pim_url');

        if (!$pimUrl) {
            throw $this->createNotFoundException('Missing pim_url query parameter.');
        }

        $this->validatePimUrl($pimUrl);

        $state = bin2hex(random_bytes(16));

        $session = $request->getSession();
        $session->set('pim_url', $pimUrl);
        $session->set('oauth_state', $state);

        $clientId = $this->getParameter('app.client_id');

        $authorizeUrl = rtrim($pimUrl, '/') . '/connect/apps/v1/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
        ]);

        return new RedirectResponse($authorizeUrl);
    }

    private function validatePimUrl(string $pimUrl): void
    {
        $parsed = parse_url($pimUrl);

        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            throw new BadRequestHttpException('Invalid pim_url.');
        }

        if ($parsed['scheme'] !== 'https') {
            throw new BadRequestHttpException('pim_url must use HTTPS.');
        }

        $host = strtolower($parsed['host']);

        if (!str_ends_with($host, '.cloud.akeneo.com')) {
            throw new BadRequestHttpException('pim_url must be an Akeneo Cloud instance (.cloud.akeneo.com).');
        }
    }
}
