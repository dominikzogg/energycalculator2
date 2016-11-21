<?php

use Chubbyphp\Csrf\CsrfProvider;
use Chubbyphp\ErrorHandler\SimpleErrorHandlerProvider;
use Chubbyphp\Model\Cache\ModelCache;
use Chubbyphp\Model\Doctrine\DBAL\Command\CreateDatabaseCommand;
use Chubbyphp\Model\Doctrine\DBAL\Command\RunSqlCommand;
use Chubbyphp\Model\Doctrine\DBAL\Command\SchemaUpdateCommand;
use Chubbyphp\Model\Resolver;
use Chubbyphp\Security\Authentication\AuthenticationProvider;
use Chubbyphp\Security\Authentication\FormAuthentication;
use Chubbyphp\Security\Authorization\AuthorizationProvider;
use Chubbyphp\Security\Authorization\RoleAuthorization;
use Chubbyphp\Session\SessionProvider;
use Chubbyphp\Translation\LocaleTranslationProvider;
use Chubbyphp\Translation\TranslationProvider;
use Chubbyphp\Translation\TranslationTwigExtension;
use Chubbyphp\Validation\Requirements\Repository;
use Chubbyphp\Validation\ValidationProvider;
use Energycalculator\Command\CreateUserCommand;
use Energycalculator\Controller\AuthController;
use Energycalculator\Controller\ComestibleController;
use Energycalculator\Controller\DayController;
use Energycalculator\Controller\HomeController;
use Energycalculator\Controller\UserController;
use Energycalculator\ErrorHandler\HtmlErrorResponseProvider;
use Energycalculator\Middleware\LocaleMiddleware;
use Energycalculator\Model\Day;
use Energycalculator\Model\Comestible;
use Energycalculator\Model\ComestibleWithinDay;
use Energycalculator\Model\User;
use Energycalculator\Provider\TwigProvider;
use Energycalculator\Repository\DayRepository;
use Energycalculator\Repository\ComestibleRepository;
use Energycalculator\Repository\ComestibleWithinDayRepository;
use Energycalculator\Repository\UserRepository;
use Energycalculator\Service\RedirectForPath;
use Energycalculator\Service\TemplateData;
use Energycalculator\Service\TwigRender;
use Energycalculator\Twig\NumericExtension;
use Energycalculator\Twig\RouterExtension;
use Negotiation\LanguageNegotiator;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Slim\Container;

/* @var Container $container */
$container->register(new AuthenticationProvider());
$container->register(new AuthorizationProvider());
$container->register(new CsrfProvider());
$container->register(new DoctrineServiceProvider());
$container->register(new SimpleErrorHandlerProvider());
$container->register(new MonologServiceProvider());
$container->register(new SessionProvider());
$container->register(new TranslationProvider());
$container->register(new TwigProvider());
$container->register(new ValidationProvider());

// extend providers
$container['errorHandler.defaultProvider'] = function () use ($container) {
    return $container[HtmlErrorResponseProvider::class];
};

$container->extend('security.authentication.authentications', function (array $authentications) use ($container) {
    $authentications[] = $container[FormAuthentication::class];

    return $authentications;
});

$container->extend('security.authorization.authorizations', function (array $authorizations) use ($container) {
    $authorizations[] = $container[RoleAuthorization::class];

    return $authorizations;
});

$container->extend('security.authorization.rolehierarchy', function (array $rolehierarchy) use ($container) {
    $rolehierarchy['ADMIN'] = ['USER'];
    $rolehierarchy['USER'] = ['COMESTIBLE', 'DAY'];
    $rolehierarchy['COMESTIBLE'] = [
        'COMESTIBLE_LIST',
        'COMESTIBLE_VIEW',
        'COMESTIBLE_CREATE',
        'COMESTIBLE_EDIT',
        'COMESTIBLE_DELETE',
    ];
    $rolehierarchy['DAY'] = [
        'DAY_LIST',
        'DAY_VIEW',
        'DAY_CREATE',
        'DAY_EDIT',
        'DAY_DELETE',
    ];

    return $rolehierarchy;
});

$container->extend('translator.providers', function (array $providers) use ($container) {
    $translationDir = $container['appDir'].'/../translations';
    $providers[] = new LocaleTranslationProvider('de', require $translationDir.'/de.php');
    $providers[] = new LocaleTranslationProvider('en', require $translationDir.'/en.php');

    return $providers;
});

$container->extend('twig.namespaces', function (array $namespaces) use ($container) {
    $namespaces['Energycalculator'] = $container['appDir'].'/../views';

    return $namespaces;
});

$container->extend('twig.extensions', function (array $extensions) use ($container) {
    $extensions[] = new NumericExtension();
    $extensions[] = new RouterExtension($container['router']);
    $extensions[] = new TranslationTwigExtension($container['translator']);
    if ($container['debug']) {
        $extensions[] = new \Twig_Extension_Debug();
    }

    return $extensions;
});

$container->extend('validator.helpers', function (array $helpers) use ($container) {
    $helpers[] = $container[Repository::class.'Comestible'];
    $helpers[] = $container[Repository::class.'Day'];
    $helpers[] = $container[Repository::class.'User'];

    return $helpers;
});

