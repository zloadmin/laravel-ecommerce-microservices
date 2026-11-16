<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Ecommerce\Shared\Http\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::orderBy('name')->get());
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function store(Request $request): JsonResponse
    {
        $category = Category::create($this->validated($request));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $category->update($this->validated($request, $category));

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response|JsonResponse
    {
        if ($category->products()->exists()) {
            return ApiError::response('category_not_empty', 'Move or delete the products in this category first.', 409);
        }

        $category->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $required = $category === null ? 'required' : 'sometimes';

        // The slug defaults to the name on creation.
        if ($category === null && ! $request->filled('slug') && is_string($request->input('name'))) {
            $request->merge(['slug' => Str::slug($request->input('name'))]);
        }

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories')->ignore($category)],
        ]);
    }
}
