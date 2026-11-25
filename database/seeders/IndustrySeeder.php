<?php

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndustrySeeder extends Seeder
{
    /**
     * Seed the industries a freelancer has experience in. Safe to run again: entries are matched by slug.
     */
    public function run(): void
    {
        $names = [
            '3rd Party Retailers (Online & Physical)',
            'Agency',
            'Alcohol',
            'Apparel & Accessories',
            'Art & Collectibles',
            'Automotive Parts & Accessories',
            'B2B SaaS',
            'Baby & Kids Products',
            'Books & Music & Movies',
            'Cannabis',
            'Consumer Electronics',
            'Consumer Technology',
            'Cryptocurrency',
            'Digital Products',
            'Dropshipping & Wholesale',
            'Food & Beverage',
            'Footwear',
            'Gift Cards & Vouchers',
            'Handmade & Custom Goods',
            'Health & Beauty',
            'Healthcare',
            'Home & Garden',
            'Industrial & Scientific Supplies',
            'Jewelry & Watches',
            'Media & Entertainment',
            'Mental Health & Wellness',
            'Office Supplies & Equipment',
            'Online Auctions & Marketplaces',
            'Online Dating Services',
            'Online Education & e-Learning',
            'Online Financial Services',
            'Online Gaming & Gambling',
            'Online Real Estate & Property Listings',
            'Online Ticketing',
            'Pet Supplies',
            'Refurbished & Used Goods',
            'Restaurants & Hospitality',
            'Sexual Wellness',
            'Smoking & Tobacco Products',
            'Sports & Outdoor Equipment',
            'Subscription Boxes',
            'Toys & Games',
            'Travel & Experience Booking',
        ];

        foreach ($names as $name) {
            Industry::query()->updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
