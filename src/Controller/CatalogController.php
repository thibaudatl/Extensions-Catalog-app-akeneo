<?php

namespace App\Controller;

use App\Repository\DeploymentRepository;
use App\Service\ExtensionDeployer;
use App\Service\GitHubCatalogProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/catalog')]
class CatalogController extends AbstractController
{
    private function requireSession(Request $request): array
    {
        $session = $request->getSession();
        $pimUrl = $session->get('pim_url');
        $accessToken = $session->get('access_token');

        if (!$pimUrl || !$accessToken) {
            throw $this->createAccessDeniedException('Please connect your PIM first.');
        }

        return [$pimUrl, $accessToken];
    }

    #[Route('', name: 'catalog_index', methods: ['GET'])]
    public function index(
        Request $request,
        GitHubCatalogProvider $catalogProvider,
        DeploymentRepository $deploymentRepo,
    ): Response {
        [$pimUrl, $accessToken] = $this->requireSession($request);

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
        $this->requireSession($request);
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
        [$pimUrl, $accessToken] = $this->requireSession($request);

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
        [$pimUrl, $accessToken] = $this->requireSession($request);

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
        [$pimUrl, $accessToken] = $this->requireSession($request);

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
