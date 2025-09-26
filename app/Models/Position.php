<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Traits\HasPermissions;

class Position extends SpatieRole
{
    protected $table = 'bs_positions';
    use HasPermissions;
    protected $fillable = ['name', 'permissions'];

    protected $casts = [
        'permissions' => 'array',
    ];
   /* public function users()
    {
        return $this->hasMany(User::class);
    }*/
}
