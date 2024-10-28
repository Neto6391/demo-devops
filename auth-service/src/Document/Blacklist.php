<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "blacklist")]
class Blacklist
{
    #[MongoDB\Id]
    private $id;

    #[MongoDB\Field(type: 'string', name: 'token')]
    #[MongoDB\Index(unique: true)]
    private $tokenIdentifier;

    #[MongoDB\Field(type: "date")]
    #[MongoDB\Index]
    private $expiresAt;

    #[MongoDB\Field(type: "date")]
    private $createdAt;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTokenIdentifier(): ?string
    {
        return $this->tokenIdentifier;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setTokenIdentifier(string $tokenIdentifier): self
    {
        $this->tokenIdentifier = $tokenIdentifier;
        return $this;
    }

    public function setExpiresAt(\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}