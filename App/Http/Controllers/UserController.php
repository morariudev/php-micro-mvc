<?php

namespace App\Http\Controllers;

use App\Models\User;
use Framework\Http\Controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends Controller
{
    private User $user;

    public function __construct(User $user, \Framework\Support\Container $container)
    {
        parent::__construct($container);
        $this->user = $user;
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->user->all();

        return $this->view('users/index.twig', [
            'users' => $users,
        ]);
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->user->find($id);

        if ($user === null) {
            return $this->view('users/show.twig', [
                'user' => null,
            ])->withStatus(404);
        }

        return $this->view('users/show.twig', [
            'user' => $user,
        ]);
    }

    public function apiIndex(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json($this->user->all());
    }

    public function apiShow(ServerRequestInterface $request, int $id): ResponseInterface
    {
        return $this->json($this->user->find($id));
    }
}
