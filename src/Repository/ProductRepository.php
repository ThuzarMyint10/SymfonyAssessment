<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findByFilters($searchTerm, $minPrice, $maxPrice, $minStock, $maxStock, $startDate, $endDate, $sortColumn, $sortDirection): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($searchTerm && strlen($searchTerm) >= 1) {
            $qb->andWhere('p.name LIKE :searchTerm OR p.description LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }    

        if ($minPrice) {
            $qb->andWhere('p.price >= :minPrice')
            ->setParameter('minPrice', (float) $minPrice); 
        }
        
        if ($maxPrice) {
            $qb->andWhere('p.price <= :maxPrice')
            ->setParameter('maxPrice', (float) $maxPrice); 
        }

        if ($minStock) {
            $qb->andWhere('p.stockQuantity >= :minStock')
            ->setParameter('minStock', $minStock);
        }

        if ($maxStock) {
            $qb->andWhere('p.stockQuantity <= :maxStock')
            ->setParameter('maxStock', $maxStock);
        }

        if ($startDate) {
            $qb->andWhere('p.createdAt >= :startDate')
            ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('p.createdAt <= :endDate')
            ->setParameter('endDate', $endDate);
        } 
        $qb->orderBy('p.' . $sortColumn, $sortDirection);

        return $qb->getQuery()->getResult();
    }


//    /**
//     * @return Product[] Returns an array of Product objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Product
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
