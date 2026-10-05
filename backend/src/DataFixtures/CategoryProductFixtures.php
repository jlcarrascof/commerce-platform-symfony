<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryProductFixtures extends Fixture
{
    public const CATEGORY_ELECTRONICS = 'category-electronics';
    public const CATEGORY_HOME = 'category-home';
    public const CATEGORY_SPORTS = 'category-sports';
    public const CATEGORY_OFFICE = 'category-office';
    public const CATEGORY_TOYS = 'category-toys';

    public function load(ObjectManager $manager): void
    {
        $categories = [
            self::CATEGORY_ELECTRONICS => new Category('Electronics', 'electronics'),
            self::CATEGORY_HOME => new Category('Home & Kitchen', 'home-kitchen'),
            self::CATEGORY_SPORTS => new Category('Sports & Outdoors', 'sports-outdoors'),
            self::CATEGORY_OFFICE => new Category('Office Supplies', 'office-supplies'),
            self::CATEGORY_TOYS => new Category('Toys & Games', 'toys-games'),
        ];

        foreach ($categories as $reference => $category) {
            $manager->persist($category);
            $this->addReference($reference, $category);
        }

        $products = [
            ['Wireless Mouse', 'wireless-mouse', 'Ergonomic wireless mouse with USB receiver.', 2499, 80, self::CATEGORY_ELECTRONICS],
            ['Mechanical Keyboard', 'mechanical-keyboard', 'RGB mechanical keyboard with blue switches.', 6999, 45, self::CATEGORY_ELECTRONICS],
            ['27-inch Monitor', '27-inch-monitor', 'Full HD IPS monitor with slim bezels.', 15999, 20, self::CATEGORY_ELECTRONICS],
            ['USB-C Hub', 'usb-c-hub', '7-in-1 USB-C hub with HDMI and card reader.', 3999, 60, self::CATEGORY_ELECTRONICS],
            ['Noise Cancelling Headphones', 'noise-cancelling-headphones', 'Over-ear headphones with active noise cancelling.', 12999, 30, self::CATEGORY_ELECTRONICS],
            ['Non-stick Frying Pan', 'non-stick-frying-pan', '28cm non-stick frying pan, induction compatible.', 1899, 50, self::CATEGORY_HOME],
            ['Electric Kettle', 'electric-kettle', '1.7L stainless steel electric kettle.', 2299, 40, self::CATEGORY_HOME],
            ['Knife Set', 'knife-set', '6-piece stainless steel kitchen knife set.', 3499, 35, self::CATEGORY_HOME],
            ['Throw Blanket', 'throw-blanket', 'Soft fleece throw blanket, 150x200cm.', 1599, 70, self::CATEGORY_HOME],
            ['Scented Candle Set', 'scented-candle-set', 'Set of 3 scented candles in glass jars.', 1299, 90, self::CATEGORY_HOME],
            ['Yoga Mat', 'yoga-mat', 'Non-slip yoga mat with carrying strap.', 1999, 65, self::CATEGORY_SPORTS],
            ['Adjustable Dumbbells', 'adjustable-dumbbells', 'Pair of adjustable dumbbells, 2-20kg each.', 8999, 15, self::CATEGORY_SPORTS],
            ['Running Shoes', 'running-shoes', 'Lightweight running shoes with breathable mesh.', 5999, 25, self::CATEGORY_SPORTS],
            ['Insulated Water Bottle', 'insulated-water-bottle', '750ml stainless steel insulated bottle.', 1799, 100, self::CATEGORY_SPORTS],
            ['Notebook Set', 'notebook-set', 'Pack of 3 A5 lined notebooks.', 999, 120, self::CATEGORY_OFFICE],
            ['Desk Organizer', 'desk-organizer', 'Multi-compartment desk organizer tray.', 1499, 55, self::CATEGORY_OFFICE],
            ['Ergonomic Office Chair', 'ergonomic-office-chair', 'Mesh-back office chair with lumbar support.', 18999, 10, self::CATEGORY_OFFICE],
            ['Building Blocks Set', 'building-blocks-set', '500-piece creative building blocks set.', 2999, 40, self::CATEGORY_TOYS],
            ['Remote Control Car', 'remote-control-car', 'Off-road RC car with rechargeable battery.', 4999, 30, self::CATEGORY_TOYS],
            ['Puzzle 1000 Pieces', 'puzzle-1000-pieces', 'Landscape-themed jigsaw puzzle, 1000 pieces.', 1399, 50, self::CATEGORY_TOYS],
        ];

        foreach ($products as [$name, $slug, $description, $price, $stock, $categoryReference]) {
            $product = new Product(
                $name,
                $slug,
                $description,
                $price,
                $stock,
                $categories[$categoryReference],
            );
            $manager->persist($product);
        }

        $manager->flush();
    }
}
