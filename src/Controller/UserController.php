<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\User;
use App\Form\LoginType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * Handles login page
     *
     * If user exists, just login. If not create a user in the db table.
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     *
     * @return RedirectResponse|Response
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/login', name: 'app_user_login')]
    public function login(Request $request, EntityManagerInterface $entityManager): RedirectResponse|Response
    {
        if(isset($_COOKIE['user_session'])){
            return $this->redirectToRoute('app_user_profile');
        }

        $user = new User();
        $form = $this->createForm(LoginType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if(isset($_COOKIE['user_session'])){
                return $this->redirectToRoute('app_user_profile');
            }

            $data = $form->getData();
            $users = $entityManager->getRepository(User::class)->findBy(['email' => $data->getEmail()]);
            $user_id = $users ? array_shift($users)->getId() : null;

            if(empty($users)){
                $user->setName($data->getName());
                $user->setEmail($data->getEmail());
                $user->setPhone($data->getPhone());

                $entityManager->persist($user);
                $entityManager->flush();

                $queryUser = $entityManager->getRepository(User::class)->findBy(['email' => $user->getEmail()]);
                $user_id   = $queryUser[0]->getId();
            }

            $cookie = new Cookie(
                "user_session",
                $user_id,
                (new \DateTime('now'))->modify("+1 day")
            );

            $response = new Response();
            $response->headers->setCookie($cookie);
            $response->sendHeaders();

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('user/login.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Removes login cookie
     *
     * @return RedirectResponse
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/logout', name: 'app_user_logout')]
    public function logOut() : RedirectResponse{
        $cookie = new Cookie(
            "user_session",
            '',
            -1
        );

        $response = new Response();
        $response->headers->setCookie($cookie);
        $response->sendHeaders();

        return $this->redirectToRoute('app_user_profile');
    }

    /**
     * Display all data on profile page
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/profile', name: 'app_user_profile')]
    public function profile(Request $request, EntityManagerInterface $entityManager) : Response
    {
        if(!isset($_COOKIE['user_session'])){
            return $this->redirectToRoute('app_user_login');
        }

        $ordersQuery = $entityManager->getRepository(Order::class)->findBy(['user' => $_COOKIE['user_session']]);
        $userQuery   = $entityManager->getRepository(User::class)->findBy(['id' => $_COOKIE['user_session']]);

        $orderParsedData = [];
        $userParsedData  = [
            'name'  => $userQuery[0]->getName(),
            'email' => $userQuery[0]->getEmail(),
            'phone' => $userQuery[0]->getPhone(),
        ];

        foreach($ordersQuery as $key => $order){
            $orderProducts = $entityManager->getRepository(OrderProduct::class)->findBy(['ordr' => $order->getId()]);
            $orderParsedData[$key] = [
                'id'            => $order->getId(),
                'summary_price' => $order->getSummaryPrice(),
                'created_at'    => $order->getCreatedAt() ? date_format($order->getCreatedAt(), "d.m.Y H:i:s") : ''
            ];

            foreach ($orderProducts as $key1 => $orderProduct){
                $product = $orderProduct->getProduct();

                $orderParsedData[$key]['products'][$key1] = [
                    'name'        => $product->getName(),
                    'description' => $product->getDescription(),
                    'quantity'    => $orderProduct->getQuantity(),
                    'price'       => $product->getPrice(),
                ];
            }
        }

        return $this->render('user/profile.html.twig', [
            'orders' => $orderParsedData,
            'user'   => $userParsedData
        ]);
    }
}
