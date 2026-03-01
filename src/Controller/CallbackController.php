<?php

namespace App\Controller;

use App\Repository\PimTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CallbackController extends AbstractController
{
    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function __invoke(
        Request $request,
        HttpClientInterface $httpClient,
        PimTokenRepository $tokenRepository,
    ): Response {
        $session = $request->getSession();

        $state = $request->query->get('state');
        $code = $request->query->get('code');
        $expectedState = $session->get('oauth_state');
        $pimUrl = $session->get('pim_url');

        if (!$state || !$code || !$expectedState || !$pimUrl) {
            throw $this->createNotFoundException('Missing OAuth parameters. Please start the flow from Akeneo.');
        }

        if ($state !== $expectedState) {
            throw $this->createAccessDeniedException('Invalid state parameter.');
        }

        $clientId = $this->getParameter('app.client_id');
        $clientSecret = $this->getParameter('app.client_secret');

        $codeIdentifier = bin2hex(random_bytes(30));
        $codeChallenge = hash('sha256', $codeIdentifier . $clientSecret);

        $tokenUrl = rtrim($pimUrl, '/') . '/connect/apps/v1/oauth2/token';

        $response = $httpClient->request('POST', $tokenUrl, [
            'json' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $clientId,
                'code_identifier' => $codeIdentifier,
                'code_challenge' => $codeChallenge,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Token exchange failed with status %d.', $response->getStatusCode()));
        }

        $data = $response->toArray();

        $accessToken = $data['access_token'] ?? null;

        if (!$accessToken) {
            throw new \RuntimeException('Token exchange response missing access_token.');
        }

        // Persist encrypted token to database
        $tokenRepository->upsert($pimUrl, $accessToken);

        // Clean up session: keep only pim_url, remove sensitive data
        $session->remove('oauth_state');
        $session->remove('access_token');

        // Regenerate session ID to prevent session fixation
        $session->migrate(true);

        // Keep pim_url in session for catalog lookups
        $session->set('pim_url', $pimUrl);

        return $this->redirectToRoute('catalog_index');
    }
}
