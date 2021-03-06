<?php

use Chubbyphp\Csrf\CsrfProvider;
use Chubbyphp\Deserialization\Mapping\LazyObjectMapping as DeserializationLazyObjectMapping;
use Chubbyphp\Deserialization\Provider\DeserializationProvider;
use Chubbyphp\Model\StorageCache\ArrayStorageCache;
use Chubbyphp\Model\Resolver;
use Chubbyphp\Security\Authentication\AuthenticationProvider;
use Chubbyphp\Security\Authentication\FormAuthentication;
use Chubbyphp\Security\Authorization\AuthorizationProvider;
use Chubbyphp\Security\Authorization\RoleAuthorization;
use Chubbyphp\Session\SessionProvider;
use Chubbyphp\Translation\LocaleTranslationProvider;
use Chubbyphp\Translation\TranslationProvider;
use Chubbyphp\Translation\TranslationTwigExtension;
use Chubbyphp\Validation\Mapping\LazyObjectMapping as ValidationLazyObjectMapping;
use Chubbyphp\Validation\Provider\ValidationProvider;
use Energycalculator\Csrf\CsrfErrorHandler;
use Energycalculator\Deserialization\ComestibleMapping as DeserializationComestibleMapping;
use Energycalculator\Deserialization\ComestibleSearchMapping as DeserializationComestibleSearchMapping;
use Energycalculator\Deserialization\ComestibleWithinDayMapping as DeserializationComestibleWithinDayMapping;
use Energycalculator\Deserialization\DateRangeMapping as DeserializationDateRangeMapping;
use Energycalculator\Deserialization\DayMapping as DeserializationDayMapping;
use Energycalculator\Deserialization\DaySearchMapping as DeserializationDaySearchMapping;
use Energycalculator\Deserialization\UserMapping as DeserializationUserMapping;
use Energycalculator\Deserialization\UserSearchMapping as DeserializationUserSearchMapping;
use Energycalculator\ErrorHandler\ErrorResponseHandler;
use Energycalculator\Model\Comestible;
use Energycalculator\Model\ComestibleWithinDay;
use Energycalculator\Model\DateRange;
use Energycalculator\Model\Day;
use Energycalculator\Model\User;
use Energycalculator\Provider\TwigProvider;
use Energycalculator\Repository\DayRepository;
use Energycalculator\Repository\ComestibleRepository;
use Energycalculator\Repository\ComestibleWithinDayRepository;
use Energycalculator\Repository\UserRepository;
use Energycalculator\Search\ComestibleSearch;
use Energycalculator\Search\DaySearch;
use Energycalculator\Search\UserSearch;
use Energycalculator\Security\AuthenticationErrorHandler;
use Energycalculator\Service\RedirectForPath;
use Energycalculator\Service\TwigRender;
use Energycalculator\Twig\NumericExtension;
use Energycalculator\Twig\RouterExtension;
use Energycalculator\Validation\ComestibleMapping as ValidationComestibleMapping;
use Energycalculator\Validation\ComestibleSearchMapping as ValidationComestibleSearchMapping;
use Energycalculator\Validation\ComestibleWithinDayMapping as ValidationComestibleWithinDayMapping;
use Energycalculator\Validation\DayMapping as ValidationDayMapping;
use Energycalculator\Validation\DaySearchMapping as ValidationDaySearchMapping;
use Energycalculator\Validation\UserMapping as ValidationUserMapping;
use Energycalculator\Validation\UserSearchMapping as ValidationUserSearchMapping;
use Negotiation\LanguageNegotiator;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Slim\Container;

/* @var Container $container */
$container->register(new AuthenticationProvider());
$container->register(new AuthorizationProvider());
$container->register(new CsrfProvider());
$container->register(new DeserializationProvider());
$container->register(new DoctrineServiceProvider());
$container->register(new MonologServiceProvider());
$container->register(new SessionProvider());
$container->register(new TranslationProvider());
$container->register(new TwigProvider());
$container->register(new ValidationProvider());

