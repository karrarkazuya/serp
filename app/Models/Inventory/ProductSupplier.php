<?php

namespace App\Models\Inventory;

use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSupplier extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_product_suppliers';

    protected $fillable = [
        'product_id', 'partner_id', 'partner_name', 'partner_product_name',
        'partner_product_code', 'min_qty', 'price', 'delay', 'active',
    ];

    protected $casts = [
        'min_qty' => 'decimal:4',
        'price'   => 'decimal:4',
        'active'  => 'boolean',
    ];

    public function product(): BelongsTo  { return $this->belongsTo(Product::class, 'product_id'); }
    public function partner(): BelongsTo  { return $this->belongsTo(Contact::class, 'partner_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }
}
