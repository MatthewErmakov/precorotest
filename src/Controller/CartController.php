<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    /**
     * Displays all items on the cart page
     *
     * @param EntityManagerInterface $entityManager
     * @return Response
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/cart', name: 'app_cart')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!isset($_COOKIE['user_session'])) {
            return $this->redirectToRoute('app_user_login');
        }

        $products = $this->getCurrentCartProducts($entityManager);

        return $this->render('cart/index.html.twig', [
            'products' => $products
        ]);
    }

    /**
     * Adds an item to the cart
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/cart/add', name: 'add_to_cart')]
    public function addToCart(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($request->getMethod() === "POST") {
            $product_id = $request->get('product-id');
            $currentProductQuantity = $request->get('product-quantity') != '' ? $request->get('product-quantity') : 1;
            $user_id = $_COOKIE['user_session'];

            $user = $entityManager->getRepository(User::class)->findBy(['id' => $user_id]);
            $product = $entityManager->getRepository(Product::class)->findBy(['id' => $product_id]);

            $cartProduct = $entityManager->getRepository(Cart::class)->findBy([
                'product' => $product_id,
                'user' => $user_id
            ]);

            $cart = new Cart();

            if (!empty($cartProduct)) {
                $existingProductQuantity = $cartProduct[0]->getQuantity();

                $cartProduct[0]->setQuantity($existingProductQuantity + $currentProductQuantity);

                $entityManager->persist($cartProduct[0]);
            } else {
                $cart->setUser($user[0]);
                $cart->setProduct($product[0]);
                $cart->setQuantity($currentProductQuantity);

                $entityManager->persist($cart);
            }

            $entityManager->flush();

        }
        return $this->redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * Removes all records in db table connected with selected product and current user
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    #[Route('/cart/remove-product', name: 'remove_product')]
    public function removeProductFromCart(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($request->getMethod() === "POST") {
            $product_id = $request->get('product-id');
            $productCart = $entityManager->getRepository(Cart::class)->findBy(['product' => $product_id, 'user' => $_COOKIE['user_session']]);

            $entityManager->remove($productCart[0]);
            $entityManager->flush();
        }
        return $this->redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * Removes all cart records for a particular user from cart table and creates new order and writes product ids and order id to intermidiate table
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     */
    #[Route('/cart/submit-cart', name: 'submit_cart')]
    public function submitCart(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $cartQuery = $entityManager->getRepository(Cart::class)->findBy(['user' => $_COOKIE['user_session']]);
        $userQuery = $entityManager->getRepository(User::class)->findBy(['id' => $_COOKIE['user_session']]);

        $summaryPrice = 0;

        foreach ($cartQuery as $cartItem) {
            $product = $cartItem->getProduct();

            $summaryPrice += $product->getPrice() * $cartItem->getQuantity();
            $entityManager->remove($cartItem);
        }

        $user = $userQuery[0];

        $order = new Order();
        $order->setUser($user);
        $order->setSummaryPrice($summaryPrice);
        $order->setCreatedAt(new \DateTimeImmutable());

        $entityManager->persist($order);
        $entityManager->flush();

        $order = $entityManager->getRepository(Order::class)
            ->findOneBy(['user' => $_COOKIE['user_session']], ['id' => 'DESC']);

        foreach ($cartQuery as $cartItem) {
            $orderProducts = new OrderProduct();
            $product = $cartItem->getProduct();

            $orderProducts->setOrdr($order);

            $orderProducts->setProduct($product);
            $orderProducts->setQuantity($cartItem->getQuantity());
            $orderProducts->setPrice($product->getPrice());

            $entityManager->persist($orderProducts);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_profile');
    }

    /**
     * Get all cart products for a particular user
     *
     * @param EntityManagerInterface $entityManager
     * @return array
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    private function getCurrentCartProducts(EntityManagerInterface $entityManager): array
    {
        $cartQuery = $entityManager->getRepository(Cart::class)->findBy(['user' => $_COOKIE['user_session']]);
        $cartProducts = [];

        foreach ($cartQuery as $cartItem) {
            $product = $entityManager->getRepository(Product::class)->findBy(['id' => $cartItem->getProduct()]);

            $cartProducts[] = [
                'id' => $product[0]->getId(),
                'name' => $product[0]->getName(),
                'description' => $product[0]->getDescription(),
                'price' => $product[0]->getPrice(),
                'quantity' => $cartItem->getQuantity()
            ];
        }

        return $cartProducts;
    }
}
