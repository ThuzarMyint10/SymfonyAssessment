<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_import']) {
            // Add the csvFile field for the import form
            $builder->add('csvFile', FileType::class, [
                'label' => 'CSV File',
                'mapped' => false, 
                'required' => true,
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid CSV file',
                    ]),
                ],
            ]);
        } else {
            // Add fields for product creation
            $builder
                ->add('name', TextType::class)
                ->add('description', TextType::class)
                ->add('price', TextType::class)
                ->add('stockQuantity', TextType::class)
                ->add('createdAt', null, [
                    'widget' => 'single_text',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'is_import' => false, 
        ]);
    }
}