// extend providers
$container['security.authentication.errorResponseHandler'] = function () use ($container) {
    return new AuthenticationErrorHandler($container[ErrorResponseHandler::class]);
};

$container['csrf.errorResponseHandler'] = function () use ($container) {
    return new CsrfErrorHandler($container['session']);
};

$container['deserializer.emptystringtonull'] = true;

$container->extend('deserializer.objectmappings', function (array $objectMappings) use ($container) {
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationComestibleMapping::class,
        Comestible::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationComestibleSearchMapping::class,
        ComestibleSearch::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationComestibleWithinDayMapping::class,
        ComestibleWithinDay::class
    );

    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationDateRangeMapping::class,
        DateRange::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationDayMapping::class,
        Day::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationDaySearchMapping::class,
        DaySearch::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationUserMapping::class,
        User::class
    );
    $objectMappings[] = new DeserializationLazyObjectMapping(
        $container,
        DeserializationUserSearchMapping::class,
        UserSearch::class
    );

    return $objectMappings;
});

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
        'COMESTIBLE_READ',
        'COMESTIBLE_CREATE',
        'COMESTIBLE_UPDATE',
        'COMESTIBLE_DELETE',
    ];
    $rolehierarchy['DAY'] = [
        'DAY_LIST',
        'DAY_READ',
        'DAY_CREATE',
        'DAY_UPDATE',
        'DAY_DELETE',
        'CHART_WEIGHT',
        'CHART_CALORIE',
        'CHART_ENERGYMIX',
    ];

    return $rolehierarchy;
});

$container->extend('translator.providers', function (array $providers) use ($container) {
    $providers[] = new LocaleTranslationProvider(
        'de',
        array_replace(
            require $container['vendorDir'].'/chubbyphp/chubbyphp-validation/translations/de.php',
            require $container['vendorDir'].'/chubbyphp/chubbyphp-validation-model/translations/de.php',
            require $container['translationDir'].'/de.php'
        )
    );
    $providers[] = new LocaleTranslationProvider(
        'en',
        array_replace(
            require $container['vendorDir'].'/chubbyphp/chubbyphp-validation/translations/en.php',
            require $container['vendorDir'].'/chubbyphp/chubbyphp-validation-model/translations/en.php',
            require $container['translationDir'].'/en.php'
        )
    );

    return $providers;
});

