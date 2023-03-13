<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CatalogController extends AbstractController
{
    /**
     * Catalog page output
     *
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/catalog', name: 'app_catalog')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        if(!isset($_COOKIE['user_session'])){
            return $this->redirectToRoute('app_user_login');
        }

        $products = $this->getProducts($entityManager);

        return $this->render('catalog/index.html.twig', [
            'controller_name' => 'CatalogController',
            'products'        => $products
        ]);
    }

    /**
     * Get all existing
     *
     * @param EntityManagerInterface $entityManager
     * @return array
     *
     * @author Matthew Ermakov <mazdaraser.91@gmail.com>
     */
    private function getProducts(EntityManagerInterface $entityManager) : array
    {
        $productsQuery      = $entityManager->getRepository(Product::class)->findAll();
        $productsParsedData = [];

        foreach ($productsQuery as $product){
            $productsParsedData[] = [
                'id'          => $product->getId(),
                'name'        => $product->getName(),
                'description' => $product->getDescription(),
                'price'       => $product->getPrice(),
            ];
        }

        return $productsParsedData;
    }
}
