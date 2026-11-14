<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'bs_contact_messages';

    protected $fillable = ['name', 'contact', 'message'];
}