// commands
$container[CreateDatabaseCommand::class] = function () use ($container) {
    return new CreateDatabaseCommand($container['db']);
};

$container[CreateUserCommand::class] = function () use ($container) {
    return new CreateUserCommand(
        $container['security.authentication.passwordmanager'],
        $container[User::class],
        $container['validator']
    );
};

$container[RunSqlCommand::class] = function () use ($container) {
    return new RunSqlCommand($container['db']);
};

$container[SchemaUpdateCommand::class] = function () use ($container) {
    return new SchemaUpdateCommand($container['db'], $container['appDir'].'/schema.php');
};

// controllers
$container[AuthController::class] = function () use ($container) {
    return new AuthController(
        $container[FormAuthentication::class], // need cause login/logout
        $container[RedirectForPath::class],
        $container['session']
    );
};


$container[HomeController::class] = function () use ($container) {
    return new HomeController($container[TemplateData::class], $container[TwigRender::class]);
};

$container[ComestibleController::class] = function () use ($container) {
    return new ComestibleController(
        $container['security.authentication'],
        $container['security.authorization'],
        $container[Comestible::class],
        $container[RedirectForPath::class],
        $container['session'],
        $container[TemplateData::class],
        $container[TwigRender::class],
        $container['validator']
    );
};

$container[DayController::class] = function () use ($container) {
    return new DayController(
        $container['security.authentication'],
        $container['security.authorization'],
        $container[Comestible::class],
        $container[ComestibleWithinDay::class],
        $container[Day::class],
        $container[RedirectForPath::class],
        $container['session'],
        $container[TemplateData::class],
        $container[TwigRender::class],
        $container['validator']
    );
};

$container[UserController::class] = function () use ($container) {
    return new UserController(
        $container['security.authentication'],
        $container['security.authorization'],
        $container['security.authentication.passwordmanager'],
        $container[RedirectForPath::class],
        $container['security.authorization.rolehierarchyresolver'],
        $container['session'],
        $container[TemplateData::class],
        $container[TwigRender::class],
        $container[User::class],
        $container['validator']
    );
};

// middlewares
$container[LocaleMiddleware::class] = function () use ($container) {
    return new LocaleMiddleware(
        $container[LanguageNegotiator::class],
        $container['localeFallback'],
        $container['locales']
    );
};

// repositories
$container[Comestible::class] = function () use ($container) {
    return new ComestibleRepository(
        $container['db'],
        $container[Resolver::class],
        new ModelCache(),
        $container['logger']
    );
};

$container[ComestibleWithinDay::class] = function () use ($container) {
    return new ComestibleWithinDayRepository(
        $container['db'],
        $container[Resolver::class],
        new ModelCache(),
        $container['logger']
    );
};

$container[Day::class] = function () use ($container) {
    return new DayRepository(
        $container['db'],
        $container[Resolver::class],
        new ModelCache(),
        $container['logger']
    );
};

$container[User::class] = function () use ($container) {
    return new UserRepository(
        $container['db'],
        $container[Resolver::class],
        new ModelCache(),
        $container['logger']
    );
};

$container[Resolver::class] = function () use ($container) {
    return new Resolver($container);
};

// services
$container[FormAuthentication::class] = function ($container) {
    return new FormAuthentication(
        $container['security.authentication.passwordmanager'],
        $container['session'],
        $container[User::class],
        $container['logger']
    );
};

$container[HtmlErrorResponseProvider::class] = function () use ($container) {
    return new HtmlErrorResponseProvider(
        $container['errorHandler'],
        $container[TemplateData::class],
        $container[TwigRender::class]
    );
};

$container[LanguageNegotiator::class] = function () use ($container) {
    return new LanguageNegotiator();
};

$container[RedirectForPath::class] = function () use ($container) {
    return new RedirectForPath($container['router']);
};

$container[Repository::class.'Comestible'] = function () use ($container) {
    return new Repository($container[Comestible::class]);
};

$container[Repository::class.'Day'] = function () use ($container) {
    return new Repository($container[Day::class]);
};

$container[Repository::class.'User'] = function () use ($container) {
    return new Repository($container[User::class]);
};

$container[RoleAuthorization::class] = function ($container) {
    return new RoleAuthorization($container['security.authorization.rolehierarchyresolver'], $container['logger']);
};

$container[TemplateData::class] = function () use ($container) {
    return new TemplateData(
        $container['security.authentication'],
        $container['debug'],
        $container['session'],
        [
            'comestible_create' => ['comestible_list'],
            'comestible_delete' => ['comestible_list'],
            'comestible_edit' => ['comestible_list'],
            'comestible_list' => [],
            'comestible_view' => ['comestible_list'],
            'day_create' => ['day_list'],
            'day_delete' => ['day_list'],
            'day_edit' => ['day_list'],
            'day_list' => [],
            'day_view' => ['day_list'],
            'user_create' => ['user_list'],
            'user_delete' => ['user_list'],
            'user_edit' => ['user_list'],
            'user_list' => [],
            'user_view' => ['user_list'],
        ]
    );
};

$container[TwigRender::class] = function () use ($container) {
    return new TwigRender($container['twig']);
};