$container->extend('twig.namespaces', function (array $namespaces) use ($container) {
    $namespaces['Energycalculator'] = $container['viewDir'];

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

$container->extend('validator.objectmappings', function (array $objectMappings) use ($container) {
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationComestibleMapping::class,
        Comestible::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationComestibleSearchMapping::class,
        ComestibleSearch::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationComestibleWithinDayMapping::class,
        ComestibleWithinDay::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationDayMapping::class,
        Day::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationDaySearchMapping::class,
        DaySearch::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationUserMapping::class,
        User::class
    );
    $objectMappings[] = new ValidationLazyObjectMapping(
        $container,
        ValidationUserSearchMapping::class,
        UserSearch::class
    );

    return $objectMappings;
});

// deserializer
$container[DeserializationComestibleMapping::class] = function () use ($container) {
    return new DeserializationComestibleMapping();
};

$container[DeserializationComestibleSearchMapping::class] = function () use ($container) {
    return new DeserializationComestibleSearchMapping();
};

$container[DeserializationComestibleWithinDayMapping::class] = function () use ($container) {
    return new DeserializationComestibleWithinDayMapping($container[Resolver::class]);
};

$container[DeserializationDateRangeMapping::class] = function () use ($container) {
    return new DeserializationDateRangeMapping();
};

$container[DeserializationDayMapping::class] = function () use ($container) {
    return new DeserializationDayMapping();
};

$container[DeserializationDaySearchMapping::class] = function () use ($container) {
    return new DeserializationDaySearchMapping();
};

$container[DeserializationUserMapping::class] = function () use ($container) {
    return new DeserializationUserMapping(
        $container['security.authentication.passwordmanager'],
        $container['security.authorization.rolehierarchyresolver']
    );
};

$container[DeserializationUserSearchMapping::class] = function () use ($container) {
    return new DeserializationUserSearchMapping();
};

// repositories
$container[ArrayStorageCache::class] = function () {
    return new ArrayStorageCache();
};

$container[ComestibleRepository::class] = function () use ($container) {
    return new ComestibleRepository(
        $container['db'],
        $container[Resolver::class],
        $container[ArrayStorageCache::class],
        $container['logger']
    );
};

$container[ComestibleWithinDayRepository::class] = function () use ($container) {
    return new ComestibleWithinDayRepository(
        $container['db'],
        $container[Resolver::class],
        $container[ArrayStorageCache::class],
        $container['logger']
    );
};

$container[DayRepository::class] = function () use ($container) {
    return new DayRepository(
        $container['db'],
        $container[Resolver::class],
        $container[ArrayStorageCache::class],
        $container['logger']
    );
};

$container[UserRepository::class] = function () use ($container) {
    return new UserRepository(
        $container['db'],
        $container[Resolver::class],
        $container[ArrayStorageCache::class],
        $container['logger']
    );
};

$container[Resolver::class] = function () use ($container) {
    return new Resolver($container, [
        ComestibleRepository::class,
        ComestibleWithinDayRepository::class,
        DayRepository::class,
        UserRepository::class,
    ]);
};

// services
$container[ErrorResponseHandler::class] = function () use ($container) {
    return new ErrorResponseHandler($container[TwigRender::class]);
};

$container[FormAuthentication::class] = function ($container) {
    return new FormAuthentication(
        $container['security.authentication.passwordmanager'],
        $container['session'],
        $container[UserRepository::class],
        $container['logger']
    );
};

$container[LanguageNegotiator::class] = function () use ($container) {
    return new LanguageNegotiator();
};

$container[RedirectForPath::class] = function () use ($container) {
    return new RedirectForPath($container['router']);
};

$container[RoleAuthorization::class] = function ($container) {
    return new RoleAuthorization($container['security.authorization.rolehierarchyresolver'], $container['logger']);
};

$container[TwigRender::class] = function () use ($container) {
    return new TwigRender(
        $container['security.authentication'],
        $container['debug'],
        $container['session'],
        [
            'chart_calorie' => ['charts'],
            'chart_energymix' => ['charts'],
            'chart_weight' => ['charts'],
            'charts' => [],
            'comestible_create' => ['comestible_list'],
            'comestible_delete' => ['comestible_list'],
            'comestible_update' => ['comestible_list'],
            'comestible_list' => [],
            'comestible_read' => ['comestible_list'],
            'day_create' => ['day_list'],
            'day_delete' => ['day_list'],
            'day_update' => ['day_list'],
            'day_list' => [],
            'day_read' => ['day_list'],
            'user_create' => ['user_list'],
            'user_delete' => ['user_list'],
            'user_update' => ['user_list'],
            'user_list' => [],
            'user_read' => ['user_list'],
        ],
        $container['translator'],
        $container['twig']
    );
};

// validation
$container[ValidationComestibleMapping::class] = function () use ($container) {
    return new ValidationComestibleMapping();
};

$container[ValidationComestibleSearchMapping::class] = function () use ($container) {
    return new ValidationComestibleSearchMapping();
};

$container[ValidationComestibleWithinDayMapping::class] = function () use ($container) {
    return new ValidationComestibleWithinDayMapping();
};

$container[ValidationDayMapping::class] = function () use ($container) {
    return new ValidationDayMapping($container[Resolver::class]);
};

$container[ValidationDaySearchMapping::class] = function () use ($container) {
    return new ValidationDaySearchMapping();
};

$container[ValidationUserMapping::class] = function () use ($container) {
    return new ValidationUserMapping($container[Resolver::class]);
};

$container[ValidationUserSearchMapping::class] = function () use ($container) {
    return new ValidationUserSearchMapping();
};
