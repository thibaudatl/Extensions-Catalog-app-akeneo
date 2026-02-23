<?php

namespace App\Service;

use App\Dto\ExtensionDto;
use App\Entity\Deployment;
use App\Repository\DeploymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ExtensionDeployer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly DeploymentRepository $deploymentRepo,
        private readonly GitHubCatalogProvider $catalogProvider,
    ) {
    }

    public function deploy(ExtensionDto $extension, string $pimUrl, string $accessToken): Deployment
    {
        $deployment = $this->deploymentRepo->findOneByPimUrlAndSlug($pimUrl, $extension->slug);
        if (!$deployment) {
            $deployment = new Deployment();
            $deployment->setExtensionSlug($extension->slug);
            $deployment->setPimUrl($pimUrl);
            $this->em->persist($deployment);
        }

        $deployment->setStatus(Deployment::STATUS_PENDING);
        $deployment->setLastError(null);
        $deployment->setUpdatedAt(new \DateTimeImmutable());

        try {
            $fileContent = $this->catalogProvider->downloadDistFile($extension);
            $tempFile = tempnam(sys_get_temp_dir(), 'ext_');
            file_put_contents($tempFile, $fileContent);

            try {
                $fields = [
                    'name' => $extension->name,
                    'type' => $extension->type,
                    'position' => $extension->position,
                    'version' => $extension->version,
                    'configuration[default_label]' => mb_substr($extension->defaultLabel, 0, 30),
                ];

                foreach ($extension->labels as $locale => $label) {
                    $fields["configuration[labels][$locale]"] = mb_substr($label, 0, 30);
                }

                if ($extension->customVariables !== null) {
                    $fields['configuration[custom_variables]'] = json_encode($extension->customVariables);
                }

                $url = rtrim($pimUrl, '/') . '/api/rest/v1/ui-extensions';

                $response = $this->sendMultipart('POST', $url, $accessToken, $fields, $tempFile, $extension->originalFileName);

                $statusCode = $response->getStatusCode();
                $data = [];
                try {
                    $data = $response->toArray(false);
                } catch (\Throwable) {
                }

                if ($statusCode >= 200 && $statusCode < 300) {
                    $deployment->setStatus(Deployment::STATUS_DEPLOYED);
                    $deployment->setRemoteUuid($data['uuid'] ?? $data['id'] ?? null);
                } else {
                    $deployment->setStatus(Deployment::STATUS_FAILED);
                    $errorMsg = $this->extractErrorMessage($data, $response);
                    $deployment->setLastError(substr($errorMsg, 0, 1000));
                }
            } finally {
                @unlink($tempFile);
            }
        } catch (\Throwable $e) {
            $deployment->setStatus(Deployment::STATUS_FAILED);
            $deployment->setLastError(substr($e->getMessage(), 0, 1000));
        }

        $this->em->flush();

        return $deployment;
    }

    public function update(Deployment $deployment, ExtensionDto $extension, string $accessToken): Deployment
    {
        $deployment->setUpdatedAt(new \DateTimeImmutable());

        try {
            $fileContent = $this->catalogProvider->downloadDistFile($extension);
            $tempFile = tempnam(sys_get_temp_dir(), 'ext_');
            file_put_contents($tempFile, $fileContent);

            try {
                $fields = [
                    '_method' => 'PATCH',
                    'name' => $extension->name,
                    'type' => $extension->type,
                    'position' => $extension->position,
                    'version' => $extension->version,
                    'configuration[default_label]' => mb_substr($extension->defaultLabel, 0, 30),
                ];

                foreach ($extension->labels as $locale => $label) {
                    $fields["configuration[labels][$locale]"] = mb_substr($label, 0, 30);
                }

                if ($extension->customVariables !== null) {
                    $fields['configuration[custom_variables]'] = json_encode($extension->customVariables);
                }

                $url = rtrim($deployment->getPimUrl(), '/') . '/api/rest/v1/ui-extensions/' . $deployment->getRemoteUuid();

                $response = $this->sendMultipart('POST', $url, $accessToken, $fields, $tempFile, $extension->originalFileName);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $deployment->setStatus(Deployment::STATUS_DEPLOYED);
                    $deployment->setLastError(null);
                } else {
                    $deployment->setStatus(Deployment::STATUS_FAILED);
                    $data = [];
                    try {
                        $data = $response->toArray(false);
                    } catch (\Throwable) {
                    }
                    $errorMsg = $this->extractErrorMessage($data, $response);
                    $deployment->setLastError(substr($errorMsg, 0, 1000));
                }
            } finally {
                @unlink($tempFile);
            }
        } catch (\Throwable $e) {
            $deployment->setStatus(Deployment::STATUS_FAILED);
            $deployment->setLastError(substr($e->getMessage(), 0, 1000));
        }

        $this->em->flush();

        return $deployment;
    }

    public function undeploy(Deployment $deployment, string $accessToken): void
    {
        $deployment->setUpdatedAt(new \DateTimeImmutable());

        try {
            $url = rtrim($deployment->getPimUrl(), '/') . '/api/rest/v1/ui-extensions/' . $deployment->getRemoteUuid();

            $response = $this->httpClient->request('DELETE', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $deployment->setStatus(Deployment::STATUS_DELETED);
                $deployment->setLastError(null);
            } else {
                $deployment->setStatus(Deployment::STATUS_FAILED);
                $deployment->setLastError('Delete failed with status ' . $statusCode);
            }
        } catch (\Throwable $e) {
            $deployment->setStatus(Deployment::STATUS_FAILED);
            $deployment->setLastError(substr($e->getMessage(), 0, 1000));
        }

        $this->em->flush();
    }

    /**
     * Build a raw multipart/form-data body to avoid Content-Type headers on
     * text parts (which Symfony's FormDataPart adds, breaking server parsing).
     */
    private function sendMultipart(string $method, string $url, string $accessToken, array $fields, string $filePath, string $fileName): ResponseInterface
    {
        $boundary = bin2hex(random_bytes(16));
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
            $body .= "$value\r\n";
        }

        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$fileName\"\r\n";
        $body .= "Content-Type: application/javascript\r\n\r\n";
        $body .= file_get_contents($filePath) . "\r\n";
        $body .= "--$boundary--\r\n";

        return $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => "multipart/form-data; boundary=$boundary",
            ],
            'body' => $body,
        ]);
    }

    private function extractErrorMessage(array $data, ResponseInterface $response): string
    {
        $message = $data['message'] ?? '';

        if (!empty($data['errors']) && is_array($data['errors'])) {
            $details = [];
            foreach ($data['errors'] as $error) {
                $property = $error['property'] ?? $error['field'] ?? '';
                $msg = $error['message'] ?? (string) $error;
                $details[] = $property ? "$property: $msg" : $msg;
            }
            if ($details) {
                $message .= ' ' . implode('; ', $details);
            }
        }

        return $message ?: $response->getContent(false);
    }
}
