<?php
namespace App\Controller;

use App\Service\AuthService;
use App\Service\BlacklistService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Document\User;

class AuthController extends AbstractController
{
    private $documentManager;
    private $passwordHasher;
    private $jwtManager;
    private $blacklistService;
    private $authService;
    private $jwtEncoder;

    public function __construct(
        DocumentManager $documentManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTManager $jwtManager,
        BlacklistService $blacklistService,
        AuthService $authService,
        JWTEncoderInterface $jwtEncoder
    ) {
        $this->documentManager = $documentManager;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
        $this->blacklistService = $blacklistService;
        $this->authService = $authService;
        $this->jwtEncoder = $jwtEncoder;
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                throw new \InvalidArgumentException('Invalid JSON data');
            }

            if (!isset($data['email']) || !isset($data['password'])) {
                return new JsonResponse(
                    ['error' => 'Email and password are required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $existingUser = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                return new JsonResponse(
                    ['error' => 'User already exists'],
                    Response::HTTP_CONFLICT
                );
            }

            $user = $this->authService->register($data);

            return new JsonResponse(
                ['message' => 'User registered successfully'],
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'Registration failed',
                    'error' => $e->getMessage()
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                throw new \InvalidArgumentException('Invalid JSON data');
            }

            if (!isset($data['email']) || !isset($data['password'])) {
                return new JsonResponse(
                    ['error' => 'Email and password are required'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

            if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
                return new JsonResponse(
                    ['error' => 'Invalid credentials'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $token = $this->jwtManager->create($user);

            $userData = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ];

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token,
                'user' => $userData,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'Login failed',
                    'error' => $e->getMessage()
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            $authHeader = $request->headers->get('Authorization');
            if (!$authHeader) {
                return new JsonResponse(['error' => 'No token provided'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $token = str_replace('Bearer ', '', $authHeader);
            
            try {
                $decodedToken = $this->jwtEncoder->decode($token);
                
                if (!isset($decodedToken['exp']) || !isset($decodedToken['username'])) {
                    return new JsonResponse(['error' => 'Invalid token format'], JsonResponse::HTTP_UNAUTHORIZED);
                }

                $expiresAt = new \DateTime();
                $expiresAt->setTimestamp($decodedToken['exp']);
                
                $tokenIdentifier = hash('sha256', $decodedToken['username'] . $decodedToken['exp']);
                
                $this->blacklistService->addToBlacklist($tokenIdentifier, $expiresAt);

                return new JsonResponse([
                    'status' => 'success', 
                    'message' => 'Logout successful'
                ]);

            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => 'Invalid token',
                    'details' => $e->getMessage()
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}