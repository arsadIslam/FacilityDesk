<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Categories are deactivated rather than deleted: work orders reference them.
 */
final class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('categories.index', [
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('categories.form', ['category' => new Category, 'priorities' => Priority::cases()]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')->with('status', "{$category->name} was created.");
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('categories.form', ['category' => $category, 'priorities' => Priority::cases()]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('status', "{$category->name} was updated.");
    }
}
