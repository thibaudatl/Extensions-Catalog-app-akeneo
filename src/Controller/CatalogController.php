<?php

namespace App\Controller;

use App\Repository\DeploymentRepository;
use App\Repository\PimTokenRepository;
use App\Service\ExtensionDeployer;
use App\Service\GitHubCatalogProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/catalog')]
class CatalogController extends AbstractController
{
    public function __construct(
        private PimTokenRepository $tokenRepository,
    ) {
    }

    private function getCredentials(Request $request): ?array
    {
        $pimUrl = $request->getSession()->get('pim_url');

        if (!$pimUrl) {
            return null;
        }

        $accessToken = $this->tokenRepository->getAccessToken($pimUrl);

        if (!$accessToken) {
            return null;
        }

        return [$pimUrl, $accessToken];
    }

    #[Route('', name: 'catalog_index', methods: ['GET'])]
    public function index(
        Request $request,
        GitHubCatalogProvider $catalogProvider,
        DeploymentRepository $deploymentRepo,
    ): Response {
        $credentials = $this->getCredentials($request);

        if (!$credentials) {
            return $this->render('catalog/not_connected.html.twig');
        }

        [$pimUrl, $accessToken] = $credentials;

        $extensions = $catalogProvider->getCatalog();
        $deployments = $deploymentRepo->findDeploymentMapForPim($pimUrl);

        return $this->render('catalog/index.html.twig', [
            'extensions' => $extensions,
            'deployments' => $deployments,
            'pim_url' => $pimUrl,
        ]);
    }

    #[Route('/refresh', name: 'catalog_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        GitHubCatalogProvider $catalogProvider,
    ): Response {
        $credentials = $this->getCredentials($request);
        if (!$credentials) {
            throw $this->createAccessDeniedException('Please connect your PIM first.');
        }

        if (!$this->isCsrfTokenValid('catalog_refresh', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $catalogProvider->clearCache();
        $this->addFlash('success', 'Catalog refreshed from GitHub.');

        return $this->redirectToRoute('catalog_index');
    }

    #[Route('/{slug}/deploy', name: 'catalog_deploy', methods: ['POST'])]
    public function deploy(
        string $slug,
        Request $request,
        GitHubCatalogProvider $catalogProvider,
        ExtensionDeployer $deployer,
    ): Response {
        $credentials = $this->getCredentials($request);
        if (!$credentials) {
            throw $this->createAccessDeniedException('Please connect your PIM first.');
        }

        if (!$this->isCsrfTokenValid('catalog_deploy_' . $slug, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        [$pimUrl, $accessToken] = $credentials;

        $extension = $catalogProvider->getExtension($slug);
        if (!$extension) {
            throw $this->createNotFoundException('Extension not found.');
        }

        $deployment = $deployer->deploy($extension, $pimUrl, $accessToken);

        if ($deployment->getStatus() === 'deployed') {
            $this->addFlash('success', sprintf('"%s" deployed successfully.', $extension->defaultLabel));
        } else {
            $this->addFlash('error', sprintf('Failed to deploy "%s": %s', $extension->defaultLabel, $deployment->getLastError()));
        }

        return $this->redirectToRoute('catalog_index');
    }

    #[Route('/{slug}/update', name: 'catalog_update', methods: ['POST'])]
    public function update(
        string $slug,
        Request $request,
        GitHubCatalogProvider $catalogProvider,
        ExtensionDeployer $deployer,
        DeploymentRepository $deploymentRepo,
    ): Response {
        $credentials = $this->getCredentials($request);
        if (!$credentials) {
            throw $this->createAccessDeniedException('Please connect your PIM first.');
        }

        if (!$this->isCsrfTokenValid('catalog_update_' . $slug, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        [$pimUrl, $accessToken] = $credentials;

        $extension = $catalogProvider->getExtension($slug);
        if (!$extension) {
            throw $this->createNotFoundException('Extension not found.');
        }

        $deployment = $deploymentRepo->findOneByPimUrlAndSlug($pimUrl, $slug);
        if (!$deployment || !$deployment->getRemoteUuid()) {
            $this->addFlash('error', 'No existing deployment found to update.');
            return $this->redirectToRoute('catalog_index');
        }

        $deployment = $deployer->update($deployment, $extension, $accessToken);

        if ($deployment->getStatus() === 'deployed') {
            $this->addFlash('success', sprintf('"%s" updated successfully.', $extension->defaultLabel));
        } else {
            $this->addFlash('error', sprintf('Failed to update "%s": %s', $extension->defaultLabel, $deployment->getLastError()));
        }

        return $this->redirectToRoute('catalog_index');
    }

    #[Route('/{slug}/undeploy', name: 'catalog_undeploy', methods: ['POST'])]
    public function undeploy(
        string $slug,
        Request $request,
        GitHubCatalogProvider $catalogProvider,
        ExtensionDeployer $deployer,
        DeploymentRepository $deploymentRepo,
    ): Response {
        $credentials = $this->getCredentials($request);
        if (!$credentials) {
            throw $this->createAccessDeniedException('Please connect your PIM first.');
        }

        if (!$this->isCsrfTokenValid('catalog_undeploy_' . $slug, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        [$pimUrl, $accessToken] = $credentials;

        $extension = $catalogProvider->getExtension($slug);
        if (!$extension) {
            throw $this->createNotFoundException('Extension not found.');
        }

        $deployment = $deploymentRepo->findOneByPimUrlAndSlug($pimUrl, $slug);
        if (!$deployment || !$deployment->getRemoteUuid()) {
            $this->addFlash('error', 'No existing deployment found to remove.');
            return $this->redirectToRoute('catalog_index');
        }

        $deployer->undeploy($deployment, $accessToken);

        if ($deployment->getStatus() === 'deleted') {
            $this->addFlash('success', sprintf('"%s" removed from your PIM.', $extension->defaultLabel));
        } else {
            $this->addFlash('error', sprintf('Failed to remove "%s": %s', $extension->defaultLabel, $deployment->getLastError()));
        }

        return $this->redirectToRoute('catalog_index');
    }
}
