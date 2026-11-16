<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Ecommerce\Shared\Auth\AuthenticatedUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** sort parameter => [column, direction] */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'price' => ['price_amount', 'asc'],
        '-price' => ['price_amount', 'desc'],
        'name' => ['name', 'asc'],
        '-name' => ['name', 'desc'],
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:255'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0'],
            'ids' => ['sometimes', 'array', 'max:100'],
            'ids.*' => ['uuid'],
            'sort' => ['sometimes', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('catalog.max_per_page')],
        ]);

        [$column, $direction] = self::SORTS[$filters['sort'] ?? 'newest'];

        $products = $this->visibleTo($request)
            ->with('category')
            ->when(isset($filters['q']), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', '%'.$filters['q'].'%')
                    ->orWhereLike('sku', '%'.$filters['q'].'%')
            ))
            ->when(isset($filters['category']), fn (Builder $query) => $query->whereIn(
                'category_id',
                // Accepts a category id or slug; an unknown category yields no products.
                Category::query()
                    ->when(
                        Str::isUuid($filters['category']),
                        fn (Builder $query) => $query->whereKey($filters['category']),
                        fn (Builder $query) => $query->where('slug', $filters['category']),
                    )
                    ->select('id')
            ))
            ->when(isset($filters['min_price']), fn (Builder $query) => $query->where('price_amount', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn (Builder $query) => $query->where('price_amount', '<=', $filters['max_price']))
            ->when(isset($filters['ids']), fn (Builder $query) => $query->whereKey($filters['ids']))
            ->orderBy($column, $direction)
            ->orderBy('id', $direction) // tiebreaker keeps cursor pagination stable
            ->cursorPaginate($filters['per_page'] ?? config('catalog.per_page'))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /** Looks a product up by id or slug. */
    public function show(Request $request, string $product): ProductResource
    {
        $found = $this->visibleTo($request)
            ->with('category')
            ->when(
                Str::isUuid($product),
                fn (Builder $query) => $query->whereKey($product),
                fn (Builder $query) => $query->where('slug', $product),
            )
            ->firstOrFail();

        return new ProductResource($found);
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validated($request));

        return (new ProductResource($product->load('category')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $product->update($this->validated($request, $product));

        return new ProductResource($product->load('category'));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * Customers and guests only see active products; admins see everything.
     *
     * @return Builder<Product>
     */
    private function visibleTo(Request $request): Builder
    {
        $user = $request->user();
        $isAdmin = $user instanceof AuthenticatedUser && $user->isAdmin();

        return Product::query()->when(! $isAdmin, fn (Builder $query) => $query->where('is_active', true));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product = null): array
    {
        $required = $product === null ? 'required' : 'sometimes';

        if ($product === null) {
            // Defaults on creation: slug from the name, currency from config.
            if (! $request->filled('slug') && is_string($request->input('name'))) {
                $request->merge(['slug' => Str::slug($request->input('name'))]);
            }

            if (! $request->filled('currency')) {
                $request->merge(['currency' => config('catalog.default_currency')]);
            }
        }

        if (is_string($request->input('currency'))) {
            $request->merge(['currency' => Str::upper($request->input('currency'))]);
        }

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products')->ignore($product)],
            'sku' => [$required, 'string', 'max:64', Rule::unique('products')->ignore($product)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price_amount' => [$required, 'integer', 'min:0'],
            'currency' => [$required, 'string', 'regex:/^[A-Z]{3}$/'],
            'category_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('categories', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
