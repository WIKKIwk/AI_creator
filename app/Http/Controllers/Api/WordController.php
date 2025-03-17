<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Word;
use App\Services\WordService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\WordRequest;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WordController extends Controller
{
    use ApiHelper;

    public function __construct(protected WordService $wordService) {}

    public function getWords(): JsonResponse
    {
        $words = $this->user()->words()->get();
        return $this->successRes(data: $words->toArray());
    }

    public function getDictionaryWords(): JsonResponse
    {
        $words = $this->user()->dictionaryWords()->get();
        return $this->successRes(data: $words->toArray());
    }

    public function show($id): JsonResponse
    {
        $word = $this->getWord($id);
        return $this->successRes(data: $word->toArray());
    }

    public function store(WordRequest $request): JsonResponse
    {
        $this->wordService->store($this->user(), $request->validated());
        return $this->successRes();
    }

    public function update($id, WordRequest $request): JsonResponse
    {
        $word = $this->getWord($id);
        $this->wordService->update($word, $request->validated());
        return $this->successRes();
    }

    public function move($id, WordRequest $request): JsonResponse
    {
        $word = $this->getWord($id);
        $this->wordService->move($word, $request->get('category_id'));
        return $this->successRes();
    }

    public function progress($id, WordRequest $request): JsonResponse
    {
        $word = $this->getWord($id);
        $this->wordService->progress($word, $request->get('learn_status'));
        return $this->successRes();
    }

    public function delete($id): JsonResponse
    {
        $this->getWord($id)->delete();
        return $this->successRes('deleted');
    }

    protected function getWord($id): Word
    {
        $word = $this->user()->words()->find($id);
        if (!$word) {
            throw new BadRequestHttpException('Word not found');
        }
        return $word;
    }
}
