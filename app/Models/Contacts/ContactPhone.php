<?php

namespace App\Models\Contacts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ContactPhone extends Model
{
    use SoftDeletes;

    protected $fillable = ['contact_id', 'phone'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
