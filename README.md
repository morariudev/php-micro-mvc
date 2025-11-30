# PHP MICRO MVC Framework – PSR-7, SQLite, Twig, Docker

A lightweight, modern, PSR-7 compatible PHP framework built with:

- **PHP 8.4**
- **Nyholm PSR-7 request/response**
- **SQLite (PDO) ORM**
- **Twig templating**
- **Autowiring DI container**
- **Middleware pipeline (PSR-15 style)**
- **Route groups, route caching, constraints, compiled router**
- **Session-based authentication**
- **CSRF middleware**
- **Events system**
- **Docker (dev + prod) + Nginx**
- **Automatic `.env` configuration**

This serves as a clean foundation for microservices, APIs, or small MVC web apps.

---

# 🚀 Features

### ✔ Modern PHP Architecture
- Autowiring DI container
- PSR-7 requests & responses
- PSR-15 style middleware
- Class-based controllers
- Route constraints `{id:\d+}`

### ✔ Routing
- Method-based routing (GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD)
- Compiled route tree for fast matching
- Automatic `HEAD` support
- Automatic `OPTIONS` handling (CORS)
- Middleware groups
- Route caching

### ✔ Security
- CSRF protection middleware
- Session-based authentication system
- Error pages (404 / 500) with Twig integration

### ✔ Database Layer
- SQLite (PDO)
- Simple ORM with:
  - `all()`
  - `find()`
  - `where()`
  - `create()`
  - `update()`
  - `delete()`

### ✔ Views
- Twig template engine
- Layout support
- Twig functions: `csrf()`, `asset()`, `url()`

### ✔ Environment Support
- `.env.dev` for development
- `.env.prod` for production
- Native `.env` loader (no external libs)

---

# 📁 Project Structure

```txt
project-root/
│
├── App/
│   ├── Config/
│   │   ├── app.php
│   │   └── database.php
│   │
│   ├── Routes/
│   │   ├── web.php
│   │   └── api.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── UserController.php
│   │   └── Middleware/
│   │       ├── ExampleMiddleware.php
│   │       └── CsrfMiddleware.php
│   │
│   ├── Models/
│   │   └── User.php
│   │
│   └── Views/
│       ├── layouts/
│       │   └── main.twig
│       ├── errors/
│       │   ├── 404.twig
│       │   └── 500.twig
│       └── users/
│           ├── index.twig
│           └── show.twig
│
├── Framework/
│   ├── Bootstrap.php
│   ├── helpers.php
│   │
│   ├── Router/
│   │   ├── Router.php
│   │   ├── Route.php
│   │   ├── RouteGroup.php
│   │   ├── RouteCollector.php
│   │   ├── Dispatcher.php
│   │   └── RouteCache.php
│   │
│   ├── Middleware/
│   │   ├── MiddlewareInterface.php
│   │   ├── SessionAuthMiddleware.php
│   │   └── CsrfMiddleware.php
│   │
│   ├── Support/
│   │   ├── Container.php
│   │   ├── Model.php
│   │   └── Database.php
│   │
│   ├── View/
│   │   └── TwigRenderer.php
│   │
│   ├── Session/
│   │   └── SessionManager.php
│   │
│   └── Events/
│       ├── EventDispatcher.php
│       └── EventListenerInterface.php
│
├── public/
│   └── index.php
│
├── database/
│   └── database.sqlite
│
├── uploads/
│   └── (uploaded files...)
│
├── cache/
│   ├── routes.cache.php
│   └── (twig cache, other cache...)
│
├── nginx/
│   ├── default.dev.conf
│   └── default.conf
│
├── docker-entrypoint.sh
├── Dockerfile
├── Dockerfile.dev
├── docker-compose.yml
├── docker-compose.prod.yml
│
├── .env.dev
├── .env.prod
├── .env.example
│
├── composer.json
├── composer.lock
└── README.md
