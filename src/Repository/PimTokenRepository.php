<?php

namespace App\Repository;

use App\Entity\PimToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PimToken>
 */
class PimTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PimToken::class);
    }

    public function upsert(string $pimUrl, string $accessToken): PimToken
    {
        $token = $this->find($pimUrl);

        if ($token !== null) {
            $token->setAccessToken($accessToken);
        } else {
            $token = new PimToken($pimUrl, $accessToken);
            $this->getEntityManager()->persist($token);
        }

        $this->getEntityManager()->flush();

        return $token;
    }

    public function getAccessToken(string $pimUrl): ?string
    {
        $token = $this->find($pimUrl);

        return $token?->getAccessToken();
    }
}
