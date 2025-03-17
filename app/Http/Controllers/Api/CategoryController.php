<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CategoryService;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CategoryController extends Controller
{
    use ApiHelper;

    public function __construct(protected CategoryService $categoryService) {}

    public function store(Request $request): JsonResponse
    {
        $user = $this->user();
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'is_favorite' => 'nullable|boolean',
            'parent_id' => 'nullable|integer|exists:categories,id,user_id,' . $user->id,
        ]);
        $this->categoryService->store($user, $validated);

        return $this->successRes();
    }

    public function delete($id): JsonResponse
    {
        $user = $this->user();
        if ($user->categories()->where('id', $id)->doesntExist()) {
            throw new BadRequestHttpException('Category not found');
        }

        $this->categoryService->delete($user, $id);

        return $this->successRes('deleted');
    }
}
