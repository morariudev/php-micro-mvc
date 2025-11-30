<?php

namespace App\Http\Controllers;

use App\Models\User;
use Framework\View\TwigRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    private User $user;
    private TwigRenderer $view;

    public function __construct(User $user, TwigRenderer $view)
    {
        $this->user = $user;
        $this->view = $view;
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->user->all();

        return $this->view->render('users/index.twig', [
            'users' => $users,
        ]);
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->user->find($id);

        if ($user === null) {
            return $this->view->render('users/show.twig', [
                'user' => null,
            ])->withStatus(404);
        }

        return $this->view->render('users/show.twig', [
            'user' => $user,
        ]);
    }

    public function apiIndex(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->user->all();

        $data = json_encode($users, JSON_THROW_ON_ERROR);

        $response = $this->view->createJsonResponse($data);

        return $response;
    }

    public function apiShow(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->user->find($id);

        $data = json_encode($user, JSON_THROW_ON_ERROR);

        $response = $this->view->createJsonResponse($data ?: 'null');

        return $response;
    }
}
