<?php

namespace Energycalculator\Controller;

use Chubbyphp\Deserialize\DeserializerInterface;
use Chubbyphp\ErrorHandler\HttpException;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Security\Authentication\AuthenticationInterface;
use Chubbyphp\Security\Authorization\AuthorizationInterface;
use Chubbyphp\Security\Authorization\RoleHierarchyResolverInterface;
use Chubbyphp\Validation\ValidatorInterface;
use Energycalculator\Repository\UserRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Energycalculator\Model\User;
use Chubbyphp\Session\FlashMessage;
use Chubbyphp\Session\SessionInterface;
use Energycalculator\Service\RedirectForPath;
use Energycalculator\Service\TemplateData;
use Energycalculator\Service\TwigRender;

final class UserController
{
    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var DeserializerInterface
     */
    private $deserializer;

    /**
     * @var RedirectForPath
     */
    private $redirectForPath;

    /**
     * @var RoleHierarchyResolverInterface
     */
    private $roleHierarchyResolver;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TemplateData
     */
    private $templateData;

    /**
     * @var TwigRender
     */
    private $twig;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param AuthenticationInterface        $authentication
     * @param AuthorizationInterface         $authorization
     * @param DeserializerInterface          $deserializer
     * @param RedirectForPath                $redirectForPath
     * @param RoleHierarchyResolverInterface $roleHierarchyResolver
     * @param SessionInterface               $session
     * @param TemplateData                   $templateData
     * @param TwigRender                     $twig
     * @param UserRepository                 $userRepository
     * @param ValidatorInterface             $validator
     */
    public function __construct(
        AuthenticationInterface $authentication,
        AuthorizationInterface $authorization,
        DeserializerInterface $deserializer,
        RedirectForPath $redirectForPath,
        RoleHierarchyResolverInterface $roleHierarchyResolver,
        SessionInterface $session,
        TemplateData $templateData,
        TwigRender $twig,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ) {
        $this->authentication = $authentication;
        $this->authorization = $authorization;
        $this->deserializer = $deserializer;
        $this->redirectForPath = $redirectForPath;
        $this->roleHierarchyResolver = $roleHierarchyResolver;
        $this->session = $session;
        $this->templateData = $templateData;
        $this->twig = $twig;
        $this->userRepository = $userRepository;
        $this->validator = $validator;
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    public function listAll(Request $request, Response $response)
    {
        if (!$this->authorization->isGranted($this->authentication->getAuthenticatedUser($request), 'ADMIN')) {
            throw HttpException::create($request, $response, 403, 'user.error.permissiondenied');
        }

        $users = $this->userRepository->findBy([]);

        return $this->twig->render($response, '@Energycalculator/user/list.html.twig',
            $this->templateData->aggregate($request, [
                'users' => prepareForView($users),
            ])
        );
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpException
     */
    public function view(Request $request, Response $response)
    {
        if (!$this->authorization->isGranted($this->authentication->getAuthenticatedUser($request), 'ADMIN')) {
            throw HttpException::create($request, $response, 403, 'user.error.permissiondenied');
        }

        $id = $request->getAttribute('id');

        $user = $this->userRepository->find($id);
        if (null === $user) {
            throw HttpException::create($request, $response, 404, 'user.error.notfound');
        }

        return $this->twig->render($response, '@Energycalculator/user/view.html.twig',
            $this->templateData->aggregate($request, [
                'user' => prepareForView($user),
            ])
        );
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     */
    public function create(Request $request, Response $response)
    {
        $authenticatedUser = $this->authentication->getAuthenticatedUser($request);

        if (!$this->authorization->isGranted($authenticatedUser, 'ADMIN')) {
            throw HttpException::create($request, $response, 403, 'user.error.permissiondenied');
        }

        $user = User::create();

        if ('POST' === $request->getMethod()) {
            /** @var User $user */
            $user = $this->deserializer->deserializeByObject($request->getParsedBody(), $user);

            if ([] === $errorMessages = $this->validator->validateObject($user)) {
                $this->userRepository->persist($user);
                $this->session->addFlash(
                    $request,
                    new FlashMessage(FlashMessage::TYPE_SUCCESS, 'user.flash.create.success')
                );

                return $this->redirectForPath->get($response, 302, 'user_edit', [
                    'locale' => $request->getAttribute('locale'),
                    'id' => $user->getId(),
                ]);
            }

            $this->session->addFlash(
                $request,
                new FlashMessage(FlashMessage::TYPE_DANGER, 'user.flash.create.failed')
            );
        }

        $possibleRoles = $this->roleHierarchyResolver->resolve(['ADMIN']);

        return $this->twig->render($response, '@Energycalculator/user/create.html.twig',
            $this->templateData->aggregate($request, [
                'errorMessages' => $errorMessages ?? [],
                'user' => prepareForView($user),
                'possibleRoles' => array_combine($possibleRoles, $possibleRoles),
            ])
        );
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpException
     */
    public function edit(Request $request, Response $response)
    {
        $authenticatedUser = $this->authentication->getAuthenticatedUser($request);

        if (!$this->authorization->isGranted($authenticatedUser, 'ADMIN')) {
            throw HttpException::create($request, $response, 403, 'user.error.permissiondenied');
        }

        $id = $request->getAttribute('id');

        /** @var User $user */
        $user = $this->userRepository->find($id);
        if (null === $user) {
            throw HttpException::create($request, $response, 404, 'user.error.notfound');
        }

        if ('POST' === $request->getMethod()) {
            /** @var User|ModelInterface $user */
            $user = $this->deserializer->deserializeByObject($request->getParsedBody(), $user);

            if ([] === $errorMessages = $this->validator->validateObject($user)) {
                $this->userRepository->persist($user);
                $this->session->addFlash(
                    $request,
                    new FlashMessage(FlashMessage::TYPE_SUCCESS, 'user.flash.edit.success')
                );

                return $this->redirectForPath->get($response, 302, 'user_edit', [
                    'locale' => $request->getAttribute('locale'),
                    'id' => $user->getId(),
                ]);
            }

            $this->session->addFlash(
                $request,
                new FlashMessage(FlashMessage::TYPE_DANGER, 'user.flash.edit.failed')
            );
        }

        $possibleRoles = $this->roleHierarchyResolver->resolve(['ADMIN']);

        return $this->twig->render($response, '@Energycalculator/user/edit.html.twig',
            $this->templateData->aggregate($request, [
                'errorMessages' => $errorMessages ?? [],
                'user' => prepareForView($user),
                'possibleRoles' => array_combine($possibleRoles, $possibleRoles),
            ])
        );
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws HttpException
     */
    public function delete(Request $request, Response $response)
    {
        $authenticatedUser = $this->authentication->getAuthenticatedUser($request);

        if (!$this->authorization->isGranted($authenticatedUser, 'ADMIN')) {
            throw HttpException::create($request, $response, 403, 'user.error.permissiondenied');
        }

        $id = $request->getAttribute('id');

        /** @var User $user */
        $user = $this->userRepository->find($id);
        if (null === $user) {
            throw HttpException::create($request, $response, 404, 'user.error.notfound');
        }

        if ($authenticatedUser->getId() === $user->getId()) {
            throw HttpException::create($request, $response, 403, 'user.error.cantdeleteyourself');
        }

        $this->userRepository->remove($user);

        return $this->redirectForPath->get($response, 302, 'user_list', ['locale' => $request->getAttribute('locale')]);
    }
}
