<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryBook extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function folders()
    {
        return $this->hasMany(LibraryFolder::class);
    }

    public function files()
    {
        return $this->hasMany(LibraryFile::class);
    }
}
