<?php
namespace App\Service;

use App\Document\Blacklist;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class BlacklistService
{
    private DocumentManager $dm;
    private CacheItemPoolInterface $cache;
    
    public function __construct(
        DocumentManager $dm,
        CacheItemPoolInterface $cache
    ) {
        $this->dm = $dm;
        $this->cache = $cache;
    }

    /**
     * Adiciona um token à blacklist
     * @throws \InvalidArgumentException se o token for inválido
     * @throws \RuntimeException se ocorrer erro ao salvar
     */
    public function addToBlacklist(string $tokenIdentifier, \DateTime $expiresAt): void
    {
        if (empty($tokenIdentifier)) {
            throw new \InvalidArgumentException('Token identifier cannot be empty');
        }

        try {
            $existingToken = $this->dm->getRepository(Blacklist::class)
                ->findOneBy(['tokenIdentifier' => $tokenIdentifier]);

            if ($existingToken) {
                if ($existingToken->getExpiresAt() < $expiresAt) {
                    $existingToken->setExpiresAt($expiresAt);
                    $this->dm->flush();
                }
                return;
            }

            $blacklistedToken = new Blacklist();
            $blacklistedToken->setTokenIdentifier($tokenIdentifier);
            $blacklistedToken->setExpiresAt($expiresAt);

            $this->dm->persist($blacklistedToken);
            $this->dm->flush();

            $cacheItem = $this->cache->getItem($this->getCacheKey($tokenIdentifier));
            $cacheItem->set(true);
            $cacheItem->expiresAt($expiresAt);
            $this->cache->save($cacheItem);

        } catch (BulkWriteException $e) {
            throw new \RuntimeException('Token already exists in blacklist', 0, $e);
        } catch (MongoDBException $e) {
            throw new \RuntimeException('Error saving token to blacklist', 0, $e);
        }
    }

    /**
     * Verifica se um token está na blacklist
     */
    public function isBlacklisted(string $tokenIdentifier): bool
    {
        if (empty($tokenIdentifier)) {
            return false;
        }

        $cacheKey = $this->getCacheKey($tokenIdentifier);
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $blacklistedToken = $this->dm->getRepository(Blacklist::class)
                ->findOneBy(['tokenIdentifier' => $tokenIdentifier]);

            if (!$blacklistedToken) {
                return false;
            }

            if ($blacklistedToken->getExpiresAt() < new \DateTime()) {
                $this->dm->remove($blacklistedToken);
                $this->dm->flush();
                return false;
            }

            $cacheItem->set(true);
            $cacheItem->expiresAt($blacklistedToken->getExpiresAt());
            $this->cache->save($cacheItem);

            return true;

        } catch (MongoDBException $e) {
            return false;
        }
    }

    /**
     * Remove tokens expirados da blacklist
     */
    public function removeExpiredTokens(): void
    {
        try {
            $qb = $this->dm->createQueryBuilder(Blacklist::class);
            $qb->remove()
               ->field('expiresAt')->lt(new \DateTime())
               ->getQuery()
               ->execute();
        } catch (MongoDBException $e) {
            throw new \RuntimeException('Error removing expired tokens', 0, $e);
        }
    }

    /**
     * Gera a chave do cache para um token
     */
    private function getCacheKey(string $token): string
    {
        return 'blacklist_' . hash('sha256', $token);
    }
}