<?php

namespace App\Dto;

class ExtensionDto
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $type,
        public readonly string $position,
        public readonly string $version,
        public readonly string $defaultLabel,
        public readonly array $labels,
        public readonly ?string $description,
        public readonly string $distPath,
        public readonly string $originalFileName,
        public readonly ?array $customVariables = null,
        public readonly ?string $documentationUrl = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            type: $data['type'] ?? 'sdk_script',
            position: $data['position'] ?? '',
            version: $data['version'] ?? '1.0.0',
            defaultLabel: $data['defaultLabel'] ?? $data['name'],
            labels: $data['labels'] ?? [],
            description: $data['description'] ?? null,
            distPath: $data['distPath'],
            originalFileName: $data['originalFileName'] ?? basename($data['distPath']),
            customVariables: $data['customVariables'] ?? null,
            documentationUrl: $data['documentationUrl'] ?? null,
        );
    }
}
