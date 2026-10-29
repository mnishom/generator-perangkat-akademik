<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleAccessRule extends Model
{
    public const SCOPE_PRODI = 'prodi';

    public const SCOPE_USER = 'user';

    protected $table = 'module_access';

    protected $fillable = ['module_key', 'scope', 'scope_id', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }
}
