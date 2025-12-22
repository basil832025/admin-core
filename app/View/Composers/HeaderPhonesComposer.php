<?php

namespace App\View\Composers;

use Illuminate\View\View;
use App\Services\HeaderContacts;

class HeaderPhonesComposer
{
    public function __construct(protected HeaderContacts $contacts) {}

    public function compose(View $view): void
    {
     //   $data = $this->contacts->build(session('location_id'));
        $slug  = config('site.header_location_slug', '3pie');
        $data  = app(HeaderContacts::class)->buildBySlug($slug);
        $view->with('headerPhones', $data['phones']);
        $view->with('headerPhonePrimary', $data['primary']);
        $view->with('headerLocation', $data['location']);
        $view->with('headerSchedule', $data['schedule']);
    }
}
