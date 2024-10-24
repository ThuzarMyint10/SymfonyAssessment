<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(name: 'app_product_index', methods: ['GET', 'POST'])]
    public function index(ProductRepository $productRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Fetch filter inputs
    $searchTerm = $request->query->get('search', '');
    $minPrice = $request->query->get('minPrice');
    $maxPrice = $request->query->get('maxPrice');
    $minStock = $request->query->get('minStock');
    $maxStock = $request->query->get('maxStock');
    $startDate = $request->query->get('startDate');
    $endDate = $request->query->get('endDate');
    
     // Fetch sorting inputs
     $sortColumn = $request->query->get('sort', 'name'); // Default sort by name
     $sortDirection = $request->query->get('direction', 'asc'); // Default sort direction is ascending
  
        if ($searchTerm) {
            $products = $productRepository->findByFilters($searchTerm, $minPrice, $maxPrice, $minStock, $maxStock, $startDate, $endDate, $sortColumn, $sortDirection);

        } elseif ($sortColumn){ 
            if (!in_array($sortDirection, ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }
 
            $products = $productRepository->findBy([], [$sortColumn => $sortDirection]);
        }
        else {
            $products = $productRepository->findAll(); // Show all products if no search term
        }
 
        $forms = []; 
            $importForm = $this->createForm(ProductType::class, null, ['is_import' => true]); // Set is_import to true

        foreach ($products as $product) {
            $form = $this->createForm(ProductType::class, $product);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
            }
 
            $forms[$product->getId()] = $form->createView();
        }
 
        return $this->render('product/index.html.twig', [
            'products' => $products,
            'searchTerm' => $searchTerm,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'minStock' => $minStock,
            'maxStock' => $maxStock,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'sortColumn' => $sortColumn, 
            'sortDirection' => $sortDirection,
            'forms' => $forms,  
            'importForm' => $importForm->createView(), 
        ]);
    }


    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product, ['is_import' => false]); // For product creation
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    { 
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);
     
        if ($form->isSubmitted() && $form->isValid()) { 
            $entityManager->flush(); 
            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }
     
        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route("/products/import", name:"app_product_import", methods:["POST"])]
    public function import(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, null, ['is_import' => true]); // Pass is_import as true
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('csvFile')->getData();
            if ($file) {
                $filename = $file->getPathname();
                $handle = fopen($filename, 'r');

                // Fetch existing products to avoid duplicates
                $existingProducts = $entityManager->getRepository(Product::class)->findAll();
                $existingNames = array_map(fn($product) => $product->getName(), $existingProducts);

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $productName = $data[0];

                    if (in_array($productName, $existingNames)) {
                        continue; 
                    }

                    $product = new Product();
                    $product->setName($productName);
                    $product->setDescription($data[1]);
                    $product->setPrice($data[2]);
                    $product->setStockQuantity($data[3]);

                    $entityManager->persist($product);
                }
                fclose($handle);
                $entityManager->flush();

                $this->addFlash('success', 'Products imported successfully!');
            }
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route("/products/export", name: "app_product_export", methods: ["GET"])]
    public function export(): Response
    {
        // Get the repository using the injected entity manager
        $products = $this->entityManager->getRepository(Product::class)->findAll();

        $csvFileName = 'products.csv';
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $csvFileName . '"');

        // Open a stream to 'php://output'
        $handle = fopen('php://output', 'w+');
        // Write CSV header
        fputcsv($handle, ['Id', 'Name', 'Description', 'Price', 'Stock Quantity', 'Created At']);

        // Write the product data
        foreach ($products as $product) {
            fputcsv($handle, [
                $product->getId(),
                $product->getName(),
                $product->getDescription(),
                $product->getPrice(),
                $product->getStockQuantity(),
                $product->getCreatedAt()->format('Y-m-d H:i')
            ]);
        }

        fclose($handle);

        return $response;
    }


}
