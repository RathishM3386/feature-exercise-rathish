<?php

namespace Tests\Feature;

use App\Product;
use App\Category;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ViewShopPageTest extends TestCase
{
    use RefreshDatabase;

   /** @test */
    public function shop_page_loads_correctly()
    {
        //Arrange

        //Act
        $response = $this->get('/shop');

        //Assert
        $response->assertStatus(200);
        $response->assertSee('Featured');
    }

    /** @test */
    public function featured_product_is_visible()
    {
        // Arrange
        $featuredProduct = factory(Product::class)->create([
            'featured' => true,
            'name' => 'Laptop 1',
            'price' => 149999,
        ]);

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertSee($featuredProduct->name);
        $response->assertSee('$1499.99');
    }

    /** @test */
    public function not_featured_product_is_not_visible()
    {
        // Arrange
        $notFeaturedProduct = factory(Product::class)->create([
            'featured' => false,
            'name' => 'Laptop 1',
            'price' => 149999,
        ]);

        // Act
        $response = $this->get('/');

        // Assert
        $response->assertDontSee($notFeaturedProduct->name);
        $response->assertDontSee('$1499.99');
    }

    /** @test */
    public function pagination_for_products_works()
    {
        // Page 1 products
        for ($i=11; $i < 20 ; $i++) {
            factory(Product::class)->create([
                'featured' => true,
                'name' => 'Product '.$i,
            ]);
        }

        // Page 2 products
        for ($i=21; $i < 30 ; $i++) {
            factory(Product::class)->create([
                'featured' => true,
                'name' => 'Product '.$i,
            ]);
        }

        $response = $this->get('/shop');

        $response->assertSee('Product 11');
        $response->assertSee('Product 19');

        $response = $this->get('/shop?page=2');

        $response->assertSee('Product 21');
        $response->assertSee('Product 29');
    }

    /** @test */
    public function sort_price_low_to_high()
    {
        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product Middle',
            'price' => 15000,
        ]);

        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product Low',
            'price' => 10000,
        ]);

        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product High',
            'price' => 20000,
        ]);

        $response = $this->get('/shop?sort=low_high');

        $response->assertSeeInOrder(['Product Low', 'Product Middle', 'Product High']);
    }

    /** @test */
    public function sort_price_high_to_low()
    {
        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product Middle',
            'price' => 15000,
        ]);

        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product Low',
            'price' => 10000,
        ]);

        factory(Product::class)->create([
            'featured' => true,
            'name' => 'Product High',
            'price' => 20000,
        ]);

        $response = $this->get('/shop?sort=high_low');

        $response->assertSeeInOrder(['Product High', 'Product Middle', 'Product Low']);
    }

    /** @test */
    public function category_page_shows_correct_products()
    {
        $laptop1 = factory(Product::class)->create(['name' => 'Laptop 1']);
        $laptop2 = factory(Product::class)->create(['name' => 'Laptop 2']);

        $laptopsCategory = Category::create([
            'name' => 'laptops',
            'slug' => 'laptops',
        ]);

        $laptop1->categories()->attach($laptopsCategory->id);
        $laptop2->categories()->attach($laptopsCategory->id);

        $response = $this->get('/shop?category=laptops');

        $response->assertSee('Laptop 1');
        $response->assertSee('Laptop 2');
    }

    /** @test */
    public function category_page_does_not_show_products_in_another_category()
    {
        $laptop1 = factory(Product::class)->create(['name' => 'Laptop 1']);
        $laptop2 = factory(Product::class)->create(['name' => 'Laptop 2']);

        $laptopsCategory = Category::create([
            'name' => 'laptops',
            'slug' => 'laptops',
        ]);

        $laptop1->categories()->attach($laptopsCategory->id);
        $laptop2->categories()->attach($laptopsCategory->id);

        $desktop1 = factory(Product::class)->create(['name' => 'Desktop 1']);
        $desktop2 = factory(Product::class)->create(['name' => 'Desktop 2']);

        $desktopsCategory = Category::create([
            'name' => 'Desktops',
            'slug' => 'desktops',
        ]);

        $desktop1->categories()->attach($desktopsCategory->id);
        $desktop2->categories()->attach($desktopsCategory->id);

        $response = $this->get('/shop?category=laptops');

        $response->assertDontSee('Desktop 1');
        $response->assertDontSee('Desktop 2');
    }

    /** @test */
    public function checkout_inventory_price_based_on_unit_price_and_special_price()
    {
        $product['1'] = factory(Product::class)->create(['name' => 'Product A', 'price' => 50]);
        $product['2'] = factory(Product::class)->create(['name' => 'Product B', 'price' => 30]);

        $offer = [
            '1' => [
                'qty' => 3,
                'price' => 130
            ],
            '2' => [
                'qty' => 2,
                'price' => 45
            ]
        ];

        $cart = [
            '1' => 3,
            '2' => 5
        ];

        $this->browse(function (Browser $browser, $offer, $cart, $product) {
            foreach($cart as $id => $qty) {
                if($offer[$id]['qty'] >= $qty) {
                    $browser->visit('/cart')
                            ->assertSee('Product A')
                            ->assertPathIs('/cart')
                            ->select('.quantity', $qty)
                            ->select('.price', $offer[$id]['price'])
                            ->pause(1000)
                            ->assertSee('Special price applied');

                    $this->assertEquals($offer[$id]['price'],$qty*$product[$id]['price']);
                }
            }
        });
    }
}
