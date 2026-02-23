<?php

namespace App\Service;

use App\Dto\ExtensionDto;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubCatalogProvider
{
    private const BASE_RAW_URL = 'https://raw.githubusercontent.com/thibaudatl/akeneo-extensions-catalog/main/';
    private const CATALOG_CACHE_KEY = 'github_catalog';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return ExtensionDto[] */
    public function getCatalog(): array
    {
        return $this->cache->get(self::CATALOG_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            $response = $this->httpClient->request('GET', self::BASE_RAW_URL . 'catalog.json');
            $data = $response->toArray();

            return array_map(
                fn(array $ext) => ExtensionDto::fromArray($ext),
                $data['extensions'] ?? [],
            );
        });
    }

    public function getExtension(string $slug): ?ExtensionDto
    {
        foreach ($this->getCatalog() as $ext) {
            if ($ext->slug === $slug) {
                return $ext;
            }
        }
        return null;
    }

    public function downloadDistFile(ExtensionDto $ext): string
    {
        $url = self::BASE_RAW_URL . $ext->distPath;
        $response = $this->httpClient->request('GET', $url);

        return $response->getContent();
    }

    public function clearCache(): void
    {
        $this->cache->delete(self::CATALOG_CACHE_KEY);
    }
}
