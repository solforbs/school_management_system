<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Expenditure\Models\ExpenditureCategory;
use Modules\Institution\Models\Institution;

class GreenValleyExpenditureHistorySeeder extends Seeder
{
    private const CATEGORIES = [
        'Utilities & Electricity' => 'Monthly electricity, water, and Internet bandwidth invoices.',
        'Laboratory Supplies' => 'Chemical reagents, glassware, and science equipment.',
        'Textbook & Library Procurement' => 'Course textbooks, reference literature, and library journals.',
        'Transport & Fleet Maintenance' => 'Fuel, vehicle servicing, and insurance for school buses.',
        'Facility Repairs & Maintenance' => 'Building repairs, plumbing, painting, and groundskeeping.',
        'Sports & Co-Curricular Equipment' => 'Football/basketball gear, drama costumes, and athletics equipment.',
        'ICT Hardware & Software' => 'Computer lab maintenance, printer toner, and software licenses.',
        'Catering & Kitchen Provisions' => 'Food supplies, kitchen gas, and dining hall consumables.',
    ];

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        $categoryIds = [];

        foreach (self::CATEGORIES as $name => $desc) {
            $cat = ExpenditureCategory::firstOrCreate(
                ['institution_id' => $institution->id, 'name' => $name],
                ['description' => $desc, 'is_active' => true]
            );
            $categoryIds[] = $cat->id;
        }

        $expendituresToInsert = [];

        $vendors = [
            'Kenya Power & Lighting Co.', 'Nairobi Water & Sewerage', 'Safaricom Business Internet',
            'Text Book Centre Ltd', 'Kenia Science Supplies Ltd', 'TotalEnergies Kenya',
            'Crown Paints Ltd', 'Superb Sports Goods', 'TechSys Solutions Kenya', 'Farmers Choice Ltd',
        ];

        // Seed monthly expenses for 36 months (Jan 2024 - Dec 2026)
        for ($month = 1; $month <= 36; $month++) {
            $spendDate = now()->subMonths(37 - $month)->startOfMonth()->addDays(rand(5, 20))->toDateString();
            $createdAt = now()->parse($spendDate);

            foreach ($categoryIds as $idx => $catId) {
                $vendor = $vendors[$idx % count($vendors)];
                $amount = (float) rand(18000, 150000);
                $methods = ['bank_transfer', 'mobile_money', 'cheque'];
                $method = $methods[rand(0, 2)];

                $expendituresToInsert[] = [
                    'institution_id' => $institution->id,
                    'expenditure_category_id' => $catId,
                    'recorded_by' => $institution->user_id,
                    'title' => 'Monthly '.self::CATEGORIES_KEYS()[$idx % count(self::CATEGORIES_KEYS())].' Invoice',
                    'payee' => $vendor,
                    'amount' => $amount,
                    'spent_on' => $spendDate,
                    'payment_method' => $method,
                    'reference' => 'EXP-'.strtoupper(Str::random(8)),
                    'status' => 'paid',
                    'paid_at' => $spendDate,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        foreach (array_chunk($expendituresToInsert, 200) as $chunk) {
            DB::table('expenditures')->insert($chunk);
        }
    }

    private static function CATEGORIES_KEYS(): array
    {
        return array_keys(self::CATEGORIES);
    }
}
