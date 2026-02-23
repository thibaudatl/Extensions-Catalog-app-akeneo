<?php

namespace App\Repository;

use App\Entity\Deployment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deployment>
 */
class DeploymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deployment::class);
    }

    public function findByPimUrl(string $pimUrl): array
    {
        return $this->findBy(['pimUrl' => $pimUrl]);
    }

    public function findOneByPimUrlAndSlug(string $pimUrl, string $extensionSlug): ?Deployment
    {
        return $this->findOneBy([
            'pimUrl' => $pimUrl,
            'extensionSlug' => $extensionSlug,
        ]);
    }

    /** @return array<string, Deployment> keyed by extension slug */
    public function findDeploymentMapForPim(string $pimUrl): array
    {
        $deployments = $this->findBy(['pimUrl' => $pimUrl]);
        $map = [];
        foreach ($deployments as $deployment) {
            $map[$deployment->getExtensionSlug()] = $deployment;
        }
        return $map;
    }
}
