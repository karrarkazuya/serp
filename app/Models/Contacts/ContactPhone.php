<?php

namespace App\Models\Contacts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPhone extends Model
{
    protected $fillable = ['contact_id', 'phone'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
