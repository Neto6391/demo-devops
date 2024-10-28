<?php
namespace App\Service;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    private $documentManager;
    private $passwordHasher;

    public function __construct(DocumentManager $documentManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->documentManager = $documentManager;
        $this->passwordHasher = $passwordHasher;
    }

    public function register(array $data): User
    {
        $user = new User();
        $user->setEmail($data['email']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $this->documentManager->persist($user);
        $this->documentManager->flush();

        return $user;
    }
}
