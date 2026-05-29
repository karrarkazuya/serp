<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data): Product
    {
        $product = Product::create($data);
        $this->chatterService->logCreated($product, __('inventory.chatter_label_product'));
        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $changes = $this->detectChanges($product, $data);
        $product->update($data);
        if (!empty($changes)) {
            $this->chatterService->logUpdated($product, $changes, __('inventory.chatter_label_product'));
        }
        return $product->fresh();
    }

    public function archive(Product $product): Product
    {
        $product->update(['active' => false]);
        $this->chatterService->logArchived($product, __('inventory.chatter_label_product'));
        return $product;
    }

    public function unarchive(Product $product): Product
    {
        $product->update(['active' => true]);
        $this->chatterService->logUnarchived($product, __('inventory.chatter_label_product'));
        return $product;
    }

    public function delete(Product $product): void
    {
        $this->chatterService->log($product, __('inventory.chatter_product_deleted'), 'system');
        $product->delete();
    }

    public function syncSuppliers(Product $product, array $suppliersData): void
    {
        $product->suppliers()->delete();
        foreach ($suppliersData as $row) {
            if (empty($row['price']) && empty($row['partner_id']) && empty($row['partner_name'])) continue;
            $product->suppliers()->create([
                'partner_id'           => $row['partner_id'] ?? null,
                'partner_name'         => $row['partner_name'] ?? null,
                'partner_product_name' => $row['partner_product_name'] ?? null,
                'partner_product_code' => $row['partner_product_code'] ?? null,
                'min_qty'              => $row['min_qty'] ?? 0,
                'price'                => $row['price'] ?? 0,
                'delay'                => $row['delay'] ?? 1,
                'active'               => true,
                'created_by'           => auth()->id(),
                'updated_by'           => auth()->id(),
            ]);
        }
    }

    private function detectChanges(Product $product, array $data): array
    {
        $changes = [];
        foreach ($product->chatterTracked as $field => $definition) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($product->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $label  = is_array($definition) ? $definition['label'] : $definition;
            $table  = is_array($definition) ? ($definition['table'] ?? null) : null;
            $column = is_array($definition) ? ($definition['column'] ?? 'name') : null;
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveDisplay($old ?: null, $table, $column),
                'to'    => $this->resolveDisplay($new ?: null, $table, $column),
            ];
        }
        return $changes;
    }

    private function resolveDisplay(?string $id, ?string $table, ?string $column = null): string
    {
        if ($id === null || $id === '') return '—';
        if (!$table) return $id;
        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) return $id;
        return (string) ($column ? ($row->{$column} ?? $id) : ($row->name ?? $id));
    }
}
