<?php

namespace App\Listeners\Update\V21;

use App\Abstracts\Listeners\Update as Listener;
use App\Events\Install\UpdateFinished as Event;
use App\Models\Setting\Category;
use App\Models\Common\Company;
use App\Utilities\Overrider;
use Illuminate\Support\Facades\Artisan;

class Version210 extends Listener
{
    const ALIAS = 'core';

    const VERSION = '2.1.0';

    /**
     * Handle the event.
     *
     * @param  $event
     * @return void
     */
    public function handle(Event $event)
    {
        if ($this->skipThisUpdate($event)) {
            return;
        }

        $this->updateCompanies();

        Artisan::call('migrate', ['--force' => true]);

        #todo remove tax_id column
        $this->copyItemTax();
    }
    protected function updateCompanies()
    {
        $company_id = session('company_id');

        $companies = Company::cursor();

        foreach ($companies as $company) {
            session(['company_id' => $company->id]);

            $this->updateSettings($company);
        }

        setting()->forgetAll();

        session(['company_id' => $company_id]);

        Overrider::load('settings');
    }

    public function updateSettings($company)
    {
        $income_category = Category::income()->enabled()->first();
        $expense_category = Category::expense()->enabled()->first();

        // Set the active company settings
        setting()->setExtraColumns(['company_id' => $company->id]);
        setting()->forgetAll();
        setting()->load(true);

        setting()->set(['default.income_category' => setting('default.income_category', $income_category->id)]);
        setting()->set(['default.expense_category' => setting('default.expense_category', $expense_category->id)]);

        setting()->save();
    }

    public function copyItemTax()
    {
        $items = DB::table('items')->cursor();

        foreach ($items as $item) {
            DB::table('item_taxes')->insert([
                'company_id' => $item->company_id,
                'item_id'    => $item->id,
                'tax_id'     => $item->tax_id,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at,
            ]);
        }
    }
}