<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
}
